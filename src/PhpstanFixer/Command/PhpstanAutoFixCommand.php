<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Command;

use PhpstanFixer\AutoFixService;
use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Command\FixerFactory;
use PhpstanFixer\Framework\FrameworkDetector;
use PhpstanFixer\Configuration\Configuration;
use PhpstanFixer\Configuration\ConfigurationLoader;
use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;
use PhpstanFixer\PhpstanLogParser;
use PhpstanFixer\Strategy\FixStrategyInterface;
use PhpstanFixer\Strategy\CallableTypeFixer;
use PhpstanFixer\Strategy\CollectionGenericDocblockFixer;
use PhpstanFixer\Strategy\ImpureFunctionFixer;
use PhpstanFixer\Strategy\MissingParamDocblockFixer;
use PhpstanFixer\Strategy\MissingPropertyDocblockFixer;
use PhpstanFixer\Strategy\MissingReturnDocblockFixer;
use PhpstanFixer\Strategy\MissingThrowsDocblockFixer;
use PhpstanFixer\Strategy\MissingUseStatementFixer;
use PhpstanFixer\Strategy\MixinFixer;
use PhpstanFixer\Strategy\PrefixedTagsFixer;
use PhpstanFixer\Strategy\ReadonlyPropertyFixer;
use PhpstanFixer\Strategy\SealedClassFixer;
use PhpstanFixer\Strategy\ImmutableClassFixer;
use PhpstanFixer\Strategy\RequireExtendsFixer;
use PhpstanFixer\Strategy\RequireImplementsFixer;
use PhpstanFixer\Strategy\ArrayOffsetTypeFixer;
use PhpstanFixer\Strategy\IterableValueTypeFixer;
use PhpstanFixer\Strategy\InternalAnnotationFixer;
use PhpstanFixer\Strategy\MagicPropertyFixer;
use PhpstanFixer\Strategy\ClassesNamedAfterInternalTypesFixer;
use PhpstanFixer\Strategy\UndefinedMethodFixer;
use PhpstanFixer\Strategy\PriorityWrapper;
use PhpstanFixer\Strategy\UndefinedPivotPropertyFixer;
use PhpstanFixer\Strategy\UndefinedVariableFixer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI command for automatically fixing PHPStan errors.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class PhpstanAutoFixCommand extends Command
{
    protected static $defaultName = 'phpstan:auto-fix';
    protected static $defaultDescription = 'Automatically fix PHPStan errors';

    public function __construct(
        private readonly ?PhpstanLogParser $parser = null,
        private readonly ?AutoFixService $autoFixService = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('phpstan:auto-fix')
            ->setDescription('Automatically fix PHPStan errors from JSON output')
            ->addOption(
                'input',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Path to PHPStan JSON output file. If omitted, will run PHPStan automatically.'
            )
            ->addOption(
                'mode',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Mode: "suggest" (default) shows proposed changes, "apply" writes changes to disk',
                'suggest'
            )
            ->addOption(
                'phpstan-command',
                null,
                InputOption::VALUE_OPTIONAL,
                'Custom PHPStan command to run (if input is not provided)',
                'vendor/bin/phpstan analyse --error-format=json'
            )
            ->addOption(
                'strategy',
                's',
                InputOption::VALUE_OPTIONAL,
                'Apply only specific fixer strategy (optional filter)'
            )
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Path to configuration file (phpstan-fixer.yaml or phpstan-fixer.json). If omitted, will search for config file automatically.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $parser = $this->parser ?? new PhpstanLogParser();
        
        // Load configuration if specified or found
        $configuration = $this->loadConfiguration($input->getOption('config'), $io);
        
        // Detect framework and inform user
        $framework = $this->detectFramework($io);
        if ($framework !== null) {
            $io->note("Detected framework: {$framework}");
        }
        
        try {
            $autoFixService = $this->autoFixService ?? $this->createDefaultAutoFixService($configuration, $framework);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $inputPath = $input->getOption('input');
        $mode = $input->getOption('mode');
        $strategyFilter = $input->getOption('strategy');

        if (!in_array($mode, ['suggest', 'apply'], true)) {
            $io->error('Mode must be either "suggest" or "apply"');
            return Command::FAILURE;
        }

        // Get PHPStan JSON output
        $jsonContent = $this->getPhpstanJson($inputPath, $input->getOption('phpstan-command'), $io);
        if ($jsonContent === null) {
            return Command::FAILURE;
        }

        // Parse issues
        // If inputPath is provided, parser expects the path; otherwise it expects JSON content
        try {
            $issues = $parser->parse($inputPath ?? $jsonContent, $inputPath !== null);
        } catch (\Exception $e) {
            $io->error('Failed to parse PHPStan JSON: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (empty($issues)) {
            $io->success('No issues found in PHPStan output!');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d issue(s) to fix', count($issues)));

        // Filter by strategy if specified
        if ($strategyFilter !== null) {
            // This would require filtering issues - simplified for now
            $io->note("Strategy filter '{$strategyFilter}' is not yet fully implemented");
        }

        // Group issues by file to get total file count for progress
        $groupedIssues = $autoFixService->groupIssuesByFile($issues);
        $totalFiles = count($groupedIssues);

        // Create progress bar if there are multiple files (always show for better UX)
        $progressBar = null;
        if ($totalFiles > 1) {
            $progressBar = new ProgressBar($output, $totalFiles);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
            $progressBar->setMessage('Processing files...');
            $progressBar->start();
        }

        // Fix issues with progress callback
        $results = $autoFixService->fixAllIssues(
            $issues,
            function (int $processed, int $total, string $currentFile) use ($progressBar): void {
                if ($progressBar !== null) {
                    $relativePath = basename($currentFile);
                    $progressBar->setMessage("Processing: {$relativePath}");
                    $progressBar->advance();
                }
            }
        );

        if ($progressBar !== null) {
            $progressBar->finish();
            $io->newLine(2);
        }
        $stats = $autoFixService->getStatistics($results);

        // Display results
        $this->displayResults($results, $stats, $mode, $autoFixService, $io);

        // Apply changes if in apply mode
        if ($mode === 'apply') {
            $this->applyFixes($results, $io);
        }

        return Command::SUCCESS;
    }

    /**
     * Apply fixes to files on disk.
     *
     * @param array<string, array<string, mixed>> $results Results from fixAllIssues
     * @param SymfonyStyle $io I/O helper
     */
    private function applyFixes(array $results, SymfonyStyle $io): void
    {
        $appliedCount = 0;
        foreach ($results as $filePath => $fileResults) {
            if (!$fileResults['hasChanges']) {
                continue;
            }

            if (file_put_contents($filePath, $fileResults['fixedContent']) !== false) {
                $appliedCount++;
                $io->writeln("✓ Fixed: {$filePath}");
            } else {
                $io->error("Failed to write: {$filePath}");
            }
        }

        if ($appliedCount > 0) {
            $io->success(sprintf('Applied fixes to %d file(s)', $appliedCount));
        }
    }

    /**
     * Get PHPStan JSON output from file or by running PHPStan.
     */
    private function getPhpstanJson(?string $inputPath, string $phpstanCommand, SymfonyStyle $io): ?string
    {
        if ($inputPath !== null) {
            if (!file_exists($inputPath)) {
                $io->error("Input file not found: {$inputPath}");
                return null;
            }
            return file_get_contents($inputPath);
        }

        // Run PHPStan automatically
        $io->note("Running PHPStan: {$phpstanCommand}");
        $output = [];
        $returnVar = 0;
        exec($phpstanCommand . ' 2>&1', $output, $returnVar);
        
        $jsonOutput = implode("\n", $output);
        
        // PHPStan returns non-zero on errors, but JSON is still valid
        if ($returnVar !== 0 && empty($jsonOutput)) {
            $io->error('PHPStan command failed. Make sure PHPStan is installed and configured.');
            return null;
        }

        return $jsonOutput;
    }

    /**
     * Display fix results in a table.
     */
    private function displayResults(
        array $results,
        array $stats,
        string $mode,
        AutoFixService $autoFixService,
        SymfonyStyle $io
    ): void {
        $io->section('Fix Results');

        $io->table(
            ['Metric', 'Count'],
            [
                ['Total Files', $stats['total_files']],
                ['Files Fixed', $stats['files_fixed']],
                ['Total Issues', $stats['total_issues']],
                ['Issues Fixed', $stats['issues_fixed']],
                ['Issues Ignored', $stats['issues_ignored'] ?? 0],
                ['Issues Reported', $stats['issues_reported'] ?? 0],
                ['Issues Failed', $stats['issues_failed']],
            ]
        );

        if ($mode === 'suggest') {
            $io->section('Proposed Changes');
            
            foreach ($results as $filePath => $fileResults) {
                if (!$fileResults['hasChanges']) {
                    continue;
                }

                $io->writeln("<info>{$filePath}</info>");
                
                // Show unified diff if content changed
                if ($fileResults['fixedContent'] !== $fileResults['originalContent']) {
                    $diff = $this->generateUnifiedDiff(
                        $filePath,
                        $fileResults['originalContent'],
                        $fileResults['fixedContent']
                    );
                    $io->writeln($diff);
                }
                
                // Show change descriptions
                foreach ($fileResults['results'] as $result) {
                    if ($result->isSuccessful()) {
                        $io->writeln("  ✓ " . $result->getChangeDescription());
                    } else {
                        $io->writeln("  ✗ " . $result->getChangeDescription());
                    }
                }
                $io->newLine();
            }

            $io->note('Run with --mode=apply to apply these changes');
        }

        // Display reported issues (from configuration)
        $reportedIssues = $autoFixService->getReportedIssues($results);
        if (!empty($reportedIssues)) {
            $io->section('Reported Issues (not fixed per configuration)');
            $io->note(sprintf('%d issue(s) are reported but not fixed (per configuration):', count($reportedIssues)));
            
            $this->displayUnfixedIssues($reportedIssues, $io);
        }

        // Display unfixed issues in PHPStan format
        $unfixedIssues = $autoFixService->getUnfixedIssues($results);
        if (!empty($unfixedIssues)) {
            $io->section('Unfixed Issues (PHPStan format)');
            $io->warning(sprintf('%d issue(s) could not be automatically fixed:', count($unfixedIssues)));
            
            $this->displayUnfixedIssues($unfixedIssues, $io);
        }

        // Display ignored issues (informational)
        $ignoredIssues = $autoFixService->getIgnoredIssues($results);
        if (!empty($ignoredIssues)) {
            $io->section('Ignored Issues');
            $io->info(sprintf('%d issue(s) were ignored per configuration.', count($ignoredIssues)));
        }
    }

    /**
     * Display unfixed issues in PHPStan-like format.
     *
     * @param \PhpstanFixer\Issue[] $issues
     */
    private function displayUnfixedIssues(array $issues, SymfonyStyle $io): void
    {
        // Group by file
        $grouped = [];
        foreach ($issues as $issue) {
            $filePath = $issue->getFilePath();
            if (!isset($grouped[$filePath])) {
                $grouped[$filePath] = [];
            }
            $grouped[$filePath][] = $issue;
        }

        foreach ($grouped as $filePath => $fileIssues) {
            $io->writeln('');
            $io->writeln(sprintf('<comment>%s</comment>', $filePath));
            
            foreach ($fileIssues as $issue) {
                $io->writeln(sprintf(
                    '  <fg=red>✗</> Line %d: %s',
                    $issue->getLine(),
                    $issue->getMessage()
                ));
            }
        }
        
        $io->writeln('');
    }

    /**
     * Load configuration from file or auto-discover.
     */
    private function loadConfiguration(?string $configPath, SymfonyStyle $io): ?Configuration
    {
        $loader = new ConfigurationLoader();
        
        try {
            if ($configPath !== null) {
                // Explicit config path provided
                $io->info("Loading configuration from: {$configPath}");
                return $loader->loadFromFile($configPath);
            }
            
            // Try to auto-discover config file
            $foundPath = $loader->findConfigurationFile();
            if ($foundPath !== null) {
                $io->info("Found configuration file: {$foundPath}");
                return $loader->loadFromFile($foundPath);
            }
        } catch (\Exception $e) {
            $io->warning("Failed to load configuration: " . $e->getMessage());
            $io->note("Continuing with default configuration (all errors will be fixed)");
        }
        
        return null;
    }

    /**
     * Create default AutoFixService with all strategies.
     *
     * @param Configuration|null $configuration Configuration object
     * @param string|null $framework Detected framework name ('laravel', 'symfony', etc.) or null
     */
    private function createDefaultAutoFixService(?Configuration $configuration = null, ?string $framework = null): AutoFixService
    {
        // Create built-in strategies
        $allStrategies = $this->createBuiltInStrategies();

        // Load custom fixers from configuration
        $customStrategies = $this->loadCustomFixers($configuration);

        // Merge built-in and custom strategies
        $allStrategies = array_merge($allStrategies, $customStrategies);

        // Filter strategies based on configuration
        $strategies = $this->filterStrategies($allStrategies, $configuration);
        
        // Filter framework-specific fixers based on detected framework
        $strategies = $this->filterFrameworkSpecificFixers($strategies, $framework);

        // Apply priorities from configuration
        $strategies = $this->applyFixerPriorities($strategies, $configuration);

        return new AutoFixService($strategies, $configuration);
    }

    /**
     * Create all built-in fixer strategies.
     *
     * @return array<FixStrategyInterface> Built-in strategies
     */
    private function createBuiltInStrategies(): array
    {
        $analyzer = new PhpFileAnalyzer();
        $docblockManipulator = new DocblockManipulator();

        return [
            new MissingReturnDocblockFixer($analyzer, $docblockManipulator),
            new MissingParamDocblockFixer($analyzer, $docblockManipulator),
            new MissingPropertyDocblockFixer($analyzer, $docblockManipulator),
            new ReadonlyPropertyFixer($analyzer, $docblockManipulator),
            new CollectionGenericDocblockFixer($analyzer, $docblockManipulator),
            new UndefinedPivotPropertyFixer($analyzer, $docblockManipulator),
            new UndefinedVariableFixer(),
            new MissingUseStatementFixer($analyzer),
            new UndefinedMethodFixer($analyzer, $docblockManipulator),
            new MissingThrowsDocblockFixer($analyzer, $docblockManipulator),
            new CallableTypeFixer($analyzer, $docblockManipulator),
            new MixinFixer($analyzer, $docblockManipulator),
            new PrefixedTagsFixer($analyzer, $docblockManipulator),
            new ImpureFunctionFixer($analyzer, $docblockManipulator),
            new ImmutableClassFixer($analyzer, $docblockManipulator),
            new SealedClassFixer($analyzer, $docblockManipulator),
            new RequireExtendsFixer($analyzer, $docblockManipulator),
            new RequireImplementsFixer($analyzer, $docblockManipulator),
            new ArrayOffsetTypeFixer($analyzer, $docblockManipulator),
            new IterableValueTypeFixer($analyzer, $docblockManipulator),
            new InternalAnnotationFixer($analyzer, $docblockManipulator),
            new ClassesNamedAfterInternalTypesFixer(),
            new MagicPropertyFixer($analyzer, $docblockManipulator),
        ];
    }

    /**
     * Load custom fixer strategies from configuration.
     *
     * @param Configuration|null $configuration Configuration object
     * @return array<FixStrategyInterface> Loaded custom fixers
     * @throws \RuntimeException If custom fixer class cannot be loaded or doesn't implement FixStrategyInterface
     */
    private function loadCustomFixers(?Configuration $configuration): array
    {
        if ($configuration === null) {
            return [];
        }

        $customFixers = $configuration->getCustomFixers();
        if (empty($customFixers)) {
            return [];
        }

        $factory = new FixerFactory(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );

        $strategies = [];
        foreach ($customFixers as $fixerClass) {
            try {
                $strategies[] = $factory->createFixer($fixerClass);
            } catch (\ReflectionException | \RuntimeException $e) {
                throw new \RuntimeException(
                    "Failed to load custom fixer {$fixerClass}: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        return $strategies;
    }

    /**
     * Filter strategies based on enabled/disabled configuration.
     *
     * @param array<FixStrategyInterface> $strategies All available strategies
     * @param Configuration|null $configuration Configuration object
     * @return array<FixStrategyInterface> Filtered strategies
     */
    private function filterStrategies(array $strategies, ?Configuration $configuration): array
    {
        if ($configuration === null) {
            return $strategies;
        }

        return array_filter($strategies, function ($strategy) use ($configuration): bool {
            return $configuration->isFixerEnabled($strategy->getName());
        });
    }

    /**
     * Filter framework-specific fixers based on detected framework.
     * Framework-agnostic fixers are always included.
     * Framework-specific fixers are included only if their framework matches the detected one.
     *
     * @param array<FixStrategyInterface> $strategies Strategies to filter
     * @param string|null $framework Detected framework name or null
     * @return array<FixStrategyInterface> Filtered strategies
     */
    private function filterFrameworkSpecificFixers(array $strategies, ?string $framework): array
    {
        if ($framework === null) {
            // If no framework detected, exclude all framework-specific fixers
            return array_filter($strategies, function ($strategy): bool {
                return empty($strategy->getSupportedFrameworks());
            });
        }

        // Include framework-agnostic fixers and fixers that support the detected framework
        return array_filter($strategies, function ($strategy) use ($framework): bool {
            $supportedFrameworks = $strategy->getSupportedFrameworks();
            return empty($supportedFrameworks) || in_array($framework, $supportedFrameworks, true);
        });
    }

    /**
     * Detect framework in the current project.
     *
     * @param \Symfony\Component\Console\Style\StyleInterface $io I/O interface for messages
     * @return string|null Framework name or null if not detected
     */
    private function detectFramework($io): ?string
    {
        $projectRoot = getcwd();
        if ($projectRoot === false) {
            return null;
        }

        $detector = new FrameworkDetector();
        return $detector->detect($projectRoot);
    }

    /**
     * Apply fixer priorities from configuration.
     * Wraps strategies with configured priorities using PriorityWrapper.
     *
     * @param array<FixStrategyInterface> $strategies Strategies to process
     * @param Configuration|null $configuration Configuration object
     * @return array<FixStrategyInterface> Strategies with priorities applied
     */
    private function applyFixerPriorities(array $strategies, ?Configuration $configuration): array
    {
        if ($configuration === null) {
            return $strategies;
        }

        return array_map(function ($strategy) use ($configuration) {
            $configuredPriority = $configuration->getFixerPriority($strategy->getName());
            if ($configuredPriority !== null) {
                return new PriorityWrapper($strategy, $configuredPriority);
            }
            return $strategy;
        }, $strategies);
    }

    /**
     * Generate unified diff between original and fixed content.
     */
    private function generateUnifiedDiff(string $filePath, string $original, string $fixed): string
    {
        $originalLines = explode("\n", $original);
        $fixedLines = explode("\n", $fixed);
        
        $diff = [];
        $diff[] = "--- a/{$filePath}";
        $diff[] = "+++ b/{$filePath}";
        
        // Compute diff operations using LCS
        $operations = $this->computeDiffOperations($originalLines, $fixedLines);
        
        if (empty($operations)) {
            return '';
        }
        
        // Group operations into hunks
        $hunks = $this->groupOperationsIntoHunks($operations, $originalLines, $fixedLines);
        
        foreach ($hunks as $hunk) {
            $diff[] = $hunk['header'];
            foreach ($hunk['lines'] as $line) {
                $diff[] = $line;
            }
        }
        
        return implode("\n", $diff);
    }

    /**
     * Compute diff operations between two arrays of lines using LCS.
     *
     * @param string[] $original
     * @param string[] $fixed
     * @return array<int, array{type: string, oldIndex: int, newIndex: int}>
     */
    private function computeDiffOperations(array $original, array $fixed): array
    {
        $operations = [];
        $originalLen = count($original);
        $fixedLen = count($fixed);
        
        // Find longest common subsequence using dynamic programming
        $lcsTable = $this->computeLCSTable($original, $fixed);
        
        // Reconstruct operations by backtracking through LCS table
        $oldIndex = $originalLen;
        $newIndex = $fixedLen;
        
        while ($oldIndex > 0 || $newIndex > 0) {
            if ($oldIndex > 0 && $newIndex > 0 && $original[$oldIndex - 1] === $fixed[$newIndex - 1]) {
                // Lines match - no change, move diagonally
                $operations[] = [
                    'type' => 'match',
                    'oldIndex' => $oldIndex - 1,
                    'newIndex' => $newIndex - 1,
                ];
                $oldIndex--;
                $newIndex--;
            } elseif ($newIndex > 0 && ($oldIndex === 0 || $lcsTable[$oldIndex][$newIndex - 1] >= $lcsTable[$oldIndex - 1][$newIndex])) {
                // Line was added
                $operations[] = [
                    'type' => 'add',
                    'oldIndex' => $oldIndex, // Position where it was inserted
                    'newIndex' => $newIndex - 1,
                ];
                $newIndex--;
            } elseif ($oldIndex > 0 && ($newIndex === 0 || $lcsTable[$oldIndex - 1][$newIndex] >= $lcsTable[$oldIndex][$newIndex - 1])) {
                // Line was removed
                $operations[] = [
                    'type' => 'remove',
                    'oldIndex' => $oldIndex - 1,
                    'newIndex' => $newIndex, // Position where it was removed
                ];
                $oldIndex--;
            } else {
                // Line was changed
                $operations[] = [
                    'type' => 'change',
                    'oldIndex' => $oldIndex - 1,
                    'newIndex' => $newIndex - 1,
                ];
                $oldIndex--;
                $newIndex--;
            }
        }
        
        // Reverse to get operations in forward order
        return array_reverse($operations);
    }

    /**
     * Compute LCS table using dynamic programming.
     *
     * @param string[] $original
     * @param string[] $fixed
     * @return array<int, array<int, int>>
     */
    private function computeLCSTable(array $original, array $fixed): array
    {
        $originalLen = count($original);
        $fixedLen = count($fixed);
        
        // Build LCS table using dynamic programming
        $dp = [];
        for ($i = 0; $i <= $originalLen; $i++) {
            $dp[$i] = array_fill(0, $fixedLen + 1, 0);
        }
        
        for ($i = 1; $i <= $originalLen; $i++) {
            for ($j = 1; $j <= $fixedLen; $j++) {
                if ($original[$i - 1] === $fixed[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
                }
            }
        }
        
        return $dp;
    }

    /**
     * Group operations into unified diff hunks.
     *
     * @param array<int, array{type: string, oldIndex: int, newIndex: int}> $operations
     * @param string[] $originalLines
     * @param string[] $fixedLines
     * @return array<int, array{header: string, lines: string[]}>
     */
    private function groupOperationsIntoHunks(array $operations, array $originalLines, array $fixedLines): array
    {
        if (empty($operations)) {
            return [];
        }
        
        [$changeOldIndices, $changeNewIndices] = $this->collectChangeIndices($operations, $originalLines, $fixedLines);
        
        if (empty($changeOldIndices) && empty($changeNewIndices)) {
            return [];
        }
        
        [$hunkOldStart, $hunkOldEnd, $hunkNewStart, $hunkNewEnd] = $this->calculateHunkBoundaries(
            $changeOldIndices,
            $changeNewIndices,
            $originalLines,
            $fixedLines
        );
        
        $lines = $this->buildDiffLines($operations, $originalLines, $fixedLines, $hunkOldStart, $hunkOldEnd, $hunkNewStart, $hunkNewEnd);
        
        if (empty($lines)) {
            return [];
        }
        
        [$oldCount, $newCount] = $this->countDiffLines($lines);
        $header = $this->formatHunkHeader($hunkOldStart, $oldCount, $hunkNewStart, $newCount);
        
        return [['header' => $header, 'lines' => $lines]];
    }

    /**
     * Collect indices of all change operations (non-match) for boundary calculation.
     *
     * @param array<int, array{type: string, oldIndex: int, newIndex: int}> $operations
     * @param string[] $originalLines
     * @param string[] $fixedLines
     * @return array{0: int[], 1: int[]}
     */
    private function collectChangeIndices(array $operations, array $originalLines, array $fixedLines): array
    {
        /** @var array<int> $changeOldIndices */
        $changeOldIndices = [];
        /** @var array<int> $changeNewIndices */
        $changeNewIndices = [];
        
        foreach ($operations as $op) {
            if ($op['type'] === 'match') {
                continue;
            }
            
            $this->addChangeIndicesForOperation(
                $op,
                $originalLines,
                $fixedLines,
                $changeOldIndices,
                $changeNewIndices
            );
        }
        
        return [$changeOldIndices, $changeNewIndices];
    }

    /**
     * Add change indices for a single operation.
     *
     * @param array{type: string, oldIndex: int, newIndex: int} $op
     * @param string[] $originalLines
     * @param string[] $fixedLines
     * @param array<int> $changeOldIndices
     * @param array<int> $changeNewIndices
     */
    private function addChangeIndicesForOperation(
        array $op,
        array $originalLines,
        array $fixedLines,
        array &$changeOldIndices,
        array &$changeNewIndices
    ): void {
        if ($op['type'] === 'add') {
            $this->addOldIndexForInsertion($op['oldIndex'], $originalLines, $changeOldIndices);
            $this->addNewIndexIfValid($op['newIndex'], $fixedLines, $changeNewIndices);
        } elseif ($op['type'] === 'remove') {
            $this->addOldIndexIfValid($op['oldIndex'], $originalLines, $changeOldIndices);
            $this->addNewIndexForRemoval($op['newIndex'], $fixedLines, $changeNewIndices);
        } else {
            // 'change' operation
            $this->addOldIndexIfValid($op['oldIndex'], $originalLines, $changeOldIndices);
            $this->addNewIndexIfValid($op['newIndex'], $fixedLines, $changeNewIndices);
        }
    }

    /**
     * Check if index is within array bounds.
     */
    private function isIndexInBounds(int $index, array $array): bool
    {
        return $index >= 0 && $index < count($array);
    }

    /**
     * Add old index if valid, or use last valid index for insertions at end.
     *
     * @param array<int> $changeOldIndices
     */
    private function addOldIndexForInsertion(int $oldIndex, array $originalLines, array &$changeOldIndices): void
    {
        if ($this->isIndexInBounds($oldIndex, $originalLines)) {
            $changeOldIndices[] = $oldIndex;
        } elseif (count($originalLines) > 0) {
            // Insertion at end - use last valid index
            $changeOldIndices[] = count($originalLines) - 1;
        }
    }

    /**
     * Add new index if valid, or use last valid index for removals at end.
     *
     * @param array<int> $changeNewIndices
     */
    private function addNewIndexForRemoval(int $newIndex, array $fixedLines, array &$changeNewIndices): void
    {
        if ($this->isIndexInBounds($newIndex, $fixedLines)) {
            $changeNewIndices[] = $newIndex;
        } elseif (count($fixedLines) > 0) {
            // Removal at end - use last valid index
            $changeNewIndices[] = count($fixedLines) - 1;
        }
    }

    /**
     * Add old index only if it's within bounds.
     *
     * @param array<int> $changeOldIndices
     */
    private function addOldIndexIfValid(int $oldIndex, array $originalLines, array &$changeOldIndices): void
    {
        if ($this->isIndexInBounds($oldIndex, $originalLines)) {
            $changeOldIndices[] = $oldIndex;
        }
    }

    /**
     * Add new index only if it's within bounds.
     *
     * @param array<int> $changeNewIndices
     */
    private function addNewIndexIfValid(int $newIndex, array $fixedLines, array &$changeNewIndices): void
    {
        if ($this->isIndexInBounds($newIndex, $fixedLines)) {
            $changeNewIndices[] = $newIndex;
        }
    }

    /**
     * Calculate hunk boundaries with context.
     *
     * @param int[] $changeOldIndices
     * @param int[] $changeNewIndices
     * @param string[] $originalLines
     * @param string[] $fixedLines
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function calculateHunkBoundaries(
        array $changeOldIndices,
        array $changeNewIndices,
        array $originalLines,
        array $fixedLines
    ): array {
        $contextLines = 3;
        
        $minOldIndex = $changeOldIndices !== [] ? min($changeOldIndices) : 0;
        $maxOldIndex = $changeOldIndices !== [] ? max($changeOldIndices) : 0;
        $minNewIndex = $changeNewIndices !== [] ? min($changeNewIndices) : 0;
        $maxNewIndex = $changeNewIndices !== [] ? max($changeNewIndices) : 0;
        
        $hunkOldStart = max(0, $minOldIndex - $contextLines);
        $hunkOldEnd = count($originalLines) > 0 ? min(count($originalLines) - 1, $maxOldIndex + $contextLines) : 0;
        $hunkNewStart = max(0, $minNewIndex - $contextLines);
        $hunkNewEnd = count($fixedLines) > 0 ? min(count($fixedLines) - 1, $maxNewIndex + $contextLines) : 0;
        
        return [$hunkOldStart, $hunkOldEnd, $hunkNewStart, $hunkNewEnd];
    }

    /**
     * Build diff lines by processing operations within hunk range.
     *
     * @param array<int, array{type: string, oldIndex: int, newIndex: int}> $operations
     * @param string[] $originalLines
     * @param string[] $fixedLines
     * @return string[]
     */
    private function buildDiffLines(
        array $operations,
        array $originalLines,
        array $fixedLines,
        int $hunkOldStart,
        int $hunkOldEnd,
        int $hunkNewStart,
        int $hunkNewEnd
    ): array {
        $lines = [];
        
        foreach ($operations as $op) {
            if (!$this->operationAffectsHunk($op, $hunkOldStart, $hunkOldEnd, $hunkNewStart, $hunkNewEnd)) {
                continue;
            }
            
            $diffLine = $this->formatDiffLineForOperation($op, $originalLines, $fixedLines);
            if ($diffLine !== null) {
                $lines = array_merge($lines, $diffLine);
            }
        }
        
        return $lines;
    }

    /**
     * Check if operation affects the hunk range.
     *
     * @param array{type: string, oldIndex: int, newIndex: int} $op
     */
    private function operationAffectsHunk(
        array $op,
        int $hunkOldStart,
        int $hunkOldEnd,
        int $hunkNewStart,
        int $hunkNewEnd
    ): bool {
        $inOldRange = $op['oldIndex'] >= $hunkOldStart && $op['oldIndex'] <= $hunkOldEnd;
        $inNewRange = $op['newIndex'] >= $hunkNewStart && $op['newIndex'] <= $hunkNewEnd;
        
        return match ($op['type']) {
            'match', 'change' => $inOldRange && $inNewRange,
            'add' => $inNewRange,
            'remove' => $inOldRange,
            default => false,
        };
    }

    /**
     * Format diff line(s) for an operation.
     *
     * @param array{type: string, oldIndex: int, newIndex: int} $op
     * @param string[] $originalLines
     * @param string[] $fixedLines
     * @return string[]|null
     */
    private function formatDiffLineForOperation(array $op, array $originalLines, array $fixedLines): ?array
    {
        return match ($op['type']) {
            'match' => [' ' . $originalLines[$op['oldIndex']]],
            'add' => ['+' . $fixedLines[$op['newIndex']]],
            'remove' => ['-' . $originalLines[$op['oldIndex']]],
            'change' => [
                '-' . $originalLines[$op['oldIndex']],
                '+' . $fixedLines[$op['newIndex']],
            ],
            default => null,
        };
    }

    /**
     * Count lines from original and fixed files shown in diff.
     *
     * @param string[] $lines
     * @return array{0: int, 1: int}
     */
    private function countDiffLines(array $lines): array
    {
        $oldCount = 0;
        $newCount = 0;
        
        foreach ($lines as $line) {
            $firstChar = $line[0] ?? ' ';
            if ($firstChar === ' ' || $firstChar === '-') {
                $oldCount++;
            }
            if ($firstChar === ' ' || $firstChar === '+') {
                $newCount++;
            }
        }
        
        return [$oldCount, $newCount];
    }

    /**
     * Format hunk header in unified diff format.
     */
    private function formatHunkHeader(int $hunkOldStart, int $oldCount, int $hunkNewStart, int $newCount): string
    {
        // Line numbers are 1-indexed in unified diff format
        $oldStart = $hunkOldStart + 1;
        $newStart = $hunkNewStart + 1;
        
        return sprintf('@@ -%d,%d +%d,%d @@', $oldStart, $oldCount, $newStart, $newCount);
    }
}


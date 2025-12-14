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
use PhpstanFixer\Configuration\Configuration;
use PhpstanFixer\Configuration\ConfigurationLoader;
use PhpstanFixer\PhpstanLogParser;
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
use PhpstanFixer\Strategy\UndefinedPivotPropertyFixer;
use PhpstanFixer\Strategy\UndefinedVariableFixer;
use Symfony\Component\Console\Command\Command;
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
        
        $autoFixService = $this->autoFixService ?? $this->createDefaultAutoFixService($configuration);

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

        // Fix issues
        $results = $autoFixService->fixAllIssues($issues);
        $stats = $autoFixService->getStatistics($results);

        // Display results
        $this->displayResults($results, $stats, $mode, $autoFixService, $io);

        // Apply changes if in apply mode
        if ($mode === 'apply') {
            $appliedCount = 0;
            foreach ($results as $filePath => $fileResults) {
                if ($fileResults['hasChanges']) {
                    if (file_put_contents($filePath, $fileResults['fixedContent']) !== false) {
                        $appliedCount++;
                        $io->writeln("✓ Fixed: {$filePath}");
                    } else {
                        $io->error("Failed to write: {$filePath}");
                    }
                }
            }

            if ($appliedCount > 0) {
                $io->success(sprintf('Applied fixes to %d file(s)', $appliedCount));
            }
        }

        return Command::SUCCESS;
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
     */
    private function createDefaultAutoFixService(?Configuration $configuration = null): AutoFixService
    {
        $analyzer = new PhpFileAnalyzer();
        $docblockManipulator = new DocblockManipulator();

        $strategies = [
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

        return new AutoFixService($strategies, $configuration);
    }
}


<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer;

use PhpstanFixer\Configuration\Configuration;
use PhpstanFixer\Strategy\FixStrategyInterface;

/**
 * Service that orchestrates fix strategies to resolve PHPStan issues.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class AutoFixService
{
    /**
     * @param FixStrategyInterface[] $strategies Array of fix strategies to use
     * @param Configuration|null $configuration Optional configuration for error handling
     */
    public function __construct(
        private array $strategies = [],
        private readonly ?Configuration $configuration = null
    ) {
        // Sort strategies by priority (higher priority = executed first)
        $this->sortStrategiesByPriority();
    }

    /**
     * Register a fix strategy.
     */
    public function addStrategy(FixStrategyInterface $strategy): void
    {
        $this->strategies[] = $strategy;
        $this->sortStrategiesByPriority();
    }

    /**
     * Sort strategies by priority (higher priority = executed first).
     * When priorities are equal, preserves original insertion order (stable sort).
     */
    private function sortStrategiesByPriority(): void
    {
        // Use Schwartzian transform to make sort stable: preserve insertion order when priorities are equal
        $decorated = array_map(
            fn(FixStrategyInterface $strategy, int $index): array => [
                'priority' => $strategy->getPriority(),
                'index' => $index,
                'strategy' => $strategy,
            ],
            $this->strategies,
            array_keys($this->strategies)
        );

        usort($decorated, function (array $a, array $b): int {
            // First compare by priority (descending)
            $priorityComparison = $b['priority'] <=> $a['priority'];
            if ($priorityComparison !== 0) {
                return $priorityComparison;
            }
            // If priorities are equal, preserve original insertion order (ascending index)
            return $a['index'] <=> $b['index'];
        });

        // Extract strategies back from decorated array
        $this->strategies = array_column($decorated, 'strategy');
    }

    /**
     * Fix a single issue using available strategies.
     *
     * @param Issue $issue The issue to fix
     * @param string $fileContent Current file content
     * @return FixResult|null Returns null if no strategy can handle the issue, or ActionResult for ignore/report
     */
    public function fixIssue(Issue $issue, string $fileContent): ?FixResult
    {
        // Check configuration first
        if ($this->configuration !== null) {
            $rule = $this->configuration->getRuleForError($issue->getMessage());

            if ($rule->isIgnore()) {
                // Return a special result indicating the issue was ignored
                return FixResult::ignored($issue, $fileContent);
            }

            if ($rule->isReport()) {
                // Return a special result indicating the issue should be reported
                return FixResult::reported($issue, $fileContent);
            }

            // If action is 'fix', continue with normal fix logic
        }

        foreach ($this->strategies as $strategy) {
            if ($strategy->canFix($issue)) {
                return $strategy->fix($issue, $fileContent);
            }
        }

        return null;
    }

    /**
     * Fix multiple issues in a file.
     *
     * @param Issue[] $issues Array of issues in the same file
     * @param string $fileContent Current file content
     * @return FixResult[] Array of fix results
     */
    public function fixIssues(array $issues, string $fileContent): array
    {
        $results = [];
        $currentContent = $fileContent;

        // Sort issues by line number (descending) to avoid line number shifts
        usort($issues, fn(Issue $a, Issue $b) => $b->getLine() <=> $a->getLine());

        foreach ($issues as $issue) {
            $result = $this->fixIssue($issue, $currentContent);
            
            if ($result === null) {
                // No strategy could handle it
                $results[] = FixResult::failure(
                    $issue,
                    $currentContent,
                    'No strategy could fix this issue'
                );
            } elseif ($result->isIgnored()) {
                // Issue was ignored by configuration - add to results for tracking
                // but don't update content (ignored issues don't modify the file)
                $results[] = $result;
            } elseif ($result->isSuccessful()) {
                // Fix was successful - update content
                $currentContent = $result->getFixedContent();
                $results[] = $result;
            } else {
                // Failed fix or reported - keep original content
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Get all issues grouped by file path.
     * Filters issues based on include/exclude paths from configuration.
     *
     * @param Issue[] $issues Array of issues
     * @return array<string, Issue[]> Issues grouped by file path
     */
    public function groupIssuesByFile(array $issues): array
    {
        $grouped = [];

        foreach ($issues as $issue) {
            $filePath = $issue->getFilePath();

            // Filter by include/exclude paths if configuration is provided
            if ($this->configuration !== null && !$this->configuration->isPathAllowed($filePath)) {
                continue;
            }

            if (!isset($grouped[$filePath])) {
                $grouped[$filePath] = [];
            }
            $grouped[$filePath][] = $issue;
        }

        return $grouped;
    }

    /**
     * Fix all issues across multiple files.
     *
     * @param Issue[] $issues Array of issues (can be from multiple files)
     * @param callable(int $processed, int $total, string $currentFile): void|null $progressCallback Optional progress callback
     * @return array<string, array<string, mixed>> Results grouped by file path
     */
    public function fixAllIssues(array $issues, ?callable $progressCallback = null): array
    {
        $groupedIssues = $this->groupIssuesByFile($issues);
        $results = [];
        
        // Filter to only processable files and count them for accurate progress reporting
        // This ensures progress callback reports correct totals even when files are skipped
        $processableFiles = [];
        foreach ($groupedIssues as $filePath => $fileIssues) {
            if (file_exists($filePath)) {
                $processableFiles[$filePath] = $fileIssues;
            }
        }
        
        $totalFiles = count($processableFiles);
        $processedFiles = 0;

        foreach ($processableFiles as $filePath => $fileIssues) {
            $fileContent = file_get_contents($filePath);
            if ($fileContent === false) {
                // Skip files that can't be read (even though they exist)
                // This shouldn't happen often, but handle it gracefully
                $totalFiles--;
                continue;
            }

            $fixResults = $this->fixIssues($fileIssues, $fileContent);

            // Determine the final fixed content
            $finalContent = $fileContent;
            $hasChanges = false;

            foreach ($fixResults as $result) {
                if ($result->isSuccessful()) {
                    $finalContent = $result->getFixedContent();
                    $hasChanges = true;
                }
            }

            // Get unfixed, reported, and ignored issues
            // Note: Use $result->getIssue() instead of $fileIssues[$index] because
            // fixIssues() sorts issues by line number (descending), which reorders them.
            // Using array indices would map to wrong issues.
            $unfixedIssues = [];
            $reportedIssues = [];
            $ignoredIssues = [];
            foreach ($fixResults as $result) {
                if ($result->isIgnored()) {
                    // Ignored issues (tracked but not displayed)
                    $ignoredIssues[] = $result->getIssue();
                } elseif ($result->isReported()) {
                    // Reported issues should be shown in output
                    $reportedIssues[] = $result->getIssue();
                } elseif (!$result->isSuccessful()) {
                    // Unfixed issues (not ignored, not reported)
                    $unfixedIssues[] = $result->getIssue();
                }
            }

            $results[$filePath] = [
                'issues' => $fileIssues,
                'results' => $fixResults,
                'originalContent' => $fileContent,
                'fixedContent' => $finalContent,
                'hasChanges' => $hasChanges,
                'fixCount' => count(array_filter($fixResults, fn($r) => $r->isSuccessful())),
                'unfixedIssues' => $unfixedIssues,
                'reportedIssues' => $reportedIssues,
                'ignoredIssues' => $ignoredIssues,
            ];

            // Call progress callback if provided
            $processedFiles++;
            if ($progressCallback !== null) {
                $progressCallback($processedFiles, $totalFiles, $filePath);
            }
        }

        return $results;
    }

    /**
     * Get all unfixed issues from results.
     *
     * @param array<string, array<string, mixed>> $results Results from fixAllIssues
     * @return Issue[] Array of unfixed issues
     */
    public function getUnfixedIssues(array $results): array
    {
        $unfixedIssues = [];

        foreach ($results as $fileResults) {
            foreach ($fileResults['unfixedIssues'] ?? [] as $issue) {
                $unfixedIssues[] = $issue;
            }
        }

        return $unfixedIssues;
    }

    /**
     * Get all ignored issues from results.
     *
     * @param array<string, array<string, mixed>> $results Results from fixAllIssues
     * @return Issue[] Array of ignored issues
     */
    public function getIgnoredIssues(array $results): array
    {
        $ignoredIssues = [];

        foreach ($results as $fileResults) {
            foreach ($fileResults['ignoredIssues'] ?? [] as $issue) {
                $ignoredIssues[] = $issue;
            }
        }

        return $ignoredIssues;
    }

    /**
     * Get all reported issues from results.
     *
     * @param array<string, array<string, mixed>> $results Results from fixAllIssues
     * @return Issue[] Array of reported issues
     */
    public function getReportedIssues(array $results): array
    {
        $reportedIssues = [];

        foreach ($results as $fileResults) {
            foreach ($fileResults['reportedIssues'] ?? [] as $issue) {
                $reportedIssues[] = $issue;
            }
        }

        return $reportedIssues;
    }

    /**
     * Get statistics about fix attempts.
     *
     * @param array<string, array<string, mixed>> $results Results from fixAllIssues
     * @return array<string, int> Statistics
     */
    public function getStatistics(array $results): array
    {
        $stats = [
            'total_files' => count($results),
            'files_fixed' => 0,
            'total_issues' => 0,
            'issues_fixed' => 0,
            'issues_failed' => 0,
            'issues_ignored' => 0,
            'issues_reported' => 0,
        ];

        foreach ($results as $fileResults) {
            if ($fileResults['hasChanges']) {
                $stats['files_fixed']++;
            }

            $stats['total_issues'] += count($fileResults['issues']);
            $stats['issues_fixed'] += $fileResults['fixCount'];
            $stats['issues_ignored'] += count($fileResults['ignoredIssues'] ?? []);
            $stats['issues_reported'] += count($fileResults['reportedIssues'] ?? []);
            
            // Issues failed = total - fixed - ignored - reported
            $stats['issues_failed'] += count($fileResults['issues']) 
                - $fileResults['fixCount']
                - count($fileResults['ignoredIssues'] ?? [])
                - count($fileResults['reportedIssues'] ?? []);
        }

        return $stats;
    }
}


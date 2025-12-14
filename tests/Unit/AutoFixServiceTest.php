<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit;

use PhpstanFixer\AutoFixService;
use PhpstanFixer\Issue;
use PHPUnit\Framework\TestCase;

final class AutoFixServiceTest extends TestCase
{
    public function testGroupIssuesByFile(): void
    {
        $service = new AutoFixService();
        
        $issues = [
            new Issue('/path/to/file1.php', 10, 'Error 1'),
            new Issue('/path/to/file1.php', 20, 'Error 2'),
            new Issue('/path/to/file2.php', 15, 'Error 3'),
        ];

        $grouped = $service->groupIssuesByFile($issues);

        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped['/path/to/file1.php']);
        $this->assertCount(1, $grouped['/path/to/file2.php']);
    }

    public function testGetStatistics(): void
    {
        $service = new AutoFixService();
        
        // Mock results structure
        $results = [
            '/path/to/file1.php' => [
                'issues' => [
                    new Issue('/path/to/file1.php', 10, 'Error 1'),
                    new Issue('/path/to/file1.php', 20, 'Error 2'),
                ],
                'fixCount' => 1,
                'hasChanges' => true,
            ],
            '/path/to/file2.php' => [
                'issues' => [
                    new Issue('/path/to/file2.php', 15, 'Error 3'),
                ],
                'fixCount' => 0,
                'hasChanges' => false,
            ],
        ];

        $stats = $service->getStatistics($results);

        $this->assertSame(2, $stats['total_files']);
        $this->assertSame(1, $stats['files_fixed']);
        $this->assertSame(3, $stats['total_issues']);
        $this->assertSame(1, $stats['issues_fixed']);
        $this->assertSame(2, $stats['issues_failed']);
    }

    public function testStrategiesAreSortedByPriority(): void
    {
        $highPriorityFixer = new class implements \PhpstanFixer\Strategy\FixStrategyInterface {
            public function canFix(\PhpstanFixer\Issue $issue): bool { return false; }
            public function fix(\PhpstanFixer\Issue $issue, string $fileContent): \PhpstanFixer\FixResult {
                return \PhpstanFixer\FixResult::failure($issue, $fileContent, '');
            }
            public function getDescription(): string { return ''; }
            public function getName(): string { return 'HighPriorityFixer'; }
            public function getPriority(): int { return 100; }
        };

        $lowPriorityFixer = new class implements \PhpstanFixer\Strategy\FixStrategyInterface {
            public function canFix(\PhpstanFixer\Issue $issue): bool { return false; }
            public function fix(\PhpstanFixer\Issue $issue, string $fileContent): \PhpstanFixer\FixResult {
                return \PhpstanFixer\FixResult::failure($issue, $fileContent, '');
            }
            public function getDescription(): string { return ''; }
            public function getName(): string { return 'LowPriorityFixer'; }
            public function getPriority(): int { return 0; }
        };

        // Add low priority first, then high priority
        $service = new AutoFixService([$lowPriorityFixer, $highPriorityFixer]);
        
        // Use reflection to check internal order
        $reflection = new \ReflectionClass($service);
        $strategiesProperty = $reflection->getProperty('strategies');
        $strategiesProperty->setAccessible(true);
        $strategies = $strategiesProperty->getValue($service);
        
        // High priority should be first
        $this->assertSame(100, $strategies[0]->getPriority());
        $this->assertSame(0, $strategies[1]->getPriority());
    }

    public function testStrategiesWithEqualPriorityPreserveInsertionOrder(): void
    {
        $fixerA = new class implements \PhpstanFixer\Strategy\FixStrategyInterface {
            public function canFix(\PhpstanFixer\Issue $issue): bool { return false; }
            public function fix(\PhpstanFixer\Issue $issue, string $fileContent): \PhpstanFixer\FixResult {
                return \PhpstanFixer\FixResult::failure($issue, $fileContent, '');
            }
            public function getDescription(): string { return ''; }
            public function getName(): string { return 'FixerA'; }
            public function getPriority(): int { return 50; }
        };

        $fixerB = new class implements \PhpstanFixer\Strategy\FixStrategyInterface {
            public function canFix(\PhpstanFixer\Issue $issue): bool { return false; }
            public function fix(\PhpstanFixer\Issue $issue, string $fileContent): \PhpstanFixer\FixResult {
                return \PhpstanFixer\FixResult::failure($issue, $fileContent, '');
            }
            public function getDescription(): string { return ''; }
            public function getName(): string { return 'FixerB'; }
            public function getPriority(): int { return 50; }
        };

        $fixerC = new class implements \PhpstanFixer\Strategy\FixStrategyInterface {
            public function canFix(\PhpstanFixer\Issue $issue): bool { return false; }
            public function fix(\PhpstanFixer\Issue $issue, string $fileContent): \PhpstanFixer\FixResult {
                return \PhpstanFixer\FixResult::failure($issue, $fileContent, '');
            }
            public function getDescription(): string { return ''; }
            public function getName(): string { return 'FixerC'; }
            public function getPriority(): int { return 50; }
        };

        // All have same priority - insertion order should be preserved
        $service = new AutoFixService([$fixerA, $fixerB, $fixerC]);
        
        // Use reflection to check internal order
        $reflection = new \ReflectionClass($service);
        $strategiesProperty = $reflection->getProperty('strategies');
        $strategiesProperty->setAccessible(true);
        $strategies = $strategiesProperty->getValue($service);
        
        // Order should be preserved: A, B, C
        $this->assertSame('FixerA', $strategies[0]->getName());
        $this->assertSame('FixerB', $strategies[1]->getName());
        $this->assertSame('FixerC', $strategies[2]->getName());
        
        // Verify all have same priority
        $this->assertSame(50, $strategies[0]->getPriority());
        $this->assertSame(50, $strategies[1]->getPriority());
        $this->assertSame(50, $strategies[2]->getPriority());
    }
}


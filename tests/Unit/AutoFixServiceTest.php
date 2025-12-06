<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
}


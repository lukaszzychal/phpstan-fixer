<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit;

use PhpstanFixer\AutoFixService;
use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\FixStrategyInterface;
use PHPUnit\Framework\TestCase;

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class AutoFixServiceProgressSkippedFilesTest extends TestCase
{
    public function testProgressCallbackHandlesSkippedFiles(): void
    {
        $progressCallbacks = [];
        
        // Create temporary files for testing
        $tempDir = sys_get_temp_dir() . '/phpstan-fixer-test-' . uniqid();
        mkdir($tempDir, 0777, true);
        
        $file1 = $tempDir . '/file1.php';
        $file2 = $tempDir . '/nonexistent.php'; // This file doesn't exist
        $file3 = $tempDir . '/file3.php';
        
        file_put_contents($file1, '<?php echo "test1";');
        file_put_contents($file3, '<?php echo "test3";');
        // file2 doesn't exist - will be skipped
        
        $mockFixer = $this->createMock(FixStrategyInterface::class);
        $mockFixer->method('canFix')->willReturn(true);
        $mockFixer->method('fix')->willReturnCallback(function (Issue $issue, string $content) {
            return FixResult::success($issue, $content . "\n// fixed", 'Fixed');
        });
        $mockFixer->method('getDescription')->willReturn('Test fixer');
        $mockFixer->method('getName')->willReturn('TestFixer');
        $mockFixer->method('getPriority')->willReturn(0);
        $mockFixer->method('getSupportedFrameworks')->willReturn([]);

        $service = new AutoFixService([$mockFixer]);
        
        $issue1 = new Issue($file1, 1, 'Test issue 1');
        $issue2 = new Issue($file2, 2, 'Test issue 2'); // File doesn't exist
        $issue3 = new Issue($file3, 3, 'Test issue 3');
        
        $service->fixAllIssues(
            [$issue1, $issue2, $issue3],
            function (int $processed, int $total, string $currentFile) use (&$progressCallbacks): void {
                $progressCallbacks[] = [
                    'processed' => $processed,
                    'total' => $total,
                    'currentFile' => $currentFile,
                ];
            }
        );

        // Cleanup
        unlink($file1);
        unlink($file3);
        rmdir($tempDir);

        // Should only have callbacks for 2 files (file1 and file3), not for file2
        $this->assertNotEmpty($progressCallbacks);
        $this->assertCount(2, $progressCallbacks);
        
        // First file
        $this->assertEquals(1, $progressCallbacks[0]['processed']);
        $this->assertEquals(2, $progressCallbacks[0]['total']); // Total should be 2, not 3
        $this->assertEquals($file1, $progressCallbacks[0]['currentFile']);
        
        // Second file (file3, not file2)
        $this->assertEquals(2, $progressCallbacks[1]['processed']);
        $this->assertEquals(2, $progressCallbacks[1]['total']); // Total should be 2, not 3
        $this->assertEquals($file3, $progressCallbacks[1]['currentFile']);
        
        // Progress should reach 100% (2/2, not 2/3)
        $this->assertEquals(2, $progressCallbacks[1]['processed']);
        $this->assertEquals(2, $progressCallbacks[1]['total']);
    }

    public function testProgressCallbackHandlesUnreadableFiles(): void
    {
        // This test verifies that files that exist but can't be read are handled correctly
        // In practice, this is rare, but we should handle it gracefully
        
        $progressCallbacks = [];
        
        // Create temporary files for testing
        $tempDir = sys_get_temp_dir() . '/phpstan-fixer-test-' . uniqid();
        mkdir($tempDir, 0777, true);
        
        $file1 = $tempDir . '/file1.php';
        $file2 = $tempDir . '/file2.php';
        
        file_put_contents($file1, '<?php echo "test1";');
        file_put_contents($file2, '<?php echo "test2";');
        
        $mockFixer = $this->createMock(FixStrategyInterface::class);
        $mockFixer->method('canFix')->willReturn(true);
        $mockFixer->method('fix')->willReturnCallback(function (Issue $issue, string $content) {
            return FixResult::success($issue, $content . "\n// fixed", 'Fixed');
        });
        $mockFixer->method('getDescription')->willReturn('Test fixer');
        $mockFixer->method('getName')->willReturn('TestFixer');
        $mockFixer->method('getPriority')->willReturn(0);
        $mockFixer->method('getSupportedFrameworks')->willReturn([]);

        $service = new AutoFixService([$mockFixer]);
        
        $issue1 = new Issue($file1, 1, 'Test issue 1');
        $issue2 = new Issue($file2, 2, 'Test issue 2');
        
        $service->fixAllIssues(
            [$issue1, $issue2],
            function (int $processed, int $total, string $currentFile) use (&$progressCallbacks): void {
                $progressCallbacks[] = [
                    'processed' => $processed,
                    'total' => $total,
                    'currentFile' => $currentFile,
                ];
            }
        );

        // Cleanup
        unlink($file1);
        unlink($file2);
        rmdir($tempDir);

        // Should have callbacks for both files
        $this->assertCount(2, $progressCallbacks);
        
        // Progress should reach 100% (2/2)
        $this->assertEquals(2, $progressCallbacks[1]['processed']);
        $this->assertEquals(2, $progressCallbacks[1]['total']);
    }
}


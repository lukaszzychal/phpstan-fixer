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
final class AutoFixServiceProgressTest extends TestCase
{
    public function testFixAllIssuesCallsProgressCallback(): void
    {
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

        $this->assertNotEmpty($progressCallbacks);
        $this->assertCount(2, $progressCallbacks);
        
        // First file
        $this->assertEquals(1, $progressCallbacks[0]['processed']);
        $this->assertEquals(2, $progressCallbacks[0]['total']);
        $this->assertEquals($file1, $progressCallbacks[0]['currentFile']);
        
        // Second file
        $this->assertEquals(2, $progressCallbacks[1]['processed']);
        $this->assertEquals(2, $progressCallbacks[1]['total']);
        $this->assertEquals($file2, $progressCallbacks[1]['currentFile']);
    }

    public function testProgressCallbackNotCalledWhenNotProvided(): void
    {
        // Create temporary file for testing
        $tempDir = sys_get_temp_dir() . '/phpstan-fixer-test-' . uniqid();
        mkdir($tempDir, 0777, true);
        
        $file1 = $tempDir . '/file1.php';
        file_put_contents($file1, '<?php echo "test";');
        
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
        
        $issue = new Issue($file1, 1, 'Test issue');
        
        // Should not throw exception when callback is not provided
        $results = $service->fixAllIssues([$issue]);
        
        // Cleanup
        unlink($file1);
        rmdir($tempDir);
        
        $this->assertNotEmpty($results);
    }
}


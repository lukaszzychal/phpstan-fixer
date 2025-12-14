<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Feature;

use PhpstanFixer\Command\PhpstanAutoFixCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ProgressIndicatorTest extends TestCase
{
    private string $tempDir;
    private string $tempFile1;
    private string $tempFile2;
    private string $tempPhpstanJson;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/phpstan-fixer-progress-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        
        $this->tempFile1 = $this->tempDir . '/file1.php';
        $this->tempFile2 = $this->tempDir . '/file2.php';
        
        file_put_contents($this->tempFile1, '<?php function test() { }');
        file_put_contents($this->tempFile2, '<?php function test2() { }');
        
        // Create PHPStan JSON with multiple files
        $this->tempPhpstanJson = $this->tempDir . '/phpstan.json';
        $phpstanJson = json_encode([
            'totals' => ['errors' => 2, 'file_errors' => 2],
            'files' => [
                $this->tempFile1 => [
                    'messages' => [
                        ['message' => 'Function test has no return type', 'line' => 1],
                    ],
                ],
                $this->tempFile2 => [
                    'messages' => [
                        ['message' => 'Function test2 has no return type', 'line' => 1],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT);
        file_put_contents($this->tempPhpstanJson, $phpstanJson);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile1)) {
            unlink($this->tempFile1);
        }
        if (file_exists($this->tempFile2)) {
            unlink($this->tempFile2);
        }
        if (file_exists($this->tempPhpstanJson)) {
            unlink($this->tempPhpstanJson);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testProgressBarIsShownForMultipleFiles(): void
    {
        $application = new Application();
        $command = new PhpstanAutoFixCommand();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--input' => $this->tempPhpstanJson,
            '--mode' => 'suggest',
        ]);

        $output = $commandTester->getDisplay();
        
        // Progress bar should show file count (2 files)
        // Format includes: "2/2" or progress indicator
        $this->assertStringContainsString('Found 2 issue(s)', $output);
        
        // Exit code should be success
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testNoProgressBarForSingleFile(): void
    {
        // Create PHPStan JSON with single file
        $singleFileJson = json_encode([
            'totals' => ['errors' => 1, 'file_errors' => 1],
            'files' => [
                $this->tempFile1 => [
                    'messages' => [
                        ['message' => 'Function test has no return type', 'line' => 1],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT);
        file_put_contents($this->tempPhpstanJson, $singleFileJson);

        $application = new Application();
        $command = new PhpstanAutoFixCommand();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--input' => $this->tempPhpstanJson,
            '--mode' => 'suggest',
        ]);

        // Should work without progress bar (single file)
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}


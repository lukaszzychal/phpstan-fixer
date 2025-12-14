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
use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\FixStrategyInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Example custom fixer for testing.
 */
final class TestCustomFixer implements FixStrategyInterface
{
    public function canFix(Issue $issue): bool
    {
        return str_contains($issue->getMessage(), 'Custom error pattern');
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        // Simple fix: add a comment
        $lines = explode("\n", $fileContent);
        $lineIndex = $issue->getLine() - 1;
        
        if (isset($lines[$lineIndex])) {
            $lines[$lineIndex] = '// Fixed by CustomFixer' . "\n" . $lines[$lineIndex];
            $newContent = implode("\n", $lines);
            return FixResult::success($issue, $newContent, 'Added custom fix comment');
        }
        
        return FixResult::failure($issue, $fileContent, 'Could not apply fix');
    }

    public function getDescription(): string
    {
        return 'Test custom fixer for custom error patterns';
    }

    public function getName(): string
    {
        return 'TestCustomFixer';
    }

    public function getPriority(): int
    {
        return 0;
    }
}

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class CustomFixerTest extends TestCase
{
    private string $tempDir;
    private string $tempConfigFile;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tempDir = sys_get_temp_dir() . '/phpstan-fixer-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->tempConfigFile = $this->tempDir . '/phpstan-fixer.json';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testCommandLoadsCustomFixerFromConfig(): void
    {
        // Create config with custom fixer
        $configContent = json_encode([
            'custom_fixers' => [
                TestCustomFixer::class,
            ],
        ], JSON_PRETTY_PRINT);
        file_put_contents($this->tempConfigFile, $configContent);

        // Create PHPStan JSON with custom error
        $phpstanJson = json_encode([
            'totals' => ['errors' => 1, 'file_errors' => 1],
            'files' => [
                'test.php' => [
                    'errors' => 1,
                    'messages' => [
                        [
                            'message' => 'Custom error pattern detected',
                            'line' => 5,
                            'ignorable' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $phpstanFile = $this->tempDir . '/phpstan.json';
        file_put_contents($phpstanFile, $phpstanJson);

        $application = new Application();
        $command = new PhpstanAutoFixCommand();
        $application->add($command);
        $commandTester = new CommandTester($command);

        // Set config file path via environment or find it automatically
        $commandTester->execute([
            '--input' => $phpstanFile,
            '--mode' => 'suggest',
        ], [
            'PHPSTAN_FIXER_CONFIG' => $this->tempConfigFile,
        ]);

        // Command should execute without errors
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testCommandThrowsWhenCustomFixerClassNotFound(): void
    {
        $configContent = json_encode([
            'custom_fixers' => [
                'NonExistent\\FixerClass',
            ],
        ], JSON_PRETTY_PRINT);
        file_put_contents($this->tempConfigFile, $configContent);

        $phpstanJson = json_encode([
            'totals' => ['errors' => 0, 'file_errors' => 0],
            'files' => [],
        ]);

        $phpstanFile = $this->tempDir . '/phpstan.json';
        file_put_contents($phpstanFile, $phpstanJson);

        $application = new Application();
        $command = new PhpstanAutoFixCommand();
        $application->add($command);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            '--input' => $phpstanFile,
            '--mode' => 'suggest',
            '--config' => $this->tempConfigFile,
        ]);

        // Should fail with error about missing class
        $this->assertEquals(1, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('not found', $output);
    }
}


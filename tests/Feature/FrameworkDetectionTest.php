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
use PhpstanFixer\Strategy\UndefinedPivotPropertyFixer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class FrameworkDetectionTest extends TestCase
{
    private string $tempDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/phpstan-fixer-framework-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->originalCwd = getcwd();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        parent::tearDown();
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

    public function testLaravelFrameworkSpecificFixerIsIncludedForLaravel(): void
    {
        // Create Laravel-like project
        $composerJson = json_encode([
            'require' => [
                'laravel/framework' => '^10.0',
            ],
        ], JSON_PRETTY_PRINT);
        file_put_contents($this->tempDir . '/composer.json', $composerJson);

        // Create a minimal PHPStan JSON (empty result)
        $phpstanJson = json_encode(['files' => []], JSON_PRETTY_PRINT);
        file_put_contents($this->tempDir . '/phpstan.json', $phpstanJson);

        $application = new Application();
        $command = new PhpstanAutoFixCommand();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--input' => $this->tempDir . '/phpstan.json',
            '--mode' => 'suggest',
        ]);

        $output = $commandTester->getDisplay();
        
        // Should detect Laravel framework
        $this->assertStringContainsString('Detected framework: laravel', $output);
        
        // Exit code should be success (0)
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testSymfonyFrameworkDetection(): void
    {
        // Create Symfony-like project
        $composerJson = json_encode([
            'require' => [
                'symfony/symfony' => '^6.0',
            ],
        ], JSON_PRETTY_PRINT);
        file_put_contents($this->tempDir . '/composer.json', $composerJson);

        // Create a minimal PHPStan JSON (empty result)
        $phpstanJson = json_encode(['files' => []], JSON_PRETTY_PRINT);
        file_put_contents($this->tempDir . '/phpstan.json', $phpstanJson);

        $application = new Application();
        $command = new PhpstanAutoFixCommand();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--input' => $this->tempDir . '/phpstan.json',
            '--mode' => 'suggest',
        ]);

        $output = $commandTester->getDisplay();
        
        // Should detect Symfony framework
        $this->assertStringContainsString('Detected framework: symfony', $output);
        
        // Exit code should be success (0)
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testFrameworkSpecificFixerExcludedWhenFrameworkNotDetected(): void
    {
        // Create non-framework project
        $composerJson = json_encode([
            'require' => [
                'php' => '^8.1',
            ],
        ], JSON_PRETTY_PRINT);
        file_put_contents($this->tempDir . '/composer.json', $composerJson);

        // Create a minimal PHPStan JSON (empty result)
        $phpstanJson = json_encode(['files' => []], JSON_PRETTY_PRINT);
        file_put_contents($this->tempDir . '/phpstan.json', $phpstanJson);

        $application = new Application();
        $command = new PhpstanAutoFixCommand();
        $application->add($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            '--input' => $this->tempDir . '/phpstan.json',
            '--mode' => 'suggest',
        ]);

        // Should not show framework detection message
        $output = $commandTester->getDisplay();
        $this->assertStringNotContainsString('Detected framework:', $output);
        
        // Exit code should be success (0)
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testUndefinedPivotPropertyFixerSupportsLaravel(): void
    {
        $fixer = new UndefinedPivotPropertyFixer(
            new \PhpstanFixer\CodeAnalysis\PhpFileAnalyzer(),
            new \PhpstanFixer\CodeAnalysis\DocblockManipulator()
        );

        $frameworks = $fixer->getSupportedFrameworks();
        
        $this->assertContains('laravel', $frameworks);
        $this->assertCount(1, $frameworks);
    }
}


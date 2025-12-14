<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Framework;

use PhpstanFixer\Framework\FrameworkDetector;
use PHPUnit\Framework\TestCase;

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class FrameworkDetectorTest extends TestCase
{
    private FrameworkDetector $detector;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = new FrameworkDetector();
        $this->tempDir = sys_get_temp_dir() . '/phpstan-fixer-framework-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
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

    public function testDetectLaravelFromComposerJson(): void
    {
        $composerJson = json_encode([
            'require' => [
                'laravel/framework' => '^10.0',
            ],
        ], JSON_PRETTY_PRINT);

        $composerPath = $this->tempDir . '/composer.json';
        file_put_contents($composerPath, $composerJson);

        $framework = $this->detector->detect($this->tempDir);

        $this->assertSame('laravel', $framework);
    }

    public function testDetectLaravelFromDirectoryStructure(): void
    {
        // Create Laravel-like directory structure
        mkdir($this->tempDir . '/app', 0777, true);
        mkdir($this->tempDir . '/config', 0777, true);
        mkdir($this->tempDir . '/routes', 0777, true);
        file_put_contents($this->tempDir . '/artisan', '#!/usr/bin/env php');

        $framework = $this->detector->detect($this->tempDir);

        $this->assertSame('laravel', $framework);
    }

    public function testDetectSymfonyFromComposerJson(): void
    {
        $composerJson = json_encode([
            'require' => [
                'symfony/symfony' => '^6.0',
            ],
        ], JSON_PRETTY_PRINT);

        $composerPath = $this->tempDir . '/composer.json';
        file_put_contents($composerPath, $composerJson);

        $framework = $this->detector->detect($this->tempDir);

        $this->assertSame('symfony', $framework);
    }

    public function testDetectSymfonyFromComponentPackages(): void
    {
        $composerJson = json_encode([
            'require' => [
                'symfony/console' => '^6.0',
                'symfony/http-foundation' => '^6.0',
            ],
        ], JSON_PRETTY_PRINT);

        $composerPath = $this->tempDir . '/composer.json';
        file_put_contents($composerPath, $composerJson);

        $framework = $this->detector->detect($this->tempDir);

        $this->assertSame('symfony', $framework);
    }

    public function testDetectSymfonyFromDirectoryStructure(): void
    {
        // Create Symfony-like directory structure
        mkdir($this->tempDir . '/src', 0777, true);
        mkdir($this->tempDir . '/config', 0777, true);
        mkdir($this->tempDir . '/public', 0777, true);
        file_put_contents($this->tempDir . '/symfony.lock', '');

        $framework = $this->detector->detect($this->tempDir);

        $this->assertSame('symfony', $framework);
    }

    public function testReturnsNullWhenNoFrameworkDetected(): void
    {
        $composerJson = json_encode([
            'require' => [
                'php' => '^8.1',
            ],
        ], JSON_PRETTY_PRINT);

        $composerPath = $this->tempDir . '/composer.json';
        file_put_contents($composerPath, $composerJson);

        $framework = $this->detector->detect($this->tempDir);

        $this->assertNull($framework);
    }

    public function testLaravelTakesPrecedenceOverSymfony(): void
    {
        $composerJson = json_encode([
            'require' => [
                'laravel/framework' => '^10.0',
                'symfony/console' => '^6.0',
            ],
        ], JSON_PRETTY_PRINT);

        $composerPath = $this->tempDir . '/composer.json';
        file_put_contents($composerPath, $composerJson);

        $framework = $this->detector->detect($this->tempDir);

        $this->assertSame('laravel', $framework);
    }

    public function testReturnsNullWhenDirectoryDoesNotExist(): void
    {
        $framework = $this->detector->detect('/nonexistent/directory');

        $this->assertNull($framework);
    }
}


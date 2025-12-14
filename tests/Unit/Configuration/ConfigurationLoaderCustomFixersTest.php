<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Configuration;

use PhpstanFixer\Configuration\ConfigurationLoader;
use PHPUnit\Framework\TestCase;

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ConfigurationLoaderCustomFixersTest extends TestCase
{
    private ConfigurationLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new ConfigurationLoader();
        $this->tempDir = sys_get_temp_dir() . '/phpstan-fixer-test-' . uniqid();
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

    public function testLoadFromJsonFileWithCustomFixers(): void
    {
        $jsonContent = <<<'JSON'
{
  "custom_fixers": [
    "MyNamespace\\MyCustomFixer",
    "AnotherNamespace\\AnotherFixer"
  ]
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertInstanceOf(\PhpstanFixer\Configuration\Configuration::class, $config);
        $customFixers = $config->getCustomFixers();
        $this->assertCount(2, $customFixers);
        $this->assertContains('MyNamespace\\MyCustomFixer', $customFixers);
        $this->assertContains('AnotherNamespace\\AnotherFixer', $customFixers);
    }

    public function testLoadFromYamlFileWithCustomFixers(): void
    {
        if (!function_exists('yaml_parse') && !class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            $this->markTestSkipped('YAML extension or Symfony YAML is not available');
            /** @phpstan-ignore-next-line - PHPStan doesn't understand that markTestSkipped() may not always throw */
            return;
        }

        $yamlContent = <<<'YAML'
custom_fixers:
  - MyNamespace\MyCustomFixer
  - AnotherNamespace\AnotherFixer
YAML;

        $filePath = $this->tempDir . '/phpstan-fixer.yaml';
        file_put_contents($filePath, $yamlContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertInstanceOf(\PhpstanFixer\Configuration\Configuration::class, $config);
        $this->assertCount(2, $config->getCustomFixers());
    }

    public function testThrowsWhenCustomFixersIsNotArray(): void
    {
        $jsonContent = <<<'JSON'
{
  "custom_fixers": "not-an-array"
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration "custom_fixers" must be an array');

        $this->loader->loadFromFile($filePath);
    }

    public function testThrowsWhenCustomFixerIsNotString(): void
    {
        $jsonContent = <<<'JSON'
{
  "custom_fixers": [
    "ValidFixer",
    123
  ]
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration "custom_fixers[1]" must be a string');

        $this->loader->loadFromFile($filePath);
    }
}


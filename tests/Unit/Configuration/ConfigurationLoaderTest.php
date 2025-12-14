<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Configuration;

use PhpstanFixer\Configuration\Configuration;
use PhpstanFixer\Configuration\ConfigurationLoader;
use PhpstanFixer\Configuration\Rule;
use PHPUnit\Framework\TestCase;

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ConfigurationLoaderTest extends TestCase
{
    private ConfigurationLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->loader = new ConfigurationLoader();
        $this->tempDir = sys_get_temp_dir() . '/phpstan-fixer-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testLoadFromJsonFile(): void
    {
        $jsonContent = <<<'JSON'
{
  "rules": {
    "Access to an undefined property": {
      "action": "fix"
    },
    "Method has no return type": {
      "action": "ignore"
    }
  },
  "default": {
    "action": "report"
  }
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertTrue($config->getRuleForError('Access to an undefined property')->isFix());
        $this->assertTrue($config->getRuleForError('Method has no return type')->isIgnore());
        $this->assertTrue($config->getDefault()->isReport());
    }

    public function testLoadFromJsonFileWithSimpleFormat(): void
    {
        $jsonContent = <<<'JSON'
{
  "rules": {
    "Access to an undefined property": "fix",
    "Method has no return type": "ignore"
  },
  "default": "report"
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertTrue($config->getRuleForError('Access to an undefined property')->isFix());
        $this->assertTrue($config->getRuleForError('Method has no return type')->isIgnore());
        $this->assertTrue($config->getDefault()->isReport());
    }

    public function testLoadFromJsonFileThrowsExceptionWhenFileNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration file not found');

        $this->loader->loadFromFile('/non/existent/path.json');
    }

    public function testLoadFromJsonFileThrowsExceptionWhenInvalidJson(): void
    {
        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, '{ invalid json }');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse JSON configuration file');

        $this->loader->loadFromFile($filePath);
    }

    public function testLoadFromYamlFileThrowsExceptionWhenExtensionNotAvailable(): void
    {
        // Skip test if yaml extension is available
        if (function_exists('yaml_parse') || class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            $this->markTestSkipped('YAML extension or Symfony YAML is available');
            return;
        }

        $yamlContent = <<<'YAML'
rules:
  "Access to an undefined property":
    action: "fix"
default:
  action: "report"
YAML;

        $filePath = $this->tempDir . '/phpstan-fixer.yaml';
        file_put_contents($filePath, $yamlContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('YAML parsing requires ext-yaml extension');

        $this->loader->loadFromFile($filePath);
    }

    public function testLoadFromYamlFileWhenExtensionAvailable(): void
    {
        if (!function_exists('yaml_parse') && !class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            $this->markTestSkipped('YAML extension or Symfony YAML is not available');
            return;
        }

        $yamlContent = <<<'YAML'
rules:
  "Access to an undefined property":
    action: "fix"
  "Method has no return type":
    action: "ignore"
default:
  action: "report"
YAML;

        $filePath = $this->tempDir . '/phpstan-fixer.yaml';
        file_put_contents($filePath, $yamlContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertTrue($config->getRuleForError('Access to an undefined property')->isFix());
        $this->assertTrue($config->getRuleForError('Method has no return type')->isIgnore());
        $this->assertTrue($config->getDefault()->isReport());
    }

    public function testFindConfigurationFileInCurrentDirectory(): void
    {
        $jsonContent = '{"rules": {}, "default": {"action": "fix"}}';
        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $found = $this->loader->findConfigurationFile($this->tempDir);

        // Use realpath to handle symlinks and normalize paths
        $this->assertSame(realpath($filePath), $found !== null ? realpath($found) : null);
    }

    public function testFindConfigurationFileReturnsNullWhenNotFound(): void
    {
        $found = $this->loader->findConfigurationFile($this->tempDir);

        $this->assertNull($found);
    }

    public function testLoadFromFileThrowsExceptionForUnsupportedFormat(): void
    {
        $filePath = $this->tempDir . '/config.txt';
        file_put_contents($filePath, 'some content');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported configuration file format: txt');

        $this->loader->loadFromFile($filePath);
    }

    public function testThrowsWhenRuleHasInvalidAction(): void
    {
        $jsonContent = <<<'JSON'
{
  "rules": {
    "Some pattern": {
      "action": "skip"
    }
  }
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid action "skip" for pattern "Some pattern"');

        $this->loader->loadFromFile($filePath);
    }

    public function testThrowsWhenRuleMissingAction(): void
    {
        $jsonContent = <<<'JSON'
{
  "rules": {
    "Some pattern": {
      "note": "missing action"
    }
  }
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rule for pattern "Some pattern" must be a string action or object with "action"');

        $this->loader->loadFromFile($filePath);
    }

    public function testThrowsWhenDefaultActionIsInvalid(): void
    {
        $jsonContent = <<<'JSON'
{
  "rules": {},
  "default": "skip"
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid action "skip" for pattern "default"');

        $this->loader->loadFromFile($filePath);
    }

    public function testLoadFromJsonFileWithFixersSection(): void
    {
        $jsonContent = <<<'JSON'
{
  "rules": {
    "Access to an undefined property": "fix"
  },
  "fixers": {
    "enabled": [
      "MissingReturnDocblockFixer",
      "MissingParamDocblockFixer"
    ],
    "disabled": [
      "UndefinedPivotPropertyFixer"
    ],
    "priorities": {
      "MissingReturnDocblockFixer": 100,
      "MissingParamDocblockFixer": 90
    }
  }
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertInstanceOf(Configuration::class, $config);
        $enabled = $config->getEnabledFixers();
        $this->assertCount(2, $enabled);
        $this->assertContains('MissingReturnDocblockFixer', $enabled);
        $this->assertContains('MissingParamDocblockFixer', $enabled);

        $disabled = $config->getDisabledFixers();
        $this->assertCount(1, $disabled);
        $this->assertContains('UndefinedPivotPropertyFixer', $disabled);

        $this->assertSame(100, $config->getFixerPriority('MissingReturnDocblockFixer'));
        $this->assertSame(90, $config->getFixerPriority('MissingParamDocblockFixer'));
    }

    public function testLoadFromJsonFileWithOnlyEnabledFixers(): void
    {
        $jsonContent = <<<'JSON'
{
  "fixers": {
    "enabled": ["MissingReturnDocblockFixer"]
  }
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertTrue($config->isFixerEnabled('MissingReturnDocblockFixer'));
        $this->assertFalse($config->isFixerEnabled('MissingParamDocblockFixer'));
    }

    public function testLoadFromJsonFileWithOnlyDisabledFixers(): void
    {
        $jsonContent = <<<'JSON'
{
  "fixers": {
    "disabled": ["UndefinedPivotPropertyFixer"]
  }
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertFalse($config->isFixerEnabled('UndefinedPivotPropertyFixer'));
        $this->assertTrue($config->isFixerEnabled('MissingReturnDocblockFixer'));
    }

    public function testLoadFromJsonFileWithOnlyPriorities(): void
    {
        $jsonContent = <<<'JSON'
{
  "fixers": {
    "priorities": {
      "MissingReturnDocblockFixer": 100
    }
  }
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertSame(100, $config->getFixerPriority('MissingReturnDocblockFixer'));
        $this->assertNull($config->getFixerPriority('MissingParamDocblockFixer'));
    }

    public function testLoadFromYamlFileWithFixersSection(): void
    {
        if (!function_exists('yaml_parse') && !class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            $this->markTestSkipped('YAML extension or Symfony YAML is not available');
            return;
        }

        $yamlContent = <<<'YAML'
fixers:
  enabled:
    - MissingReturnDocblockFixer
  disabled:
    - UndefinedPivotPropertyFixer
  priorities:
    MissingReturnDocblockFixer: 100
YAML;

        $filePath = $this->tempDir . '/phpstan-fixer.yaml';
        file_put_contents($filePath, $yamlContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertTrue($config->isFixerEnabled('MissingReturnDocblockFixer'));
        $this->assertFalse($config->isFixerEnabled('UndefinedPivotPropertyFixer'));
        $this->assertSame(100, $config->getFixerPriority('MissingReturnDocblockFixer'));
    }

    public function testThrowsWhenFixersEnabledIsNotArray(): void
    {
        $jsonContent = <<<'JSON'
{
  "fixers": {
    "enabled": "not-an-array"
  }
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration "fixers.enabled" must be an array');

        $this->loader->loadFromFile($filePath);
    }

    public function testThrowsWhenFixersDisabledIsNotArray(): void
    {
        $jsonContent = <<<'JSON'
{
  "fixers": {
    "disabled": "not-an-array"
  }
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration "fixers.disabled" must be an array');

        $this->loader->loadFromFile($filePath);
    }

    public function testThrowsWhenFixersPrioritiesIsNotObject(): void
    {
        $jsonContent = <<<'JSON'
{
  "fixers": {
    "priorities": "not-an-object"
  }
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration "fixers.priorities" must be an object');

        $this->loader->loadFromFile($filePath);
    }

    public function testLoadFromJsonFileWithIncludeExcludePaths(): void
    {
        $jsonContent = <<<'JSON'
{
  "include_paths": ["src/", "app/"],
  "exclude_paths": ["vendor/", "tests/", "**/*Test.php"]
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertInstanceOf(Configuration::class, $config);
        $includePaths = $config->getIncludePaths();
        $this->assertCount(2, $includePaths);
        $this->assertContains('src/', $includePaths);
        $this->assertContains('app/', $includePaths);

        $excludePaths = $config->getExcludePaths();
        $this->assertCount(3, $excludePaths);
        $this->assertContains('vendor/', $excludePaths);
        $this->assertContains('tests/', $excludePaths);
        $this->assertContains('**/*Test.php', $excludePaths);
    }

    public function testLoadFromYamlFileWithIncludeExcludePaths(): void
    {
        if (!function_exists('yaml_parse') && !class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            $this->markTestSkipped('YAML extension or Symfony YAML is not available');
            return;
        }

        $yamlContent = <<<'YAML'
include_paths:
  - src/
  - app/
exclude_paths:
  - vendor/
  - "**/*Test.php"
YAML;

        $filePath = $this->tempDir . '/phpstan-fixer.yaml';
        file_put_contents($filePath, $yamlContent);

        $config = $this->loader->loadFromFile($filePath);

        $this->assertInstanceOf(Configuration::class, $config);
        $this->assertCount(2, $config->getIncludePaths());
        $this->assertCount(2, $config->getExcludePaths());
    }

    public function testThrowsWhenIncludePathsIsNotArray(): void
    {
        $jsonContent = <<<'JSON'
{
  "include_paths": "not-an-array"
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration "include_paths" must be an array');

        $this->loader->loadFromFile($filePath);
    }

    public function testThrowsWhenExcludePathsIsNotArray(): void
    {
        $jsonContent = <<<'JSON'
{
  "exclude_paths": "not-an-array"
}
JSON;

        $filePath = $this->tempDir . '/phpstan-fixer.json';
        file_put_contents($filePath, $jsonContent);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Configuration "exclude_paths" must be an array');

        $this->loader->loadFromFile($filePath);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}


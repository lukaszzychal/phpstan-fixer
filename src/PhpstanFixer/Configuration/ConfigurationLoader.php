<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Configuration;

/**
 * Loads configuration from YAML or JSON files.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ConfigurationLoader
{
    /**
     * Load configuration from a file.
     *
     * @param string $filePath Path to configuration file
     * @return Configuration Loaded configuration
     * @throws \RuntimeException If file cannot be loaded or parsed
     */
    public function loadFromFile(string $filePath): Configuration
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Configuration file not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \RuntimeException("Configuration file is not readable: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'yaml', 'yml' => $this->loadFromYaml($filePath),
            'json' => $this->loadFromJson($filePath),
            default => throw new \RuntimeException(
                "Unsupported configuration file format: {$extension}. Supported: yaml, yml, json"
            ),
        };
    }

    /**
     * Find configuration file in common locations.
     *
     * @param string|null $startDir Starting directory (defaults to current working directory)
     * @return string|null Path to configuration file or null if not found
     */
    public function findConfigurationFile(?string $startDir = null): ?string
    {
        $startDir = $startDir ?? getcwd();
        $dir = realpath($startDir);

        if ($dir === false) {
            return null;
        }

        $configNames = ['phpstan-fixer.yaml', 'phpstan-fixer.yml', 'phpstan-fixer.json', '.phpstan-fixer.yaml'];

        // Check current directory
        foreach ($configNames as $name) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        // Traverse up to find project root (look for composer.json)
        $maxDepth = 10;
        $currentDir = $dir;

        for ($i = 0; $i < $maxDepth; $i++) {
            foreach ($configNames as $name) {
                $path = $currentDir . DIRECTORY_SEPARATOR . $name;
                if (file_exists($path) && is_readable($path)) {
                    return $path;
                }
            }

            // Check if we're at project root (has composer.json)
            if (file_exists($currentDir . DIRECTORY_SEPARATOR . 'composer.json')) {
                break;
            }

            $parentDir = dirname($currentDir);
            if ($parentDir === $currentDir) {
                // Reached filesystem root
                break;
            }
            $currentDir = $parentDir;
        }

        return null;
    }

    /**
     * Load configuration from YAML file.
     *
     * @param string $filePath Path to YAML file
     * @return Configuration Loaded configuration
     */
    private function loadFromYaml(string $filePath): Configuration
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read configuration file: {$filePath}");
        }

        // Try to use yaml_parse if available (ext-yaml)
        if (function_exists('yaml_parse')) {
            $data = yaml_parse($content);
            if ($data === false) {
                throw new \RuntimeException("Failed to parse YAML configuration file: {$filePath}");
            }
        } elseif (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
            // Fallback to Symfony YAML if available
            $data = \Symfony\Component\Yaml\Yaml::parse($content);
            if ($data === false || !is_array($data)) {
                throw new \RuntimeException("Failed to parse YAML configuration file: {$filePath}");
            }
        } else {
            throw new \RuntimeException(
                "YAML parsing requires ext-yaml extension or symfony/yaml package. " .
                "Install ext-yaml (pecl install yaml) or add symfony/yaml to composer.json"
            );
        }

        return $this->buildConfiguration($data);
    }

    /**
     * Load configuration from JSON file.
     *
     * @param string $filePath Path to JSON file
     * @return Configuration Loaded configuration
     */
    private function loadFromJson(string $filePath): Configuration
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read configuration file: {$filePath}");
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                "Failed to parse JSON configuration file: {$filePath}. Error: " . json_last_error_msg()
            );
        }

        return $this->buildConfiguration($data);
    }

    /**
     * Build Configuration object from parsed data.
     *
     * @param array<string, mixed> $data Parsed configuration data
     * @return Configuration Built configuration
     */
    private function buildConfiguration(array $data): Configuration
    {
        $rules = [];
        $defaultAction = Rule::ACTION_FIX;

        // Parse rules
        if (isset($data['rules']) && is_array($data['rules'])) {
            foreach ($data['rules'] as $pattern => $ruleData) {
                if (is_string($ruleData)) {
                    // Simple format: "pattern": "action"
                    $action = $ruleData;
                } elseif (is_array($ruleData) && isset($ruleData['action'])) {
                    // Full format: "pattern": { "action": "fix" }
                    $action = $ruleData['action'];
                } else {
                    continue;
                }

                $rules[$pattern] = new Rule($action);
            }
        }

        // Parse default
        if (isset($data['default'])) {
            if (is_string($data['default'])) {
                $defaultAction = $data['default'];
            } elseif (is_array($data['default']) && isset($data['default']['action'])) {
                $defaultAction = $data['default']['action'];
            }
        }

        return new Configuration($rules, new Rule($defaultAction));
    }
}


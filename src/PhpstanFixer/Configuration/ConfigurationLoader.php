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

        if (isset($data['rules']) && !is_array($data['rules'])) {
            throw new \RuntimeException('Configuration "rules" must be an object/map.');
        }

        // Parse rules
        if (isset($data['rules']) && is_array($data['rules'])) {
            foreach ($data['rules'] as $pattern => $ruleData) {
                $action = $this->extractAction($pattern, $ruleData);
                $rules[$pattern] = $this->createRule($pattern, $action);
            }
        }

        // Parse default
        if (isset($data['default'])) {
            if (is_string($data['default'])) {
                $defaultAction = $data['default'];
            } elseif (is_array($data['default']) && isset($data['default']['action'])) {
                $defaultAction = $data['default']['action'];
            } else {
                throw new \RuntimeException('Configuration "default" must be a string action or object with action.');
            }
        }

        // Parse fixers section
        [$enabledFixers, $disabledFixers, $fixerPriorities] = $this->parseFixersSection($data);

        // Parse include_paths
        $includePaths = [];
        if (isset($data['include_paths'])) {
            if (!is_array($data['include_paths'])) {
                throw new \RuntimeException('Configuration "include_paths" must be an array.');
            }
            $includePaths = $this->extractStringArray($data['include_paths'], 'include_paths');
        }

        // Parse exclude_paths
        $excludePaths = [];
        if (isset($data['exclude_paths'])) {
            if (!is_array($data['exclude_paths'])) {
                throw new \RuntimeException('Configuration "exclude_paths" must be an array.');
            }
            $excludePaths = $this->extractStringArray($data['exclude_paths'], 'exclude_paths');
        }

        return new Configuration(
            $rules,
            $this->createRule('default', $defaultAction),
            $enabledFixers,
            $disabledFixers,
            $fixerPriorities,
            $includePaths,
            $excludePaths
        );
    }

    /**
     * Parse fixers section from configuration data.
     *
     * @param array<string, mixed> $data Parsed configuration data
     * @return array{0: array<string>, 1: array<string>, 2: array<string, int>} [enabled, disabled, priorities]
     */
    private function parseFixersSection(array $data): array
    {
        $enabledFixers = [];
        $disabledFixers = [];
        $fixerPriorities = [];

        if (!isset($data['fixers'])) {
            return [$enabledFixers, $disabledFixers, $fixerPriorities];
        }

        if (!is_array($data['fixers'])) {
            throw new \RuntimeException('Configuration "fixers" must be an object/map.');
        }

        $fixersData = $data['fixers'];

        // Parse enabled fixers
        if (isset($fixersData['enabled'])) {
            if (!is_array($fixersData['enabled'])) {
                throw new \RuntimeException('Configuration "fixers.enabled" must be an array.');
            }
            $enabledFixers = $this->extractStringArray($fixersData['enabled'], 'fixers.enabled');
        }

        // Parse disabled fixers
        if (isset($fixersData['disabled'])) {
            if (!is_array($fixersData['disabled'])) {
                throw new \RuntimeException('Configuration "fixers.disabled" must be an array.');
            }
            $disabledFixers = $this->extractStringArray($fixersData['disabled'], 'fixers.disabled');
        }

        // Parse priorities
        if (isset($fixersData['priorities'])) {
            if (!is_array($fixersData['priorities'])) {
                throw new \RuntimeException('Configuration "fixers.priorities" must be an object.');
            }
            $fixerPriorities = $this->extractPriorities($fixersData['priorities']);
        }

        return [$enabledFixers, $disabledFixers, $fixerPriorities];
    }

    /**
     * Extract string array from configuration data.
     *
     * @param mixed $data The data to extract from
     * @param string $path The configuration path for error messages
     * @return array<string>
     */
    private function extractStringArray(mixed $data, string $path): array
    {
        $result = [];
        foreach ($data as $index => $item) {
            if (!is_string($item)) {
                throw new \RuntimeException(sprintf(
                    'Configuration "%s[%d]" must be a string, got %s',
                    $path,
                    $index,
                    gettype($item)
                ));
            }
            $result[] = $item;
        }
        return $result;
    }

    /**
     * Extract fixer priorities from configuration data.
     *
     * @param array<string, mixed> $data The priorities data
     * @return array<string, int>
     */
    private function extractPriorities(array $data): array
    {
        $priorities = [];
        foreach ($data as $fixerName => $priority) {
            if (!is_string($fixerName)) {
                throw new \RuntimeException(sprintf(
                    'Configuration "fixers.priorities" keys must be strings, got %s',
                    gettype($fixerName)
                ));
            }
            if (!is_int($priority)) {
                throw new \RuntimeException(sprintf(
                    'Configuration "fixers.priorities[\"%s\"]" must be an integer, got %s',
                    $fixerName,
                    gettype($priority)
                ));
            }
            $priorities[$fixerName] = $priority;
        }
        return $priorities;
    }

    /**
     * @param mixed $ruleData
     */
    private function extractAction(string $pattern, mixed $ruleData): string
    {
        if (is_string($ruleData)) {
            return $ruleData;
        }

        if (is_array($ruleData) && array_key_exists('action', $ruleData)) {
            if (!is_string($ruleData['action'])) {
                throw new \RuntimeException(sprintf(
                    'Invalid action type for pattern "%s": expected string',
                    $pattern
                ));
            }

            return $ruleData['action'];
        }

        throw new \RuntimeException(sprintf(
            'Rule for pattern "%s" must be a string action or object with "action"',
            $pattern
        ));
    }

    private function createRule(string $pattern, string $action): Rule
    {
        try {
            return new Rule($action);
        } catch (\InvalidArgumentException $exception) {
            throw new \RuntimeException(sprintf(
                'Invalid action "%s" for pattern "%s". Allowed: %s',
                $action,
                $pattern,
                implode(', ', [Rule::ACTION_FIX, Rule::ACTION_IGNORE, Rule::ACTION_REPORT])
            ), 0, $exception);
        }
    }
}


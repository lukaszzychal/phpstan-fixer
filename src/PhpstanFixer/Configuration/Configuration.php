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
 * Configuration value object for PHPStan Fixer.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class Configuration
{
    /**
     * @param array<string, Rule> $rules Error message patterns mapped to rules
     * @param Rule $default Default rule for unmatched errors
     * @param array<string> $enabledFixers List of enabled fixer names (empty = all enabled)
     * @param array<string> $disabledFixers List of disabled fixer names
     * @param array<string, int> $fixerPriorities Map of fixer names to priorities
     */
    public function __construct(
        private readonly array $rules = [],
        private readonly Rule $default = new Rule('fix'),
        private readonly array $enabledFixers = [],
        private readonly array $disabledFixers = [],
        private readonly array $fixerPriorities = []
    ) {
    }

    /**
     * Get rule for a specific error message.
     *
     * @param string $errorMessage The error message to match
     * @return Rule The matching rule or default rule
     */
    public function getRuleForError(string $errorMessage): Rule
    {
        foreach ($this->rules as $pattern => $rule) {
            if ($this->matchesPattern($errorMessage, $pattern)) {
                return $rule;
            }
        }

        return $this->default;
    }

    /**
     * Check if error message matches a pattern.
     *
     * @param string $errorMessage The error message
     * @param string $pattern The pattern (exact match, regex, or wildcard)
     * @return bool True if matches
     */
    private function matchesPattern(string $errorMessage, string $pattern): bool
    {
        // Exact match
        if ($errorMessage === $pattern) {
            return true;
        }

        // Regex pattern (if starts and ends with /)
        if (preg_match('/^\/.+\/[imsxADSUXu]*$/', $pattern)) {
            return (bool) preg_match($pattern, $errorMessage);
        }

        // Wildcard pattern (convert * to .* for regex)
        if (str_contains($pattern, '*')) {
            // Escape special regex characters except *
            $escaped = preg_quote($pattern, '/');
            // Replace escaped \* with .* (match any characters)
            $regex = '/^' . str_replace('\*', '.*', $escaped) . '$/';
            return (bool) preg_match($regex, $errorMessage);
        }

        return false;
    }

    /**
     * Get all rules.
     *
     * @return array<string, Rule>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Get default rule.
     */
    public function getDefault(): Rule
    {
        return $this->default;
    }

    /**
     * Check if configuration has any rules.
     */
    public function hasRules(): bool
    {
        return !empty($this->rules);
    }

    /**
     * Get list of enabled fixer names.
     * Empty array means all fixers are enabled (unless disabled list is specified).
     *
     * @return array<string>
     */
    public function getEnabledFixers(): array
    {
        return $this->enabledFixers;
    }

    /**
     * Get list of disabled fixer names.
     *
     * @return array<string>
     */
    public function getDisabledFixers(): array
    {
        return $this->disabledFixers;
    }

    /**
     * Get priority for a specific fixer.
     * Returns null if priority is not configured.
     *
     * @param string $fixerName The fixer name
     * @return int|null The priority or null if not configured
     */
    public function getFixerPriority(string $fixerName): ?int
    {
        return $this->fixerPriorities[$fixerName] ?? null;
    }

    /**
     * Check if a fixer is enabled.
     * Logic:
     * - If fixer is in disabled list → false
     * - If enabled list is empty → true (all enabled by default)
     * - If enabled list is not empty → true only if in enabled list
     *
     * @param string $fixerName The fixer name
     * @return bool True if enabled
     */
    public function isFixerEnabled(string $fixerName): bool
    {
        // Disabled list takes precedence
        if (in_array($fixerName, $this->disabledFixers, true)) {
            return false;
        }

        // If enabled list is empty, all fixers are enabled
        if (empty($this->enabledFixers)) {
            return true;
        }

        // Otherwise, only fixers in enabled list are enabled
        return in_array($fixerName, $this->enabledFixers, true);
    }
}


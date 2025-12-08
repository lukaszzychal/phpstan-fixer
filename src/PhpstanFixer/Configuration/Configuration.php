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
     */
    public function __construct(
        private readonly array $rules = [],
        private readonly Rule $default = new Rule('fix')
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
}


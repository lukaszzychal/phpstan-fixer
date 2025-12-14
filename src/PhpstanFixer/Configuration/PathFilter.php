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
 * Utility class for filtering file paths using glob patterns, regex, and exact paths.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class PathFilter
{
    /**
     * Check if a path matches any of the given patterns.
     *
     * @param string $path The file path to check
     * @param array<string> $patterns Array of patterns (glob, regex, or exact paths)
     * @return bool True if path matches any pattern
     */
    public static function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a path matches a single pattern.
     *
     * @param string $path The file path to check
     * @param string $pattern The pattern (glob, regex, or exact path)
     * @return bool True if path matches pattern
     */
    public static function matches(string $path, string $pattern): bool
    {
        // Regex pattern (if starts and ends with /) - check before normalization
        if (preg_match('/^\/.+\/[imsxADSUXu]*$/', $pattern)) {
            return (bool) preg_match($pattern, $path);
        }

        // Normalize paths for comparison
        $normalizedPath = self::normalizePath($path);
        $normalizedPattern = self::normalizePath($pattern);

        // Exact match
        if ($normalizedPath === $normalizedPattern) {
            return true;
        }

        // Check if pattern is a directory and path is inside it
        // Pattern ending with / is treated as directory prefix
        if (self::isDirectoryPattern($pattern)) {
            // For directory patterns, check if path starts with the pattern
            // Pattern already has trailing /, so we compare directly
            return str_starts_with($normalizedPath . '/', $normalizedPattern . '/');
        }

        // Glob pattern (convert to regex)
        if (str_contains($normalizedPattern, '*') || str_contains($normalizedPattern, '?')) {
            return self::matchesGlob($normalizedPath, $normalizedPattern);
        }

        return false;
    }

    /**
     * Normalize path for comparison (remove trailing slashes, convert to forward slashes).
     */
    private static function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        return rtrim($normalized, '/');
    }

    /**
     * Check if pattern represents a directory (ends with /).
     * We check for trailing slash rather than using is_dir() to avoid filesystem dependencies.
     */
    private static function isDirectoryPattern(string $pattern): bool
    {
        return str_ends_with($pattern, '/');
    }

    /**
     * Match path against glob pattern.
     *
     * @param string $path Normalized path
     * @param string $pattern Glob pattern
     * @return bool True if matches
     */
    private static function matchesGlob(string $path, string $pattern): bool
    {
        // Convert glob pattern to regex
        $regex = self::globToRegex($pattern);
        return (bool) preg_match($regex, $path);
    }

    /**
     * Convert glob pattern to regex.
     *
     * @param string $glob Glob pattern (supports * and ?)
     * @return string Regex pattern
     */
    private static function globToRegex(string $glob): string
    {
        // Escape special regex characters
        $escaped = preg_quote($glob, '/');

        // Replace escaped glob patterns with regex equivalents
        // * matches any characters (except / in directory context, but we'll allow it for simplicity)
        $escaped = str_replace('\*', '.*', $escaped);
        // ? matches single character
        $escaped = str_replace('\?', '.', $escaped);

        // Handle ** (matches across directory boundaries)
        $escaped = str_replace('\.\*\.\*', '.*', $escaped);

        return '/^' . $escaped . '$/';
    }
}


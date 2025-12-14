<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\CodeAnalysis;

/**
 * Helper class for parsing PHPStan error messages.
 * Provides common methods to extract information from error messages.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ErrorMessageParser
{
    /**
     * Extract parameter name from error message.
     * Handles patterns like "Parameter $name", "Parameter #1 $name", "parameter 'name'", 
     * and simple variable references like "Unknown array offset type on $items".
     *
     * @param string $message The error message
     * @return string|null The parameter name (without $) or null if not found
     */
    public static function parseParameterName(string $message): ?string
    {
        // Try "Parameter #N $name" pattern first (most specific)
        if (preg_match('/Parameter\s+#\d+\s+\$(\w+)/i', $message, $matches)) {
            return $matches[1];
        }

        // Try "Parameter $name" pattern
        if (preg_match('/Parameter\s+\$(\w+)/i', $message, $matches)) {
            return $matches[1];
        }

        // Try "parameter 'name'" pattern
        if (preg_match("/parameter\s+['\"](\w+)['\"]/i", $message, $matches)) {
            return $matches[1];
        }

        // Try simple variable reference pattern (e.g., "Unknown array offset type on $items")
        // Look for $variable that appears to be in a parameter context
        if (preg_match('/\$(\w+)/', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract parameter index (position) from error message.
     * Handles patterns like "Parameter #1", "Parameter #2", etc.
     *
     * @param string $message The error message
     * @return int|null The parameter index (0-based) or null if not found
     */
    public static function parseParameterIndex(string $message): ?int
    {
        if (preg_match('/Parameter\s+#(\d+)/i', $message, $matches)) {
            $index = (int) $matches[1];
            // Convert to 0-based index
            return $index > 0 ? $index - 1 : null;
        }

        return null;
    }

    /**
     * Extract type from error message.
     * Handles patterns like "expects string", "expects array|string", "of type 'string'", etc.
     *
     * @param string $message The error message
     * @return string|null The type string or null if not found
     */
    public static function parseType(string $message): ?string
    {
        // Try "expects type" pattern
        if (preg_match("/expects\s+([a-zA-Z0-9_\\\|\[\]<>,\s]+?)(?:\s|$|,|\.|;)/i", $message, $matches)) {
            $type = trim($matches[1]);
            // Clean up common trailing words
            $type = preg_replace('/\s+(but|was|is|has|does).*$/i', '', $type);
            return $type ?: null;
        }

        // Try "of type 'type'" pattern
        if (preg_match("/of\s+type\s+['\"]?([a-zA-Z0-9_\\\|\[\]<>,\s]+?)['\"]?(?:\s|$|,|\.|;)/i", $message, $matches)) {
            return trim($matches[1]) ?: null;
        }

        return null;
    }

    /**
     * Extract class name from error message.
     * Handles patterns like "Class X", "Class X not found", "class 'X'", etc.
     *
     * @param string $message The error message
     * @return string|null The class name (FQN if available) or null if not found
     */
    public static function parseClassName(string $message): ?string
    {
        // Try "Class X" pattern
        if (preg_match('/Class\s+([\\\\a-zA-Z0-9_]+)/i', $message, $matches)) {
            return $matches[1];
        }

        // Try "class 'X'" pattern
        if (preg_match("/class\s+['\"]?([\\\\a-zA-Z0-9_]+)['\"]?/i", $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract exception type from error message.
     * Handles patterns like "throws Exception", "throws \Exception", "throwing 'Exception'", etc.
     *
     * @param string $message The error message
     * @return string|null The exception type (FQN if available) or null if not found
     */
    public static function parseExceptionType(string $message): ?string
    {
        // Try "throws X" pattern
        if (preg_match('/throws\s+([\\\\a-zA-Z0-9_]+)/i', $message, $matches)) {
            return $matches[1];
        }

        // Try "throwing 'X'" pattern
        if (preg_match("/throwing\s+['\"]?([\\\\a-zA-Z0-9_]+)['\"]?/i", $message, $matches)) {
            return $matches[1];
        }

        return null;
    }
}


<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer;

/**
 * Value object representing a single PHPStan error/issue.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class Issue
{
    public function __construct(
        private readonly string $filePath,
        private readonly int $line,
        private readonly string $message,
        private readonly ?string $errorCode = null,
        private readonly ?string $identifier = null,
        private readonly ?int $column = null
    ) {
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function getColumn(): ?int
    {
        return $this->column;
    }

    /**
     * Check if this issue matches a pattern in the error message.
     */
    public function matchesPattern(string $pattern): bool
    {
        return (bool) preg_match($pattern, $this->message);
    }

    /**
     * Extract a property name from error message (e.g., "Access to undefined property $foo")
     */
    public function extractPropertyName(): ?string
    {
        if (preg_match('/property\s+\$(\w+)/i', $this->message, $matches)) {
            return $matches[1];
        }
        if (preg_match('/\$(\w+)/', $this->message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract a method/function name from error message.
     */
    public function extractMethodName(): ?string
    {
        if (preg_match('/method\s+(\w+)/i', $this->message, $matches)) {
            return $matches[1];
        }
        if (preg_match('/function\s+(\w+)/i', $this->message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract a class name from error message.
     */
    public function extractClassName(): ?string
    {
        if (preg_match('/class\s+([\\\\\w]+)/i', $this->message, $matches)) {
            return $matches[1];
        }
        if (preg_match('/Class\s+([\\\\\w]+)/i', $this->message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Check if this is an undefined property error.
     */
    public function isUndefinedProperty(): bool
    {
        return $this->matchesPattern('/Access to (an )?undefined property/i');
    }

    /**
     * Check if this is an undefined method error.
     */
    public function isUndefinedMethod(): bool
    {
        return $this->matchesPattern('/Call to (an )?undefined method/i');
    }

    /**
     * Check if this is a missing return type error.
     */
    public function isMissingReturnType(): bool
    {
        return $this->matchesPattern('/(has no return type|Return type is missing)/i');
    }

    /**
     * Check if this is a missing parameter type error.
     */
    public function isMissingParameterType(): bool
    {
        return $this->matchesPattern('/Parameter.*has no type specified/i');
    }
}


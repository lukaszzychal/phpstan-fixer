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
 * Value object representing the result of a fix operation.
 *
 * @author Łukasz Zychal <lukasz@zychal.pl>
 */
final class FixResult
{
    public function __construct(
        private readonly Issue $issue,
        private readonly bool $successful,
        private readonly string $fixedContent,
        private readonly ?string $description = null,
        private readonly array $changes = []
    ) {
    }

    public function getIssue(): Issue
    {
        return $this->issue;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function getFixedContent(): string
    {
        return $this->fixedContent;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get a human-readable description of what changed.
     */
    public function getChangeDescription(): string
    {
        if ($this->description !== null) {
            return $this->description;
        }

        $baseMessage = $this->successful
            ? sprintf('Fixed issue at line %d', $this->issue->getLine())
            : sprintf('Could not fix issue at line %d', $this->issue->getLine());

        if (!empty($this->changes)) {
            return $baseMessage . ' (' . implode(', ', $this->changes) . ')';
        }

        return $baseMessage;
    }

    /**
     * Get detailed changes made.
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Create a successful fix result.
     */
    public static function success(
        Issue $issue,
        string $fixedContent,
        ?string $description = null,
        array $changes = []
    ): self {
        return new self($issue, true, $fixedContent, $description, $changes);
    }

    /**
     * Create a failed fix result (no changes made).
     */
    public static function failure(
        Issue $issue,
        string $originalContent,
        ?string $reason = null
    ): self {
        return new self(
            $issue,
            false,
            $originalContent,
            $reason ?? 'Could not determine how to fix this issue'
        );
    }

    /**
     * Check if the content actually changed.
     */
    public function hasChanges(): bool
    {
        return $this->successful && $this->fixedContent !== '';
    }
}


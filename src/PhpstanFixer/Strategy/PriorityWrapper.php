<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Strategy;

use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;

/**
 * Wrapper for a fix strategy that overrides its priority.
 * Used when configuration specifies a custom priority for a fixer.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class PriorityWrapper implements FixStrategyInterface
{
    public function __construct(
        private readonly FixStrategyInterface $strategy,
        private readonly int $priority
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $this->strategy->canFix($issue);
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        return $this->strategy->fix($issue, $fileContent);
    }

    public function getDescription(): string
    {
        return $this->strategy->getDescription();
    }

    public function getName(): string
    {
        return $this->strategy->getName();
    }

    public function getPriority(): int
    {
        return $this->priority;
    }
}


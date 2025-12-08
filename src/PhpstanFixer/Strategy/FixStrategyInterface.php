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
 * Interface for all fix strategies.
 * Each fixer implements this interface to handle specific types of PHPStan errors.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
interface FixStrategyInterface
{
    /**
     * Check if this strategy can fix the given issue.
     */
    public function canFix(Issue $issue): bool;

    /**
     * Apply the fix for the given issue.
     *
     * @param Issue $issue The issue to fix
     * @param string $fileContent The current content of the file
     * @return FixResult The result of the fix operation
     */
    public function fix(Issue $issue, string $fileContent): FixResult;

    /**
     * Get a human-readable description of what this fixer does.
     */
    public function getDescription(): string;

    /**
     * Get the name/identifier of this fixer strategy.
     */
    public function getName(): string;
}


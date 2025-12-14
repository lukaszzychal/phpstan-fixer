<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Strategy;

/**
 * Trait providing default priority and framework support implementation for fixers.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
trait PriorityTrait
{
    /**
     * Get the priority of this fixer (higher = executed earlier).
     * Default priority is 0.
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Get list of frameworks this fixer is specific to.
     * Default implementation returns empty array (framework-agnostic).
     *
     * @return array<string>
     */
    public function getSupportedFrameworks(): array
    {
        return [];
    }
}


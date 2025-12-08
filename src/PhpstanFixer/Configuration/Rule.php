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
 * Represents a configuration rule for error handling.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class Rule
{
    public const ACTION_FIX = 'fix';
    public const ACTION_IGNORE = 'ignore';
    public const ACTION_REPORT = 'report';

    /**
     * @param string $action One of: 'fix', 'ignore', 'report'
     */
    public function __construct(
        private readonly string $action = self::ACTION_FIX
    ) {
        if (!in_array($action, [self::ACTION_FIX, self::ACTION_IGNORE, self::ACTION_REPORT], true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid action "%s". Must be one of: fix, ignore, report', $action)
            );
        }
    }

    /**
     * Get the action for this rule.
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Check if action is 'fix'.
     */
    public function isFix(): bool
    {
        return $this->action === self::ACTION_FIX;
    }

    /**
     * Check if action is 'ignore'.
     */
    public function isIgnore(): bool
    {
        return $this->action === self::ACTION_IGNORE;
    }

    /**
     * Check if action is 'report'.
     */
    public function isReport(): bool
    {
        return $this->action === self::ACTION_REPORT;
    }
}


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
use PhpstanFixer\Strategy\PriorityTrait;

/**
 * Adjusts PHPDoc to use fully-qualified names when class names conflict with internal types.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ClassesNamedAfterInternalTypesFixer implements FixStrategyInterface
{
    use PriorityTrait;
    
    /** @var string[] */
    private array $internalTypes = ['Resource', 'Double', 'Number'];

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/internal/i')
            && $this->containsInternalTypeName($issue->getMessage());
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        $type = $this->extractInternalType($issue->getMessage());
        if ($type === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract internal type');
        }

        $lines = explode("\n", $fileContent);
        $updated = false;

        foreach ($lines as $idx => $line) {
            if (str_contains($line, '@')) {
                $newLine = preg_replace(
                    '/@([a-zA-Z]+)\s+' . preg_quote($type, '/') . '\b/',
                    '@$1 \\' . $type,
                    $line
                );
                if ($newLine !== null && $newLine !== $line) {
                    $lines[$idx] = $newLine;
                    $updated = true;
                }
            }
        }

        if (!$updated) {
            return FixResult::failure($issue, $fileContent, 'No matching annotation to adjust');
        }

        return FixResult::success(
            $issue,
            implode("\n", $lines),
            "Adjusted internal type {$type} to fully-qualified",
            ["Replaced {$type} with \\{$type} in PHPDoc"]
        );
    }

    public function getDescription(): string
    {
        return 'Uses fully-qualified names in PHPDoc for classes named after internal PHP types';
    }

    public function getName(): string
    {
        return 'ClassesNamedAfterInternalTypesFixer';
    }

    private function containsInternalTypeName(string $message): bool
    {
        foreach ($this->internalTypes as $type) {
            if (stripos($message, $type) !== false) {
                return true;
            }
        }
        return false;
    }

    private function extractInternalType(string $message): ?string
    {
        foreach ($this->internalTypes as $type) {
            if (stripos($message, $type) !== false) {
                return $type;
            }
        }
        return null;
    }
}


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
 * Fixes undefined variable errors by adding inline @var annotation.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class UndefinedVariableFixer implements FixStrategyInterface
{
    public function __construct()
    {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/Undefined variable/i') ||
               $issue->matchesPattern('/Variable.*is undefined/i');
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        if (!file_exists($issue->getFilePath())) {
            return FixResult::failure($issue, $fileContent, 'File does not exist');
        }

        $targetLine = $issue->getLine();
        $lines = explode("\n", $fileContent);

        // Extract variable name from error message
        $variableName = $this->extractVariableName($issue->getMessage());
        if ($variableName === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract variable name');
        }

        // Check if we're at a valid line
        if ($targetLine < 1 || $targetLine > count($lines)) {
            return FixResult::failure($issue, $fileContent, 'Invalid line number');
        }

        $targetIndex = $targetLine - 1;
        $targetLineContent = $lines[$targetIndex];

        // Check if there's already an inline @var annotation on the previous line
        if ($targetIndex > 0) {
            $previousLine = trim($lines[$targetIndex - 1]);
            if (preg_match('/@var\s+[^\s]+\s+\$' . preg_quote($variableName, '/') . '/', $previousLine)) {
                return FixResult::failure($issue, $fileContent, "Inline @var annotation already exists for \${$variableName}");
            }
        }

        // Add inline @var annotation on the line before
        $varAnnotation = "/** @var mixed \${$variableName} */";
        
        // Check if previous line is empty or contains code
        $insertIndex = $targetIndex;
        if ($targetIndex > 0) {
            $prevLine = trim($lines[$targetIndex - 1]);
            // If previous line is empty or just whitespace, replace it with annotation
            if ($prevLine === '' || ctype_space($prevLine)) {
                // Replace empty line with annotation (use $targetIndex - 1, not $targetIndex)
                $lines[$targetIndex - 1] = $varAnnotation;
            } else {
                // Insert before target line
                array_splice($lines, $targetIndex, 0, [$varAnnotation]);
            }
        } else {
            // Insert at the beginning
            array_splice($lines, 0, 0, [$varAnnotation]);
        }

        $fixedContent = implode("\n", $lines);
        return FixResult::success(
            $issue,
            $fixedContent,
            "Added inline @var annotation for \${$variableName}",
            ["Added /** @var mixed \${$variableName} */ at line {$targetLine}"]
        );
    }

    public function getDescription(): string
    {
        return 'Adds inline @var annotation for undefined variables';
    }

    public function getName(): string
    {
        return 'UndefinedVariableFixer';
    }

    /**
     * Extract variable name from error message.
     */
    private function extractVariableName(string $message): ?string
    {
        // Pattern: "Undefined variable: $variableName"
        if (preg_match('/variable:\s*\$(\w+)/i', $message, $matches)) {
            return $matches[1];
        }

        // Pattern: "Variable $variableName is undefined"
        if (preg_match('/Variable\s+\$(\w+)/i', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }
}


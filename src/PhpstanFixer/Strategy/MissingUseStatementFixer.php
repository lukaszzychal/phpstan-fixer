<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Strategy;

use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;

/**
 * Fixes missing use statements by adding them after namespace declaration.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class MissingUseStatementFixer implements FixStrategyInterface
{
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/Class\s+[^\s]+\s+not found/i') ||
               $issue->matchesPattern('/Cannot resolve symbol/i') ||
               $issue->matchesPattern('/Class.*does not exist/i');
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        if (!file_exists($issue->getFilePath())) {
            return FixResult::failure($issue, $fileContent, 'File does not exist');
        }

        $ast = $this->analyzer->parse($fileContent);
        if ($ast === null) {
            return FixResult::failure($issue, $fileContent, 'Could not parse file');
        }

        // Extract class name (may be FQN) from error message
        $className = $this->extractClassName($issue->getMessage());
        if ($className === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract class name from error');
        }

        $fullyQualified = ltrim($className, '\\');
        $shortName = $this->getShortClassName($fullyQualified);

        $existingUses = $this->analyzer->getUseStatements($ast);
        
        // Check if use statement already exists
        foreach ($existingUses as $alias => $fullName) {
            if ($alias === $shortName || $fullName === $fullyQualified) {
                return FixResult::failure($issue, $fileContent, "Use statement for {$fullyQualified} already exists");
            }
            
            // Check if it's the last part of the FQN
            $parts = explode('\\', $fullName);
            if (end($parts) === $shortName) {
                return FixResult::failure($issue, $fileContent, "Use statement for {$fullName} already exists (imported as {$alias})");
            }
        }

        // Try to infer FQN - this is a simplified approach
        // In a real implementation, you might want to search vendor/ directories
        // or use a symbol discovery mechanism
        
        $lines = explode("\n", $fileContent);
        
        // Find namespace line
        $namespaceLineIndex = null;
        for ($i = 0; $i < count($lines); $i++) {
            if (preg_match('/^\s*namespace\s+/', $lines[$i])) {
                $namespaceLineIndex = $i;
                break;
            }
        }

        if ($namespaceLineIndex === null) {
            // No namespace, add use statements after <?php
            $insertIndex = 0;
            for ($i = 0; $i < count($lines); $i++) {
                if (str_starts_with(trim($lines[$i]), '<?php')) {
                    $insertIndex = $i + 1;
                    break;
                }
            }
        } else {
            // Find where use statements end or class declaration begins
            $insertIndex = $namespaceLineIndex + 1;
            for ($i = $namespaceLineIndex + 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                
                // Skip empty lines and comments
                if ($line === '' || str_starts_with($line, '//') || str_starts_with($line, '/*')) {
                    continue;
                }
                
                // If we hit a use statement, continue
                if (str_starts_with($line, 'use ')) {
                    $insertIndex = $i + 1;
                    continue;
                }
                
                // If we hit class/interface/trait, stop
                if (preg_match('/^\s*(class|interface|trait|abstract)\s+/', $line)) {
                    break;
                }
            }
        }

        // Add use statement using the best-known FQN (from the error message)
        $useStatement = "use {$fullyQualified};";
        
        // Add blank line before if needed
        if ($insertIndex > 0 && trim($lines[$insertIndex - 1]) !== '') {
            array_splice($lines, $insertIndex, 0, ['']);
            $insertIndex++;
        }
        
        array_splice($lines, $insertIndex, 0, [$useStatement]);

        $fixedContent = implode("\n", $lines);
        
        return FixResult::success(
            $issue,
            $fixedContent,
            "Added use statement for {$fullyQualified}",
            ["Added use statement at line " . ($insertIndex + 1)]
        );
    }

    public function getDescription(): string
    {
        return 'Adds missing use statements for undefined classes';
    }

    public function getName(): string
    {
        return 'MissingUseStatementFixer';
    }

    /**
     * Extract class name from error message.
     */
    private function extractClassName(string $message): ?string
    {
        // Pattern: "Class 'ClassName' not found"
        if (preg_match("/Class\s+['\"]?([\w\\\\]+)['\"]?\s+not found/i", $message, $matches)) {
            return $matches[1];
        }

        // Pattern: "Class ClassName does not exist"
        if (preg_match("/Class\s+([\w\\\\]+)\s+does not exist/i", $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getShortClassName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return (string) end($parts);
    }
}


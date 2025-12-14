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
use PhpstanFixer\Strategy\PriorityTrait;

/**
 * Fixes missing use statements by adding them after namespace declaration.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class MissingUseStatementFixer implements FixStrategyInterface
{
    use PriorityTrait;
    use FileValidationTrait;

    public function __construct(
        private readonly PhpFileAnalyzer $analyzer
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/Class\s+[^\s]+\s+not found/i') ||
               $issue->matchesPattern('/Cannot resolve symbol/i') ||
               $issue->matchesPattern('/Class.*does not exist/i') ||
               $issue->matchesPattern('/Unknown class/i') ||
               $issue->matchesPattern('/Class\s+[^\s]+\s+is undefined/i') ||
               $issue->matchesPattern('/Instantiated class\s+[^\s]+\s+not found/i') ||
               $issue->matchesPattern('/Referenced class\s+[^\s]+\s+not found/i');
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        $validation = $this->validateFileAndParse($issue, $fileContent, $this->analyzer);
        if ($validation instanceof FixResult) {
            return $validation;
        }

        $ast = $validation['ast'];

        // Extract class name (may be FQN) from error message
        $className = $this->extractClassName($issue->getMessage());
        if ($className === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract class name from error');
        }

        $fullyQualified = $this->resolveFullyQualifiedClassName($className, $issue->getFilePath());
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

        // Add use statement using the best-known FQN (from the error message or discovery)
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

        // Pattern: "Unknown class 'ClassName'"
        if (preg_match("/Unknown class\s+['\"]?([\w\\\\]+)['\"]?/i", $message, $matches)) {
            return $matches[1];
        }

        // Pattern: "Class ClassName is undefined"
        if (preg_match("/Class\s+['\"]?([\w\\\\]+)['\"]?\s+is undefined/i", $message, $matches)) {
            return $matches[1];
        }

        // Pattern: "Instantiated class 'ClassName' not found"
        if (preg_match("/Instantiated class\s+['\"]?([\w\\\\]+)['\"]?\s+not found/i", $message, $matches)) {
            return $matches[1];
        }

        // Pattern: "Referenced class 'ClassName' not found"
        if (preg_match("/Referenced class\s+['\"]?([\w\\\\]+)['\"]?\s+not found/i", $message, $matches)) {
            return $matches[1];
        }

        // Pattern: "Cannot resolve symbol 'ClassName'"
        if (preg_match("/Cannot resolve symbol\s+['\"]?([\w\\\\]+)['\"]?/i", $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getShortClassName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return (string) end($parts);
    }

    private function resolveFullyQualifiedClassName(string $className, string $filePath): string
    {
        $candidate = ltrim($className, '\\');

        if (str_contains($candidate, '\\')) {
            return $candidate;
        }

        $discovered = $this->discoverClass($candidate, $filePath);

        if ($discovered !== null) {
            return $discovered;
        }

        return $candidate;
    }

    private function discoverClass(string $shortName, string $filePath): ?string
    {
        $projectRoot = $this->findProjectRoot($filePath);

        $searchDirs = array_filter([
            $projectRoot . '/src',
            $projectRoot . '/vendor',
        ], static fn(string $dir) => is_dir($dir));

        foreach ($searchDirs as $dir) {
            $fqn = $this->findClassInDirectory($dir, $shortName);
            if ($fqn !== null) {
                return $fqn;
            }
        }

        return null;
    }

    private function findClassInDirectory(string $directory, string $shortName): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if (strtolower($fileInfo->getBasename()) !== strtolower($shortName . '.php')) {
                continue;
            }

            $namespace = $this->extractNamespaceFromFile($fileInfo->getPathname());

            if ($namespace !== null) {
                return $namespace . '\\' . $shortName;
            }

            return $shortName;
        }

        return null;
    }

    private function extractNamespaceFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        if (preg_match('/^\s*namespace\s+([^;]+);/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function findProjectRoot(string $filePath): string
    {
        $dir = realpath(dirname($filePath));

        while ($dir !== false && $dir !== DIRECTORY_SEPARATOR) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return realpath(dirname($filePath)) ?: dirname($filePath);
    }
}


<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Strategy;

use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;

/**
 * Fixes missing @throws annotations.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class MissingThrowsDocblockFixer implements FixStrategyInterface
{
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/@throws.*annotation is missing/i') ||
               $issue->matchesPattern('/throws exception.*but.*@throws/i');
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

        // Extract exception type from error message
        $exceptionType = $this->extractExceptionType($issue->getMessage());
        if ($exceptionType === null) {
            $exceptionType = '\\Exception'; // Default fallback
        }

        $targetLine = $issue->getLine();
        $lines = explode("\n", $fileContent);

        // Find function/method at this line
        $functions = $this->analyzer->getFunctions($ast);
        $classes = $this->analyzer->getClasses($ast);

        $targetFunction = null;
        $targetMethod = null;

        foreach ($functions as $function) {
            $functionLine = $this->analyzer->getNodeLine($function);
            if ($functionLine === $targetLine || abs($functionLine - $targetLine) <= 5) {
                $targetFunction = $function;
                break;
            }
        }

        if ($targetFunction === null) {
            foreach ($classes as $class) {
                $methods = $this->analyzer->getMethods($class);
                foreach ($methods as $method) {
                    $methodLine = $this->analyzer->getNodeLine($method);
                    if ($methodLine === $targetLine || abs($methodLine - $targetLine) <= 5) {
                        $targetMethod = $method;
                        break 2;
                    }
                }
            }
        }

        if ($targetFunction === null && $targetMethod === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find function/method near specified line');
        }

        $targetNode = $targetFunction ?? $targetMethod;
        $nodeLine = $this->analyzer->getNodeLine($targetNode);
        $nodeIndex = $nodeLine - 1;

        // Check for existing docblock
        $existingDocblock = $this->docblockManipulator->extractDocblock($lines, $nodeIndex);

        if ($existingDocblock !== null) {
            // Check if @throws already exists for this exception
            $annotations = $this->docblockManipulator->parseDocblock($existingDocblock['content']);
            $hasThrows = false;

            foreach ($annotations['throws'] ?? [] as $throws) {
                if (isset($throws['exception']) && $throws['exception'] === $exceptionType) {
                    $hasThrows = true;
                    break;
                }
            }

            if ($hasThrows) {
                return FixResult::failure($issue, $fileContent, "@throws annotation already exists for {$exceptionType}");
            }

            // Add @throws to existing docblock
            $updatedDocblock = $this->docblockManipulator->addAnnotation(
                $existingDocblock['content'],
                'throws',
                $exceptionType
            );

            $docblockLines = explode("\n", $updatedDocblock);
            array_splice(
                $lines,
                $existingDocblock['startLine'],
                $existingDocblock['endLine'] - $existingDocblock['startLine'] + 1,
                $docblockLines
            );
        } else {
            // Create new docblock with @throws
            $docblock = "/**\n * @throws {$exceptionType}\n */";
            $docblockLines = explode("\n", $docblock);
            array_splice($lines, $nodeIndex, 0, $docblockLines);
        }

        $fixedContent = implode("\n", $lines);
        return FixResult::success(
            $issue,
            $fixedContent,
            "Added @throws annotation for {$exceptionType}",
            ["Added @throws {$exceptionType} at line {$nodeLine}"]
        );
    }

    public function getDescription(): string
    {
        return 'Adds @throws annotation when exceptions are thrown';
    }

    public function getName(): string
    {
        return 'MissingThrowsDocblockFixer';
    }

    /**
     * Extract exception type from error message.
     */
    private function extractExceptionType(string $message): ?string
    {
        // Pattern: "throws ExceptionType but @throws annotation is missing"
        if (preg_match('/throws\s+([\\\\\w]+)/i', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }
}


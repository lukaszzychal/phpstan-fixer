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
 * Fixes missing return type annotations by adding @return mixed.
 *
 * @author Łukasz Zychal <lukasz@zychal.pl>
 */
final class MissingReturnDocblockFixer implements FixStrategyInterface
{
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->isMissingReturnType() || $issue->matchesPattern('/Return type is missing/i');
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

        $targetLine = $issue->getLine();
        $lines = explode("\n", $fileContent);

        // Find function/method at this line
        $functions = $this->analyzer->getFunctions($ast);
        $classes = $this->analyzer->getClasses($ast);

        $targetFunction = null;
        $targetMethod = null;

        // Check functions first
        foreach ($functions as $function) {
            $functionLine = $this->analyzer->getNodeLine($function);
            if ($functionLine === $targetLine) {
                $targetFunction = $function;
                break;
            }
        }

        // Check methods in classes
        if ($targetFunction === null) {
            foreach ($classes as $class) {
                $methods = $this->analyzer->getMethods($class);
                foreach ($methods as $method) {
                    $methodLine = $this->analyzer->getNodeLine($method);
                    if ($methodLine === $targetLine) {
                        $targetMethod = $method;
                        break 2;
                    }
                }
            }
        }

        if ($targetFunction === null && $targetMethod === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find function/method at specified line');
        }

        $targetNode = $targetFunction ?? $targetMethod;
        $nodeLine = $this->analyzer->getNodeLine($targetNode);
        $nodeIndex = $nodeLine - 1; // Convert to 0-based index

        // Check for existing docblock
        $existingDocblock = $this->docblockManipulator->extractDocblock($lines, $nodeIndex);
        $hasReturn = false;

        if ($existingDocblock !== null) {
            $hasReturn = $this->docblockManipulator->hasAnnotation(
                $existingDocblock['content'],
                'return'
            );
        }

        // If @return already exists, nothing to do
        if ($hasReturn) {
            return FixResult::failure($issue, $fileContent, '@return annotation already exists');
        }

        // Determine return type from native type hint if available
        $returnType = 'mixed';
        if ($targetFunction !== null && $targetFunction->getReturnType() !== null) {
            $returnType = $this->formatType($targetFunction->getReturnType());
        } elseif ($targetMethod !== null && $targetMethod->getReturnType() !== null) {
            $returnType = $this->formatType($targetMethod->getReturnType());
        }

        // Add or create docblock with @return
        if ($existingDocblock !== null) {
            // Add @return to existing docblock
            $updatedDocblock = $this->docblockManipulator->addAnnotation(
                $existingDocblock['content'],
                'return',
                $returnType
            );

            // Replace the docblock in lines
            $docblockLines = explode("\n", $updatedDocblock);
            array_splice(
                $lines,
                $existingDocblock['startLine'],
                $existingDocblock['endLine'] - $existingDocblock['startLine'] + 1,
                $docblockLines
            );
        } else {
            // Create new docblock
            $docblock = "/**\n * @return {$returnType}\n */";
            $docblockLines = explode("\n", $docblock);
            array_splice($lines, $nodeIndex, 0, $docblockLines);
        }

        $fixedContent = implode("\n", $lines);

        return FixResult::success(
            $issue,
            $fixedContent,
            "Added @return {$returnType} annotation",
            ["Added @return {$returnType} at line {$nodeLine}"]
        );
    }

    public function getDescription(): string
    {
        return 'Adds @return annotation when PHPStan reports missing return type';
    }

    public function getName(): string
    {
        return 'MissingReturnDocblockFixer';
    }

    /**
     * Format a PHP-Parser type node to string.
     */
    private function formatType($typeNode): string
    {
        if (is_string($typeNode)) {
            return $typeNode;
        }

        if ($typeNode instanceof \PhpParser\Node\Name) {
            return $typeNode->toString();
        }

        if ($typeNode instanceof \PhpParser\Node\Identifier) {
            return $typeNode->name;
        }

        if ($typeNode instanceof \PhpParser\Node\NullableType) {
            return '?' . $this->formatType($typeNode->type);
        }

        if ($typeNode instanceof \PhpParser\Node\UnionType) {
            return implode('|', array_map([$this, 'formatType'], $typeNode->types));
        }

        return 'mixed';
    }
}


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
use PhpstanFixer\CodeAnalysis\TypeInference;
use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\PriorityTrait;
use PhpstanFixer\Strategy\TypeFormatterTrait;
use PhpstanFixer\Strategy\FunctionLocatorTrait;

/**
 * Fixes missing return type annotations by adding @return mixed.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class MissingReturnDocblockFixer implements FixStrategyInterface
{
    use PriorityTrait;
    use TypeFormatterTrait;
    use FileValidationTrait;
    use FunctionLocatorTrait;
    private TypeInference $typeInference;

    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
        $this->typeInference = new TypeInference($analyzer);
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->isMissingReturnType() || $issue->matchesPattern('/Return type is missing/i');
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        $validation = $this->validateFileAndParse($issue, $fileContent, $this->analyzer);
        if ($validation instanceof FixResult) {
            return $validation;
        }

        $ast = $validation['ast'];
        $targetLine = $issue->getLine();
        $lines = explode("\n", $fileContent);

        // Find function/method at this line
        $located = $this->findFunctionOrMethodAtLine($ast, $targetLine, $this->analyzer, 0);
        $targetFunction = $located['function'];
        $targetMethod = $located['method'];

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
        $returnType = null;
        if ($targetFunction !== null && $targetFunction->getReturnType() !== null) {
            $returnType = $this->formatType($targetFunction->getReturnType());
        } elseif ($targetMethod !== null && $targetMethod->getReturnType() !== null) {
            $returnType = $this->formatType($targetMethod->getReturnType());
        }

        // If no type hint, try to infer from return statements
        if ($returnType === null) {
            $ast = $this->analyzer->parse($fileContent);
            $targetNode = $targetFunction ?? $targetMethod;
            $inferredType = $this->typeInference->inferReturnType($targetNode, $ast);
            $returnType = $inferredType ?? 'mixed';
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

    public function getPriority(): int
    {
        return 100;
    }
}


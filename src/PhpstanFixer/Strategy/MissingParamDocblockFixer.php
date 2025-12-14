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
use PhpstanFixer\CodeAnalysis\ErrorMessageParser;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\CodeAnalysis\TypeInference;
use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\PriorityTrait;
use PhpstanFixer\Strategy\TypeFormatterTrait;
use PhpstanFixer\Strategy\FileValidationTrait;
use PhpstanFixer\Strategy\FunctionLocatorTrait;

/**
 * Fixes missing parameter type annotations by adding @param mixed.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class MissingParamDocblockFixer implements FixStrategyInterface
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
        return $issue->isMissingParameterType() || $issue->matchesPattern('/Parameter.*has no type specified/i');
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        $validation = $this->validateFileAndParse($issue, $fileContent, $this->analyzer);
        if ($validation instanceof FixResult) {
            return $validation;
        }

        $ast = $validation['ast'];

        // Extract parameter information from error message
        $paramInfo = $this->extractParameterInfo($issue->getMessage());
        if ($paramInfo === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract parameter information');
        }

        $targetLine = $issue->getLine();
        $lines = explode("\n", $fileContent);

        // Find function/method at this line (with tolerance of 5 lines)
        $located = $this->findFunctionOrMethodAtLine($ast, $targetLine, $this->analyzer, 5);
        $targetFunction = $located['function'];
        $targetMethod = $located['method'];

        if ($targetFunction === null && $targetMethod === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find function/method near specified line');
        }

        $targetNode = $targetFunction ?? $targetMethod;
        $nodeLine = $this->analyzer->getNodeLine($targetNode);
        $nodeIndex = $nodeLine - 1;

        // Check for existing docblock
        $existingDocblock = $this->docblockManipulator->extractDocblock($lines, $nodeIndex);
        $paramName = '$' . $paramInfo['name'];

        if ($existingDocblock !== null) {
            // Check if @param already exists for this parameter
            if ($this->docblockManipulator->hasAnnotation($existingDocblock['content'], 'param', $paramName)) {
                return FixResult::failure($issue, $fileContent, "@param annotation already exists for {$paramName}");
            }

            // Determine type from native type hint if available
            $paramType = $this->getParameterType($targetNode, $paramInfo['position']);
            if ($paramType === null) {
                // Try to infer type from usage
                $ast = $this->analyzer->parse($fileContent);
                $inferredType = $this->typeInference->inferParameterType($targetNode, $paramInfo['position'], $ast);
                $paramType = $inferredType ?? 'mixed';
            }

            // Add @param to existing docblock
            $updatedDocblock = $this->docblockManipulator->addAnnotation(
                $existingDocblock['content'],
                'param',
                "{$paramType} {$paramName}"
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
            $paramType = $this->getParameterType($targetNode, $paramInfo['position']);
            if ($paramType === null) {
                // Try to infer type from usage
                $ast = $this->analyzer->parse($fileContent);
                $inferredType = $this->typeInference->inferParameterType($targetNode, $paramInfo['position'], $ast);
                $paramType = $inferredType ?? 'mixed';
            }
            $docblock = "/**\n * @param {$paramType} {$paramName}\n */";
            $docblockLines = explode("\n", $docblock);
            array_splice($lines, $nodeIndex, 0, $docblockLines);
        }

        $fixedContent = implode("\n", $lines);

        return FixResult::success(
            $issue,
            $fixedContent,
            "Added @param annotation for {$paramName}",
            ["Added @param {$paramType} {$paramName} at line {$nodeLine}"]
        );
    }

    public function getDescription(): string
    {
        return 'Adds @param annotations when PHPStan reports missing parameter types';
    }

    public function getName(): string
    {
        return 'MissingParamDocblockFixer';
    }

    public function getPriority(): int
    {
        return 90;
    }

    /**
     * Extract parameter information from error message.
     *
     * @param string $message Error message
     * @return array{position: int, name: string}|null
     */
    private function extractParameterInfo(string $message): ?array
    {
        $paramName = ErrorMessageParser::parseParameterName($message);
        if ($paramName === null) {
            return null;
        }

        $paramIndex = ErrorMessageParser::parseParameterIndex($message);

        return [
            'position' => $paramIndex ?? 0, // Use parsed index or default to 0
            'name' => $paramName,
        ];
    }

    /**
     * Get parameter type from function/method signature.
     */
    private function getParameterType($node, int $position): ?string
    {
        if (!isset($node->params[$position])) {
            return null;
        }

        $param = $node->params[$position];
        if ($param->type === null) {
            return null;
        }

        return $this->formatType($param->type);
    }
}


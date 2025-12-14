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
use PhpstanFixer\Strategy\PriorityTrait;

/**
 * Fixes missing parameter type annotations by adding @param mixed.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class MissingParamDocblockFixer implements FixStrategyInterface
{
    use PriorityTrait;
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->isMissingParameterType() || $issue->matchesPattern('/Parameter.*has no type specified/i');
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

        // Extract parameter information from error message
        $paramInfo = $this->extractParameterInfo($issue->getMessage());
        if ($paramInfo === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract parameter information');
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
            if ($functionLine === $targetLine || abs($functionLine - $targetLine) <= 5) {
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
        $paramName = '$' . $paramInfo['name'];

        if ($existingDocblock !== null) {
            // Check if @param already exists for this parameter
            if ($this->docblockManipulator->hasAnnotation($existingDocblock['content'], 'param', $paramName)) {
                return FixResult::failure($issue, $fileContent, "@param annotation already exists for {$paramName}");
            }

            // Determine type from native type hint if available
            $paramType = $this->getParameterType($targetNode, $paramInfo['position']);
            if ($paramType === null) {
                $paramType = 'mixed';
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
            $paramType = $this->getParameterType($targetNode, $paramInfo['position']) ?? 'mixed';
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

    /**
     * Extract parameter information from error message.
     *
     * @param string $message Error message
     * @return array{position: int, name: string}|null
     */
    private function extractParameterInfo(string $message): ?array
    {
        // Pattern: "Parameter #1 $name has no type specified"
        if (preg_match('/Parameter\s+#(\d+)\s+\$(\w+)/i', $message, $matches)) {
            return [
                'position' => (int) $matches[1] - 1, // Convert to 0-based
                'name' => $matches[2],
            ];
        }

        // Pattern: "Parameter $name has no type specified"
        if (preg_match('/Parameter\s+\$(\w+)/i', $message, $matches)) {
            return [
                'position' => 0, // Default to first parameter
                'name' => $matches[1],
            ];
        }

        return null;
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


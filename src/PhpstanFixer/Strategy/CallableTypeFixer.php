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
 * Fixes callable type issues by adding proper callable invocation annotations.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class CallableTypeFixer implements FixStrategyInterface
{
    use PriorityTrait;
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/callable.*invoked/i') ||
               $issue->matchesPattern('/Parameter.*expects callable/i');
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

        // Extract parameter information
        $paramInfo = $this->extractCallableParameterInfo($issue->getMessage());
        if ($paramInfo === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract callable parameter information');
        }

        $targetLine = $issue->getLine();
        $lines = explode("\n", $fileContent);

        // Find function/method
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

        // Determine invocation timing from context
        // Default: functions execute immediately, methods execute later
        $isImmediate = $targetFunction !== null;
        $annotationType = $isImmediate ? 'param-immediately-invoked-callable' : 'param-later-invoked-callable';

        // Check for existing docblock
        $existingDocblock = $this->docblockManipulator->extractDocblock($lines, $nodeIndex);
        $paramName = '$' . $paramInfo['name'];

        if ($existingDocblock !== null) {
            // Check if annotation already exists
            if ($this->docblockManipulator->hasAnnotation(
                $existingDocblock['content'],
                $annotationType,
                $paramName
            )) {
                return FixResult::failure(
                    $issue,
                    $fileContent,
                    "@{$annotationType} annotation already exists for {$paramName}"
                );
            }

            // Add annotation to existing docblock
            $updatedDocblock = $this->docblockManipulator->addAnnotation(
                $existingDocblock['content'],
                $annotationType,
                $paramName
            );

            $docblockLines = explode("\n", $updatedDocblock);
            array_splice(
                $lines,
                $existingDocblock['startLine'],
                $existingDocblock['endLine'] - $existingDocblock['startLine'] + 1,
                $docblockLines
            );
        } else {
            // Create new docblock
            $docblock = "/**\n * @{$annotationType} {$paramName}\n */";
            $docblockLines = explode("\n", $docblock);
            array_splice($lines, $nodeIndex, 0, $docblockLines);
        }

        $fixedContent = implode("\n", $lines);
        $description = $isImmediate ? 'immediately invoked' : 'later invoked';
        
        return FixResult::success(
            $issue,
            $fixedContent,
            "Added @{$annotationType} annotation for {$paramName} ({$description})",
            ["Added @{$annotationType} {$paramName} at line {$nodeLine}"]
        );
    }

    public function getDescription(): string
    {
        return 'Adds callable invocation timing annotations (@param-immediately-invoked-callable, @param-later-invoked-callable)';
    }

    public function getName(): string
    {
        return 'CallableTypeFixer';
    }

    /**
     * Extract callable parameter information from error message.
     *
     * @return array{name: string}|null
     */
    private function extractCallableParameterInfo(string $message): ?array
    {
        // Pattern: "Parameter #1 $callback expects callable"
        if (preg_match('/Parameter\s+#\d+\s+\$(\w+).*callable/i', $message, $matches)) {
            return ['name' => $matches[1]];
        }

        // Pattern: "Parameter $callback expects callable"
        if (preg_match('/Parameter\s+\$(\w+).*callable/i', $message, $matches)) {
            return ['name' => $matches[1]];
        }

        return null;
    }
}


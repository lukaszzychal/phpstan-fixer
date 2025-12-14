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
use PhpstanFixer\Strategy\FileValidationTrait;

/**
 * Adds iterable value types (iterable<mixed>) when PHPStan reports missing iterable value type.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class IterableValueTypeFixer implements FixStrategyInterface
{
    use PriorityTrait;
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/iterable value type/i')
            || $issue->matchesPattern('/Missing iterable value type/i');
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
        $paramName = $this->extractParamName($issue->getMessage());

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

        $targetNode = $targetFunction ?? $targetMethod;
        if ($targetNode === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find function/method near specified line');
        }

        $nodeLine = $this->analyzer->getNodeLine($targetNode);
        $nodeIndex = $nodeLine - 1;
        $lines = explode("\n", $fileContent);

        if ($paramName === null && isset($targetNode->params[0])) {
            $paramName = '$' . $targetNode->params[0]->var->name;
        }

        if ($paramName === null) {
            return FixResult::failure($issue, $fileContent, 'Could not determine parameter name');
        }

        $existingDocblock = $this->docblockManipulator->extractDocblock($lines, $nodeIndex);

        if ($existingDocblock !== null) {
            $parsed = $this->docblockManipulator->parseDocblock($existingDocblock['content']);
            foreach ($parsed['param'] ?? [] as $param) {
                if (($param['name'] ?? '') === $paramName) {
                    $type = $param['type'] ?? '';
                    if (str_starts_with($type, 'iterable') && str_contains($type, '<')) {
                        return FixResult::failure($issue, $fileContent, 'Iterable value type already exists');
                    }
                }
            }

            $updatedDocblock = $this->replaceIterableWithGeneric($existingDocblock['content'], $paramName);

            if ($updatedDocblock === $existingDocblock['content']) {
                $updatedDocblock = $this->docblockManipulator->addAnnotation(
                    $existingDocblock['content'],
                    'param',
                    "iterable<mixed> {$paramName}"
                );
            }

            $docblockLines = explode("\n", $updatedDocblock);
            array_splice(
                $lines,
                $existingDocblock['startLine'],
                $existingDocblock['endLine'] - $existingDocblock['startLine'] + 1,
                $docblockLines
            );
        } else {
            $docblock = "/**\n * @param iterable<mixed> {$paramName}\n */";
            $docblockLines = explode("\n", $docblock);
            array_splice($lines, $nodeIndex, 0, $docblockLines);
        }

        return FixResult::success(
            $issue,
            implode("\n", $lines),
            "Added iterable value type for {$paramName}",
            ["Added @param iterable<mixed> {$paramName}"]
        );
    }

    public function getDescription(): string
    {
        return 'Adds iterable value types when PHPStan reports missing iterable value type';
    }

    public function getName(): string
    {
        return 'IterableValueTypeFixer';
    }

    private function extractParamName(string $message): ?string
    {
        if (preg_match('/\\$(\\w+)/', $message, $matches)) {
            return '$' . $matches[1];
        }

        return null;
    }

    private function replaceIterableWithGeneric(string $docblockContent, string $paramName): string
    {
        $pattern = '/@param\\s+iterable\\s+' . preg_quote($paramName, '/') . '(\\b|\\s)/';
        $replacement = '@param iterable<mixed> ' . $paramName . '$1';

        return preg_replace($pattern, $replacement, $docblockContent) ?? $docblockContent;
    }
}


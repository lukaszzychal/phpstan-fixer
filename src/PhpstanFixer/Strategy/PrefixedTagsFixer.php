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
 * Adds @phpstan-param / @phpstan-return for advanced types (class-string, literal-string, generics).
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class PrefixedTagsFixer implements FixStrategyInterface
{
    private const ADVANCED_TYPE_PATTERN = '/class-string|literal-string|non-empty-string|numeric-string|callable-string|trait-string|array-key|value-of|key-of|list<|array<|array\{|int<|string<|positive-int|negative-int|non-falsy-string|truthy-string/i';

    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $this->containsAdvancedType($issue->getMessage());
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

        $paramInfo = $this->extractParamInfo($issue->getMessage());
        $returnType = $this->extractReturnType($issue->getMessage());

        if ($paramInfo === null && $returnType === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract advanced phpstan type');
        }

        $target = $this->findFunctionOrMethod($ast, $issue->getLine());
        if ($target === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find function/method near line');
        }

        [$node, $nodeLine] = $target;
        $nodeIndex = $nodeLine - 1;
        $lines = explode("\n", $fileContent);
        $docblock = $this->docblockManipulator->extractDocblock($lines, $nodeIndex);

        if ($paramInfo !== null) {
            $annotationType = 'phpstan-param';
            $annotationValue = sprintf('%s $%s', $paramInfo['type'], $paramInfo['name']);
            $name = '$' . $paramInfo['name'];
        } else {
            $annotationType = 'phpstan-return';
            $annotationValue = $returnType;
            $name = null;
        }

        return $this->applyAnnotation(
            $issue,
            $lines,
            $docblock,
            $nodeIndex,
            $annotationType,
            $annotationValue,
            $name
        );
    }

    public function getDescription(): string
    {
        return 'Adds @phpstan-param / @phpstan-return for advanced PHPStan-only types';
    }

    public function getName(): string
    {
        return 'PrefixedTagsFixer';
    }

    private function applyAnnotation(
        Issue $issue,
        array $lines,
        ?array $docblock,
        int $nodeIndex,
        string $annotationType,
        string $annotationValue,
        ?string $name
    ): FixResult {
        if ($docblock !== null) {
            if ($this->docblockManipulator->hasAnnotation($docblock['content'], $annotationType, $name)) {
                return FixResult::failure($issue, implode("\n", $lines), "@{$annotationType} already exists");
            }

            $updated = $this->docblockManipulator->addAnnotation(
                $docblock['content'],
                $annotationType,
                $annotationValue
            );

            $docblockLines = explode("\n", $updated);
            array_splice(
                $lines,
                $docblock['startLine'],
                $docblock['endLine'] - $docblock['startLine'] + 1,
                $docblockLines
            );
        } else {
            $docblockLines = explode("\n", "/**\n * @{$annotationType} {$annotationValue}\n */");
            array_splice($lines, $nodeIndex, 0, $docblockLines);
        }

        return FixResult::success(
            $issue,
            implode("\n", $lines),
            sprintf('Added @%s %s', $annotationType, $annotationValue)
        );
    }

    private function extractParamInfo(string $message): ?array
    {
        if (preg_match('/Parameter\s+#?\d*\s+\$(\w+).*?expects\s+([^.,;]+)/i', $message, $matches)) {
            $type = trim($matches[2]);
            if ($this->containsAdvancedType($type)) {
                return ['name' => $matches[1], 'type' => $type];
            }
        }

        if (preg_match('/Parameter\s+\$(\w+).*?expects\s+([^.,;]+)/i', $message, $matches)) {
            $type = trim($matches[2]);
            if ($this->containsAdvancedType($type)) {
                return ['name' => $matches[1], 'type' => $type];
            }
        }

        return null;
    }

    private function extractReturnType(string $message): ?string
    {
        if (preg_match('/return type.*should (?:be|return)\s+([^.,;]+)/i', $message, $matches)) {
            $type = trim($matches[1]);
            return $this->containsAdvancedType($type) ? $type : null;
        }

        return null;
    }

    private function findFunctionOrMethod(array $ast, int $targetLine): ?array
    {
        foreach ($this->analyzer->getFunctions($ast) as $function) {
            $line = $this->analyzer->getNodeLine($function);
            if (abs($line - $targetLine) <= 5) {
                return [$function, $line];
            }
        }

        foreach ($this->analyzer->getClasses($ast) as $class) {
            foreach ($this->analyzer->getMethods($class) as $method) {
                $line = $this->analyzer->getNodeLine($method);
                if (abs($line - $targetLine) <= 5) {
                    return [$method, $line];
                }
            }
        }

        return null;
    }

    private function containsAdvancedType(string $text): bool
    {
        return (bool) preg_match(self::ADVANCED_TYPE_PATTERN, $text);
    }
}


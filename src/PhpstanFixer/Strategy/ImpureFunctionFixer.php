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
 * Adds @phpstan-impure or @phpstan-pure to functions/methods based on PHPStan purity diagnostics.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ImpureFunctionFixer implements FixStrategyInterface
{
    use PriorityTrait;
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        $message = $issue->getMessage();

        return $issue->matchesPattern('/impure/i')
            || $issue->matchesPattern('/side effect/i')
            || $issue->matchesPattern('/different values/i')
            || str_contains(strtolower($message), 'non-deterministic')
            || (bool) preg_match('/\bpure\b/i', $message);
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

        $annotationType = $this->resolveAnnotationType($issue->getMessage());
        [$targetNode, $nodeLine] = $this->findFunctionOrMethod($ast, $issue->getLine()) ?? [null, null];

        if ($targetNode === null || $nodeLine === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find function/method near line');
        }

        $nodeIndex = $nodeLine - 1;
        $lines = explode("\n", $fileContent);
        $docblock = $this->docblockManipulator->extractDocblock($lines, $nodeIndex);

        if ($docblock !== null && $this->docblockManipulator->hasAnnotation($docblock['content'], $annotationType)) {
            return FixResult::failure(
                $issue,
                $fileContent,
                "@{$annotationType} annotation already exists"
            );
        }

        if ($docblock !== null) {
            $updatedDocblock = $this->docblockManipulator->addAnnotation(
                $docblock['content'],
                $annotationType,
                ''
            );

            $docblockLines = explode("\n", $updatedDocblock);
            array_splice(
                $lines,
                $docblock['startLine'],
                $docblock['endLine'] - $docblock['startLine'] + 1,
                $docblockLines
            );
        } else {
            $docblockLines = [
                '/**',
                " * @{$annotationType}",
                ' */',
            ];
            array_splice($lines, $nodeIndex, 0, $docblockLines);
        }

        return FixResult::success(
            $issue,
            implode("\n", $lines),
            sprintf('Added @%s annotation', $annotationType),
            [sprintf('Annotated as %s at line %d', $annotationType, $nodeLine)]
        );
    }

    public function getDescription(): string
    {
        return 'Marks functions/methods as @phpstan-impure or @phpstan-pure based on purity diagnostics';
    }

    public function getName(): string
    {
        return 'ImpureFunctionFixer';
    }

    private function resolveAnnotationType(string $message): string
    {
        if (preg_match('/impure|side effect|different values|non-deterministic/i', $message)) {
            return 'phpstan-impure';
        }

        if (preg_match('/\bpure\b/i', $message)) {
            return 'phpstan-pure';
        }

        // Default to impure as the safer assumption
        return 'phpstan-impure';
    }

    /**
     * @param array<int, mixed> $ast
     * @return array{0: mixed, 1: int}|null
     */
    private function findFunctionOrMethod(array $ast, int $targetLine): ?array
    {
        foreach ($this->analyzer->getFunctions($ast) as $function) {
            $line = $this->analyzer->getNodeLine($function);
            if ($line === $targetLine || abs($line - $targetLine) <= 5) {
                return [$function, $line];
            }
        }

        foreach ($this->analyzer->getClasses($ast) as $class) {
            foreach ($this->analyzer->getMethods($class) as $method) {
                $line = $this->analyzer->getNodeLine($method);
                if ($line === $targetLine || abs($line - $targetLine) <= 5) {
                    return [$method, $line];
                }
            }
        }

        return null;
    }
}


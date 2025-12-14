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
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpstanFixer\Strategy\PriorityTrait;

/**
 * Adds @phpstan-require-extends ClassName to interfaces/traits that need a specific base class.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class RequireExtendsFixer implements FixStrategyInterface
{
    use PriorityTrait;
    use FileValidationTrait;

    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
        $this->nodeFinder = new NodeFinder();
    }

    private NodeFinder $nodeFinder;

    public function canFix(Issue $issue): bool
    {
        return (bool) preg_match('/require(?:s)?\s+(?:extends|extend)\s+[\\\\\w]+/i', $issue->getMessage());
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        $validation = $this->validateFileAndParse($issue, $fileContent, $this->analyzer);
        if ($validation instanceof FixResult) {
            return $validation;
        }

        $ast = $validation['ast'];

        $requiredClass = $this->extractRequiredClass($issue->getMessage());
        if ($requiredClass === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract required base class');
        }

        [$classNode, $classLine] = $this->findClassLikeAtLine($ast, $issue->getLine()) ?? [null, null];
        if ($classNode === null || $classLine === null) {
            return FixResult::failure($issue, $fileContent, 'Could not locate interface/trait for annotation');
        }

        $lines = explode("\n", $fileContent);
        $classIndex = $classLine - 1;
        $docblock = $this->docblockManipulator->extractDocblock($lines, $classIndex);

        if ($docblock !== null) {
            $parsed = $this->docblockManipulator->parseDocblock($docblock['content']);
            foreach ($parsed['phpstan-require-extends'] ?? [] as $existing) {
                if (($existing['className'] ?? null) === $requiredClass) {
                    return FixResult::failure(
                        $issue,
                        $fileContent,
                        "@phpstan-require-extends already exists for {$requiredClass}"
                    );
                }
            }

            $updated = $this->docblockManipulator->addAnnotation(
                $docblock['content'],
                'phpstan-require-extends',
                $requiredClass
            );

            $docblockLines = explode("\n", $updated);
            array_splice(
                $lines,
                $docblock['startLine'],
                $docblock['endLine'] - $docblock['startLine'] + 1,
                $docblockLines
            );
        } else {
            $docblockLines = [
                '/**',
                " * @phpstan-require-extends {$requiredClass}",
                ' */',
            ];
            array_splice($lines, $classIndex, 0, $docblockLines);
        }

        return FixResult::success(
            $issue,
            implode("\n", $lines),
            sprintf('Added @phpstan-require-extends %s', $requiredClass),
            [sprintf('Annotated %s at line %d', $requiredClass, $classLine)]
        );
    }

    public function getDescription(): string
    {
        return 'Adds @phpstan-require-extends to interfaces/traits requiring a specific base class';
    }

    public function getName(): string
    {
        return 'RequireExtendsFixer';
    }

    private function extractRequiredClass(string $message): ?string
    {
        if (preg_match('/require(?:s)?\s+(?:extends|extend)\s+([\\\\\w]+)/i', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param array<int, mixed> $ast
     * @return array{0: mixed, 1: int}|null
     */
    private function findClassLikeAtLine(array $ast, int $targetLine): ?array
    {
        /** @var ClassLike[] $classLikes */
        $classLikes = $this->nodeFinder->findInstanceOf($ast, ClassLike::class);

        foreach ($classLikes as $classLike) {
            $line = $this->analyzer->getNodeLine($classLike);
            if ($targetLine >= $line - 1 && $targetLine <= $line + 2) {
                return [$classLike, $line];
            }
        }

        return null;
    }
}


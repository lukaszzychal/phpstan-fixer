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
use PhpstanFixer\Strategy\FileValidationTrait;

/**
 * Adds @phpstan-require-implements InterfaceName to traits requiring specific interface implementation.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class RequireImplementsFixer implements FixStrategyInterface
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
        return (bool) preg_match('/require(?:s)?\s+implements\s+[\\\\\w]+/i', $issue->getMessage());
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        $validation = $this->validateFileAndParse($issue, $fileContent, $this->analyzer);
        if ($validation instanceof FixResult) {
            return $validation;
        }

        $ast = $validation['ast'];

        $interface = $this->extractInterface($issue->getMessage());
        if ($interface === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract interface name');
        }

        [$classNode, $classLine] = $this->findClassLikeAtLine($ast, $issue->getLine()) ?? [null, null];
        if ($classNode === null || $classLine === null) {
            return FixResult::failure($issue, $fileContent, 'Could not locate trait for annotation');
        }

        $lines = explode("\n", $fileContent);
        $classIndex = $classLine - 1;
        $docblock = $this->docblockManipulator->extractDocblock($lines, $classIndex);

        if ($docblock !== null) {
            $parsed = $this->docblockManipulator->parseDocblock($docblock['content']);
            foreach ($parsed['phpstan-require-implements'] ?? [] as $existing) {
                if (($existing['className'] ?? null) === $interface) {
                    return FixResult::failure(
                        $issue,
                        $fileContent,
                        "@phpstan-require-implements already exists for {$interface}"
                    );
                }
            }

            $updated = $this->docblockManipulator->addAnnotation(
                $docblock['content'],
                'phpstan-require-implements',
                $interface
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
                " * @phpstan-require-implements {$interface}",
                ' */',
            ];
            array_splice($lines, $classIndex, 0, $docblockLines);
        }

        return FixResult::success(
            $issue,
            implode("\n", $lines),
            sprintf('Added @phpstan-require-implements %s', $interface),
            [sprintf('Annotated %s at line %d', $interface, $classLine)]
        );
    }

    public function getDescription(): string
    {
        return 'Adds @phpstan-require-implements to traits requiring a specific interface implementation';
    }

    public function getName(): string
    {
        return 'RequireImplementsFixer';
    }

    private function extractInterface(string $message): ?string
    {
        if (preg_match('/require(?:s)?\s+implements\s+([\\\\\w]+)/i', $message, $matches)) {
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


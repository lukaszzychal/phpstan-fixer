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
 * Adds @phpstan-sealed Class1|Class2 when a class extends a sealed class.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class SealedClassFixer implements FixStrategyInterface
{
    use PriorityTrait;
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/sealed class/i');
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
        $classes = $this->analyzer->getClasses($ast);
        $targetClass = null;
        $classLine = null;
        $sealedBase = $this->extractSealedBase($issue->getMessage());

        foreach ($classes as $class) {
            $classStartLine = $this->analyzer->getNodeLine($class);
            if ($targetLine >= $classStartLine && $targetLine <= $classStartLine + 500) {
                $targetClass = $class;
                $classLine = $classStartLine;
                break;
            }
        }

        if ($targetClass === null || $classLine === null || $sealedBase === null) {
            return FixResult::failure($issue, $fileContent, 'Could not determine class or sealed base');
        }

        $lines = explode("\n", $fileContent);
        $classIndex = $classLine - 1;
        $docblock = $this->docblockManipulator->extractDocblock($lines, $classIndex);

        if ($docblock !== null) {
            $parsed = $this->docblockManipulator->parseDocblock($docblock['content']);
            $sealedEntries = $parsed['phpstan-sealed'] ?? [];
            if (!empty($sealedEntries)) {
                return FixResult::failure($issue, $fileContent, '@phpstan-sealed already exists');
            }

            $updated = $this->docblockManipulator->addAnnotation(
                $docblock['content'],
                'phpstan-sealed',
                $sealedBase
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
                " * @phpstan-sealed {$sealedBase}",
                ' */',
            ];
            array_splice($lines, $classIndex, 0, $docblockLines);
        }

        return FixResult::success(
            $issue,
            implode("\n", $lines),
            "Added @phpstan-sealed {$sealedBase}",
            ["Added @phpstan-sealed {$sealedBase} at class"]
        );
    }

    public function getDescription(): string
    {
        return 'Adds @phpstan-sealed annotation when extending sealed classes.';
    }

    public function getName(): string
    {
        return 'SealedClassFixer';
    }

    private function extractSealedBase(string $message): ?string
    {
        if (preg_match('/sealed class\\s+([\\\\\\w]+)/i', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }
}


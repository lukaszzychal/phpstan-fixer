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
 * Adds @immutable annotation to classes reported as immutable with properties assigned outside.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ImmutableClassFixer implements FixStrategyInterface
{
    use PriorityTrait;
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/immutable/i')
            && $issue->matchesPattern('/property/i');
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

        $classes = $this->analyzer->getClasses($ast);
        $targetLine = $issue->getLine();
        $targetClass = null;
        $classLine = null;

        foreach ($classes as $class) {
            $classStartLine = $this->analyzer->getNodeLine($class);
            // rough range
            if ($targetLine >= $classStartLine && $targetLine <= $classStartLine + 500) {
                $targetClass = $class;
                $classLine = $classStartLine;
                break;
            }
        }

        if ($targetClass === null || $classLine === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find class for immutable annotation');
        }

        $lines = explode("\n", $fileContent);
        $classIndex = $classLine - 1;
        $docblock = $this->docblockManipulator->extractDocblock($lines, $classIndex);

        if ($docblock !== null && str_contains($docblock['content'], '@immutable')) {
            return FixResult::failure($issue, $fileContent, '@immutable already exists');
        }

        if ($docblock !== null) {
            $updated = $this->docblockManipulator->addAnnotation($docblock['content'], 'immutable', '');
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
                ' * @immutable',
                ' */',
            ];
            array_splice($lines, $classIndex, 0, $docblockLines);
        }

        return FixResult::success(
            $issue,
            implode("\n", $lines),
            'Added @immutable to class',
            ['Added @immutable annotation']
        );
    }

    public function getDescription(): string
    {
        return 'Adds @immutable annotation to immutable classes with external assignments.';
    }

    public function getName(): string
    {
        return 'ImmutableClassFixer';
    }
}


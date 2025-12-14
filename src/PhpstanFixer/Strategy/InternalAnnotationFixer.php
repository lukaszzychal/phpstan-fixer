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
 * Adds @internal annotation when accessing internal elements.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class InternalAnnotationFixer implements FixStrategyInterface
{
    use PriorityTrait;
    use FileValidationTrait;

    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/internal/i');
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        $validation = $this->validateFileAndParse($issue, $fileContent, $this->analyzer);
        if ($validation instanceof FixResult) {
            return $validation;
        }

        $ast = $validation['ast'];
        $classes = $this->analyzer->getClasses($ast);
        $targetLine = $issue->getLine();
        $targetClass = null;
        $classLine = null;

        foreach ($classes as $class) {
            $classStartLine = $this->analyzer->getNodeLine($class);
            if ($targetLine >= $classStartLine && $targetLine <= $classStartLine + 500) {
                $targetClass = $class;
                $classLine = $classStartLine;
                break;
            }
        }

        if ($targetClass === null || $classLine === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find class for @internal');
        }

        $lines = explode("\n", $fileContent);
        $classIndex = $classLine - 1;
        $docblock = $this->docblockManipulator->extractDocblock($lines, $classIndex);

        if ($docblock !== null && str_contains($docblock['content'], '@internal')) {
            return FixResult::failure($issue, $fileContent, '@internal already exists');
        }

        if ($docblock !== null) {
            $updated = $this->docblockManipulator->addAnnotation($docblock['content'], 'internal', '');
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
                ' * @internal',
                ' */',
            ];
            array_splice($lines, $classIndex, 0, $docblockLines);
        }

        return FixResult::success(
            $issue,
            implode("\n", $lines),
            'Added @internal to class',
            ['Added @internal annotation']
        );
    }

    public function getDescription(): string
    {
        return 'Adds @internal annotation for internal elements.';
    }

    public function getName(): string
    {
        return 'InternalAnnotationFixer';
    }
}


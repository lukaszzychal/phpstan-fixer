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
 * Adds @property annotations for magic properties resolved via __get.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class MagicPropertyFixer implements FixStrategyInterface
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
        return $issue->isUndefinedProperty();
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        $validation = $this->validateFileAndParse($issue, $fileContent, $this->analyzer);
        if ($validation instanceof FixResult) {
            return $validation;
        }

        $ast = $validation['ast'];
        $property = $issue->extractPropertyName();
        if ($property === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract property name');
        }

        $targetLine = $issue->getLine();
        $classes = $this->analyzer->getClasses($ast);
        $targetClass = null;
        $classLine = null;

        foreach ($classes as $class) {
            $start = $this->analyzer->getNodeLine($class);
            if ($targetLine >= $start && $targetLine <= $start + 500) {
                $targetClass = $class;
                $classLine = $start;
                break;
            }
        }

        if ($targetClass === null || $classLine === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find class for magic property');
        }

        $lines = explode("\n", $fileContent);
        $classIndex = $classLine - 1;
        $docblock = $this->docblockManipulator->extractDocblock($lines, $classIndex);

        if ($docblock !== null) {
            $parsed = $this->docblockManipulator->parseDocblock($docblock['content']);
            foreach (['property', 'property-read'] as $tag) {
                foreach ($parsed[$tag] ?? [] as $prop) {
                    if (($prop['name'] ?? '') === '$' . $property) {
                        return FixResult::failure($issue, $fileContent, '@property already exists');
                    }
                }
            }

            $updated = $this->docblockManipulator->addAnnotation(
                $docblock['content'],
                'property',
                "mixed \${$property}"
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
                " * @property mixed \${$property}",
                ' */',
            ];
            array_splice($lines, $classIndex, 0, $docblockLines);
        }

        return FixResult::success(
            $issue,
            implode("\n", $lines),
            "Added @property mixed \${$property}",
            ["Added @property mixed \${$property} at class level"]
        );
    }

    public function getDescription(): string
    {
        return 'Enhances magic property detection by adding @property for __get';
    }

    public function getName(): string
    {
        return 'MagicPropertyFixer';
    }
}


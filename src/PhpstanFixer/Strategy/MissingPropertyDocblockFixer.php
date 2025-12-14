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
 * Fixes undefined property errors by adding @property or @var annotations.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class MissingPropertyDocblockFixer implements FixStrategyInterface
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
        // Only handle undefined property errors, not pivot (handled by separate fixer)
        if ($issue->isUndefinedProperty()) {
            $propertyName = $issue->extractPropertyName();
            // Skip pivot property - handled by UndefinedPivotPropertyFixer
            if ($propertyName === 'pivot') {
                return false;
            }
            return true;
        }

        return false;
    }

    public function fix(Issue $issue, string $fileContent): FixResult
    {
        $validation = $this->validateFileAndParse($issue, $fileContent, $this->analyzer);
        if ($validation instanceof FixResult) {
            return $validation;
        }

        $ast = $validation['ast'];
        $propertyName = $issue->extractPropertyName();
        if ($propertyName === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract property name');
        }

        $targetLine = $issue->getLine();
        $lines = explode("\n", $fileContent);

        // Find the class containing this property usage
        $classes = $this->analyzer->getClasses($ast);
        $targetClass = null;
        $classLine = null;

        foreach ($classes as $class) {
            $classStartLine = $this->analyzer->getNodeLine($class);
            // Estimate class end line (rough approximation)
            $classEndLine = $classStartLine + 500; // Safety margin

            if ($targetLine >= $classStartLine && $targetLine <= $classEndLine) {
                $targetClass = $class;
                $classLine = $classStartLine;
                break;
            }
        }

        if ($targetClass === null || $classLine === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find class containing the property');
        }

        $classIndex = $classLine - 1;

        // Check if property is declared in the class
        $properties = $this->analyzer->getProperties($targetClass);
        $isDeclaredProperty = false;
        foreach ($properties as $property) {
            foreach ($property->props as $prop) {
                if ($prop->name->name === $propertyName) {
                    $isDeclaredProperty = true;
                    break 2;
                }
            }
        }

        // Check for existing docblock on class
        $existingDocblock = $this->docblockManipulator->extractDocblock($lines, $classIndex);
        $propertyVar = '$' . $propertyName;

        if ($isDeclaredProperty) {
            // For declared properties, add @var above the property
            $propertyNode = null;
            foreach ($properties as $property) {
                foreach ($property->props as $prop) {
                    if ($prop->name->name === $propertyName) {
                        $propertyNode = $property;
                        break 2;
                    }
                }
            }

            if ($propertyNode !== null) {
                $propLine = $this->analyzer->getNodeLine($propertyNode);
                $propIndex = $propLine - 1;

                // Check if @var already exists
                $propDocblock = $this->docblockManipulator->extractDocblock($lines, $propIndex);
                if ($propDocblock !== null) {
                    if ($this->docblockManipulator->hasAnnotation($propDocblock['content'], 'var', $propertyVar)) {
                        return FixResult::failure($issue, $fileContent, "@var annotation already exists for {$propertyVar}");
                    }
                }

                // Add @var annotation
                if ($propDocblock !== null) {
                    $updatedDocblock = $this->docblockManipulator->addAnnotation(
                        $propDocblock['content'],
                        'var',
                        "mixed {$propertyVar}"
                    );
                    $docblockLines = explode("\n", $updatedDocblock);
                    array_splice(
                        $lines,
                        $propDocblock['startLine'],
                        $propDocblock['endLine'] - $propDocblock['startLine'] + 1,
                        $docblockLines
                    );
                } else {
                    $docblock = "/**\n * @var mixed {$propertyVar}\n */";
                    $docblockLines = explode("\n", $docblock);
                    array_splice($lines, $propIndex, 0, $docblockLines);
                }

                $fixedContent = implode("\n", $lines);
                return FixResult::success(
                    $issue,
                    $fixedContent,
                    "Added @var annotation for {$propertyVar}",
                    ["Added @var mixed {$propertyVar} at line {$propLine}"]
                );
            }
        } else {
            // For magic/dynamic properties, add @property on class
            if ($existingDocblock !== null) {
                // Check if @property already exists
                $annotations = $this->docblockManipulator->parseDocblock($existingDocblock['content']);
                $hasProperty = false;

                foreach ($annotations['property'] ?? [] as $prop) {
                    if (isset($prop['name']) && $prop['name'] === $propertyVar) {
                        $hasProperty = true;
                        break;
                    }
                }

                if ($hasProperty) {
                    return FixResult::failure($issue, $fileContent, "@property annotation already exists for {$propertyVar}");
                }

                // Add @property to existing docblock
                $updatedDocblock = $this->docblockManipulator->addAnnotation(
                    $existingDocblock['content'],
                    'property',
                    "mixed {$propertyVar}"
                );

                $docblockLines = explode("\n", $updatedDocblock);
                array_splice(
                    $lines,
                    $existingDocblock['startLine'],
                    $existingDocblock['endLine'] - $existingDocblock['startLine'] + 1,
                    $docblockLines
                );
            } else {
                // Create new docblock with @property
                $docblock = "/**\n * @property mixed {$propertyVar}\n */";
                $docblockLines = explode("\n", $docblock);
                array_splice($lines, $classIndex, 0, $docblockLines);
            }

            $fixedContent = implode("\n", $lines);
            return FixResult::success(
                $issue,
                $fixedContent,
                "Added @property annotation for {$propertyVar}",
                ["Added @property mixed {$propertyVar} at class level"]
            );
        }

        return FixResult::failure($issue, $fileContent, 'Could not determine how to fix property');
    }

    public function getDescription(): string
    {
        return 'Adds @property or @var annotations for undefined properties';
    }

    public function getName(): string
    {
        return 'MissingPropertyDocblockFixer';
    }
}


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
 * Fixes undefined pivot property errors by adding @property-read annotation.
 * This is specifically for Laravel Eloquent models.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class UndefinedPivotPropertyFixer implements FixStrategyInterface
{
    use PriorityTrait;
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        if (!$issue->isUndefinedProperty()) {
            return false;
        }

        $propertyName = $issue->extractPropertyName();
        
        // Specifically handle pivot property
        return $propertyName === 'pivot' || 
               str_contains($issue->getMessage(), '->pivot') ||
               str_contains($issue->getMessage(), 'pivot');
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
        $lines = explode("\n", $fileContent);

        // Find the class containing this pivot usage
        $classes = $this->analyzer->getClasses($ast);
        $targetClass = null;
        $classLine = null;

        foreach ($classes as $class) {
            $classStartLine = $this->analyzer->getNodeLine($class);
            // Estimate class end line
            $classEndLine = $classStartLine + 500;

            if ($targetLine >= $classStartLine && $targetLine <= $classEndLine) {
                $targetClass = $class;
                $classLine = $classStartLine;
                break;
            }
        }

        if ($targetClass === null || $classLine === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find class containing the pivot property');
        }

        $classIndex = $classLine - 1;

        // Check for existing docblock on class
        $existingDocblock = $this->docblockManipulator->extractDocblock($lines, $classIndex);
        $pivotVar = '$pivot';

        if ($existingDocblock !== null) {
            // Check if @property-read pivot already exists
            $annotations = $this->docblockManipulator->parseDocblock($existingDocblock['content']);
            $hasPivot = false;

            foreach ($annotations['property-read'] ?? [] as $prop) {
                if (isset($prop['name']) && $prop['name'] === $pivotVar) {
                    $hasPivot = true;
                    break;
                }
            }

            if ($hasPivot) {
                return FixResult::failure($issue, $fileContent, '@property-read $pivot annotation already exists');
            }

            // Add @property-read pivot to existing docblock
            // Use Laravel's Pivot class
            $pivotType = '\\Illuminate\\Database\\Eloquent\\Relations\\Pivot';
            $updatedDocblock = $this->docblockManipulator->addAnnotation(
                $existingDocblock['content'],
                'property-read',
                "{$pivotType} {$pivotVar}"
            );

            $docblockLines = explode("\n", $updatedDocblock);
            array_splice(
                $lines,
                $existingDocblock['startLine'],
                $existingDocblock['endLine'] - $existingDocblock['startLine'] + 1,
                $docblockLines
            );
        } else {
            // Create new docblock with @property-read pivot
            $pivotType = '\\Illuminate\\Database\\Eloquent\\Relations\\Pivot';
            $docblock = "/**\n * @property-read {$pivotType} {$pivotVar}\n */";
            $docblockLines = explode("\n", $docblock);
            array_splice($lines, $classIndex, 0, $docblockLines);
        }

        $fixedContent = implode("\n", $lines);
        return FixResult::success(
            $issue,
            $fixedContent,
            "Added @property-read annotation for \$pivot property",
            ["Added @property-read {$pivotType} {$pivotVar} at class level"]
        );
    }

    public function getDescription(): string
    {
        return 'Adds @property-read annotation for Eloquent pivot property';
    }

    public function getName(): string
    {
        return 'UndefinedPivotPropertyFixer';
    }

    public function getSupportedFrameworks(): array
    {
        return ['laravel'];
    }
}


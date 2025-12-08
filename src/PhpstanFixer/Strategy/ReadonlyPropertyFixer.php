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
 * Adds @readonly annotation to properties that should not be reassigned
 * (pre-PHP 8.1 compatibility for readonly properties).
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ReadonlyPropertyFixer implements FixStrategyInterface
{
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        $message = $issue->getMessage();

        return $issue->matchesPattern('/readonly property/i')
            || $issue->matchesPattern('/read-only property/i')
            || (stripos($message, 'assigned outside of') !== false
                && stripos($message, 'property') !== false);
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

        $propertyName = $issue->extractPropertyName();
        if ($propertyName === null) {
            return FixResult::failure($issue, $fileContent, 'Could not extract property name');
        }

        $lines = explode("\n", $fileContent);
        $classes = $this->analyzer->getClasses($ast);

        foreach ($classes as $class) {
            foreach ($this->analyzer->getProperties($class) as $property) {
                foreach ($property->props as $prop) {
                    if ($prop->name->name !== $propertyName) {
                        continue;
                    }

                    $propLine = $this->analyzer->getNodeLine($property);
                    $propIndex = $propLine - 1;

                    $docblockInfo = $this->docblockManipulator->extractDocblock($lines, $propIndex);

                    if ($docblockInfo !== null && stripos($docblockInfo['content'], '@readonly') !== false) {
                        return FixResult::failure(
                            $issue,
                            $fileContent,
                            sprintf('@readonly already exists for $%s', $propertyName)
                        );
                    }

                    if ($docblockInfo !== null) {
                        $updatedDocblock = $this->insertReadonlyIntoDocblock($docblockInfo['content']);
                        $docblockLines = explode("\n", $updatedDocblock);

                        array_splice(
                            $lines,
                            $docblockInfo['startLine'],
                            $docblockInfo['endLine'] - $docblockInfo['startLine'] + 1,
                            $docblockLines
                        );
                    } else {
                        $docblockLines = [
                            '/**',
                            ' * @readonly',
                            ' */',
                        ];

                        array_splice($lines, $propIndex, 0, $docblockLines);
                    }

                    $fixedContent = implode("\n", $lines);

                    return FixResult::success(
                        $issue,
                        $fixedContent,
                        sprintf('Added @readonly to $%s', $propertyName),
                        [sprintf('Added @readonly annotation for $%s at line %d', $propertyName, $propLine)]
                    );
                }
            }
        }

        return FixResult::failure($issue, $fileContent, 'Could not find property declaration');
    }

    public function getDescription(): string
    {
        return 'Adds @readonly annotation for properties that should not be reassigned (PHP < 8.1)';
    }

    public function getName(): string
    {
        return 'ReadonlyPropertyFixer';
    }

    /**
     * Insert @readonly into an existing docblock.
     */
    private function insertReadonlyIntoDocblock(string $docblockContent): string
    {
        $lines = explode("\n", $docblockContent);

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (trim($lines[$i]) === '*/') {
                array_splice($lines, $i, 0, ' * @readonly');
                break;
            }
        }

        return implode("\n", $lines);
    }
}


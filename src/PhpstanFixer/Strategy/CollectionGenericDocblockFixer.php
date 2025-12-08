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
 * Fixes missing generic type parameters in Collection types.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class CollectionGenericDocblockFixer implements FixStrategyInterface
{
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    public function canFix(Issue $issue): bool
    {
        return $issue->matchesPattern('/Generic.*Collection.*needs parameters/i') ||
               $issue->matchesPattern('/Generic type.*Collection.*needs.*parameters/i');
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

        // Find the annotation that needs fixing
        // Look for Collection type in docblocks near the target line
        $startSearch = max(0, $targetLine - 20);
        $endSearch = min(count($lines), $targetLine + 5);

        for ($i = $startSearch; $i < $endSearch; $i++) {
            $line = $lines[$i];
            
            // Check if this line contains Collection without generics
            if (preg_match('/@(return|var|param)\s+([^\\s]+\\\\)?Collection(\s|$)/i', $line, $matches)) {
                // Found Collection without generics
                $annotationType = $matches[1];
                // Offset 2 may not exist if there's no namespace prefix before Collection
                /** @phpstan-ignore-next-line */
                $collectionType = $matches[2] ?? '';
                $fullMatch = $matches[0];

                // Extract the full annotation to update
                if (preg_match('/@' . preg_quote($annotationType, '/') . '\s+([^\n]+)/', $line, $fullMatches)) {
                    $fullAnnotation = $fullMatches[1];
                    
                    // Replace Collection with Collection<int, mixed>
                    $newAnnotation = preg_replace(
                        '/([^\\s\\\\]+\\\\)?Collection(\s|$)/i',
                        '${1}Collection<int, mixed>$2',
                        $fullAnnotation,
                        1
                    );

                    // Update the line
                    $lines[$i] = str_replace($fullAnnotation, $newAnnotation, $line);

                    $fixedContent = implode("\n", $lines);
                    return FixResult::success(
                        $issue,
                        $fixedContent,
                        "Added generic types to Collection annotation",
                        ["Updated @{$annotationType} annotation at line " . ($i + 1) . " with Collection<int, mixed>"]
                    );
                }
            }
        }

        // Try to find and update in a docblock block
        $docblock = $this->docblockManipulator->extractDocblock($lines, $targetLine - 1);
        if ($docblock !== null) {
            $docblockContent = $docblock['content'];
            
            // Check if docblock contains Collection without generics
            if (preg_match('/@(return|var|param)\s+([^\\s]+\\\\)?Collection(\s|$)/i', $docblockContent)) {
                // Update all Collection references in the docblock
                $updatedContent = preg_replace(
                    '/(@(?:return|var|param)\s+[^\\s\\\\]*?)([^\\s\\\\]+\\\\)?Collection(\s|$)/i',
                    '$1$2Collection<int, mixed>$3',
                    $docblockContent
                );

                $updatedLines = explode("\n", $updatedContent);
                array_splice(
                    $lines,
                    $docblock['startLine'],
                    $docblock['endLine'] - $docblock['startLine'] + 1,
                    $updatedLines
                );

                $fixedContent = implode("\n", $lines);
                return FixResult::success(
                    $issue,
                    $fixedContent,
                    "Added generic types to Collection annotations in docblock",
                    ["Updated Collection types with generics in docblock at line " . ($docblock['startLine'] + 1)]
                );
            }
        }

        return FixResult::failure($issue, $fileContent, 'Could not find Collection type to fix');
    }

    public function getDescription(): string
    {
        return 'Adds generic type parameters (e.g., Collection<int, mixed>) to Collection types';
    }

    public function getName(): string
    {
        return 'CollectionGenericDocblockFixer';
    }
}


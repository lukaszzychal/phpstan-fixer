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
 * Fixes undefined method errors by adding @method annotation.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class UndefinedMethodFixer implements FixStrategyInterface
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
        return $issue->isUndefinedMethod() || $issue->matchesPattern('/Call to (an )?undefined method/i');
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

        // Extract method name from error message
        $methodName = $issue->extractMethodName();
        if ($methodName === null) {
            // Try to extract from message pattern
            if (preg_match('/method\s+(\w+)\s*\(/i', $issue->getMessage(), $matches)) {
                $methodName = $matches[1];
            } else {
                return FixResult::failure($issue, $fileContent, 'Could not extract method name');
            }
        }

        $targetLine = $issue->getLine();
        $lines = explode("\n", $fileContent);

        // Find the class containing this method call
        $classes = $this->analyzer->getClasses($ast);
        $targetClass = null;
        $classLine = null;

        foreach ($classes as $class) {
            $classStartLine = $this->analyzer->getNodeLine($class);
            $classEndLine = $classStartLine + 500; // Safety margin

            if ($targetLine >= $classStartLine && $targetLine <= $classEndLine) {
                $targetClass = $class;
                $classLine = $classStartLine;
                break;
            }
        }

        if ($targetClass === null || $classLine === null) {
            return FixResult::failure($issue, $fileContent, 'Could not find class containing the method call');
        }

        $classIndex = $classLine - 1;

        // Check for existing docblock on class
        $existingDocblock = $this->docblockManipulator->extractDocblock($lines, $classIndex);

        if ($existingDocblock !== null) {
            // Check if @method already exists
            $annotations = $this->docblockManipulator->parseDocblock($existingDocblock['content']);
            $hasMethod = false;

            foreach ($annotations['method'] ?? [] as $method) {
                if (isset($method['name']) && $method['name'] === $methodName) {
                    $hasMethod = true;
                    break;
                }
            }

            if ($hasMethod) {
                return FixResult::failure($issue, $fileContent, "@method annotation already exists for {$methodName}()");
            }

            // Add @method annotation - use mixed as default return type
            $methodAnnotation = "mixed {$methodName}()";
            $updatedDocblock = $this->docblockManipulator->addAnnotation(
                $existingDocblock['content'],
                'method',
                $methodAnnotation
            );

            $docblockLines = explode("\n", $updatedDocblock);
            array_splice(
                $lines,
                $existingDocblock['startLine'],
                $existingDocblock['endLine'] - $existingDocblock['startLine'] + 1,
                $docblockLines
            );
        } else {
            // Create new docblock with @method
            $methodAnnotation = "mixed {$methodName}()";
            $docblock = "/**\n * @method {$methodAnnotation}\n */";
            $docblockLines = explode("\n", $docblock);
            array_splice($lines, $classIndex, 0, $docblockLines);
        }

        $fixedContent = implode("\n", $lines);
        return FixResult::success(
            $issue,
            $fixedContent,
            "Added @method annotation for {$methodName}()",
            ["Added @method mixed {$methodName}() at class level"]
        );
    }

    public function getDescription(): string
    {
        return 'Adds @method annotation for undefined method calls (magic methods)';
    }

    public function getName(): string
    {
        return 'UndefinedMethodFixer';
    }
}


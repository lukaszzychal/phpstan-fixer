<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Strategy;

use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;

/**
 * Trait providing methods to locate functions and methods at specific line numbers.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
trait FunctionLocatorTrait
{
    /**
     * Find function or method at a specific line.
     *
     * @param array<\PhpParser\Node\Stmt> $ast The AST
     * @param int $targetLine The target line number
     * @param PhpFileAnalyzer $analyzer The analyzer instance
     * @param int $tolerance Tolerance for line matching (0 = exact match, >0 = allow ±tolerance)
     * @return array{function: Function_|null, method: ClassMethod|null, class: \PhpParser\Node\Stmt\ClassLike|null}
     *         Returns an array with 'function', 'method', and 'class' keys (one will be non-null if found)
     */
    protected function findFunctionOrMethodAtLine(
        array $ast,
        int $targetLine,
        PhpFileAnalyzer $analyzer,
        int $tolerance = 0
    ): array {
        $functions = $analyzer->getFunctions($ast);
        $classes = $analyzer->getClasses($ast);

        $targetFunction = null;
        $targetMethod = null;
        $targetClass = null;

        // Check functions first
        foreach ($functions as $function) {
            $functionLine = $analyzer->getNodeLine($function);
            if ($this->matchesLine($functionLine, $targetLine, $tolerance)) {
                $targetFunction = $function;
                break;
            }
        }

        // Check methods in classes if no function found
        if ($targetFunction === null) {
            foreach ($classes as $class) {
                $methods = $analyzer->getMethods($class);
                foreach ($methods as $method) {
                    $methodLine = $analyzer->getNodeLine($method);
                    if ($this->matchesLine($methodLine, $targetLine, $tolerance)) {
                        $targetMethod = $method;
                        $targetClass = $class;
                        break 2;
                    }
                }
            }
        }

        return [
            'function' => $targetFunction,
            'method' => $targetMethod,
            'class' => $targetClass,
        ];
    }

    /**
     * Check if a line number matches the target line (with optional tolerance).
     *
     * @param int $line The line number to check
     * @param int $targetLine The target line number
     * @param int $tolerance Tolerance for matching (0 = exact, >0 = allow ±tolerance)
     * @return bool True if the line matches
     */
    private function matchesLine(int $line, int $targetLine, int $tolerance): bool
    {
        if ($tolerance === 0) {
            return $line === $targetLine;
        }

        return abs($line - $targetLine) <= $tolerance;
    }
}


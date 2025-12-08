<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\CodeAnalysis;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Analyzes PHP files using AST parsing.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class PhpFileAnalyzer
{
    private Parser $parser;
    private NodeFinder $nodeFinder;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * Parse a PHP file and return the AST.
     *
     * @return Stmt[]|null Array of statements or null if parsing failed
     */
    public function parse(string $fileContent): ?array
    {
        try {
            return $this->parser->parse($fileContent);
        } catch (\PhpParser\Error $e) {
            return null;
        }
    }

    /**
     * Get the namespace declaration from the file.
     *
     * @param Stmt[]|null $ast Parsed AST
     * @return string|null Namespace name or null if not found
     */
    public function getNamespace(?array $ast): ?string
    {
        if ($ast === null) {
            return null;
        }

        $namespace = $this->nodeFinder->findFirstInstanceOf($ast, Namespace_::class);
        if ($namespace instanceof Namespace_ && $namespace->name !== null) {
            return $namespace->name->toString();
        }

        return null;
    }

    /**
     * Get all use statements from the file.
     *
     * @param Stmt[]|null $ast Parsed AST
     * @return array<string, string> Array of ['alias' => 'Full\ClassName'] or ['ClassName' => 'Full\ClassName']
     */
    public function getUseStatements(?array $ast): array
    {
        if ($ast === null) {
            return [];
        }

        $useStatements = [];
        $uses = $this->nodeFinder->findInstanceOf($ast, Use_::class);

        foreach ($uses as $use) {
            foreach ($use->uses as $useUse) {
                $fullName = $useUse->name->toString();
                $alias = $useUse->alias !== null ? $useUse->alias->name : $useUse->name->getLast();
                $useStatements[$alias] = $fullName;
            }
        }

        return $useStatements;
    }

    /**
     * Get all classes from the file.
     *
     * @param Stmt[]|null $ast Parsed AST
     * @return Class_[] Array of class nodes
     */
    public function getClasses(?array $ast): array
    {
        if ($ast === null) {
            return [];
        }

        return $this->nodeFinder->findInstanceOf($ast, Class_::class);
    }

    /**
     * Find a class by name.
     *
     * @param Stmt[]|null $ast Parsed AST
     * @param string $className Simple class name or fully qualified name
     * @return Class_|null Class node or null if not found
     */
    public function findClass(?array $ast, string $className): ?Class_
    {
        $classes = $this->getClasses($ast);
        foreach ($classes as $class) {
            if ($class->name === null) {
                continue;
            }

            $classSimpleName = $class->name->name;
            if ($classSimpleName === $className) {
                return $class;
            }

            // Check fully qualified name
            $namespace = $this->getNamespace($ast);
            if ($namespace !== null) {
                $fqn = $namespace . '\\' . $classSimpleName;
                if ($fqn === $className) {
                    return $class;
                }
            }
        }

        return null;
    }

    /**
     * Get all methods from a class.
     *
     * @param Class_ $class Class node
     * @return ClassMethod[] Array of method nodes
     */
    public function getMethods(Class_ $class): array
    {
        return array_filter(
            $class->stmts ?? [],
            fn($stmt) => $stmt instanceof ClassMethod
        );
    }

    /**
     * Find a method by name in a class.
     *
     * @param Class_ $class Class node
     * @param string $methodName Method name
     * @return ClassMethod|null Method node or null if not found
     */
    public function findMethod(Class_ $class, string $methodName): ?ClassMethod
    {
        $methods = $this->getMethods($class);
        foreach ($methods as $method) {
            if ($method->name->name === $methodName) {
                return $method;
            }
        }

        return null;
    }

    /**
     * Get all functions from the file (non-class functions).
     *
     * @param Stmt[]|null $ast Parsed AST
     * @return Function_[] Array of function nodes
     */
    public function getFunctions(?array $ast): array
    {
        if ($ast === null) {
            return [];
        }

        return $this->nodeFinder->findInstanceOf($ast, Function_::class);
    }

    /**
     * Find a function by name.
     *
     * @param Stmt[]|null $ast Parsed AST
     * @param string $functionName Function name
     * @return Function_|null Function node or null if not found
     */
    public function findFunction(?array $ast, string $functionName): ?Function_
    {
        $functions = $this->getFunctions($ast);
        foreach ($functions as $function) {
            if ($function->name->name === $functionName) {
                return $function;
            }
        }

        return null;
    }

    /**
     * Get all properties from a class.
     *
     * @param Class_ $class Class node
     * @return Property[] Array of property nodes
     */
    public function getProperties(Class_ $class): array
    {
        return array_filter(
            $class->stmts ?? [],
            fn($stmt) => $stmt instanceof Property
        );
    }

    /**
     * Get the line number for a node (considering comments).
     *
     * @param Node $node AST node
     * @return int Line number
     */
    public function getNodeLine(Node $node): int
    {
        return $node->getStartLine();
    }

    /**
     * Get content at a specific line.
     *
     * @param string $fileContent File content
     * @param int $lineNumber Line number (1-based)
     * @return string|null Line content or null if line doesn't exist
     */
    public function getLineContent(string $fileContent, int $lineNumber): ?string
    {
        $lines = explode("\n", $fileContent);
        if ($lineNumber < 1 || $lineNumber > count($lines)) {
            return null;
        }

        return $lines[$lineNumber - 1];
    }

    /**
     * Get content between two line numbers (inclusive).
     *
     * @param string $fileContent File content
     * @param int $startLine Start line (1-based)
     * @param int $endLine End line (1-based, inclusive)
     * @return string[] Array of line contents
     */
    public function getLines(string $fileContent, int $startLine, int $endLine): array
    {
        $lines = explode("\n", $fileContent);
        $startIndex = max(0, $startLine - 1);
        $endIndex = min(count($lines), $endLine);

        return array_slice($lines, $startIndex, $endIndex - $startIndex);
    }
}


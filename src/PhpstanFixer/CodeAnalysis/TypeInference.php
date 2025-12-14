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
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;

/**
 * Infers types from code context (return statements, usage patterns, etc.).
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class TypeInference
{
    private NodeFinder $nodeFinder;

    public function __construct(
        private readonly PhpFileAnalyzer $analyzer
    ) {
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * Infer return type from function/method body.
     *
     * @param Function_|ClassMethod $node Function or method node
     * @param Stmt[]|null $ast Full AST for context
     * @return string|null Inferred type or null if cannot infer
     */
    public function inferReturnType($node, ?array $ast): ?string
    {
        if ($ast === null) {
            return null;
        }

        $returnTypes = [];

        // Find all return statements in the function/method
        $returns = $this->nodeFinder->findInstanceOf($node->stmts ?? [], Return_::class);

        foreach ($returns as $return) {
            if ($return->expr === null) {
                continue; // Skip 'return;' without expression
            }

            $type = $this->inferExpressionType($return->expr, $node, $ast);
            if ($type !== null && !in_array($type, $returnTypes, true)) {
                $returnTypes[] = $type;
            }
        }

        if (empty($returnTypes)) {
            return null;
        }

        if (count($returnTypes) === 1) {
            return $returnTypes[0];
        }

        // Multiple return types - create union type
        return implode('|', $returnTypes);
    }

    /**
     * Infer parameter type from its usage in function/method body.
     *
     * @param Function_|ClassMethod $node Function or method node
     * @param int $paramPosition Parameter position (0-based)
     * @param Stmt[]|null $ast Full AST for context
     * @return string|null Inferred type or null if cannot infer
     */
    public function inferParameterType($node, int $paramPosition, ?array $ast): ?string
    {
        if ($ast === null || !isset($node->params[$paramPosition])) {
            return null;
        }

        $param = $node->params[$paramPosition];
        $paramName = $param->var->name ?? null;

        if ($paramName === null || $node->stmts === null) {
            return null;
        }

        // Find all usages of this parameter
        $types = [];
        foreach ($node->stmts as $stmt) {
            $type = $this->inferParameterUsageType($stmt, $paramName, $node, $ast);
            if ($type !== null && !in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        if (empty($types)) {
            return null;
        }

        if (count($types) === 1) {
            return $types[0];
        }

        // Multiple usage types - create union type
        return implode('|', $types);
    }

    /**
     * Infer type from an expression.
     *
     * @param Expr $expr Expression node
     * @param Function_|ClassMethod|null $context Function/method context
     * @param Stmt[]|null $ast Full AST for context
     * @return string|null Inferred type or null if cannot infer
     */
    private function inferExpressionType(Expr $expr, $context, ?array $ast): ?string
    {
        // Handle scalar types
        if ($expr instanceof Node\Scalar\String_) {
            return 'string';
        }

        if ($expr instanceof Node\Scalar\LNumber) {
            return 'int';
        }

        if ($expr instanceof Node\Scalar\DNumber) {
            return 'float';
        }

        if ($expr instanceof Node\Expr\ConstFetch) {
            $name = $expr->name->toString();
            if (in_array(strtolower($name), ['true', 'false'], true)) {
                return 'bool';
            }
            if (strtolower($name) === 'null') {
                return 'null';
            }
        }

        // Handle arrays
        if ($expr instanceof Node\Expr\Array_) {
            return 'array';
        }

        // Handle method calls - try to infer from method return type
        if ($expr instanceof MethodCall && $context !== null && $ast !== null) {
            return $this->inferMethodCallReturnType($expr, $context, $ast);
        }

        // Handle array access
        if ($expr instanceof ArrayDimFetch) {
            return 'array';
        }

        // Handle property access
        if ($expr instanceof PropertyFetch) {
            return 'object';
        }

        // Handle variable - try to find its type from context
        if ($expr instanceof Node\Expr\Variable) {
            return $this->inferVariableType($expr, $context, $ast);
        }

        return null;
    }

    /**
     * Infer parameter usage type from statement.
     */
    private function inferParameterUsageType(Node\Stmt $stmt, string $paramName, $context, ?array $ast): ?string
    {
        // Check if parameter is used in method call
        $methodCalls = $this->nodeFinder->findInstanceOf([$stmt], MethodCall::class);
        foreach ($methodCalls as $methodCall) {
            if ($methodCall->var instanceof Node\Expr\Variable && 
                $methodCall->var->name === $paramName) {
                return 'object';
            }
        }

        // Check if parameter is used in array access
        $arrayAccesses = $this->nodeFinder->findInstanceOf([$stmt], ArrayDimFetch::class);
        foreach ($arrayAccesses as $arrayAccess) {
            if ($arrayAccess->var instanceof Node\Expr\Variable && 
                $arrayAccess->var->name === $paramName) {
                return 'array';
            }
        }

        // Check if parameter is used in property access
        $propertyAccesses = $this->nodeFinder->findInstanceOf([$stmt], PropertyFetch::class);
        foreach ($propertyAccesses as $propertyAccess) {
            if ($propertyAccess->var instanceof Node\Expr\Variable && 
                $propertyAccess->var->name === $paramName) {
                return 'object';
            }
        }

        return null;
    }

    /**
     * Infer method call return type from method definition.
     */
    private function inferMethodCallReturnType(MethodCall $methodCall, $context, ?array $ast): ?string
    {
        $methodName = null;
        if ($methodCall->name instanceof Node\Identifier) {
            $methodName = $methodCall->name->name;
        }

        if ($methodName === null || $ast === null) {
            return null;
        }

        // Try to find method in same class if methodCall is on $this
        if ($methodCall->var instanceof Node\Expr\Variable && 
            $methodCall->var->name === 'this' &&
            $context instanceof ClassMethod) {
            
            // Find class containing this method
            $classes = $this->analyzer->getClasses($ast);
            foreach ($classes as $class) {
                $methods = $this->analyzer->getMethods($class);
                foreach ($methods as $method) {
                    if ($method->name->name === $methodName && $method->getReturnType() !== null) {
                        return $this->formatType($method->getReturnType());
                    }
                }
            }
        }

        return null;
    }

    /**
     * Infer variable type from context.
     *
     * @phpstan-ignore-next-line Method is kept for future extension - will analyze variable assignments
     */
    private function inferVariableType(Node\Expr\Variable $var, $context, ?array $ast): ?string
    {
        // For now, return null - can be extended to analyze variable assignments
        return null;
    }

    /**
     * Format a PHP-Parser type node to string.
     */
    private function formatType($typeNode): string
    {
        if (is_string($typeNode)) {
            return $typeNode;
        }

        if ($typeNode instanceof Node\Name) {
            return $typeNode->toString();
        }

        if ($typeNode instanceof Node\Identifier) {
            return $typeNode->name;
        }

        if ($typeNode instanceof Node\NullableType) {
            return '?' . $this->formatType($typeNode->type);
        }

        // @phpstan-ignore-next-line - false positive: $typeNode is a union type, instanceof check is valid
        if ($typeNode instanceof Node\UnionType) {
            return implode('|', array_map([$this, 'formatType'], $typeNode->types));
        }

        return 'mixed';
    }
}


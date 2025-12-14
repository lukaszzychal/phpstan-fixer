<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Command;

use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Strategy\FixStrategyInterface;

/**
 * Factory for creating fixer strategy instances.
 * Handles dependency injection for fixer constructors.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class FixerFactory
{
    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
    }

    /**
     * Create a fixer strategy instance from a class name.
     *
     * @param string $fixerClass Fully qualified class name
     * @return FixStrategyInterface
     * @throws \RuntimeException If the class cannot be instantiated
     */
    public function createFixer(string $fixerClass): FixStrategyInterface
    {
        if (!class_exists($fixerClass)) {
            throw new \RuntimeException(
                "Custom fixer class not found: {$fixerClass}. Make sure the class is autoloaded."
            );
        }

        if (!is_subclass_of($fixerClass, FixStrategyInterface::class)) {
            throw new \RuntimeException(
                "Custom fixer class {$fixerClass} must implement " . FixStrategyInterface::class
            );
        }

        return $this->instantiateFixer($fixerClass);
    }

    /**
     * Instantiate a fixer using reflection to inject dependencies.
     *
     * @param string $fixerClass Fully qualified class name
     * @return FixStrategyInterface
     * @throws \RuntimeException If instantiation fails
     */
    private function instantiateFixer(string $fixerClass): FixStrategyInterface
    {
        $reflection = new \ReflectionClass($fixerClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $fixerClass();
        }

        $args = $this->resolveConstructorArguments($constructor);
        return $reflection->newInstanceArgs($args);
    }

    /**
     * Resolve constructor arguments by type-hinting.
     *
     * @param \ReflectionMethod $constructor The constructor reflection
     * @return array<int, mixed> Constructor arguments
     */
    private function resolveConstructorArguments(\ReflectionMethod $constructor): array
    {
        $args = [];
        $params = $constructor->getParameters();

        foreach ($params as $param) {
            $type = $param->getType();
            if (!($type instanceof \ReflectionNamedType)) {
                $args[] = null;
                continue;
            }

            $typeName = $type->getName();
            $resolvedArg = $this->resolveDependency($typeName, $param);

            $args[] = $resolvedArg;
        }

        return $args;
    }

    /**
     * Resolve a dependency by type name.
     *
     * @param string $typeName The type name (FQN or short name)
     * @param \ReflectionParameter $param The parameter reflection
     * @return mixed The resolved dependency or null
     */
    private function resolveDependency(string $typeName, \ReflectionParameter $param): mixed
    {
        // Handle fully qualified names
        if ($typeName === PhpFileAnalyzer::class || $typeName === 'PhpstanFixer\CodeAnalysis\PhpFileAnalyzer') {
            return $this->analyzer;
        }

        if ($typeName === DocblockManipulator::class || $typeName === 'PhpstanFixer\CodeAnalysis\DocblockManipulator') {
            return $this->docblockManipulator;
        }

        // For other types, try to instantiate if not primitive
        if (class_exists($typeName) || interface_exists($typeName)) {
            // Note: This could be extended to support more dependency injection
            // For now, we'll use null and let the constructor handle it
            if ($param->allowsNull()) {
                return null;
            }
        }

        // Use default value if available
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // If parameter is nullable, use null
        if ($param->allowsNull()) {
            return null;
        }

        // Last resort: return null (this will likely cause an error, but it's better than crashing)
        return null;
    }
}


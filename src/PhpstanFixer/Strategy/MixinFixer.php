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
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\NodeFinder;
use PhpstanFixer\Strategy\PriorityTrait;

/**
 * Fixes undefined method/property errors by adding @mixin annotation when class uses magic methods.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class MixinFixer implements FixStrategyInterface
{
    use PriorityTrait;
    private NodeFinder $nodeFinder;

    public function __construct(
        private readonly PhpFileAnalyzer $analyzer,
        private readonly DocblockManipulator $docblockManipulator
    ) {
        $this->nodeFinder = new NodeFinder();
    }

    public function canFix(Issue $issue): bool
    {
        // Only handle undefined method/property errors
        // Note: We check for magic methods in fix() method since we need file content
        return $issue->isUndefinedMethod() || $issue->isUndefinedProperty();
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
        
        // Find the class containing the error
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
            return FixResult::failure($issue, $fileContent, 'Could not find target class');
        }

        // Check if class has __call, __get, or __set
        $hasMagicMethod = $this->hasMagicMethod($targetClass);
        
        if (!$hasMagicMethod) {
            return FixResult::failure($issue, $fileContent, 'Class does not have magic methods (__call, __get, __set)');
        }

        // Extract the delegated class name from the error message or analyze the magic method body
        // For now, we'll try to extract it from common patterns
        $mixinClassName = $this->extractMixinClassName($targetClass, $fileContent, $ast);
        
        if ($mixinClassName === null) {
            return FixResult::failure($issue, $fileContent, 'Could not determine mixin class name');
        }

        // Check if @mixin already exists
        $lines = explode("\n", $fileContent);
        $docblockInfo = $this->docblockManipulator->extractDocblock($lines, $classLine);
        
        if ($docblockInfo !== null) {
            $existingDocblock = $docblockInfo['content'];
            $parsed = $this->docblockManipulator->parseDocblock($existingDocblock);
            
            // Check if @mixin already exists with this class
            if (isset($parsed['mixin'])) {
                foreach ($parsed['mixin'] as $mixin) {
                    if (isset($mixin['className']) && $mixin['className'] === $mixinClassName) {
                        // Already has this mixin
                        return FixResult::failure($issue, $fileContent, '@mixin already exists');
                    }
                }
            }
            
            // Add @mixin to existing docblock
            return $this->addMixinToExistingDocblock(
                $issue,
                $fileContent,
                $lines,
                $docblockInfo,
                $mixinClassName
            );
        } else {
            // Create new docblock with @mixin
            return $this->createNewDocblockWithMixin(
                $issue,
                $fileContent,
                $lines,
                $classLine,
                $mixinClassName
            );
        }
    }

    public function getDescription(): string
    {
        return 'Adds @mixin annotation for classes using magic methods (__call, __get, __set)';
    }

    public function getName(): string
    {
        return 'MixinFixer';
    }

    /**
     * Check if class has magic methods (__call, __get, __set).
     */
    private function hasMagicMethod(\PhpParser\Node\Stmt\Class_ $class): bool
    {
        $methods = $this->analyzer->getMethods($class);
        
        foreach ($methods as $method) {
            $methodName = $method->name->name;
            if (in_array($methodName, ['__call', '__get', '__set', '__callStatic'], true)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract the mixin class name from magic method body or error message.
     */
    private function extractMixinClassName(
        \PhpParser\Node\Stmt\Class_ $class,
        string $fileContent,
        ?array $ast
    ): ?string {
        // Try to find the delegated class by analyzing __call/__get/__set body
        // Common patterns:
        // - $this->delegate->$name(...)
        // - $this->delegator->$name(...)
        // - $this->target->$name(...)
        // - static::$delegate->$name(...)
        
        $methods = $this->analyzer->getMethods($class);
        
        foreach ($methods as $method) {
            $methodName = $method->name->name;
            if (!in_array($methodName, ['__call', '__get', '__set'], true)) {
                continue;
            }
            
            // Analyze method body AST to find delegated property
            if ($method->stmts === null) {
                continue;
            }
            
            // Find property access patterns in method body
            $delegatedProperty = $this->findDelegatedProperty($method->stmts, $class, $fileContent);
            if ($delegatedProperty !== null) {
                return $delegatedProperty;
            }
        }
        
        // Strategy 2: Check properties with common delegate names
        $properties = $this->analyzer->getProperties($class);
        foreach ($properties as $property) {
            if (count($property->props) === 0) {
                continue;
            }
            
            $propName = $property->props[0]->name->name;
            
            // Check if property name suggests delegation
            if (preg_match('/^(delegate|delegator|target|handler|wrapped|inner|backing)$/i', $propName)) {
                // Try to get type from property declaration
                if ($property->type !== null) {
                    $typeName = $property->type->toString();
                    // Remove leading backslash if present
                    return ltrim($typeName, '\\');
                }
                
                // Try to get type from property docblock
                $propDocblock = $this->findPropertyDocblock($property, $class, $fileContent);
                if ($propDocblock !== null) {
                    $parsed = $this->docblockManipulator->parseDocblock($propDocblock);
                    if (isset($parsed['var'][0]['type'])) {
                        $typeName = $parsed['var'][0]['type'];
                        return ltrim($typeName, '\\');
                    }
                }
            }
        }
        
        // Strategy 3: Check class docblock for hints
        $classDocblock = $this->findClassDocblock($class, $fileContent);
        if ($classDocblock !== null) {
            $parsed = $this->docblockManipulator->parseDocblock($classDocblock);
            // Check @property annotations for delegate-like properties
            foreach (['property', 'property-read'] as $tag) {
                if (isset($parsed[$tag])) {
                    foreach ($parsed[$tag] as $prop) {
                        if (isset($prop['name']) && preg_match('/^(delegate|delegator|target|handler)$/i', $prop['name'])) {
                            if (isset($prop['type'])) {
                                return ltrim($prop['type'], '\\');
                            }
                        }
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Find delegated property by analyzing method body AST.
     *
     * @param Node\Stmt[] $statements Method body statements
     * @param \PhpParser\Node\Stmt\Class_ $class Class node
     * @param string $fileContent File content for docblock analysis
     * @return string|null Class name or null if not found
     */
    private function findDelegatedProperty(array $statements, \PhpParser\Node\Stmt\Class_ $class, string $fileContent): ?string
    {
        // Find MethodCall or PropertyFetch nodes in the method body
        // Pattern: $this->property->$name(...) or $this->property[$name]
        $methodCalls = $this->nodeFinder->findInstanceOf($statements, MethodCall::class);
        $propertyFetches = $this->nodeFinder->findInstanceOf($statements, PropertyFetch::class);
        
        $candidates = [];
        
        // Analyze MethodCall nodes: $this->property->$name(...)
        foreach ($methodCalls as $methodCall) {
            if ($methodCall->var instanceof PropertyFetch) {
                $propFetch = $methodCall->var;
                if ($propFetch->var instanceof Variable && $propFetch->var->name === 'this') {
                    $propName = $propFetch->name->name ?? null;
                    if ($propName !== null) {
                        $candidates[] = $propName;
                    }
                }
            }
        }
        
        // Analyze PropertyFetch nodes: $this->property->$name
        foreach ($propertyFetches as $propFetch) {
            if ($propFetch->var instanceof PropertyFetch) {
                $outerPropFetch = $propFetch->var;
                if ($outerPropFetch->var instanceof Variable && $outerPropFetch->var->name === 'this') {
                    $propName = $outerPropFetch->name->name ?? null;
                    if ($propName !== null) {
                        $candidates[] = $propName;
                    }
                }
            }
        }
        
        // Check static property access: static::$property->$name(...)
        $staticPropFetches = $this->nodeFinder->findInstanceOf($statements, StaticPropertyFetch::class);
        foreach ($staticPropFetches as $staticPropFetch) {
            $propName = $staticPropFetch->name->name ?? null;
            if ($propName !== null) {
                $candidates[] = $propName;
            }
        }
        
        // Find most common candidate (likely the delegate)
        if (empty($candidates)) {
            return null;
        }
        
        $candidateCounts = array_count_values($candidates);
        arsort($candidateCounts);
        $mostCommonProp = array_key_first($candidateCounts);
        
        // Get type for the most common property
        $properties = $this->analyzer->getProperties($class);
        foreach ($properties as $property) {
            if (count($property->props) === 0) {
                continue;
            }
            
            if ($property->props[0]->name->name === $mostCommonProp) {
                // Check property type
                if ($property->type !== null) {
                    return ltrim($property->type->toString(), '\\');
                }
                
                // Check property docblock
                $propDocblock = $this->findPropertyDocblock($property, $class, $fileContent);
                if ($propDocblock !== null) {
                    $parsed = $this->docblockManipulator->parseDocblock($propDocblock);
                    if (isset($parsed['var'][0]['type'])) {
                        return ltrim($parsed['var'][0]['type'], '\\');
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Find docblock for a property.
     */
    private function findPropertyDocblock(
        \PhpParser\Node\Stmt\Property $property,
        \PhpParser\Node\Stmt\Class_ $class,
        string $fileContent
    ): ?string {
        // First try to get docblock from node comments
        $comments = $property->getComments();
        foreach ($comments as $comment) {
            if ($comment instanceof \PhpParser\Comment\Doc) {
                return '/**' . "\n" . $comment->getText() . "\n" . ' */';
            }
        }
        
        // Fallback: try to find docblock before the property line
        $propLine = $this->analyzer->getNodeLine($property);
        $lines = explode("\n", $fileContent);
        
        $docblockInfo = $this->docblockManipulator->extractDocblock($lines, $propLine);
        return $docblockInfo !== null ? $docblockInfo['content'] : null;
    }

    /**
     * Find docblock for a class.
     */
    private function findClassDocblock(
        \PhpParser\Node\Stmt\Class_ $class,
        string $fileContent
    ): ?string {
        $classLine = $this->analyzer->getNodeLine($class);
        $lines = explode("\n", $fileContent);
        
        $docblockInfo = $this->docblockManipulator->extractDocblock($lines, $classLine);
        return $docblockInfo !== null ? $docblockInfo['content'] : null;
    }

    /**
     * Add @mixin to existing docblock.
     */
    private function addMixinToExistingDocblock(
        Issue $issue,
        string $fileContent,
        array $lines,
        array $docblockInfo,
        string $mixinClassName
    ): FixResult {
        $docblockContent = $docblockInfo['content'];
        $startLine = $docblockInfo['startLine'];
        $endLine = $docblockInfo['endLine'];
        
        // Parse existing docblock
        $parsed = $this->docblockManipulator->parseDocblock($docblockContent);
        
        // Add @mixin annotation
        $mixinAnnotation = " * @mixin {$mixinClassName}";
        
        // Insert before closing */
        $docblockLines = explode("\n", $docblockContent);
        $newDocblockLines = [];
        
        foreach ($docblockLines as $line) {
            $newDocblockLines[] = $line;
            // Insert before closing */
            if (trim($line) === '*/') {
                // Insert @mixin before the last line
                array_pop($newDocblockLines);
                $newDocblockLines[] = $mixinAnnotation;
                $newDocblockLines[] = ' */';
                break;
            }
        }
        
        $newDocblockContent = implode("\n", $newDocblockLines);
        
        // Replace docblock in file
        $newLines = $lines;
        array_splice($newLines, $startLine, $endLine - $startLine + 1, explode("\n", $newDocblockContent));
        
        $newContent = implode("\n", $newLines);
        
        return FixResult::success(
            $issue,
            $newContent,
            sprintf('Added @mixin %s to class docblock', $mixinClassName)
        );
    }

    /**
     * Create new docblock with @mixin.
     */
    private function createNewDocblockWithMixin(
        Issue $issue,
        string $fileContent,
        array $lines,
        int $classLine,
        string $mixinClassName
    ): FixResult {
        $docblock = "/**\n * @mixin {$mixinClassName}\n */";
        
        // Insert before class declaration
        $newLines = $lines;
        array_splice($newLines, $classLine, 0, explode("\n", $docblock));
        
        $newContent = implode("\n", $newLines);
        
        return FixResult::success(
            $issue,
            $newContent,
            sprintf('Created docblock with @mixin %s', $mixinClassName)
        );
    }
}


<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\CodeAnalysis;

use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\CodeAnalysis\TypeInference;
use PHPUnit\Framework\TestCase;

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class TypeInferenceTest extends TestCase
{
    private TypeInference $typeInference;
    private PhpFileAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new PhpFileAnalyzer();
        $this->typeInference = new TypeInference($this->analyzer);
    }

    public function testInferReturnTypeFromReturnStatement(): void
    {
        $code = <<<'PHP'
<?php

function test(): void {
    return 'string';
}
PHP;

        $ast = $this->analyzer->parse($code);
        $this->assertNotNull($ast);

        $functions = $this->analyzer->getFunctions($ast);
        $this->assertCount(1, $functions);

        $inferredType = $this->typeInference->inferReturnType($functions[0], $ast);
        
        // Should infer 'string' from return statement
        $this->assertSame('string', $inferredType);
    }

    public function testInferReturnTypeFromMultipleReturnStatements(): void
    {
        $code = <<<'PHP'
<?php

function test($condition): void {
    if ($condition) {
        return 42;
    }
    return 'string';
}
PHP;

        $ast = $this->analyzer->parse($code);
        $this->assertNotNull($ast);

        $functions = $this->analyzer->getFunctions($ast);
        $this->assertCount(1, $functions);

        $inferredType = $this->typeInference->inferReturnType($functions[0], $ast);
        
        // Should infer union type: int|string
        $this->assertSame('int|string', $inferredType);
    }

    public function testInferParameterTypeFromUsage(): void
    {
        $code = <<<'PHP'
<?php

function test($param): void {
    $param->method();
    return $param;
}
PHP;

        $ast = $this->analyzer->parse($code);
        $this->assertNotNull($ast);

        $functions = $this->analyzer->getFunctions($ast);
        $this->assertCount(1, $functions);

        $inferredType = $this->typeInference->inferParameterType($functions[0], 0, $ast);
        
        // Should infer 'object' from method call
        $this->assertSame('object', $inferredType);
    }

    public function testInferParameterTypeFromArrayAccess(): void
    {
        $code = <<<'PHP'
<?php

function test($param): void {
    return $param['key'];
}
PHP;

        $ast = $this->analyzer->parse($code);
        $this->assertNotNull($ast);

        $functions = $this->analyzer->getFunctions($ast);
        $this->assertCount(1, $functions);

        $inferredType = $this->typeInference->inferParameterType($functions[0], 0, $ast);
        
        // Should infer 'array' from array access
        $this->assertSame('array', $inferredType);
    }

    public function testReturnsNullWhenCannotInfer(): void
    {
        $code = <<<'PHP'
<?php

function test($param): void {
    $var = $param;
}
PHP;

        $ast = $this->analyzer->parse($code);
        $this->assertNotNull($ast);

        $functions = $this->analyzer->getFunctions($ast);
        $this->assertCount(1, $functions);

        $inferredType = $this->typeInference->inferParameterType($functions[0], 0, $ast);
        
        // Should return null when cannot infer
        $this->assertNull($inferredType);
    }

    public function testInferReturnTypeFromMethodCall(): void
    {
        $code = <<<'PHP'
<?php

class Test {
    public function getString(): string {
        return 'test';
    }
    
    public function test(): void {
        return $this->getString();
    }
}
PHP;

        $ast = $this->analyzer->parse($code);
        $this->assertNotNull($ast);

        $classes = $this->analyzer->getClasses($ast);
        $this->assertCount(1, $classes);

        $methods = $this->analyzer->getMethods($classes[0]);
        $testMethod = null;
        foreach ($methods as $method) {
            if ($method->name->name === 'test') {
                $testMethod = $method;
                break;
            }
        }

        $this->assertNotNull($testMethod);

        $inferredType = $this->typeInference->inferReturnType($testMethod, $ast);
        
        // Should infer 'string' from method call return type
        $this->assertSame('string', $inferredType);
    }
}


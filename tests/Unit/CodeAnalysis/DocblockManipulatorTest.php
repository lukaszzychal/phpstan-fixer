<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\CodeAnalysis;

use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PHPUnit\Framework\TestCase;

final class DocblockManipulatorTest extends TestCase
{
    private DocblockManipulator $manipulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manipulator = new DocblockManipulator();
    }

    public function testHasAnnotation(): void
    {
        $docblock = "/**\n * @param string \$name\n * @return void\n */";
        
        $this->assertTrue($this->manipulator->hasAnnotation($docblock, 'param'));
        $this->assertTrue($this->manipulator->hasAnnotation($docblock, 'return'));
        $this->assertFalse($this->manipulator->hasAnnotation($docblock, 'throws'));
    }

    public function testHasAnnotationWithName(): void
    {
        $docblock = "/**\n * @param string \$name\n * @param int \$age\n */";
        
        $this->assertTrue($this->manipulator->hasAnnotation($docblock, 'param', '$name'));
        $this->assertTrue($this->manipulator->hasAnnotation($docblock, 'param', '$age'));
        $this->assertFalse($this->manipulator->hasAnnotation($docblock, 'param', '$email'));
    }

    public function testAddAnnotation(): void
    {
        $docblock = "/**\n * @param string \$name\n */";
        $updated = $this->manipulator->addAnnotation($docblock, 'return', 'void');

        $this->assertStringContainsString('@return void', $updated);
        $this->assertStringContainsString('@param string $name', $updated);
    }

    public function testAddAnnotationToNewDocblock(): void
    {
        $docblock = '';
        $updated = $this->manipulator->addAnnotation($docblock, 'return', 'void');

        $this->assertStringContainsString('/**', $updated);
        $this->assertStringContainsString('@return void', $updated);
        $this->assertStringContainsString('*/', $updated);
    }

    public function testCreateDocblock(): void
    {
        $annotations = [
            'param' => ['string $name', 'int $age'],
            'return' => ['void'],
        ];
        
        $docblock = $this->manipulator->createDocblock($annotations);

        $this->assertStringContainsString('@param string $name', $docblock);
        $this->assertStringContainsString('@param int $age', $docblock);
        $this->assertStringContainsString('@return void', $docblock);
    }

    public function testParseDocblock(): void
    {
        $docblock = "/**\n * @param string \$name\n * @return void\n */";
        $parsed = $this->manipulator->parseDocblock($docblock);

        $this->assertNotEmpty($parsed['param']);
        $this->assertNotEmpty($parsed['return']);
        $this->assertSame('string', $parsed['param'][0]['type']);
        $this->assertSame('$name', $parsed['param'][0]['name']);
        $this->assertSame('void', $parsed['return'][0]['type']);
    }

    public function testExtractDocblock(): void
    {
        $lines = [
            '',
            '/**',
            ' * Test docblock',
            ' * @param string $name',
            ' */',
            'class Test {}',
        ];

        $docblock = $this->manipulator->extractDocblock($lines, 5);

        $this->assertNotNull($docblock);
        $this->assertSame(1, $docblock['startLine']);
        $this->assertSame(4, $docblock['endLine']);
        $this->assertStringContainsString('Test docblock', $docblock['content']);
    }
}


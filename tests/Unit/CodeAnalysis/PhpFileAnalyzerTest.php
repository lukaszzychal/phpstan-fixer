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

use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PHPUnit\Framework\TestCase;

final class PhpFileAnalyzerTest extends TestCase
{
    private PhpFileAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new PhpFileAnalyzer();
    }

    public function testParseValidPHP(): void
    {
        $code = '<?php namespace Test; class Foo {}';
        $ast = $this->analyzer->parse($code);

        $this->assertNotNull($ast);
        $this->assertIsArray($ast); // @phpstan-ignore-line - PhpParser returns array|null
    }

    public function testParseInvalidPHP(): void
    {
        $code = '<?php class { invalid syntax }';
        $ast = $this->analyzer->parse($code);

        $this->assertNull($ast);
    }

    public function testGetNamespace(): void
    {
        $code = '<?php namespace Test\\Namespace; class Foo {}';
        $ast = $this->analyzer->parse($code);

        $this->assertNotNull($ast);
        $namespace = $this->analyzer->getNamespace($ast);
        $this->assertSame('Test\\Namespace', $namespace);
    }

    public function testGetNamespaceWithoutNamespace(): void
    {
        $code = '<?php class Foo {}';
        $ast = $this->analyzer->parse($code);

        $this->assertNotNull($ast);
        $namespace = $this->analyzer->getNamespace($ast);
        $this->assertNull($namespace);
    }

    public function testGetUseStatements(): void
    {
        $code = <<<'PHP'
<?php
namespace Test;
use Foo\Bar;
use Baz\Qux as Quux;
class Test {}
PHP;
        $ast = $this->analyzer->parse($code);

        $this->assertNotNull($ast);
        $uses = $this->analyzer->getUseStatements($ast);

        $this->assertArrayHasKey('Bar', $uses);
        $this->assertSame('Foo\\Bar', $uses['Bar']);
        $this->assertArrayHasKey('Quux', $uses);
        $this->assertSame('Baz\\Qux', $uses['Quux']);
    }

    public function testGetClasses(): void
    {
        $code = <<<'PHP'
<?php
namespace Test;
class Foo {}
class Bar {}
PHP;
        $ast = $this->analyzer->parse($code);

        $this->assertNotNull($ast);
        $classes = $this->analyzer->getClasses($ast);

        $this->assertCount(2, $classes);
        $this->assertSame('Foo', $classes[0]->name->name);
        $this->assertSame('Bar', $classes[1]->name->name);
    }

    public function testGetLineContent(): void
    {
        $code = "line1\nline2\nline3";
        $content = $this->analyzer->getLineContent($code, 2);

        $this->assertSame('line2', $content);
    }

    public function testGetLineContentOutOfBounds(): void
    {
        $code = "line1\nline2";
        $content = $this->analyzer->getLineContent($code, 10);

        $this->assertNull($content);
    }
}


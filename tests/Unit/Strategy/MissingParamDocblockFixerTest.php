<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Strategy;

use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\MissingParamDocblockFixer;
use PHPUnit\Framework\TestCase;

final class MissingParamDocblockFixerTest extends TestCase
{
    private MissingParamDocblockFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new MissingParamDocblockFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testCanFixMissingParameterType(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Parameter #1 $name has no type specified');
        
        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testCannotFixOtherErrors(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Access to undefined property');
        
        $this->assertFalse($this->fixer->canFix($issue));
    }

    public function testGetName(): void
    {
        $this->assertSame('MissingParamDocblockFixer', $this->fixer->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertNotEmpty($this->fixer->getDescription());
        $this->assertStringContainsString('param', strtolower($this->fixer->getDescription()));
    }
}


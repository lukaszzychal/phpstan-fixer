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
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\UndefinedVariableFixer;
use PHPUnit\Framework\TestCase;

final class UndefinedVariableFixerTest extends TestCase
{
    private UndefinedVariableFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new UndefinedVariableFixer(
            new DocblockManipulator()
        );
    }

    public function testCanFixUndefinedVariable(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Undefined variable: $foo');
        
        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testGetName(): void
    {
        $this->assertSame('UndefinedVariableFixer', $this->fixer->getName());
    }
}


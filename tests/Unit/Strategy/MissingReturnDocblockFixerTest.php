<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Strategy;

use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\MissingReturnDocblockFixer;
use PHPUnit\Framework\TestCase;

final class MissingReturnDocblockFixerTest extends TestCase
{
    private MissingReturnDocblockFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new MissingReturnDocblockFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testCanFixMissingReturnType(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Method has no return type specified');
        
        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testCannotFixOtherErrors(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Access to undefined property');
        
        $this->assertFalse($this->fixer->canFix($issue));
    }

    public function testGetName(): void
    {
        $this->assertSame('MissingReturnDocblockFixer', $this->fixer->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertNotEmpty($this->fixer->getDescription());
        $this->assertStringContainsString('return', strtolower($this->fixer->getDescription()));
    }
}


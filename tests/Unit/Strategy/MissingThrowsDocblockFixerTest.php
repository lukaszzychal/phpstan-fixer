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
use PhpstanFixer\Strategy\MissingThrowsDocblockFixer;
use PHPUnit\Framework\TestCase;

final class MissingThrowsDocblockFixerTest extends TestCase
{
    private MissingThrowsDocblockFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new MissingThrowsDocblockFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testCanFixMissingThrows(): void
    {
        $issue = new Issue('/path/to/file.php', 10, '@throws annotation is missing');
        
        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testGetName(): void
    {
        $this->assertSame('MissingThrowsDocblockFixer', $this->fixer->getName());
    }
}


<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */


declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Strategy;

use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\UndefinedMethodFixer;
use PHPUnit\Framework\TestCase;

final class UndefinedMethodFixerTest extends TestCase
{
    private UndefinedMethodFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new UndefinedMethodFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testCanFixUndefinedMethod(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Call to an undefined method bar()');
        
        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testGetName(): void
    {
        $this->assertSame('UndefinedMethodFixer', $this->fixer->getName());
    }
}


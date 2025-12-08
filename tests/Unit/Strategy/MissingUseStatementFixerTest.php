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

use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\MissingUseStatementFixer;
use PHPUnit\Framework\TestCase;

final class MissingUseStatementFixerTest extends TestCase
{
    private MissingUseStatementFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new MissingUseStatementFixer(
            new PhpFileAnalyzer()
        );
    }

    public function testCanFixClassNotFound(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Class Foo not found');
        
        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testGetName(): void
    {
        $this->assertSame('MissingUseStatementFixer', $this->fixer->getName());
    }
}


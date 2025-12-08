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
use PhpstanFixer\Strategy\CallableTypeFixer;
use PHPUnit\Framework\TestCase;

final class CallableTypeFixerTest extends TestCase
{
    private CallableTypeFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new CallableTypeFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testCanFixCallableIssue(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Parameter #1 $callback expects callable');
        
        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testGetName(): void
    {
        $this->assertSame('CallableTypeFixer', $this->fixer->getName());
    }
}


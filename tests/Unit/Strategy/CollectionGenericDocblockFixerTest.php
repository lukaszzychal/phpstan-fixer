<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 *
declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Strategy;

use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\CollectionGenericDocblockFixer;
use PHPUnit\Framework\TestCase;

final class CollectionGenericDocblockFixerTest extends TestCase
{
    private CollectionGenericDocblockFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new CollectionGenericDocblockFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testCanFixCollectionGeneric(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Generic type Collection needs parameters');
        
        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testGetName(): void
    {
        $this->assertSame('CollectionGenericDocblockFixer', $this->fixer->getName());
    }
}


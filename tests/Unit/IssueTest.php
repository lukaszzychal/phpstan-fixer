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

namespace PhpstanFixer\Tests\Unit;

use PhpstanFixer\Issue;
use PHPUnit\Framework\TestCase;

final class IssueTest extends TestCase
{
    public function testBasicProperties(): void
    {
        $issue = new Issue(
            filePath: '/path/to/file.php',
            line: 42,
            message: 'Some error message',
            errorCode: 'error.code',
            identifier: 'error.id',
            column: 10
        );

        $this->assertSame('/path/to/file.php', $issue->getFilePath());
        $this->assertSame(42, $issue->getLine());
        $this->assertSame('Some error message', $issue->getMessage());
        $this->assertSame('error.code', $issue->getErrorCode());
        $this->assertSame('error.id', $issue->getIdentifier());
        $this->assertSame(10, $issue->getColumn());
    }

    public function testMatchesPattern(): void
    {
        $issue = new Issue(
            filePath: '/path/to/file.php',
            line: 42,
            message: 'Access to an undefined property $foo'
        );

        $this->assertTrue($issue->matchesPattern('/undefined property/i'));
        $this->assertFalse($issue->matchesPattern('/undefined method/i'));
    }

    public function testExtractPropertyName(): void
    {
        $issue = new Issue(
            filePath: '/path/to/file.php',
            line: 42,
            message: 'Access to an undefined property $foo'
        );

        $this->assertSame('foo', $issue->extractPropertyName());
    }

    public function testExtractMethodName(): void
    {
        $issue = new Issue(
            filePath: '/path/to/file.php',
            line: 42,
            message: 'Call to an undefined method bar()'
        );

        $this->assertSame('bar', $issue->extractMethodName());
    }

    public function testIsUndefinedProperty(): void
    {
        $issue = new Issue(
            filePath: '/path/to/file.php',
            line: 42,
            message: 'Access to an undefined property $foo'
        );

        $this->assertTrue($issue->isUndefinedProperty());
    }

    public function testIsUndefinedMethod(): void
    {
        $issue = new Issue(
            filePath: '/path/to/file.php',
            line: 42,
            message: 'Call to an undefined method bar()'
        );

        $this->assertTrue($issue->isUndefinedMethod());
    }

    public function testIsMissingReturnType(): void
    {
        $issue = new Issue(
            filePath: '/path/to/file.php',
            line: 42,
            message: 'Method has no return type specified'
        );

        $this->assertTrue($issue->isMissingReturnType());
    }
}


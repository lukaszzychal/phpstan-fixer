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

namespace PhpstanFixer\Tests\Unit;

use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;
use PHPUnit\Framework\TestCase;

final class FixResultTest extends TestCase
{
    public function testSuccessCreation(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Some error');
        $result = FixResult::success($issue, 'fixed content', 'Description');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('fixed content', $result->getFixedContent());
        $this->assertSame('Description', $result->getDescription());
        $this->assertSame($issue, $result->getIssue());
        $this->assertTrue($result->hasChanges());
    }

    public function testFailureCreation(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Some error');
        $result = FixResult::failure($issue, 'original content', 'Reason');

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('original content', $result->getFixedContent());
        $this->assertSame('Reason', $result->getDescription());
        $this->assertFalse($result->hasChanges());
    }

    public function testGetChangeDescription(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Some error');
        $result = FixResult::success($issue, 'fixed', 'Custom description', ['change1', 'change2']);

        $this->assertSame('Custom description', $result->getChangeDescription());
        $this->assertSame(['change1', 'change2'], $result->getChanges());
    }

    public function testGetChangeDescriptionWithoutDescription(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Some error');
        $result = FixResult::success($issue, 'fixed', null, ['change1']);

        $this->assertStringContainsString('Fixed issue at line 10', $result->getChangeDescription());
    }
}


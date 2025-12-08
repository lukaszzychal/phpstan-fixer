<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Configuration;

use PhpstanFixer\Configuration\Rule;
use PHPUnit\Framework\TestCase;

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class RuleTest extends TestCase
{
    public function testCreateRuleWithFixAction(): void
    {
        $rule = new Rule(Rule::ACTION_FIX);

        $this->assertTrue($rule->isFix());
        $this->assertFalse($rule->isIgnore());
        $this->assertFalse($rule->isReport());
        $this->assertSame(Rule::ACTION_FIX, $rule->getAction());
    }

    public function testCreateRuleWithIgnoreAction(): void
    {
        $rule = new Rule(Rule::ACTION_IGNORE);

        $this->assertFalse($rule->isFix());
        $this->assertTrue($rule->isIgnore());
        $this->assertFalse($rule->isReport());
        $this->assertSame(Rule::ACTION_IGNORE, $rule->getAction());
    }

    public function testCreateRuleWithReportAction(): void
    {
        $rule = new Rule(Rule::ACTION_REPORT);

        $this->assertFalse($rule->isFix());
        $this->assertFalse($rule->isIgnore());
        $this->assertTrue($rule->isReport());
        $this->assertSame(Rule::ACTION_REPORT, $rule->getAction());
    }

    public function testCreateRuleWithDefaultAction(): void
    {
        $rule = new Rule();

        $this->assertTrue($rule->isFix());
        $this->assertSame(Rule::ACTION_FIX, $rule->getAction());
    }

    public function testCreateRuleWithInvalidActionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid action "invalid". Must be one of: fix, ignore, report');

        new Rule('invalid');
    }
}


<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Configuration;

use PhpstanFixer\Configuration\Configuration;
use PhpstanFixer\Configuration\Rule;
use PHPUnit\Framework\TestCase;

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class ConfigurationTest extends TestCase
{
    public function testGetRuleForErrorWithExactMatch(): void
    {
        $rules = [
            'Access to an undefined property' => new Rule(Rule::ACTION_FIX),
            'Method has no return type' => new Rule(Rule::ACTION_IGNORE),
        ];
        $config = new Configuration($rules);

        $rule = $config->getRuleForError('Access to an undefined property');

        $this->assertTrue($rule->isFix());
    }

    public function testGetRuleForErrorWithWildcardPattern(): void
    {
        $rules = [
            'Access to an undefined *' => new Rule(Rule::ACTION_IGNORE),
            'Method has no return type' => new Rule(Rule::ACTION_FIX),
        ];
        $config = new Configuration($rules);

        $rule = $config->getRuleForError('Access to an undefined property');

        $this->assertTrue($rule->isIgnore());
    }

    public function testGetRuleForErrorWithRegexPattern(): void
    {
        $rules = [
            '/Access to an undefined \w+/' => new Rule(Rule::ACTION_REPORT),
        ];
        $config = new Configuration($rules);

        $rule = $config->getRuleForError('Access to an undefined property');

        $this->assertTrue($rule->isReport());
    }

    public function testGetRuleForErrorReturnsDefaultWhenNoMatch(): void
    {
        $rules = [
            'Some other error' => new Rule(Rule::ACTION_IGNORE),
        ];
        $default = new Rule(Rule::ACTION_REPORT);
        $config = new Configuration($rules, $default);

        $rule = $config->getRuleForError('Unknown error message');

        $this->assertTrue($rule->isReport());
        $this->assertSame($default, $rule);
    }

    public function testGetRuleForErrorWithDefaultFixAction(): void
    {
        $config = new Configuration();

        $rule = $config->getRuleForError('Any error message');

        $this->assertTrue($rule->isFix());
    }

    public function testGetRulesReturnsAllRules(): void
    {
        $rules = [
            'Error 1' => new Rule(Rule::ACTION_FIX),
            'Error 2' => new Rule(Rule::ACTION_IGNORE),
        ];
        $config = new Configuration($rules);

        $this->assertSame($rules, $config->getRules());
    }

    public function testGetDefaultReturnsDefaultRule(): void
    {
        $default = new Rule(Rule::ACTION_REPORT);
        $config = new Configuration([], $default);

        $this->assertSame($default, $config->getDefault());
    }

    public function testHasRulesReturnsTrueWhenRulesExist(): void
    {
        $rules = [
            'Error 1' => new Rule(Rule::ACTION_FIX),
        ];
        $config = new Configuration($rules);

        $this->assertTrue($config->hasRules());
    }

    public function testHasRulesReturnsFalseWhenNoRules(): void
    {
        $config = new Configuration();

        $this->assertFalse($config->hasRules());
    }

    public function testPatternMatchingPriorityExactOverWildcard(): void
    {
        $rules = [
            'Access to an undefined property' => new Rule(Rule::ACTION_FIX),
            'Access to an undefined *' => new Rule(Rule::ACTION_IGNORE),
        ];
        $config = new Configuration($rules);

        $rule = $config->getRuleForError('Access to an undefined property');

        // Exact match should take priority
        $this->assertTrue($rule->isFix());
    }

    public function testPatternMatchingWithMultipleWildcards(): void
    {
        $rules = [
            'Call to an undefined method *' => new Rule(Rule::ACTION_REPORT),
        ];
        $config = new Configuration($rules);

        $rule = $config->getRuleForError('Call to an undefined method getValue');

        $this->assertTrue($rule->isReport());
    }
}


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

    public function testGetEnabledFixersReturnsEmptyArrayByDefault(): void
    {
        $config = new Configuration();

        $this->assertSame([], $config->getEnabledFixers());
    }

    public function testGetEnabledFixersReturnsConfiguredList(): void
    {
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            ['MissingReturnDocblockFixer', 'MissingParamDocblockFixer']
        );

        $enabled = $config->getEnabledFixers();

        $this->assertCount(2, $enabled);
        $this->assertContains('MissingReturnDocblockFixer', $enabled);
        $this->assertContains('MissingParamDocblockFixer', $enabled);
    }

    public function testGetDisabledFixersReturnsEmptyArrayByDefault(): void
    {
        $config = new Configuration();

        $this->assertSame([], $config->getDisabledFixers());
    }

    public function testGetDisabledFixersReturnsConfiguredList(): void
    {
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            [],
            ['UndefinedPivotPropertyFixer']
        );

        $disabled = $config->getDisabledFixers();

        $this->assertCount(1, $disabled);
        $this->assertContains('UndefinedPivotPropertyFixer', $disabled);
    }

    public function testGetFixerPriorityReturnsNullWhenNotConfigured(): void
    {
        $config = new Configuration();

        $this->assertNull($config->getFixerPriority('MissingReturnDocblockFixer'));
    }

    public function testGetFixerPriorityReturnsConfiguredPriority(): void
    {
        $priorities = [
            'MissingReturnDocblockFixer' => 100,
            'MissingParamDocblockFixer' => 90,
        ];
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            [],
            [],
            $priorities
        );

        $this->assertSame(100, $config->getFixerPriority('MissingReturnDocblockFixer'));
        $this->assertSame(90, $config->getFixerPriority('MissingParamDocblockFixer'));
    }

    public function testIsFixerEnabledReturnsTrueWhenInEnabledList(): void
    {
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            ['MissingReturnDocblockFixer']
        );

        $this->assertTrue($config->isFixerEnabled('MissingReturnDocblockFixer'));
    }

    public function testIsFixerEnabledReturnsFalseWhenInDisabledList(): void
    {
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            ['MissingReturnDocblockFixer'],
            ['MissingReturnDocblockFixer']
        );

        // Disabled takes precedence
        $this->assertFalse($config->isFixerEnabled('MissingReturnDocblockFixer'));
    }

    public function testIsFixerEnabledReturnsTrueWhenNotInAnyList(): void
    {
        $config = new Configuration();

        // If no enabled/disabled lists, all fixers are enabled by default
        $this->assertTrue($config->isFixerEnabled('MissingReturnDocblockFixer'));
    }

    public function testIsFixerEnabledReturnsFalseWhenOnlyEnabledListExistsAndFixerNotInIt(): void
    {
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            ['MissingParamDocblockFixer'] // Only this one enabled
        );

        $this->assertFalse($config->isFixerEnabled('MissingReturnDocblockFixer'));
    }

    public function testGetIncludePathsReturnsEmptyArrayByDefault(): void
    {
        $config = new Configuration();

        $this->assertSame([], $config->getIncludePaths());
    }

    public function testGetIncludePathsReturnsConfiguredList(): void
    {
        $includePaths = ['src/', 'app/'];
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            [],
            [],
            [],
            $includePaths
        );

        $this->assertSame($includePaths, $config->getIncludePaths());
    }

    public function testGetExcludePathsReturnsEmptyArrayByDefault(): void
    {
        $config = new Configuration();

        $this->assertSame([], $config->getExcludePaths());
    }

    public function testGetExcludePathsReturnsConfiguredList(): void
    {
        $excludePaths = ['vendor/', 'tests/'];
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            [],
            [],
            [],
            [],
            $excludePaths
        );

        $this->assertSame($excludePaths, $config->getExcludePaths());
    }

    public function testIsPathAllowedReturnsTrueWhenNoFiltersConfigured(): void
    {
        $config = new Configuration();

        $this->assertTrue($config->isPathAllowed('src/SomeClass.php'));
    }

    public function testIsPathAllowedReturnsTrueWhenPathMatchesIncludePattern(): void
    {
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            [],
            [],
            [],
            ['src/']
        );

        $this->assertTrue($config->isPathAllowed('src/SomeClass.php'));
        $this->assertFalse($config->isPathAllowed('tests/SomeTest.php'));
    }

    public function testIsPathAllowedReturnsFalseWhenPathMatchesExcludePattern(): void
    {
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            [],
            [],
            [],
            [],
            ['tests/']
        );

        $this->assertFalse($config->isPathAllowed('tests/SomeTest.php'));
        $this->assertTrue($config->isPathAllowed('src/SomeClass.php'));
    }

    public function testIsPathAllowedWithGlobPatterns(): void
    {
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            [],
            [],
            [],
            ['src/'],
            ['**/*Test.php']
        );

        $this->assertTrue($config->isPathAllowed('src/SomeClass.php'));
        $this->assertFalse($config->isPathAllowed('src/SomeTest.php'));
    }

    public function testIsPathAllowedExcludeTakesPrecedenceOverInclude(): void
    {
        $config = new Configuration(
            [],
            new Rule(Rule::ACTION_FIX),
            [],
            [],
            [],
            ['src/'],
            ['src/Excluded.php']
        );

        $this->assertTrue($config->isPathAllowed('src/SomeClass.php'));
        $this->assertFalse($config->isPathAllowed('src/Excluded.php'));
    }
}


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
use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\MissingParamDocblockFixer;
use PhpstanFixer\Strategy\MissingReturnDocblockFixer;
use PHPUnit\Framework\TestCase;

/**
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class TypeInferenceIntegrationTest extends TestCase
{
    private PhpFileAnalyzer $analyzer;
    private DocblockManipulator $docblockManipulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new PhpFileAnalyzer();
        $this->docblockManipulator = new DocblockManipulator();
    }

    public function testMissingParamDocblockFixerInfersArrayTypeFromUsage(): void
    {
        $code = <<<'PHP'
<?php

function test($param) {
    return $param['key'];
}
PHP;

        $issue = new Issue(
            __FILE__,
            3,
            'Parameter #1 $param has no type specified'
        );

        $fixer = new MissingParamDocblockFixer($this->analyzer, $this->docblockManipulator);
        
        $this->assertTrue($fixer->canFix($issue));
        
        $result = $fixer->fix($issue, $code);
        
        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('@param array $param', $result->getFixedContent());
    }

    public function testMissingParamDocblockFixerInfersObjectTypeFromMethodCall(): void
    {
        $code = <<<'PHP'
<?php

function test($param) {
    $param->method();
}
PHP;

        $issue = new Issue(
            __FILE__,
            3,
            'Parameter #1 $param has no type specified'
        );

        $fixer = new MissingParamDocblockFixer($this->analyzer, $this->docblockManipulator);
        
        $this->assertTrue($fixer->canFix($issue));
        
        $result = $fixer->fix($issue, $code);
        
        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('@param object $param', $result->getFixedContent());
    }

    public function testMissingReturnDocblockFixerInfersStringFromReturnStatement(): void
    {
        $code = <<<'PHP'
<?php

function test() {
    return 'string';
}
PHP;

        $issue = new Issue(
            __FILE__,
            3,
            'Function test has no return type'
        );

        $fixer = new MissingReturnDocblockFixer($this->analyzer, $this->docblockManipulator);
        
        $this->assertTrue($fixer->canFix($issue));
        
        $result = $fixer->fix($issue, $code);
        
        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('@return string', $result->getFixedContent());
    }

    public function testMissingReturnDocblockFixerInfersUnionTypeFromMultipleReturns(): void
    {
        $code = <<<'PHP'
<?php

function test($condition) {
    if ($condition) {
        return 42;
    }
    return 'string';
}
PHP;

        $issue = new Issue(
            __FILE__,
            3,
            'Function test has no return type'
        );

        $fixer = new MissingReturnDocblockFixer($this->analyzer, $this->docblockManipulator);
        
        $this->assertTrue($fixer->canFix($issue));
        
        $result = $fixer->fix($issue, $code);
        
        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('@return int|string', $result->getFixedContent());
    }

    public function testMissingReturnDocblockFixerFallsBackToMixedWhenCannotInfer(): void
    {
        $code = <<<'PHP'
<?php

function test($param) {
    $var = $param;
    return $var;
}
PHP;

        $issue = new Issue(
            __FILE__,
            3,
            'Function test has no return type'
        );

        $fixer = new MissingReturnDocblockFixer($this->analyzer, $this->docblockManipulator);
        
        $this->assertTrue($fixer->canFix($issue));
        
        $result = $fixer->fix($issue, $code);
        
        $this->assertTrue($result->isSuccessful());
        // Should fall back to mixed when cannot infer
        $this->assertStringContainsString('@return mixed', $result->getFixedContent());
    }
}


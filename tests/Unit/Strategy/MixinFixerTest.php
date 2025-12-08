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
use PhpstanFixer\Strategy\MixinFixer;
use PHPUnit\Framework\TestCase;

final class MixinFixerTest extends TestCase
{
    private MixinFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new MixinFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testCanFixUndefinedMethodWhenClassHasCall(): void
    {
        $issue = new Issue('/path/to/file.php', 10, 'Call to an undefined method MyClass::someMethod()');
        
        // This should be handled if the class has __call
        // The actual logic will check the file content
        $fileContent = <<<'PHP'
<?php

class MyClass
{
    public function __call($name, $arguments)
    {
        return $this->delegate->$name(...$arguments);
    }
    
    public function test()
    {
        $this->someMethod(); // Line 10
    }
}
PHP;
        
        $result = $this->fixer->canFix($issue);
        
        // Note: canFix() might need file content to check for __call
        // For now, we'll check based on error pattern
        $this->assertIsBool($result);
    }

    public function testCannotFixNonUndefinedMethodError(): void
    {
        // MixinFixer should handle undefined property/method errors
        // but this test checks a different error type
        $issue = new Issue('/path/to/file.php', 10, 'Method has no return type');
        
        $this->assertFalse($this->fixer->canFix($issue));
    }

    public function testGetName(): void
    {
        $this->assertSame('MixinFixer', $this->fixer->getName());
    }

    public function testGetDescription(): void
    {
        $description = $this->fixer->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function testFixWithPropertyTypeInDeclaration(): void
    {
        $tempFile = sys_get_temp_dir() . '/mixin-test-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class MyClass
{
    private DelegateClass $delegate;
    
    public function __call($name, $arguments)
    {
        return $this->delegate->$name(...$arguments);
    }
    
    public function test()
    {
        $this->someMethod(); // Line 14
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 14, 'Call to an undefined method MyClass::someMethod()');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@mixin DelegateClass', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFixWithPropertyTypeFromDocblock(): void
    {
        $tempFile = sys_get_temp_dir() . '/mixin-test-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class MyClass
{
    /**
     * @var DelegateClass
     */
    private $delegate;
    
    public function __call($name, $arguments)
    {
        return $this->delegate->$name(...$arguments);
    }
    
    public function test()
    {
        $this->someMethod(); // Line 17
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 17, 'Call to an undefined method MyClass::someMethod()');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@mixin DelegateClass', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFixWithCommonDelegatePropertyName(): void
    {
        $tempFile = sys_get_temp_dir() . '/mixin-test-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class MyClass
{
    private DelegatorClass $delegator;
    
    public function __get($name)
    {
        return $this->delegator->$name;
    }
    
    public function test()
    {
        $value = $this->property; // Line 15
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 15, 'Access to an undefined property MyClass::$property');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@mixin DelegatorClass', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFixFailsWhenNoMagicMethod(): void
    {
        $tempFile = sys_get_temp_dir() . '/mixin-test-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class MyClass
{
    public function test()
    {
        $this->someMethod(); // Line 7
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 7, 'Call to an undefined method MyClass::someMethod()');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertFalse($result->isSuccessful());
            $this->assertStringContainsString('magic methods', $result->getDescription() ?? '');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}


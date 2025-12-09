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
use PhpstanFixer\Strategy\PrefixedTagsFixer;
use PHPUnit\Framework\TestCase;

final class PrefixedTagsFixerTest extends TestCase
{
    private PrefixedTagsFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new PrefixedTagsFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testAddsPhpstanParam(): void
    {
        $tempFile = sys_get_temp_dir() . '/prefixed-param-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Foo
{
    /**
     * @param string $class
     */
    public function bar($class)
    {
        return $class; // line 10
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 10, 'Parameter #1 $class of method Foo::bar() expects class-string<Foo>, string given');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@phpstan-param class-string<Foo> $class', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testAddsPhpstanReturn(): void
    {
        $tempFile = sys_get_temp_dir() . '/prefixed-return-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Foo
{
    public function baz()
    {
        return 'value'; // line 8
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 8, 'Return type of method Foo::baz() should be non-empty-string');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@phpstan-return non-empty-string', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSkipsWhenAnnotationAlreadyPresent(): void
    {
        $tempFile = sys_get_temp_dir() . '/prefixed-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Foo
{
    /**
     * @param string $class
     * @phpstan-param class-string<Foo> $class
     */
    public function bar($class)
    {
        return $class; // line 11
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 11, 'Parameter $class expects class-string<Foo>');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertFalse($result->isSuccessful());
            $this->assertStringContainsString('already exists', $result->getDescription() ?? '');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testCannotDetermineTypeReturnsFailure(): void
    {
        $tempFile = sys_get_temp_dir() . '/prefixed-fail-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Foo
{
    public function bar($class)
    {
        return $class; // line 7
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 7, 'Parameter $class expects string');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertFalse($result->isSuccessful());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}


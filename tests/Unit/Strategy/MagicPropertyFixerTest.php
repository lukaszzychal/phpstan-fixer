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
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\MagicPropertyFixer;
use PHPUnit\Framework\TestCase;

final class MagicPropertyFixerTest extends TestCase
{
    private MagicPropertyFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new MagicPropertyFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testAddsPropertyForMagicGetter(): void
    {
        $tempFile = sys_get_temp_dir() . '/magic-property-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Foo
{
    public function __get(string $name)
    {
        if ($name === 'bar') {
            return 123;
        }
        return null;
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                6,
                'Access to an undefined property Foo::$bar'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@property mixed $bar', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSkipsWhenPropertyExists(): void
    {
        $tempFile = sys_get_temp_dir() . '/magic-property-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

/**
 * @property mixed $bar
 */
class Foo
{
    public function __get(string $name)
    {
        if ($name === 'bar') {
            return 123;
        }
        return null;
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                11,
                'Access to an undefined property Foo::$bar'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertFalse($result->isSuccessful());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}


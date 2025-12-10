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
use PhpstanFixer\Strategy\RequireImplementsFixer;
use PHPUnit\Framework\TestCase;

final class RequireImplementsFixerTest extends TestCase
{
    private RequireImplementsFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new RequireImplementsFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testAddsRequireImplementsToTrait(): void
    {
        $tempFile = sys_get_temp_dir() . '/require-implements-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

trait FooTrait
{
    public function bar() {}
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                3,
                'Trait FooTrait requires implements FooContract'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@phpstan-require-implements FooContract', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFailsWhenAnnotationAlreadyExists(): void
    {
        $tempFile = sys_get_temp_dir() . '/require-implements-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

/**
 * @phpstan-require-implements FooContract
 */
trait FooTrait
{
    public function bar() {}
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                6,
                'Trait FooTrait requires implements FooContract'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertFalse($result->isSuccessful());
            $this->assertStringContainsString('already exists', $result->getDescription() ?? '');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testCanFixPattern(): void
    {
        $issue = new Issue(
            '/tmp/file.php',
            3,
            'Trait FooTrait requires implements FooContract'
        );

        $this->assertTrue($this->fixer->canFix($issue));
    }
}


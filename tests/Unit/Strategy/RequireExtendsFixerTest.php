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
use PhpstanFixer\Strategy\RequireExtendsFixer;
use PHPUnit\Framework\TestCase;

final class RequireExtendsFixerTest extends TestCase
{
    private RequireExtendsFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new RequireExtendsFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testAddsRequireExtendsToInterface(): void
    {
        $tempFile = sys_get_temp_dir() . '/require-extends-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

interface Foo
{
    public function bar();
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                3,
                'Interface Foo requires extends BaseContract'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@phpstan-require-extends BaseContract', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFailsWhenAnnotationAlreadyExists(): void
    {
        $tempFile = sys_get_temp_dir() . '/require-extends-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

/**
 * @phpstan-require-extends BaseContract
 */
interface Foo
{
    public function bar();
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                5,
                'Interface Foo requires extends BaseContract'
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
            'Trait Foo requires extend BaseContract'
        );

        $this->assertTrue($this->fixer->canFix($issue));
    }
}


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
use PhpstanFixer\Strategy\ImmutableClassFixer;
use PHPUnit\Framework\TestCase;

final class ImmutableClassFixerTest extends TestCase
{
    private ImmutableClassFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new ImmutableClassFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testAddsImmutableToClass(): void
    {
        $tempFile = sys_get_temp_dir() . '/immutable-class-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Foo
{
    public int $x;
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                4,
                'Class Foo is immutable and property $x is assigned outside'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@immutable', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSkipsWhenAlreadyImmutable(): void
    {
        $tempFile = sys_get_temp_dir() . '/immutable-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

/**
 * @immutable
 */
class Foo
{
    public int $x;
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                8,
                'Class Foo is immutable and property $x is assigned outside'
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


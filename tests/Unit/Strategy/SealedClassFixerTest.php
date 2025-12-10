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
use PhpstanFixer\Strategy\SealedClassFixer;
use PHPUnit\Framework\TestCase;

final class SealedClassFixerTest extends TestCase
{
    private SealedClassFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new SealedClassFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testAddsSealedAnnotation(): void
    {
        $tempFile = sys_get_temp_dir() . '/sealed-class-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Foo extends Base
{
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                3,
                'Class Foo extends sealed class Base'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@phpstan-sealed', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSkipsWhenAlreadySealed(): void
    {
        $tempFile = sys_get_temp_dir() . '/sealed-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

/**
 * @phpstan-sealed Base|Other
 */
class Foo extends Base
{
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                7,
                'Class Foo extends sealed class Base'
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


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
use PhpstanFixer\Strategy\InternalAnnotationFixer;
use PHPUnit\Framework\TestCase;

final class InternalAnnotationFixerTest extends TestCase
{
    private InternalAnnotationFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new InternalAnnotationFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testAddsInternalToClass(): void
    {
        $tempFile = sys_get_temp_dir() . '/internal-class-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Foo
{
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                3,
                'Access to internal class Foo'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@internal', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSkipsWhenAlreadyInternal(): void
    {
        $tempFile = sys_get_temp_dir() . '/internal-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

/**
 * @internal
 */
class Foo
{
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                7,
                'Access to internal class Foo'
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


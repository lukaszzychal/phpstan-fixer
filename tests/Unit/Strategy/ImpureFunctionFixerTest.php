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
use PhpstanFixer\Strategy\ImpureFunctionFixer;
use PHPUnit\Framework\TestCase;

final class ImpureFunctionFixerTest extends TestCase
{
    private ImpureFunctionFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new ImpureFunctionFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testAddsImpureAnnotationToMethod(): void
    {
        $tempFile = sys_get_temp_dir() . '/impure-annot-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Foo
{
    /**
     * @return int
     */
    public function random()
    {
        return rand(1, 10); // line 12
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 12, 'Function Foo::random() is impure (non-deterministic)');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@phpstan-impure', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testAddsPureAnnotationWhenSuggested(): void
    {
        $tempFile = sys_get_temp_dir() . '/pure-annot-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

function identity($value)
{
    return $value; // line 6
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 6, 'Function identity seems pure');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@phpstan-pure', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFailsWhenAnnotationAlreadyExists(): void
    {
        $tempFile = sys_get_temp_dir() . '/impure-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Foo
{
    /**
     * @phpstan-impure
     */
    public function random()
    {
        return rand(1, 10); // line 11
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 11, 'Function Foo::random() is impure');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertFalse($result->isSuccessful());
            $this->assertStringContainsString('already exists', $result->getDescription() ?? '');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testCanFixPureMessage(): void
    {
        $issue = new Issue('/tmp/file.php', 6, 'Function identity seems pure');

        $this->assertTrue($this->fixer->canFix($issue));
    }
}


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
use PhpstanFixer\Strategy\IterableValueTypeFixer;
use PHPUnit\Framework\TestCase;

final class IterableValueTypeFixerTest extends TestCase
{
    private IterableValueTypeFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new IterableValueTypeFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testAddsIterableValueType(): void
    {
        $tempFile = sys_get_temp_dir() . '/iterable-value-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

function process(iterable $items)
{
    foreach ($items as $item) { // line 5
        // use $item
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                5,
                'Missing iterable value type for $items'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@param iterable<mixed> $items', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSkipsWhenAlreadyGeneric(): void
    {
        $tempFile = sys_get_temp_dir() . '/iterable-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

/**
 * @param iterable<string> $items
 */
function process(iterable $items)
{
    foreach ($items as $item) { // line 10
        // use $item
    }
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                10,
                'Missing iterable value type for $items'
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


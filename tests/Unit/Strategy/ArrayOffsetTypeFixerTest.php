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
use PhpstanFixer\Strategy\ArrayOffsetTypeFixer;
use PHPUnit\Framework\TestCase;

final class ArrayOffsetTypeFixerTest extends TestCase
{
    private ArrayOffsetTypeFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new ArrayOffsetTypeFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testAddsGenericParamWhenMissingDocblock(): void
    {
        $tempFile = sys_get_temp_dir() . '/array-offset-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

function take(array $items)
{
    return $items[0]; // line 6
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                6,
                'Unknown array offset type on $items'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@param array<int, mixed> $items', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFailsWhenAlreadyGeneric(): void
    {
        $tempFile = sys_get_temp_dir() . '/array-offset-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

/**
 * @param array<int, mixed> $items
 */
function take(array $items)
{
    return $items[0]; // line 9
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                9,
                'Unknown array offset type on $items'
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


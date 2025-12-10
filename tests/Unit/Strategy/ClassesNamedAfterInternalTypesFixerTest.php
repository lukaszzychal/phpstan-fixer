<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Strategy;

use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\ClassesNamedAfterInternalTypesFixer;
use PHPUnit\Framework\TestCase;

final class ClassesNamedAfterInternalTypesFixerTest extends TestCase
{
    private ClassesNamedAfterInternalTypesFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new ClassesNamedAfterInternalTypesFixer();
    }

    public function testAdjustsInternalTypeInDocblock(): void
    {
        $tempFile = sys_get_temp_dir() . '/internal-type-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

/**
 * @param Resource $value
 */
function foo($value)
{
    return $value; // line 8
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                8,
                'Class Resource is internal'
            );

            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@param \\Resource $value', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testSkipsWhenAlreadyQualified(): void
    {
        $tempFile = sys_get_temp_dir() . '/internal-type-existing-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

/**
 * @param \Resource $value
 */
function foo($value)
{
    return $value; // line 9
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue(
                $tempFile,
                9,
                'Class Resource is internal'
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


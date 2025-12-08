<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Strategy;

use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Issue;
use PhpstanFixer\Strategy\ReadonlyPropertyFixer;
use PHPUnit\Framework\TestCase;

final class ReadonlyPropertyFixerTest extends TestCase
{
    private ReadonlyPropertyFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixer = new ReadonlyPropertyFixer(
            new PhpFileAnalyzer(),
            new DocblockManipulator()
        );
    }

    public function testCanFixReadonlyPattern(): void
    {
        $issue = new Issue(
            '/tmp/file.php',
            10,
            'Read-only property App\\User::$email is assigned outside of its declaring scope.'
        );

        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testFixAddsReadonlyToExistingDocblock(): void
    {
        $tempFile = sys_get_temp_dir() . '/readonly-fixer-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class User
{
    /**
     * @var string
     */
    private $email;
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 7, 'Read-only property User::$email is assigned outside of its declaring scope.');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString('@readonly', $result->getFixedContent());
            $this->assertStringContainsString('@var string', $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFixAddsDocblockWhenMissing(): void
    {
        $tempFile = sys_get_temp_dir() . '/readonly-fixer-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Token
{
    private string $value;
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 6, 'Read-only property Token::$value is assigned outside of its declaring scope.');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertTrue($result->isSuccessful());
            $this->assertStringContainsString("/**\n * @readonly\n */\n    private string \$value;", $result->getFixedContent());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFixFailsWhenReadonlyAlreadyPresent(): void
    {
        $tempFile = sys_get_temp_dir() . '/readonly-fixer-' . uniqid() . '.php';
        $fileContent = <<<'PHP'
<?php

class Example
{
    /**
     * @readonly
     */
    private int $id;
}
PHP;
        file_put_contents($tempFile, $fileContent);

        try {
            $issue = new Issue($tempFile, 8, 'Read-only property Example::$id is assigned outside of its declaring scope.');
            $result = $this->fixer->fix($issue, $fileContent);

            $this->assertFalse($result->isSuccessful());
            $this->assertStringContainsString('@readonly already exists', $result->getDescription() ?? '');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}


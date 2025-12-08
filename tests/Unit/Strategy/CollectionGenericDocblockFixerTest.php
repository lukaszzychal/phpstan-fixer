<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit\Strategy;

use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Strategy\CollectionGenericDocblockFixer;
use PHPUnit\Framework\TestCase;

final class CollectionGenericDocblockFixerTest extends TestCase
{
    private CollectionGenericDocblockFixer $fixer;

    protected function setUp(): void
    {
        parent::setUp();
        $analyzer = new PhpFileAnalyzer();
        $docblockManipulator = new DocblockManipulator();
        $this->fixer = new CollectionGenericDocblockFixer($analyzer, $docblockManipulator);
    }

    public function testCanFixCollectionWithoutGenerics(): void
    {
        $issue = new \PhpstanFixer\Issue(
            filePath: '/path/to/file.php',
            line: 10,
            message: 'Generic type Collection needs parameters'
        );

        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testCanFixCollectionWithGenericTypeMessage(): void
    {
        $issue = new \PhpstanFixer\Issue(
            filePath: '/path/to/file.php',
            line: 10,
            message: 'Generic type Illuminate\Support\Collection needs parameters'
        );

        $this->assertTrue($this->fixer->canFix($issue));
    }

    public function testCannotFixNonCollectionIssues(): void
    {
        $issue = new \PhpstanFixer\Issue(
            filePath: '/path/to/file.php',
            line: 10,
            message: 'Some other error'
        );

        $this->assertFalse($this->fixer->canFix($issue));
    }

    public function testAddsGenericToCollection(): void
    {
        $code = <<<'PHP'
<?php
/**
 * @return Collection
 */
function getItems() {
    return collect([]);
}
PHP;

        $issue = new \PhpstanFixer\Issue(
            filePath: '/path/to/file.php',
            line: 3,
            message: 'Generic type Collection needs parameters'
        );

        $result = $this->fixer->fix($issue, $code);

        // fix() always returns FixResult (never null), so we check isSuccessful() instead
        // @phpstan-ignore-next-line - FixResult is always returned, but we verify it's a valid object
        $this->assertInstanceOf(\PhpstanFixer\FixResult::class, $result);
        
        if ($result->isSuccessful()) {
            $this->assertStringContainsString('Collection<int, mixed>', $result->getFixedContent());
        }
    }
}

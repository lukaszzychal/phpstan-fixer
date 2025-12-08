<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


declare(strict_types=1);

namespace PhpstanFixer\Tests\Feature;

use PhpstanFixer\AutoFixService;
use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Issue;
use PhpstanFixer\PhpstanLogParser;
use PhpstanFixer\Strategy\CallableTypeFixer;
use PhpstanFixer\Strategy\CollectionGenericDocblockFixer;
use PhpstanFixer\Strategy\MissingParamDocblockFixer;
use PhpstanFixer\Strategy\MissingPropertyDocblockFixer;
use PhpstanFixer\Strategy\MissingReturnDocblockFixer;
use PhpstanFixer\Strategy\MissingThrowsDocblockFixer;
use PhpstanFixer\Strategy\MissingUseStatementFixer;
use PhpstanFixer\Strategy\UndefinedMethodFixer;
use PhpstanFixer\Strategy\UndefinedPivotPropertyFixer;
use PhpstanFixer\Strategy\UndefinedVariableFixer;
use PHPUnit\Framework\TestCase;

final class EndToEndFixTest extends TestCase
{
    private AutoFixService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        $analyzer = new PhpFileAnalyzer();
        $docblockManipulator = new DocblockManipulator();

        $strategies = [
            new MissingReturnDocblockFixer($analyzer, $docblockManipulator),
            new MissingParamDocblockFixer($analyzer, $docblockManipulator),
            new MissingPropertyDocblockFixer($analyzer, $docblockManipulator),
            new CollectionGenericDocblockFixer($analyzer, $docblockManipulator),
            new UndefinedPivotPropertyFixer($analyzer, $docblockManipulator),
            new UndefinedVariableFixer(),
            new MissingUseStatementFixer($analyzer),
            new UndefinedMethodFixer($analyzer, $docblockManipulator),
            new MissingThrowsDocblockFixer($analyzer, $docblockManipulator),
            new CallableTypeFixer($analyzer, $docblockManipulator),
        ];

        $this->service = new AutoFixService($strategies);
    }

    public function testServiceGroupsIssuesByFile(): void
    {
        $issues = [
            new Issue('/path/to/file1.php', 10, 'Error 1'),
            new Issue('/path/to/file1.php', 20, 'Error 2'),
            new Issue('/path/to/file2.php', 15, 'Error 3'),
        ];

        $grouped = $this->service->groupIssuesByFile($issues);

        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped['/path/to/file1.php']);
        $this->assertCount(1, $grouped['/path/to/file2.php']);
    }

    public function testServiceReturnsUnfixedIssues(): void
    {
        // Create results structure
        $results = [
            '/path/to/file.php' => [
                'unfixedIssues' => [
                    new Issue('/path/to/file.php', 10, 'Cannot fix this'),
                ],
            ],
        ];

        $unfixed = $this->service->getUnfixedIssues($results);

        $this->assertCount(1, $unfixed);
        $this->assertSame('Cannot fix this', $unfixed[0]->getMessage());
    }

    public function testParseRealPHPStanOutput(): void
    {
        $json = file_get_contents(__DIR__ . '/../Fixtures/phpstan-basic.json');
        
        $parser = new PhpstanLogParser();
        $issues = $parser->parse($json, false);

        $this->assertNotEmpty($issues);
        $this->assertCount(2, $issues);

        // Check that issues are properly parsed
        $this->assertSame('Access to an undefined property $foo', $issues[0]->getMessage());
        $this->assertSame('Method has no return type specified', $issues[1]->getMessage());
    }
}


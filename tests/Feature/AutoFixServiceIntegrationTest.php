<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>

declare(strict_types=1);

namespace PhpstanFixer\Tests\Feature;

use PhpstanFixer\AutoFixService;
use PhpstanFixer\CodeAnalysis\DocblockManipulator;
use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\Issue;
use PhpstanFixer\PhpstanLogParser;
use PhpstanFixer\Strategy\MissingReturnDocblockFixer;
use PHPUnit\Framework\TestCase;

final class AutoFixServiceIntegrationTest extends TestCase
{
    public function testParseAndProcessRealPHPStanOutput(): void
    {
        $json = file_get_contents(__DIR__ . '/../Fixtures/phpstan-basic.json');
        
        $parser = new PhpstanLogParser();
        $issues = $parser->parse($json, false);

        $this->assertNotEmpty($issues);
        $this->assertCount(2, $issues);
    }

    public function testServiceWithRealFixer(): void
    {
        $analyzer = new PhpFileAnalyzer();
        $docblockManipulator = new DocblockManipulator();
        
        $fixer = new MissingReturnDocblockFixer($analyzer, $docblockManipulator);
        $service = new AutoFixService([$fixer]);

        // Create a test issue
        $testFile = __DIR__ . '/../Fixtures/php/missing-return.php';
        $fileContent = file_get_contents($testFile);
        
        $issue = new Issue($testFile, 3, 'Function has no return type specified');
        
        $result = $service->fixIssue($issue, $fileContent);
        
        $this->assertNotNull($result);
        // Note: Actual fix testing would require more complex setup with real file system
    }
}


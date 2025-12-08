<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>

declare(strict_types=1);

namespace PhpstanFixer\Tests\Unit;

use PhpstanFixer\Issue;
use PhpstanFixer\PhpstanLogParser;
use PHPUnit\Framework\TestCase;

final class PhpstanLogParserTest extends TestCase
{
    private PhpstanLogParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PhpstanLogParser();
    }

    public function testParseStandardFormat(): void
    {
        $json = json_encode([
            'files' => [
                '/path/to/file.php' => [
                    'messages' => [
                        [
                            'message' => 'Access to an undefined property $foo',
                            'line' => 42,
                            'ignorable' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $issues = $this->parser->parse($json, false);

        $this->assertCount(1, $issues);
        $this->assertInstanceOf(Issue::class, $issues[0]);
        $this->assertSame('/path/to/file.php', $issues[0]->getFilePath());
        $this->assertSame(42, $issues[0]->getLine());
        $this->assertStringContainsString('undefined property', $issues[0]->getMessage());
    }

    public function testParseEmptyOutput(): void
    {
        $json = json_encode([
            'files' => [],
        ]);

        $issues = $this->parser->parse($json, false);

        $this->assertEmpty($issues);
    }

    public function testParseMultipleFiles(): void
    {
        $json = json_encode([
            'files' => [
                '/path/to/file1.php' => [
                    'messages' => [
                        [
                            'message' => 'Error in file 1',
                            'line' => 10,
                        ],
                    ],
                ],
                '/path/to/file2.php' => [
                    'messages' => [
                        [
                            'message' => 'Error in file 2',
                            'line' => 20,
                        ],
                        [
                            'message' => 'Another error',
                            'line' => 30,
                        ],
                    ],
                ],
            ],
        ]);

        $issues = $this->parser->parse($json, false);

        $this->assertCount(3, $issues);
    }

    public function testParseWithErrorCode(): void
    {
        $json = json_encode([
            'files' => [
                '/path/to/file.php' => [
                    'messages' => [
                        [
                            'message' => 'Some error',
                            'line' => 5,
                            'identifier' => 'phpstan.error.code',
                        ],
                    ],
                ],
            ],
        ]);

        $issues = $this->parser->parse($json, false);

        $this->assertCount(1, $issues);
        $this->assertSame('phpstan.error.code', $issues[0]->getErrorCode());
    }

    public function testInvalidJsonThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse('invalid json', false);
    }

    public function testGetTotalErrors(): void
    {
        $json = json_encode([
            'totals' => [
                'file_errors' => 5,
            ],
            'files' => [
                '/path/to/file.php' => [
                    'messages' => [
                        ['message' => 'Error 1', 'line' => 1],
                        ['message' => 'Error 2', 'line' => 2],
                    ],
                ],
            ],
        ]);

        $data = json_decode($json, true);
        $totalErrors = $this->parser->getTotalErrors($data);

        $this->assertSame(5, $totalErrors);
    }
}


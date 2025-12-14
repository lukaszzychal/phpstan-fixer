<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Strategy;

use PhpstanFixer\CodeAnalysis\PhpFileAnalyzer;
use PhpstanFixer\FixResult;
use PhpstanFixer\Issue;
use PhpParser\Node\Stmt;

/**
 * Trait providing file validation and AST parsing methods.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
trait FileValidationTrait
{
    /**
     * Validate file exists and parse AST.
     * Returns FixResult on failure, or null on success (to allow chaining).
     *
     * @param Issue $issue The issue being fixed
     * @param string $fileContent Current file content
     * @param PhpFileAnalyzer $analyzer Parser instance
     * @return array{ast: array<Stmt>, analyzer: PhpFileAnalyzer}|FixResult Returns array with ast and analyzer on success, FixResult on failure
     */
    protected function validateFileAndParse(Issue $issue, string $fileContent, PhpFileAnalyzer $analyzer): array|FixResult
    {
        if (!file_exists($issue->getFilePath())) {
            return FixResult::failure($issue, $fileContent, 'File does not exist');
        }

        $ast = $analyzer->parse($fileContent);
        if ($ast === null) {
            return FixResult::failure($issue, $fileContent, 'Could not parse file');
        }

        return ['ast' => $ast, 'analyzer' => $analyzer];
    }
}


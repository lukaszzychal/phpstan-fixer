<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer;

use JsonException;

/**
 * Parses PHPStan JSON output into Issue objects.
 *
 * @author Łukasz Zychal <lukasz@zychal.pl>
 */
final class PhpstanLogParser
{
    /**
     * Parse PHPStan JSON output from a file or string.
     *
     * @param string $jsonContent Path to JSON file or JSON string content
     * @param bool $isPath Whether the input is a file path (true) or JSON content (false)
     * @return Issue[]
     * @throws \InvalidArgumentException If JSON is invalid or file cannot be read
     */
    public function parse(string $jsonContent, bool $isPath = true): array
    {
        if ($isPath) {
            if (!file_exists($jsonContent)) {
                throw new \InvalidArgumentException("PHPStan JSON file not found: {$jsonContent}");
            }
            $content = file_get_contents($jsonContent);
            if ($content === false) {
                throw new \InvalidArgumentException("Could not read PHPStan JSON file: {$jsonContent}");
            }
        } else {
            $content = $jsonContent;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new \InvalidArgumentException("Invalid JSON in PHPStan output: " . $e->getMessage(), 0, $e);
        }

        return $this->extractIssues($data);
    }

    /**
     * Extract Issue objects from parsed JSON data.
     *
     * @param array<string, mixed> $data Parsed JSON data
     * @return Issue[]
     */
    private function extractIssues(array $data): array
    {
        $issues = [];

        // Handle different JSON formats from PHPStan
        if (isset($data['files']) && is_array($data['files'])) {
            // Standard format: { "files": { "/path/file.php": { "messages": [...] } } }
            foreach ($data['files'] as $filePath => $fileData) {
                if (!is_array($fileData)) {
                    continue;
                }

                $messages = $fileData['messages'] ?? [];
                if (!is_array($messages)) {
                    continue;
                }

                foreach ($messages as $messageData) {
                    if (!is_array($messageData)) {
                        continue;
                    }

                    $issue = $this->createIssueFromMessage($filePath, $messageData);
                    if ($issue !== null) {
                        $issues[] = $issue;
                    }
                }
            }
        } elseif (isset($data['messages']) && is_array($data['messages'])) {
            // Alternative format: direct messages array (if file path is in each message)
            foreach ($data['messages'] as $messageData) {
                if (!is_array($messageData)) {
                    continue;
                }

                $filePath = $messageData['file'] ?? null;
                if ($filePath === null || !is_string($filePath)) {
                    continue;
                }

                $issue = $this->createIssueFromMessage($filePath, $messageData);
                if ($issue !== null) {
                    $issues[] = $issue;
                }
            }
        }

        return $issues;
    }

    /**
     * Create an Issue from a message data array.
     *
     * @param string $filePath Path to the file
     * @param array<string, mixed> $messageData Message data from JSON
     * @return Issue|null Returns null if required data is missing
     */
    private function createIssueFromMessage(string $filePath, array $messageData): ?Issue
    {
        $message = $messageData['message'] ?? null;
        if ($message === null || !is_string($message)) {
            return null;
        }

        $line = $messageData['line'] ?? null;
        if ($line === null || !is_numeric($line)) {
            return null;
        }

        // Normalize file path
        $normalizedPath = $this->normalizePath($filePath);

        return new Issue(
            filePath: $normalizedPath,
            line: (int) $line,
            message: $message,
            errorCode: $messageData['identifier'] ?? $messageData['errorCode'] ?? null,
            identifier: $messageData['identifier'] ?? null,
            column: isset($messageData['column']) && is_numeric($messageData['column'])
                ? (int) $messageData['column']
                : null
        );
    }

    /**
     * Normalize file path to absolute path.
     */
    private function normalizePath(string $path): string
    {
        // Convert relative paths to absolute if possible
        if (!file_exists($path)) {
            return $path;
        }

        $realPath = realpath($path);
        return $realPath !== false ? $realPath : $path;
    }

    /**
     * Get total error count from parsed JSON data.
     *
     * @param array<string, mixed> $data Parsed JSON data
     * @return int Total number of errors
     */
    public function getTotalErrors(array $data): int
    {
        if (isset($data['totals']['file_errors']) && is_numeric($data['totals']['file_errors'])) {
            return (int) $data['totals']['file_errors'];
        }

        // Count manually if totals not available
        $count = 0;
        if (isset($data['files']) && is_array($data['files'])) {
            foreach ($data['files'] as $fileData) {
                if (is_array($fileData) && isset($fileData['messages']) && is_array($fileData['messages'])) {
                    $count += count($fileData['messages']);
                }
            }
        }

        return $count;
    }
}


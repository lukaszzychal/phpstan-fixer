<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\CodeAnalysis;

/**
 * Utility for manipulating PHPDoc blocks in PHP code.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
final class DocblockManipulator
{
    /**
     * Extract existing PHPDoc block from lines before a given line number.
     *
     * @param string[] $lines Array of file lines
     * @param int $lineIndex Index of the line (0-based) to check for docblock above
     * @return array<string, mixed>|null Returns docblock info or null if not found
     *                                    Format: ['content' => string, 'startLine' => int, 'endLine' => int]
     */
    public function extractDocblock(array $lines, int $lineIndex): ?array
    {
        if ($lineIndex < 1 || $lineIndex >= count($lines)) {
            return null;
        }

        // Look backwards from the target line for a docblock
        $docblockStart = null;
        $docblockEnd = null;

        for ($i = $lineIndex - 1; $i >= 0; $i--) {
            $rawLine = $lines[$i];
            $line = trim($rawLine);

            // Found closing of docblock
            if ($line === '*/') {
                $docblockEnd = $i;
                continue;
            }

            // Found opening of docblock
            if ($line === '/**' || str_starts_with($line, '/**')) {
                $docblockStart = $i;
                break;
            }

            // If we hit a non-empty line that's not part of docblock, stop
            // Check raw line for ' *' pattern (not trimmed, as trim removes the space)
            if ($docblockEnd !== null && $line !== '' && !str_starts_with(trim($rawLine, " \t"), '*')) {
                break;
            }

            // If we're in a docblock but haven't found start, continue
            // Check if line starts with '*' (docblock content line)
            if ($docblockEnd !== null && (str_starts_with(trim($rawLine, " \t"), '*') || $line === '')) {
                continue;
            }

            // Not a docblock line
            if ($docblockEnd === null && !str_starts_with(trim($rawLine, " \t"), '*') && $line !== '') {
                break;
            }
        }

        if ($docblockStart === null || $docblockEnd === null) {
            return null;
        }

        $content = implode("\n", array_slice($lines, $docblockStart, $docblockEnd - $docblockStart + 1));

        return [
            'content' => $content,
            'startLine' => $docblockStart,
            'endLine' => $docblockEnd,
        ];
    }

    /**
     * Parse PHPDoc content into structured annotations.
     *
     * @param string $docblockContent PHPDoc content (with opening and closing markers)
     * @return array<string, array<int, array<string, mixed>>> Parsed annotations by type
     */
    public function parseDocblock(string $docblockContent): array
    {
        $annotations = [
            'param' => [],
            'return' => [],
            'var' => [],
            'throws' => [],
            'property' => [],
            'property-read' => [],
            'property-write' => [],
            'method' => [],
            'mixin' => [],
            'param-immediately-invoked-callable' => [],
            'param-later-invoked-callable' => [],
            'phpstan-param' => [],
            'phpstan-return' => [],
            'phpstan-impure' => [],
            'phpstan-pure' => [],
            'phpstan-require-extends' => [],
            'phpstan-require-implements' => [],
            'phpstan-sealed' => [],
        ];

        // Remove /** and */ markers
        $content = preg_replace('/^\s*\/\*\*\s*/', '', $docblockContent);
        $content = preg_replace('/\s*\*\/\s*$/', '', $content);

        // Split into lines and parse
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\s*\*\s*/', '', $line);

            if ($line === '' || str_starts_with($line, '@')) {
                // Parse annotation (value is optional)
                if (preg_match('/^@(\w+(?:-\w+)*)(?:\s+(.+))?$/', $line, $matches)) {
                    $tag = $matches[1];
                    $value = $matches[2] ?? '';

                    if (isset($annotations[$tag])) {
                        $annotations[$tag][] = $this->parseAnnotationValue($tag, $value);
                    }
                }
            }
        }

        return $annotations;
    }

    /**
     * Parse annotation value based on tag type.
     *
     * @param string $tag Annotation tag
     * @param string $value Annotation value
     * @return array<string, mixed> Parsed annotation data
     */
    private function parseAnnotationValue(string $tag, string $value): array
    {
        return match ($tag) {
            'param', 'phpstan-param' => $this->parseParamAnnotation($value),
            'var' => $this->parseVarAnnotation($value),
            'return', 'phpstan-return' => $this->parseReturnAnnotation($value),
            'throws' => $this->parseThrowsAnnotation($value),
            'property', 'property-read', 'property-write' => $this->parsePropertyAnnotation($value),
            'method' => $this->parseMethodAnnotation($value),
            'mixin' => $this->parseMixinAnnotation($value),
            'phpstan-impure', 'phpstan-pure' => ['flag' => true],
            'phpstan-require-extends' => $this->parseClassNameAnnotation($value, 'className'),
            'phpstan-require-implements' => $this->parseClassNameAnnotation($value, 'className'),
            'phpstan-sealed' => $this->parseSealedAnnotation($value),
            default => ['raw' => $value],
        };
    }

    /**
     * Parse @param or @phpstan-param annotation.
     *
     * @param string $value Annotation value
     * @return array<string, mixed>
     */
    private function parseParamAnnotation(string $value): array
    {
        // @param Type $name Description
        if (preg_match('/^([^\s$]+)\s+(\$\w+)(?:\s+(.+))?$/', $value, $matches)) {
            return [
                'type' => $matches[1],
                'name' => $matches[2],
                'description' => $matches[3] ?? null,
            ];
        }

        return ['raw' => $value];
    }

    /**
     * Parse @var annotation.
     *
     * @param string $value Annotation value
     * @return array<string, mixed>
     */
    private function parseVarAnnotation(string $value): array
    {
        // @var Type $name or @var Type
        if (preg_match('/^([^\s$]+)(?:\s+(\$\w+))?(?:\s+(.+))?$/', $value, $matches)) {
            return [
                'type' => $matches[1],
                'name' => $matches[2] ?? null,
                'description' => $matches[3] ?? null,
            ];
        }

        return ['raw' => $value];
    }

    /**
     * Parse @return or @phpstan-return annotation.
     *
     * @param string $value Annotation value
     * @return array<string, mixed>
     */
    private function parseReturnAnnotation(string $value): array
    {
        // @return Type Description
        if (preg_match('/^([^\s]+)(?:\s+(.+))?$/', $value, $matches)) {
            return [
                'type' => $matches[1],
                'description' => $matches[2] ?? null,
            ];
        }

        return ['raw' => $value];
    }

    /**
     * Parse @throws annotation.
     *
     * @param string $value Annotation value
     * @return array<string, mixed>
     */
    private function parseThrowsAnnotation(string $value): array
    {
        // @throws ExceptionType Description
        if (preg_match('/^([^\s]+)(?:\s+(.+))?$/', $value, $matches)) {
            return [
                'exception' => $matches[1],
                'description' => $matches[2] ?? null,
            ];
        }

        return ['raw' => $value];
    }

    /**
     * Parse @property, @property-read, or @property-write annotation.
     *
     * @param string $value Annotation value
     * @return array<string, mixed>
     */
    private function parsePropertyAnnotation(string $value): array
    {
        // @property Type $name Description
        if (preg_match('/^([^\s$]+)\s+(\$\w+)(?:\s+(.+))?$/', $value, $matches)) {
            return [
                'type' => $matches[1],
                'name' => $matches[2],
                'description' => $matches[3] ?? null,
            ];
        }

        return ['raw' => $value];
    }

    /**
     * Parse @method annotation.
     *
     * @param string $value Annotation value
     * @return array<string, mixed>
     */
    private function parseMethodAnnotation(string $value): array
    {
        // @method ReturnType methodName(Type $param) Description
        if (preg_match('/^(?:(static)\s+)?([^\s(]+)\s+(\w+)\s*\(([^)]*)\)(?:\s+(.+))?$/', $value, $matches)) {
            return [
                'static' => $matches[1] === 'static',
                'returnType' => $matches[2],
                'name' => $matches[3],
                'parameters' => $matches[4],
                'description' => $matches[5] ?? null,
            ];
        }

        return ['raw' => $value];
    }

    /**
     * Parse @mixin annotation.
     *
     * @param string $value Annotation value
     * @return array<string, mixed>
     */
    private function parseMixinAnnotation(string $value): array
    {
        // @mixin ClassName
        if (preg_match('/^([\\\\\w]+)(?:\s+(.+))?$/', $value, $matches)) {
            return [
                'className' => $matches[1],
                'description' => $matches[2] ?? null,
            ];
        }

        return ['raw' => $value];
    }

    /**
     * Parse annotation with class name (e.g., @phpstan-require-extends, @phpstan-require-implements).
     *
     * @param string $value Annotation value
     * @param string $key Key name for class name in result array
     * @return array<string, mixed>
     */
    private function parseClassNameAnnotation(string $value, string $key): array
    {
        // @phpstan-require-extends ClassName or @phpstan-require-implements InterfaceName
        if (preg_match('/^([\\\\\w]+)(?:\s+(.+))?$/', $value, $matches)) {
            return [
                $key => $matches[1],
                'description' => $matches[2] ?? null,
            ];
        }

        return ['raw' => $value];
    }

    /**
     * Parse @phpstan-sealed annotation.
     *
     * @param string $value Annotation value
     * @return array<string, mixed>
     */
    private function parseSealedAnnotation(string $value): array
    {
        // @phpstan-sealed Class1|Class2
        if (preg_match('/^([\\\\\w|]+)(?:\s+(.+))?$/', $value, $matches)) {
            return [
                'classList' => $matches[1],
                'description' => $matches[2] ?? null,
            ];
        }

        return ['raw' => $value];
    }

    /**
     * Check if a PHPDoc block has a specific annotation.
     *
     * @param string $docblockContent PHPDoc content
     * @param string $annotationType Annotation type (e.g., 'param', 'return')
     * @param string|null $name Optional name to match (for @param, @var, etc.)
     * @return bool True if annotation exists
     */
    public function hasAnnotation(string $docblockContent, string $annotationType, ?string $name = null): bool
    {
        $annotations = $this->parseDocblock($docblockContent);

        if (!isset($annotations[$annotationType])) {
            return false;
        }

        if ($name === null) {
            return !empty($annotations[$annotationType]);
        }

        foreach ($annotations[$annotationType] as $annotation) {
            if (isset($annotation['name']) && $annotation['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add an annotation to a PHPDoc block.
     *
     * @param string $docblockContent Existing PHPDoc content
     * @param string $annotationType Type of annotation (e.g., 'param', 'return')
     * @param string $annotationValue Annotation value (e.g., 'mixed $param')
     * @return string Updated PHPDoc content
     */
    public function addAnnotation(string $docblockContent, string $annotationType, string $annotationValue): string
    {
        $annotationValue = trim($annotationValue);
        $annotationLine = $annotationValue === ''
            ? "@{$annotationType}"
            : "@{$annotationType} {$annotationValue}";

        // If no docblock exists, create one
        if (!str_contains($docblockContent, '/**')) {
            $docblock = "/**\n * {$annotationLine}\n */";
            return $docblock;
        }

        // Parse existing docblock
        $lines = explode("\n", $docblockContent);
        $newAnnotation = " * {$annotationLine}";

        // Find insertion point (before closing */)
        $insertIndex = count($lines) - 1;
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (trim($lines[$i]) === '*/') {
                $insertIndex = $i;
                break;
            }
        }

        array_splice($lines, $insertIndex, 0, $newAnnotation);

        return implode("\n", $lines);
    }

    /**
     * Create a new PHPDoc block with annotations.
     *
     * @param array<string, array<string>> $annotations Array of ['type' => ['value1', 'value2']]
     * @param string|null $description Optional description
     * @return string PHPDoc block
     */
    public function createDocblock(array $annotations, ?string $description = null): string
    {
        $lines = ['/**'];

        if ($description !== null) {
            $lines[] = ' * ' . $description;
            $lines[] = ' *';
        }

        // Add annotations in a consistent order
        $order = [
            'param',
            'phpstan-param',
            'param-immediately-invoked-callable',
            'param-later-invoked-callable',
            'return',
            'phpstan-return',
            'phpstan-impure',
            'phpstan-pure',
            'phpstan-require-extends',
            'phpstan-require-implements',
            'phpstan-sealed',
            'throws',
            'var',
            'property',
            'property-read',
            'property-write',
            'method'
        ];

        foreach ($order as $type) {
            if (isset($annotations[$type])) {
                foreach ($annotations[$type] as $value) {
                    $line = ' * @' . $type;
                    if ($value !== '') {
                        $line .= ' ' . $value;
                    }
                    $lines[] = $line;
                }
            }
        }

        $lines[] = ' */';

        return implode("\n", $lines);
    }

    /**
     * Format a PHPDoc block nicely.
     *
     * @param string $docblockContent PHPDoc content
     * @return string Formatted PHPDoc
     */
    public function formatDocblock(string $docblockContent): string
    {
        $annotations = $this->parseDocblock($docblockContent);
        $description = $this->extractDescription($docblockContent);

        $formatted = [];
        foreach ($annotations as $type => $values) {
            foreach ($values as $value) {
                if (isset($value['raw'])) {
                    $formatted[$type][] = $value['raw'];
                } else {
                    // Reconstruct from parsed values
                    $formatted[$type][] = $this->reconstructAnnotation($type, $value);
                }
            }
        }

        return $this->createDocblock($formatted, $description);
    }

    /**
     * Extract description from PHPDoc (text before annotations).
     */
    private function extractDescription(string $docblockContent): ?string
    {
        $content = preg_replace('/^\s*\/\*\*\s*/', '', $docblockContent);
        $content = preg_replace('/\s*\*\/\s*$/', '', $content);
        $lines = explode("\n", $content);

        $descriptionLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\s*\*\s*/', '', $line);

            if (str_starts_with($line, '@')) {
                break;
            }

            if ($line !== '') {
                $descriptionLines[] = $line;
            }
        }

        return !empty($descriptionLines) ? implode(' ', $descriptionLines) : null;
    }

    /**
     * Reconstruct annotation string from parsed data.
     */
    private function reconstructAnnotation(string $type, array $data): string
    {
        return match ($type) {
            'param', 'phpstan-param' => $this->reconstructParamAnnotation($data),
            'return', 'phpstan-return' => $this->reconstructReturnAnnotation($data),
            'var' => $this->reconstructVarAnnotation($data),
            'property', 'property-read', 'property-write' => $this->reconstructPropertyAnnotation($data),
            'throws' => $this->reconstructThrowsAnnotation($data),
            'method' => $this->reconstructMethodAnnotation($data),
            'mixin' => $this->reconstructMixinAnnotation($data),
            'phpstan-impure', 'phpstan-pure' => '',
            'phpstan-require-extends' => $this->reconstructClassNameAnnotation($data, 'className'),
            'phpstan-require-implements' => $this->reconstructClassNameAnnotation($data, 'className'),
            'phpstan-sealed' => $this->reconstructSealedAnnotation($data),
            default => $data['raw'] ?? '',
        };
    }

    /**
     * Reconstruct @param or @phpstan-param annotation string.
     *
     * @param array<string, mixed> $data Parsed annotation data
     * @return string
     */
    private function reconstructParamAnnotation(array $data): string
    {
        $result = ($data['type'] ?? 'mixed') . ' ' . ($data['name'] ?? '');
        if (isset($data['description'])) {
            $result .= ' ' . $data['description'];
        }
        return $result;
    }

    /**
     * Reconstruct @return or @phpstan-return annotation string.
     *
     * @param array<string, mixed> $data Parsed annotation data
     * @return string
     */
    private function reconstructReturnAnnotation(array $data): string
    {
        $result = $data['type'] ?? 'mixed';
        if (isset($data['description'])) {
            $result .= ' ' . $data['description'];
        }
        return $result;
    }

    /**
     * Reconstruct @var annotation string.
     *
     * @param array<string, mixed> $data Parsed annotation data
     * @return string
     */
    private function reconstructVarAnnotation(array $data): string
    {
        $result = $data['type'] ?? 'mixed';
        if (isset($data['name'])) {
            $result .= ' ' . $data['name'];
        }
        if (isset($data['description'])) {
            $result .= ' ' . $data['description'];
        }
        return $result;
    }

    /**
     * Reconstruct @property, @property-read, or @property-write annotation string.
     *
     * @param array<string, mixed> $data Parsed annotation data
     * @return string
     */
    private function reconstructPropertyAnnotation(array $data): string
    {
        $result = ($data['type'] ?? 'mixed') . ' ' . ($data['name'] ?? '');
        if (isset($data['description'])) {
            $result .= ' ' . $data['description'];
        }
        return $result;
    }

    /**
     * Reconstruct class name annotation string (e.g., @phpstan-require-extends, @phpstan-require-implements).
     *
     * @param array<string, mixed> $data Parsed annotation data
     * @param string $key Key name for class name in data array
     * @return string
     */
    private function reconstructClassNameAnnotation(array $data, string $key): string
    {
        $result = $data[$key] ?? '';
        if (isset($data['description'])) {
            $result .= ' ' . $data['description'];
        }
        return trim($result);
    }

    /**
     * Reconstruct @phpstan-sealed annotation string.
     *
     * @param array<string, mixed> $data Parsed annotation data
     * @return string
     */
    private function reconstructSealedAnnotation(array $data): string
    {
        $result = $data['classList'] ?? '';
        if (isset($data['description'])) {
            $result .= ' ' . $data['description'];
        }
        return trim($result);
    }

    /**
     * Reconstruct @throws annotation string.
     *
     * @param array<string, mixed> $data Parsed annotation data
     * @return string
     */
    private function reconstructThrowsAnnotation(array $data): string
    {
        $result = $data['exception'] ?? '';
        if (isset($data['description'])) {
            $result .= ' ' . $data['description'];
        }
        return trim($result);
    }

    /**
     * Reconstruct @method annotation string.
     *
     * @param array<string, mixed> $data Parsed annotation data
     * @return string
     */
    private function reconstructMethodAnnotation(array $data): string
    {
        $result = '';
        if (isset($data['static']) && $data['static']) {
            $result .= 'static ';
        }
        $result .= $data['returnType'] ?? 'void';
        if (isset($data['name'])) {
            $result .= ' ' . $data['name'];
        }
        $parameters = $data['parameters'] ?? '';
        $result .= '(' . $parameters . ')';
        if (isset($data['description'])) {
            $result .= ' ' . $data['description'];
        }
        return trim($result);
    }

    /**
     * Reconstruct @mixin annotation string.
     *
     * @param array<string, mixed> $data Parsed annotation data
     * @return string
     */
    private function reconstructMixinAnnotation(array $data): string
    {
        $result = $data['className'] ?? '';
        if (isset($data['description'])) {
            $result .= ' ' . $data['description'];
        }
        return trim($result);
    }
}

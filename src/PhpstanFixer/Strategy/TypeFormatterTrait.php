<?php

/**
 * Copyright (c) 2025 Łukasz Zychal
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PhpstanFixer\Strategy;

/**
 * Trait providing a method to format PHP-Parser type nodes to strings.
 *
 * @author Łukasz Zychal <lukasz.zychal.dev@gmail.com>
 */
trait TypeFormatterTrait
{
    /**
     * Format a PHP-Parser type node to string.
     *
     * @param \PhpParser\Node\Name|\PhpParser\Node\Identifier|\PhpParser\Node\NullableType|\PhpParser\Node\UnionType|string $typeNode
     * @return string Formatted type string
     */
    private function formatType($typeNode): string
    {
        if (is_string($typeNode)) {
            return $typeNode;
        }

        if ($typeNode instanceof \PhpParser\Node\Name) {
            return $typeNode->toString();
        }

        if ($typeNode instanceof \PhpParser\Node\Identifier) {
            return $typeNode->name;
        }

        if ($typeNode instanceof \PhpParser\Node\NullableType) {
            return '?' . $this->formatType($typeNode->type);
        }

        if ($typeNode instanceof \PhpParser\Node\UnionType) {
            return implode('|', array_map([$this, 'formatType'], $typeNode->types));
        }

        return 'mixed';
    }
}


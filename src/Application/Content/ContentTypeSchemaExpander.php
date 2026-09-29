<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Mcp\Application\Content;

use Mcp\Schema\Tool;

/**
 * Fills the resourceKey placeholders of tool and resource metadata with the resourceKeys that
 * are registered at runtime. A description or an `enum` cannot list them statically, because a
 * bundle plugs in its own type. The MCP registry applies this when it hands out a tool.
 *
 * Use {@see self::RESOURCE_KEYS} for the search and preview tools, which skip content types
 * marked {@see \Sulu\Mcp\Domain\Content\NotSearchableContentTypeInterface}, and
 * {@see self::CONTENT_RESOURCE_KEYS} for the content and block tools, which cover every type.
 *
 * @phpstan-import-type ToolInputSchema from Tool
 *
 * @internal
 */
final readonly class ContentTypeSchemaExpander
{
    /**
     * Expands to the searchable resourceKeys, e.g. `"pages", "articles"`, and as a single
     * `enum` entry to one value per key.
     */
    public const RESOURCE_KEYS = '{resourceKeys}';

    /**
     * Like {@see self::RESOURCE_KEYS}, but for every registered content type, snippets included.
     */
    public const CONTENT_RESOURCE_KEYS = '{contentResourceKeys}';

    public function __construct(
        private ContentTypeExtensionRegistry $extensionRegistry,
    ) {
    }

    public function expandText(?string $text): ?string
    {
        if (null === $text || !\str_contains($text, '{')) {
            return $text;
        }

        return \str_replace(
            [self::RESOURCE_KEYS, self::CONTENT_RESOURCE_KEYS],
            [$this->quoted($this->extensionRegistry->searchableResourceKeys()), $this->quoted($this->extensionRegistry->resourceKeys())],
            $text,
        );
    }

    /**
     * @param ToolInputSchema $schema
     *
     * @return ToolInputSchema
     */
    public function expandInputSchema(array $schema): array
    {
        /** @var ToolInputSchema $expanded the walk only replaces `enum` and `description` values */
        $expanded = $this->expandNode($schema);

        return $expanded;
    }

    /**
     * @param array<array-key, mixed> $schema
     *
     * @return array<array-key, mixed>
     */
    private function expandNode(array $schema): array
    {
        foreach ($schema as $key => $value) {
            if ('enum' === $key && [self::RESOURCE_KEYS] === $value) {
                $schema[$key] = $this->extensionRegistry->searchableResourceKeys();
            } elseif ('enum' === $key && [self::CONTENT_RESOURCE_KEYS] === $value) {
                $schema[$key] = $this->extensionRegistry->resourceKeys();
            } elseif ('description' === $key && \is_string($value)) {
                $schema[$key] = $this->expandText($value);
            } elseif (\is_array($value)) {
                $schema[$key] = $this->expandNode($value);
            }
        }

        return $schema;
    }

    /**
     * @param list<string> $resourceKeys
     */
    private function quoted(array $resourceKeys): string
    {
        return \implode(', ', \array_map(static fn (string $key): string => \sprintf('"%s"', $key), $resourceKeys));
    }
}

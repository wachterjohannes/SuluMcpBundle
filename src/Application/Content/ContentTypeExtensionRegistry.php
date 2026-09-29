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

use Sulu\Mcp\Domain\Content\ContentTypeExtensionInterface;

/**
 * Collects every {@see ContentTypeExtensionInterface} tagged
 * `sulu_mcp.content_type_extension`, keyed by type and by resourceKey.
 *
 * @internal
 */
final class ContentTypeExtensionRegistry
{
    /**
     * Sentinel candidate context: grants access if the caller has VIEW on ANY
     * registered extension's security context. Mirrors
     * WebspacePermissionResolver::ANY_WEBSPACE_CONTEXT and
     * ArticleSecurityContextResolver::ANY_ARTICLE_GROUP_CONTEXT.
     */
    public const ANY_EXTENSION_CONTEXT = 'sulu.mcp.content_type_extension.#any#';

    /**
     * @var array<string, ContentTypeExtensionInterface>
     */
    private readonly array $byType;

    /**
     * @var array<string, ContentTypeExtensionInterface>
     */
    private readonly array $byResourceKey;

    /**
     * @param iterable<ContentTypeExtensionInterface> $extensions
     */
    public function __construct(iterable $extensions)
    {
        $byType = [];
        $byResourceKey = [];
        foreach ($extensions as $extension) {
            $byType[$extension->getType()] = $extension;
            $byResourceKey[$extension->getResourceKey()] = $extension;
        }

        $this->byType = $byType;
        $this->byResourceKey = $byResourceKey;
    }

    public function has(string $type): bool
    {
        return isset($this->byType[$type]);
    }

    public function get(string $type): ContentTypeExtensionInterface
    {
        return $this->byType[$type] ?? throw new \InvalidArgumentException(\sprintf('No content type extension registered for type "%s".', $type));
    }

    public function findByResourceKey(string $resourceKey): ?ContentTypeExtensionInterface
    {
        return $this->byResourceKey[$resourceKey] ?? null;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return \array_keys($this->byType);
    }

    /**
     * @return list<string>
     */
    public function resourceKeys(): array
    {
        return \array_keys($this->byResourceKey);
    }

    /**
     * @return list<ContentTypeExtensionInterface>
     */
    public function all(): array
    {
        return \array_values($this->byType);
    }
}

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
 * `sulu_mcp.content_type_extension`, keyed by resourceKey.
 *
 * @internal
 */
final class ContentTypeExtensionRegistry
{
    /**
     * Sentinel candidate context: grants access if the caller has VIEW on ANY
     * registered extension's security contexts. Mirrors
     * WebspacePermissionResolver::ANY_WEBSPACE_CONTEXT and
     * ArticleSecurityContextResolver::ANY_ARTICLE_GROUP_CONTEXT.
     */
    public const ANY_EXTENSION_CONTEXT = 'sulu.mcp.content_type_extension.#any#';

    /**
     * @var array<string, ContentTypeExtensionInterface>
     */
    private readonly array $byResourceKey;

    /**
     * @param iterable<ContentTypeExtensionInterface> $extensions
     */
    public function __construct(iterable $extensions)
    {
        $byResourceKey = [];
        foreach ($extensions as $extension) {
            $byResourceKey[$extension->getResourceKey()] = $extension;
        }

        $this->byResourceKey = $byResourceKey;
    }

    public function has(string $resourceKey): bool
    {
        return isset($this->byResourceKey[$resourceKey]);
    }

    public function get(string $resourceKey): ContentTypeExtensionInterface
    {
        return $this->byResourceKey[$resourceKey] ?? throw new \InvalidArgumentException(\sprintf('No content type extension registered for resourceKey "%s".', $resourceKey));
    }

    public function find(string $resourceKey): ?ContentTypeExtensionInterface
    {
        return $this->byResourceKey[$resourceKey] ?? null;
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
        return \array_values($this->byResourceKey);
    }
}

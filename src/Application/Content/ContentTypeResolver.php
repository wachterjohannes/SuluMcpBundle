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
 * Resolves the content-type-specific parts of block and content operations (loading the
 * draft entity, building the right message) so the tools can be written once and dispatch over
 * a `resourceKey`: every {@see ContentTypeExtensionRegistry} extension (pages, articles, snippets,
 * any bundle's type).
 *
 * Everything else (content resolve/normalize, block-tree manipulation) is already
 * type-agnostic and stays in the tools.
 *
 * @internal
 */
final readonly class ContentTypeResolver
{
    public function __construct(
        private ContentTypeExtensionRegistry $extensionRegistry,
    ) {
    }

    public function supports(string $resourceKey): bool
    {
        return null !== $this->find($resourceKey);
    }

    /**
     * @return list<string>
     */
    public function supportedResourceKeys(): array
    {
        return $this->extensionRegistry->resourceKeys();
    }

    /**
     * @return list<ContentTypeExtensionInterface>
     */
    public function all(): array
    {
        return $this->extensionRegistry->all();
    }

    public function find(string $resourceKey): ?ContentTypeExtensionInterface
    {
        return $this->extensionRegistry->find($resourceKey);
    }

    public function get(string $resourceKey): ContentTypeExtensionInterface
    {
        return $this->find($resourceKey) ?? throw new \InvalidArgumentException(\sprintf(
            'Unsupported content type "%s". Supported: %s.',
            $resourceKey,
            \implode(', ', $this->supportedResourceKeys()),
        ));
    }

    /**
     * Load the draft aggregate for the given resourceKey, or null when it is unsupported or no
     * matching entity exists.
     *
     * $loadGhost also matches an entity in a locale it has no content in. It is opt-in:
     * the returned aggregate spans every locale, so a caller that does not check for a
     * missing translation with ContentLocaleTrait would act on all of them.
     */
    public function loadDraft(string $resourceKey, string $uuid, string $locale, bool $loadGhost = false): ?object
    {
        try {
            return $this->find($resourceKey)?->loadDraft($uuid, $locale, $loadGhost);
        } catch (\Throwable) {
            return null;
        }
    }

    public function loadForTransition(string $resourceKey, string $uuid, string $locale): ?object
    {
        try {
            return $this->find($resourceKey)?->loadForTransition($uuid, $locale);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createModifyMessage(string $resourceKey, string $uuid, array $data): object
    {
        return $this->get($resourceKey)->createModifyMessage($uuid, $data);
    }

    /**
     * `forceRemoveChildren` only affects pages (which can have a subtree).
     */
    public function createRemoveMessage(string $resourceKey, string $uuid, string $locale, bool $forceRemoveChildren = false): object
    {
        return $this->get($resourceKey)->createRemoveMessage($uuid, $locale, $forceRemoveChildren);
    }

    /**
     * Build the per-type workflow transition message (e.g. 'publish' / 'unpublish').
     */
    public function createTransitionMessage(string $resourceKey, string $uuid, string $locale, string $transition): object
    {
        return $this->get($resourceKey)->createTransitionMessage($uuid, $locale, $transition);
    }
}

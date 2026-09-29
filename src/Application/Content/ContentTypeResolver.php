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
use Sulu\Mcp\Infrastructure\Sulu\Content\SnippetContentTypeExtension;
use Sulu\Snippet\Domain\Repository\SnippetRepositoryInterface;

/**
 * Resolves the content-type-specific parts of block and content operations (loading the
 * draft entity, building the right message) so the tools can be written once and dispatch over
 * a `resourceKey`: every {@see ContentTypeExtensionRegistry} extension (pages, articles, any
 * bundle's type) plus snippets, which are not in the registry because search and previews
 * do not cover them.
 *
 * Everything else (content resolve/normalize, block-tree manipulation) is already
 * type-agnostic and stays in the tools.
 *
 * @internal
 */
final readonly class ContentTypeResolver
{
    private SnippetContentTypeExtension $snippetExtension;

    public function __construct(
        SnippetRepositoryInterface $snippetRepository,
        private ContentTypeExtensionRegistry $extensionRegistry,
    ) {
        $this->snippetExtension = new SnippetContentTypeExtension($snippetRepository);
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
        return [...$this->extensionRegistry->resourceKeys(), $this->snippetExtension->getResourceKey()];
    }

    /**
     * @return list<ContentTypeExtensionInterface>
     */
    public function all(): array
    {
        return [...$this->extensionRegistry->all(), $this->snippetExtension];
    }

    public function find(string $resourceKey): ?ContentTypeExtensionInterface
    {
        if ($this->snippetExtension->getResourceKey() === $resourceKey) {
            return $this->snippetExtension;
        }

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

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

namespace Sulu\Mcp\Domain\Content;

/**
 * A content type the unified content/block tools, `sulu_content_search`, the preview tools and
 * the templates resource work on. Pages and articles are built in, a bundle plugs in its own
 * type without SuluMcpBundle knowing that bundle exists. Implement it and tag the service
 * `sulu_mcp.content_type_extension` (autoconfigured for any class implementing this interface).
 *
 * The resourceKey is the identifier everywhere: the `resourceKey` tool parameter and the
 * resourceKey of the SEAL `website` index.
 */
interface ContentTypeExtensionInterface
{
    /**
     * The value of the `resourceKey` tool parameter and the SEAL `website` index resourceKey,
     * e.g. "products".
     */
    public function getResourceKey(): string;

    /**
     * The template type Sulu's form metadata is keyed by, e.g. "product". Not the tool parameter.
     */
    public function getTemplateType(): string;

    /**
     * Security contexts of which VIEW on any one makes this type visible (search, tool discovery).
     * An empty list means visibility is governed by webspace permissions, which the caller
     * has already been checked against.
     *
     * @return list<string>
     */
    public function getViewSecurityContexts(): array;

    /**
     * The security context guarding one loaded entity. $templateKey is the template key of the
     * resolved dimension content, only passed when {@see requiresResolvedContent()} is true.
     */
    public function getEntitySecurityContext(object $aggregate, ?string $templateKey): string;

    /**
     * Whether {@see getEntitySecurityContext()} needs the template key, i.e. the security context
     * depends on the resolved content (articles are secured per template group).
     */
    public function requiresResolvedContent(): bool;

    /**
     * The class checked for object level permissions (ACL) of an entity of this type, or null when
     * the type has none.
     */
    public function getAclObjectType(): ?string;

    /**
     * The key of the webspace the entity belongs to, or null when the type is not webspace bound.
     */
    public function getWebspaceKey(object $aggregate): ?string;

    /**
     * Throws a PermissionDeniedException when the caller may not remove $uuid, called after the
     * permission on the entity itself passed. A no-op for types without dependent entities.
     */
    public function assertCanRemove(string $uuid): void;

    /**
     * Load the draft aggregate for a block/content operation, or null if none
     * matches. $loadGhost also matches an entity in a locale it has no content in.
     */
    public function loadDraft(string $uuid, string $locale, bool $loadGhost = false): ?object;

    /**
     * Load the aggregate for a workflow transition (publish/unpublish), with
     * dimension contents hydrated for both draft and live stage.
     */
    public function loadForTransition(string $uuid, string $locale): ?object;

    /**
     * @param array<string, mixed> $data
     */
    public function createModifyMessage(string $uuid, array $data): object;

    public function createRemoveMessage(string $uuid, string $locale, bool $forceRemoveChildren = false): object;

    public function createTransitionMessage(string $uuid, string $locale, string $transition): object;
}

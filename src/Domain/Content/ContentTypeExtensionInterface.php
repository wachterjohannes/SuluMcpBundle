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
 * Plugs a content type owned by another bundle into the unified content/block
 * tools, `sulu_content_search` and `sulu_get_context`'s template list, without
 * SuluMcpBundle knowing that bundle exists. Implement it and tag the service
 * `sulu_mcp.content_type_extension` (autoconfigured for any class implementing
 * this interface).
 */
interface ContentTypeExtensionInterface
{
    /**
     * The `type` value the unified tools accept, e.g. "product".
     */
    public function getType(): string;

    /**
     * The SEAL `website` index resourceKey for this type, e.g. "products".
     */
    public function getResourceKey(): string;

    /**
     * The security context checked for VIEW/DELETE/... on this type.
     */
    public function getSecurityContext(): string;

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

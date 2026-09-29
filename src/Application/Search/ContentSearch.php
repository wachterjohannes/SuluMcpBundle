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

namespace Sulu\Mcp\Application\Search;

use CmsIg\Seal\Search\Condition\Condition;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Mcp\Application\Content\ContentTypeExtensionRegistry;
use Sulu\Mcp\Application\Security\ToolPermissionCheckerInterface;
use Sulu\Mcp\Application\Security\WebspacePermissionResolver;
use Sulu\Mcp\Infrastructure\Sulu\Security\ArticleSecurityContextResolver;

/**
 * Keyword search over the `website` SEAL index, the logic behind the `sulu_content_search` MCP
 * tool.
 *
 * Whitelist, not blacklist: only pages, articles the caller may view and resourceKeys a
 * {@see ContentTypeExtensionRegistry} extension declares are ever returned. An
 * indexed resourceKey nobody registered for MCP stays invisible, rather than
 * leaking to anyone with webspace VIEW.
 *
 * @internal
 */
final class ContentSearch
{
    private const TYPE_MAP = [
        'page' => 'pages',
        'article' => 'articles',
    ];

    private const PAGE_RESOURCE_KEY = 'pages';
    private const ARTICLE_RESOURCE_KEY = 'articles';

    public function __construct(
        private readonly WebsiteSearch $websiteSearch,
        private readonly WebspacePermissionResolver $webspacePermissionResolver,
        private readonly ToolPermissionCheckerInterface $permissionChecker,
        private readonly ContentTypeExtensionRegistry $extensionRegistry,
        private readonly ArticleSecurityContextResolver $articleContextResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function search(
        string $query,
        string $locale,
        ?string $webspace = null,
        ?string $type = null,
        int $page = 1,
        int $limit = 20,
    ): array {
        // The `website` index carries only `webspaces`, no securityContext,
        // so per-object ACL filtering isn't possible here. Constraining to the webspaces
        // the caller may VIEW is the best available mirror.
        $permitted = $this->webspacePermissionResolver->permittedWebspaceKeys(PermissionTypes::VIEW, $locale);
        if ([] === $permitted) {
            return ['results' => [], 'total' => 0, 'hint' => 'No webspaces are readable with your permissions.'];
        }

        $effective = null !== $webspace ? \array_values(\array_intersect($permitted, [$webspace])) : $permitted;
        if ([] === $effective) {
            return ['results' => [], 'total' => 0, 'hint' => \sprintf('Webspace "%s" is not readable with your permissions.', $webspace)];
        }

        // A page's security context is its webspace, checked above. Articles and extension
        // types carry their own context, so each needs an extra check here.
        $visibleResourceKeys = [self::PAGE_RESOURCE_KEY];
        $canSeeArticles = $this->hasArticlePermission($locale);
        if ($canSeeArticles) {
            $visibleResourceKeys[] = self::ARTICLE_RESOURCE_KEY;
        }
        foreach ($this->extensionRegistry->all() as $extension) {
            if ($this->permissionChecker->has($extension->getSecurityContext(), PermissionTypes::VIEW, $locale)) {
                $visibleResourceKeys[] = $extension->getResourceKey();
            }
        }

        $resourceKey = null !== $type ? (self::TYPE_MAP[$type] ?? $type) : null;

        if (self::ARTICLE_RESOURCE_KEY === $resourceKey && !$canSeeArticles) {
            return [
                'error' => 'Permission denied: no accessible security context grants the required permissions.',
                'hint' => 'Requires VIEW on "sulu.article.articles" (or the matching article group context).',
            ];
        }

        if (null !== $resourceKey && !\in_array($resourceKey, $visibleResourceKeys, true)) {
            $extension = $this->extensionRegistry->findByResourceKey($resourceKey);
            if (null !== $extension) {
                return [
                    'error' => 'Permission denied: no accessible security context grants the required permissions.',
                    'hint' => \sprintf('Requires VIEW on "%s".', $extension->getSecurityContext()),
                ];
            }

            return [
                'error' => \sprintf('Unsupported content type "%s".', $type),
                'hint' => \sprintf('Supported: %s.', \implode(', ', ['page', 'article', ...$this->extensionRegistry->types()])),
            ];
        }

        try {
            $builder = $this->websiteSearch->builder($locale, $query, $page, $limit)
                ->addFilter(Condition::in('webspaces', $effective));

            if (null !== $resourceKey) {
                $builder->addFilter(Condition::equal('resourceKey', $resourceKey));
            } else {
                $builder->addFilter(Condition::in('resourceKey', $visibleResourceKeys));
            }

            return $this->websiteSearch->run($builder, $page, $limit);
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Content search failed: %s', $e->getMessage()),
                'hint' => \sprintf(
                    'Only published content is indexed. Verify the locale is correct and type is %s (or omit to search all).',
                    \implode(', ', \array_map(static fn (string $t): string => \sprintf('"%s"', $t), ['page', 'article', ...$this->extensionRegistry->types()])),
                ),
            ];
        }
    }

    /**
     * The `website` index carries no template, so per-group filtering the way
     * ArticleListTool does isn't possible here: VIEW on any one article group is
     * enough to see article results at all.
     */
    private function hasArticlePermission(string $locale): bool
    {
        foreach ($this->articleContextResolver->candidates() as $context) {
            if ($this->permissionChecker->has($context, PermissionTypes::VIEW, $locale)) {
                return true;
            }
        }

        return false;
    }
}

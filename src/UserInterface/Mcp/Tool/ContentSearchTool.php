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

namespace Sulu\Mcp\UserInterface\Mcp\Tool;

use CmsIg\Seal\Search\Condition\Condition;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Schema\ToolAnnotations;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Mcp\Application\Search\WebsiteSearch;
use Sulu\Mcp\Application\Security\ToolPermissionCheckerInterface;
use Sulu\Mcp\Application\Security\WebspacePermissionResolver;
use Sulu\Mcp\Domain\Security\PermissionRequirement;
use Sulu\Mcp\Domain\Security\RequiresPermission;

/**
 * @internal
 */
class ContentSearchTool
{
    private const TYPE_MAP = [
        'page' => 'pages',
        'article' => 'articles',
        'product' => 'products',
    ];

    // Spelled out literally, not via ProductInterface::RESOURCE_KEY/ProductAdmin::SECURITY_CONTEXT:
    // this class is registered whether or not SuluProductBundle is installed.
    private const PRODUCT_RESOURCE_KEY = 'products';
    private const PRODUCT_SECURITY_CONTEXT = 'sulu.product.products';

    public function __construct(
        private readonly WebsiteSearch $websiteSearch,
        private readonly WebspacePermissionResolver $webspacePermissionResolver,
        private readonly ToolPermissionCheckerInterface $permissionChecker,
        private readonly bool $productsIndexed = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'sulu_content_search',
        title: 'Search Content',
        description: 'Search published website content (articles, pages, and products when SuluProductBundle is installed) by keyword. Searches titles and full content text, including product code, family and attribute values as free text, not as a structured attribute filter. Use sulu_product_search for that. Returns matching items with their UUID and resource type. Use resourceKey to pick the right get tool (sulu_article_get, sulu_page_get, or sulu_product_get, which also resolves a variant) and resourceId as the UUID. Filter by type ("page", "article" or "product") to restrict results to one content type. Filter by webspace to scope results to one site. Only published content is searchable.',
        annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    )]
    #[RequiresPermission(
        requirements: [new PermissionRequirement('#context#', PermissionTypes::VIEW)],
        objectResolved: true,
        discoveryContexts: [WebspacePermissionResolver::ANY_WEBSPACE_CONTEXT],
    )]
    public function search(
        string $query,
        string $locale,
        #[Schema(description: 'Webspace key to restrict results to one site (e.g. "example"). Omit to search all webspaces.')]
        ?string $webspace = null,
        #[Schema(description: 'Content type to search. Valid values: "page", "article" or "product" (only when SuluProductBundle is installed). Omit to search all.', enum: ['page', 'article', 'product'])]
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

        // Products land in the same `website` index as pages/articles, indexed whenever
        // SuluProductBundle is installed regardless of "additional_product_filters". An untyped
        // search or an explicit type="products" would otherwise leak them to anyone with
        // webspace VIEW. The product security context is separate and has to be checked here.
        $resourceKey = null !== $type ? (self::TYPE_MAP[$type] ?? $type) : null;

        if (self::PRODUCT_RESOURCE_KEY === $resourceKey && !$this->productsIndexed) {
            return [
                'error' => 'Unsupported content type "product".',
                'hint' => 'Requires SuluProductBundle to be installed.',
            ];
        }

        $canSeeProducts = $this->productsIndexed
            && $this->permissionChecker->has(self::PRODUCT_SECURITY_CONTEXT, PermissionTypes::VIEW, $locale);

        if (self::PRODUCT_RESOURCE_KEY === $resourceKey && !$canSeeProducts) {
            return [
                'error' => 'Permission denied: no accessible security context grants the required permissions.',
                'hint' => \sprintf('Requires VIEW on "%s".', self::PRODUCT_SECURITY_CONTEXT),
            ];
        }

        try {
            $builder = $this->websiteSearch->builder($locale, $query, $page, $limit)
                ->addFilter(Condition::in('webspaces', $effective));

            if (null !== $resourceKey) {
                $builder->addFilter(Condition::equal('resourceKey', $resourceKey));
            } elseif (!$canSeeProducts) {
                $builder->addFilter(Condition::notEqual('resourceKey', self::PRODUCT_RESOURCE_KEY));
            }

            return $this->websiteSearch->run($builder, $page, $limit);
        } catch (\Throwable $e) {
            return [
                'error' => \sprintf('Content search failed: %s', $e->getMessage()),
                'hint' => 'Only published content is indexed. Verify the locale is correct and type is "page", "article" or "product" (or omit to search all).',
            ];
        }
    }
}

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
use Sulu\Mcp\Application\Security\ToolPermissionCheckerInterface;
use Sulu\Mcp\Application\Security\WebspacePermissionResolver;

/**
 * Keyword search over the `website` SEAL index, the logic behind the `sulu_content_search` MCP
 * tool. Framework-agnostic on purpose: it depends on no MCP type, so it can be driven from any
 * caller, not only the tool adapter.
 *
 * @internal
 */
final class ContentSearch
{
    private const TYPE_MAP = [
        'page' => 'pages',
        'article' => 'articles',
        'product' => 'products',
    ];

    // Spelled out literally, not via ProductInterface::RESOURCE_KEY/ProductAdmin::SECURITY_CONTEXT:
    // this class is instantiated whether or not SuluProductBundle is installed.
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

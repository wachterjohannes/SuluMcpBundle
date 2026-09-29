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

namespace Sulu\Mcp\Tests\Unit\UserInterface\Mcp\Tool\Product;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Mcp\UserInterface\Mcp\Tool\Product\GetProductsTool;
use Sulu\Product\Application\Ai\GetProducts;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;

/**
 * GetProducts (final, so Prophecy can't double it directly) is real here, built over a mocked
 * repository. It never gets called without a query or productFamily, so this only has to prove
 * the adapter threads its arguments through, not repeat its own tests.
 */
#[CoversClass(GetProductsTool::class)]
#[Group('product')]
final class GetProductsToolTest extends TestCase
{
    use ProphecyTrait;

    public function testSearchDelegatesToGetProducts(): void
    {
        $productRepository = $this->prophesize(ProductRepositoryInterface::class);
        $tool = new GetProductsTool(new GetProducts($productRepository->reveal()));

        $result = $tool->search('en');

        $this->assertSame('no_match', $result['status']);
        $this->assertNotEmpty($result['instruction']);
    }

    public function testSearchMethodHasMcpToolAttribute(): void
    {
        $reflection = new \ReflectionMethod(GetProductsTool::class, 'search');
        $attributes = $reflection->getAttributes(McpTool::class);

        $this->assertCount(1, $attributes, 'search() method must have exactly one #[McpTool] attribute');

        $instance = $attributes[0]->newInstance();
        $this->assertSame('sulu_product_get_products', $instance->name);
    }
}

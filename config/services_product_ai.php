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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sulu\Mcp\UserInterface\Mcp\Tool\Product\GetProductsTool;
use Sulu\Mcp\UserInterface\Mcp\Tool\Product\SearchProductsByAttributesTool;

/*
 * Imported by SuluMcpBundle only when SuluProductBundle is registered AND symfony/ai-agent is
 * installed: SuluProductBundle's own #[AsTool] services exist only under that same condition
 * (SuluProductBundle.php:1324), and these two MCP tools just wrap them.
 */
return static function(ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    $services->set(GetProductsTool::class);
    $services->set(SearchProductsByAttributesTool::class);
};

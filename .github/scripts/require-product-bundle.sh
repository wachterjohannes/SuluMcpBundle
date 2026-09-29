#!/usr/bin/env bash

# Adds sulu/product-bundle, the sulu/sulu 3.1 it requires, and symfony/ai-agent to composer.json
# for the "product bundle" workflow. All three are optional, so none is part of the committed
# composer.json. symfony/ai-agent is included here too: without it, phpstan can't resolve the
# #[AsTool] attribute referenced by the product AI tool wrappers and the agent-side tools.

set -eu

composer require --no-update "sulu/sulu:3.1.x-dev"
composer require --no-update --dev "sulu/product-bundle:3.0.x-dev" "symfony/ai-agent:^0.14"

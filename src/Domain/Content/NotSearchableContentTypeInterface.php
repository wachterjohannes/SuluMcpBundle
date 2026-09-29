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
 * Marks a {@see ContentTypeExtensionInterface} whose content is not part of the `website` search
 * index and has no preview. The unified content and block tools still work on it, but
 * `sulu_content_search` and the preview tools neither accept nor list its resourceKey.
 */
interface NotSearchableContentTypeInterface
{
}

<?php

declare(strict_types=1);

namespace LiteAdmin\Attribute;

use Attribute;

/**
 * Marks an entity as manageable via the LiteAdmin dashboard.
 *
 * Example:
 *   #[AdminResource(title: 'Products', icon: 'package', group: 'Shop')]
 *   class Product { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AdminResource
{
    public function __construct(
        public string $title,
        public ?string $slug = null,
        public ?string $icon = 'table',
        public string $group = 'General',
        public int $order = 0,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace LiteAdmin\Attribute;

use Attribute;

/**
 * Configures how an entity property is rendered in the DataTable list view.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class AdminColumn
{
    public function __construct(
        public ?string $label = null,
        public bool $sortable = true,
        public bool $searchable = false,
        public ?string $format = null, // e.g. 'currency', 'datetime', 'badge', 'boolean'
        public int $order = 0,
    ) {
    }
}

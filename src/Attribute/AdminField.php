<?php

declare(strict_types=1);

namespace LiteAdmin\Attribute;

use Attribute;

/**
 * Configures how an entity property is rendered in Create/Edit forms.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class AdminField
{
    /**
     * @param ?string $label Field display label
     * @param string $type Input type: 'text', 'number', 'textarea', 'select', 'checkbox', 'datetime', 'email'
     * @param bool $required Whether field is required
     * @param ?string $placeholder Placeholder hint
     * @param array<string|int, string> $options Options for 'select' or 'radio' inputs
     * @param bool $readonly Whether input is read-only
     */
    public function __construct(
        public ?string $label = null,
        public string $type = 'text',
        public bool $required = false,
        public ?string $placeholder = null,
        public array $options = [],
        public bool $readonly = false,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace LiteAdmin\Resource;

/**
 * Resolved configuration metadata for an Admin entity resource.
 */
final readonly class ResourceMetadata
{
    /**
     * @param array<string, array{property: string, label: string, sortable: bool, searchable: bool, format: ?string, order: int}> $columns
     * @param array<string, array{property: string, label: string, type: string, required: bool, placeholder: ?string, options: array, readonly: bool}> $fields
     */
    public function __construct(
        public string $entityClass,
        public string $tableName,
        public string $primaryKey,
        public string $title,
        public string $slug,
        public string $icon,
        public string $group,
        public int $order,
        public array $columns,
        public array $fields,
    ) {
    }
}

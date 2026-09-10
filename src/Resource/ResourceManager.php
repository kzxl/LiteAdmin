<?php

declare(strict_types=1);

namespace LiteAdmin\Resource;

use InvalidArgumentException;
use LiteAdmin\Attribute\{AdminColumn, AdminField, AdminResource};
use LiteORM\Metadata\AttributeReader;
use ReflectionClass;

/**
 * Registry and introspector for admin entities.
 */
class ResourceManager
{
    /** @var array<string, ResourceMetadata> Indexed by slug */
    private array $resources = [];

    /**
     * Register an entity class to be managed in the admin panel.
     */
    public function register(string $entityClass): self
    {
        $meta = $this->introspectEntity($entityClass);
        $this->resources[$meta->slug] = $meta;
        return $this;
    }

    /**
     * Get resource by URL slug.
     */
    public function getResource(string $slug): ?ResourceMetadata
    {
        return $this->resources[$slug] ?? null;
    }

    /**
     * Get all registered resources.
     *
     * @return array<string, ResourceMetadata>
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Get resources grouped by section (e.g. ['General' => [...], 'Shop' => [...]])
     *
     * @return array<string, list<ResourceMetadata>>
     */
    public function getResourcesByGroup(): array
    {
        $groups = [];
        foreach ($this->resources as $res) {
            $groups[$res->group][] = $res;
        }

        // Sort items inside groups by order
        foreach ($groups as $group => $items) {
            usort($items, fn(ResourceMetadata $a, ResourceMetadata $b) => $a->order <=> $b->order);
            $groups[$group] = $items;
        }

        return $groups;
    }

    private function introspectEntity(string $entityClass): ResourceMetadata
    {
        $ref = new ReflectionClass($entityClass);

        // 1. Read AdminResource attribute
        $resourceAttrs = $ref->getAttributes(AdminResource::class);
        $adminAttr = !empty($resourceAttrs) ? $resourceAttrs[0]->newInstance() : null;

        $shortName = $ref->getShortName();
        $title = $adminAttr?->title ?? $shortName;
        $slug = $adminAttr?->slug ?? strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $shortName) ?? $shortName);
        $icon = $adminAttr?->icon ?? 'table';
        $group = $adminAttr?->group ?? 'General';
        $order = $adminAttr?->order ?? 0;

        // 2. Read LiteORM Metadata for table & PK
        $ormMeta = AttributeReader::read($entityClass);
        $tableName = $ormMeta->tableName;
        $primaryKey = $ormMeta->primaryKey ?: 'id';

        // 3. Introspect Properties for Columns and Fields
        $columns = [];
        $fields = [];

        foreach ($ref->getProperties() as $prop) {
            $propName = $prop->getName();
            $colAttr = $prop->getAttributes(AdminColumn::class)[0] ?? null;
            $fieldAttr = $prop->getAttributes(AdminField::class)[0] ?? null;

            $colInstance = $colAttr?->newInstance();
            $fieldInstance = $fieldAttr?->newInstance();

            // Format human label
            $defaultLabel = ucwords(str_replace(['_', '-'], ' ', preg_replace('/(?<!^)[A-Z]/', ' $0', $propName) ?? $propName));

            // Determine if required via LiteValidate attribute
            $isRequired = $fieldInstance?->required ?? false;
            foreach ($prop->getAttributes() as $attr) {
                if (str_ends_with($attr->getName(), 'Required')) {
                    $isRequired = true;
                    break;
                }
            }

            // Infer input type from PHP type
            $inferredType = 'text';
            if ($prop->hasType()) {
                $type = (string)$prop->getType();
                if (str_contains($type, 'int') || str_contains($type, 'float')) {
                    $inferredType = 'number';
                } elseif (str_contains($type, 'bool')) {
                    $inferredType = 'checkbox';
                } elseif (str_contains($type, 'DateTime')) {
                    $inferredType = 'datetime';
                }
            }

            // Add Column (skip non-column or complex objects unless explicitly tagged)
            $columns[$propName] = [
                'property' => $propName,
                'label' => $colInstance?->label ?? $defaultLabel,
                'sortable' => $colInstance?->sortable ?? true,
                'searchable' => $colInstance?->searchable ?? ($inferredType === 'text'),
                'format' => $colInstance?->format ?? ($inferredType === 'checkbox' ? 'boolean' : null),
                'order' => $colInstance?->order ?? 0,
            ];

            // Add Form Field (skip auto-increment primary key in edit/create forms)
            $isPk = ($propName === $primaryKey);
            $fields[$propName] = [
                'property' => $propName,
                'label' => $fieldInstance?->label ?? $defaultLabel,
                'type' => $fieldInstance?->type ?? $inferredType,
                'required' => $isRequired,
                'placeholder' => $fieldInstance?->placeholder,
                'options' => $fieldInstance?->options ?? [],
                'readonly' => $fieldInstance?->readonly ?? $isPk,
            ];
        }

        return new ResourceMetadata(
            entityClass: $entityClass,
            tableName: $tableName,
            primaryKey: $primaryKey,
            title: $title,
            slug: $slug,
            icon: $icon,
            group: $group,
            order: $order,
            columns: $columns,
            fields: $fields,
        );
    }
}

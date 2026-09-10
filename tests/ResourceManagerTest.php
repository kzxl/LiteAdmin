<?php

declare(strict_types=1);

namespace LiteAdmin\Tests;

use LiteAdmin\Attribute\{AdminColumn, AdminField, AdminResource};
use LiteAdmin\Resource\ResourceManager;
use LiteORM\Attribute\{AutoIncrement, Column, Entity, Id, Table};
use LiteValidate\Attribute\{Email, Required};
use PHPUnit\Framework\TestCase;

#[Entity]
#[Table('products')]
#[AdminResource(title: 'Products', slug: 'products', icon: 'package', group: 'Sales', order: 1)]
class TestProduct
{
    #[Id, AutoIncrement]
    public int $id;

    #[Required]
    #[Column(length: 150)]
    #[AdminColumn(label: 'Product Name', sortable: true, searchable: true)]
    #[AdminField(label: 'Product Name', placeholder: 'Enter name...')]
    public string $name;

    #[Column]
    #[AdminColumn(label: 'Price', format: 'currency')]
    #[AdminField(label: 'Price', type: 'number')]
    public float $price;

    #[Column(nullable: true)]
    #[AdminColumn(label: 'In Stock', format: 'boolean')]
    #[AdminField(label: 'In Stock', type: 'checkbox')]
    public bool $inStock = true;

    #[Column(length: 50, nullable: true)]
    #[AdminColumn(label: 'System Code')]
    #[AdminField(label: 'System Code', readonly: true)]
    public ?string $sysCode = 'SYSTEM_PROTECTED';
}

final class ResourceManagerTest extends TestCase
{
    public function testResourceIntrospection(): void
    {
        $manager = new ResourceManager();
        $manager->register(TestProduct::class);

        $res = $manager->getResource('products');
        $this->assertNotNull($res);
        $this->assertEquals('Products', $res->title);
        $this->assertEquals('products', $res->slug);
        $this->assertEquals('Sales', $res->group);
        $this->assertEquals('products', $res->tableName);
        $this->assertEquals('id', $res->primaryKey);

        // Columns
        $this->assertArrayHasKey('name', $res->columns);
        $this->assertEquals('Product Name', $res->columns['name']['label']);
        $this->assertTrue($res->columns['name']['searchable']);
        $this->assertEquals('currency', $res->columns['price']['format']);

        // Fields
        $this->assertArrayHasKey('name', $res->fields);
        $this->assertTrue($res->fields['name']['required']); // Detected via #[Required]
        $this->assertEquals('number', $res->fields['price']['type']);
        $this->assertEquals('checkbox', $res->fields['inStock']['type']);

        // Groups
        $groups = $manager->getResourcesByGroup();
        $this->assertArrayHasKey('Sales', $groups);
        $this->assertCount(1, $groups['Sales']);
    }
}

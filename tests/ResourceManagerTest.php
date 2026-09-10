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
#[AdminResource(title: 'Sản phẩm', slug: 'products', icon: 'package', group: 'Kinh doanh', order: 1)]
class TestProduct
{
    #[Id, AutoIncrement]
    public int $id;

    #[Required]
    #[Column(length: 150)]
    #[AdminColumn(label: 'Tên sản phẩm', sortable: true, searchable: true)]
    #[AdminField(label: 'Tên sản phẩm', placeholder: 'Nhập tên...')]
    public string $name;

    #[Column]
    #[AdminColumn(label: 'Giá tiền', format: 'currency')]
    #[AdminField(label: 'Giá tiền', type: 'number')]
    public float $price;

    #[Column(nullable: true)]
    #[AdminColumn(label: 'Còn hàng', format: 'boolean')]
    #[AdminField(label: 'Còn hàng', type: 'checkbox')]
    public bool $inStock = true;

    #[Column(length: 50, nullable: true)]
    #[AdminColumn(label: 'Mã hệ thống')]
    #[AdminField(label: 'Mã hệ thống', readonly: true)]
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
        $this->assertEquals('Sản phẩm', $res->title);
        $this->assertEquals('products', $res->slug);
        $this->assertEquals('Kinh doanh', $res->group);
        $this->assertEquals('products', $res->tableName);
        $this->assertEquals('id', $res->primaryKey);

        // Columns
        $this->assertArrayHasKey('name', $res->columns);
        $this->assertEquals('Tên sản phẩm', $res->columns['name']['label']);
        $this->assertTrue($res->columns['name']['searchable']);
        $this->assertEquals('currency', $res->columns['price']['format']);

        // Fields
        $this->assertArrayHasKey('name', $res->fields);
        $this->assertTrue($res->fields['name']['required']); // Detected via #[Required]
        $this->assertEquals('number', $res->fields['price']['type']);
        $this->assertEquals('checkbox', $res->fields['inStock']['type']);

        // Groups
        $groups = $manager->getResourcesByGroup();
        $this->assertArrayHasKey('Kinh doanh', $groups);
        $this->assertCount(1, $groups['Kinh doanh']);
    }
}

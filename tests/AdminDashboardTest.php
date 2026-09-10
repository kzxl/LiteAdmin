<?php

declare(strict_types=1);

namespace LiteAdmin\Tests;

use LiteAdmin\AdminDashboard;
use LiteAudit\Actor\StaticActorProvider;
use LiteAudit\AuditManager;
use LiteAudit\Bridge\LiteOrmAuditBridge;
use LiteAudit\Storage\MemoryAuditStorage;
use LiteORM\EntityManager;
use Nyholm\Psr7\{Response, ServerRequest};
use PHPUnit\Framework\TestCase;

final class AdminDashboardTest extends TestCase
{
    private EntityManager $em;
    private AdminDashboard $admin;
    private AuditManager $auditManager;

    protected function setUp(): void
    {
        $this->em = new EntityManager('sqlite::memory:');
        $this->em->createTable(TestProduct::class);

        // Setup Audit
        $auditStorage = new MemoryAuditStorage();
        $this->auditManager = new AuditManager($auditStorage, new StaticActorProvider('admin_user'));
        LiteOrmAuditBridge::register($this->em, $this->auditManager);

        // Setup Admin
        $this->admin = new AdminDashboard($this->em, $this->auditManager, '/admin');
        $this->admin->register(TestProduct::class);
    }

    public function testListAndIndexFlow(): void
    {
        // 1. Seed some products
        $p1 = new TestProduct();
        $p1->name = 'Laptop Pro';
        $p1->price = 1200.0;
        $p1->inStock = true;

        $p2 = new TestProduct();
        $p2->name = 'Wireless Mouse';
        $p2->price = 25.5;
        $p2->inStock = false;

        $this->em->persist($p1);
        $this->em->persist($p2);
        $this->em->flush();

        // 2. Index route redirects to first resource
        $res = $this->admin->handleIndex(new ServerRequest('GET', '/admin'), new Response());
        $this->assertEquals(302, $res->getStatusCode());
        $this->assertEquals('/admin/products', $res->getHeaderLine('Location'));

        // 3. List page renders both products
        $res = $this->admin->handleList(
            new ServerRequest('GET', '/admin/products'),
            new Response(),
            ['slug' => 'products']
        );

        $this->assertEquals(200, $res->getStatusCode());
        $body = (string)$res->getBody();
        $this->assertStringContainsString('Sản phẩm', $body);
        $this->assertStringContainsString('Laptop Pro', $body);
        $this->assertStringContainsString('Wireless Mouse', $body);
        $this->assertStringContainsString('Tổng số: 2 bản ghi', $body);
    }

    public function testCreateAndStoreFlow(): void
    {
        // 1. Render create form
        $resForm = $this->admin->handleCreate(
            new ServerRequest('GET', '/admin/products/create'),
            new Response(),
            ['slug' => 'products']
        );
        $this->assertEquals(200, $resForm->getStatusCode());
        $this->assertStringContainsString('Tạo mới Sản phẩm', (string)$resForm->getBody());

        // 2. Store valid product
        $reqStore = (new ServerRequest('POST', '/admin/products/create'))
            ->withParsedBody([
                'name' => 'Mechanical Keyboard',
                'price' => '85.0',
                'inStock' => '1',
            ]);

        $resStore = $this->admin->handleStore($reqStore, new Response(), ['slug' => 'products']);
        $this->assertEquals(302, $resStore->getStatusCode());
        $this->assertEquals('/admin/products', $resStore->getHeaderLine('Location'));

        // Verify entity persisted in SQLite
        $stored = $this->em->query(TestProduct::class)->where('name', 'Mechanical Keyboard')->first();
        $this->assertNotNull($stored);
        $this->assertEquals(85.0, $stored->price);
        $this->assertTrue($stored->inStock);
    }

    public function testValidationFailureOnStoreReturns422(): void
    {
        // 'name' is required via #[Required]
        $reqStore = (new ServerRequest('POST', '/admin/products/create'))
            ->withParsedBody([
                'name' => '', // Invalid!
                'price' => '50.0',
            ]);

        $resStore = $this->admin->handleStore($reqStore, new Response(), ['slug' => 'products']);
        $this->assertEquals(422, $resStore->getStatusCode());
        $body = (string)$resStore->getBody();
        $this->assertStringContainsString('Vui lòng kiểm tra lại', $body);
    }

    public function testEditUpdateAndDetailFlowWithAuditTrail(): void
    {
        // 1. Create product
        $p = new TestProduct();
        $p->name = 'Gaming Monitor';
        $p->price = 300.0;
        $this->em->persist($p);
        $this->em->flush();
        $id = $p->id;

        // 2. Edit form
        $resEdit = $this->admin->handleEdit(
            new ServerRequest('GET', "/admin/products/edit/{$id}"),
            new Response(),
            ['slug' => 'products', 'id' => (string)$id]
        );
        $this->assertEquals(200, $resEdit->getStatusCode());
        $this->assertStringContainsString('Gaming Monitor', (string)$resEdit->getBody());

        // 3. Update product
        $reqUpdate = (new ServerRequest('POST', "/admin/products/edit/{$id}"))
            ->withParsedBody([
                'name' => 'Gaming Monitor Ultra',
                'price' => '350.0',
            ]);
        $resUpdate = $this->admin->handleUpdate($reqUpdate, new Response(), ['slug' => 'products', 'id' => (string)$id]);
        $this->assertEquals(302, $resUpdate->getStatusCode());

        // 4. View detail and check LiteAudit timeline
        $resDetail = $this->admin->handleDetail(
            new ServerRequest('GET', "/admin/products/detail/{$id}"),
            new Response(),
            ['slug' => 'products', 'id' => (string)$id]
        );

        $this->assertEquals(200, $resDetail->getStatusCode());
        $body = (string)$resDetail->getBody();
        $this->assertStringContainsString('Chi tiết Sản phẩm #' . $id, $body);
        $this->assertStringContainsString('Gaming Monitor Ultra', $body);
        $this->assertStringContainsString('Lịch sử thay đổi (Audit Trail)', $body);
        $this->assertStringContainsString('admin_user', $body);
    }

    public function testDeleteFlow(): void
    {
        $p = new TestProduct();
        $p->name = 'To Delete';
        $p->price = 10.0;
        $this->em->persist($p);
        $this->em->flush();
        $id = $p->id;

        $resDelete = $this->admin->handleDelete(
            new ServerRequest('POST', "/admin/products/delete/{$id}"),
            new Response(),
            ['slug' => 'products', 'id' => (string)$id]
        );

        $this->assertEquals(302, $resDelete->getStatusCode());
        $this->assertNull($this->em->find(TestProduct::class, $id));
    }

    public function testExportFlow(): void
    {
        $p = new TestProduct();
        $p->name = 'Export Item';
        $p->price = 99.0;
        $this->em->persist($p);
        $this->em->flush();

        $resExport = $this->admin->handleExport(
            new ServerRequest('GET', '/admin/products/export'),
            new Response(),
            ['slug' => 'products']
        );

        $this->assertEquals(200, $resExport->getStatusCode());
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $resExport->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('attachment; filename="products_export_', $resExport->getHeaderLine('Content-Disposition'));
        $this->assertGreaterThan(100, strlen((string)$resExport->getBody()));
    }
}

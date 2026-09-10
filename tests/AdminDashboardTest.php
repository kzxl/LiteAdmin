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

        // Setup Admin with CSRF enabled
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

        // 3. List page renders both products and includes CSRF token in forms
        $res = $this->admin->handleList(
            new ServerRequest('GET', '/admin/products'),
            new Response(),
            ['slug' => 'products']
        );

        $this->assertEquals(200, $res->getStatusCode());
        $body = (string)$res->getBody();
        $this->assertStringContainsString('Products', $body);
        $this->assertStringContainsString('Laptop Pro', $body);
        $this->assertStringContainsString('Wireless Mouse', $body);
        $this->assertStringContainsString('Total: 2 records', $body);
        $this->assertStringContainsString('name="_csrf"', $body);
    }

    public function testSortingWhitelistPreventsSqlInjection(): void
    {
        $p1 = new TestProduct();
        $p1->name = 'Apple';
        $p1->price = 10.0;
        $p2 = new TestProduct();
        $p2->name = 'Banana';
        $p2->price = 20.0;

        $this->em->persist($p1);
        $this->em->persist($p2);
        $this->em->flush();

        // Safe sort by whitelisted column 'name' DESC
        $req = (new ServerRequest('GET', '/admin/products'))->withQueryParams(['sort' => 'name', 'order' => 'desc']);
        $res = $this->admin->handleList($req, new Response(), ['slug' => 'products']);
        $this->assertEquals(200, $res->getStatusCode());

        // Malicious or unwhitelisted column is ignored safely without crash
        $reqBad = (new ServerRequest('GET', '/admin/products'))->withQueryParams(['sort' => 'non_existent_col; DROP TABLE products', 'order' => 'asc']);
        $resBad = $this->admin->handleList($reqBad, new Response(), ['slug' => 'products']);
        $this->assertEquals(200, $resBad->getStatusCode());
    }

    public function testCsrfProtectionBlocksUnauthorizedMutations(): void
    {
        // 1. Store without CSRF -> 403 Forbidden
        $reqStoreNoCsrf = (new ServerRequest('POST', '/admin/products/create'))
            ->withParsedBody(['name' => 'Attack Item', 'price' => '10.0']);
        $resStore = $this->admin->handleStore($reqStoreNoCsrf, new Response(), ['slug' => 'products']);
        $this->assertEquals(403, $resStore->getStatusCode());
        $this->assertStringContainsString('Security Error (CSRF Verification Failed)', (string)$resStore->getBody());

        // 2. Update without CSRF -> 403 Forbidden
        $reqUpdateNoCsrf = (new ServerRequest('POST', '/admin/products/edit/1'))
            ->withParsedBody(['name' => 'Attack Update', 'price' => '10.0']);
        $resUpdate = $this->admin->handleUpdate($reqUpdateNoCsrf, new Response(), ['slug' => 'products', 'id' => '1']);
        $this->assertEquals(403, $resUpdate->getStatusCode());

        // 3. Delete without CSRF -> 403 Forbidden
        $reqDeleteNoCsrf = new ServerRequest('POST', '/admin/products/delete/1');
        $resDelete = $this->admin->handleDelete($reqDeleteNoCsrf, new Response(), ['slug' => 'products', 'id' => '1']);
        $this->assertEquals(403, $resDelete->getStatusCode());

        // 4. Mutation with forged/tampered CSRF -> 403 Forbidden
        $reqForged = (new ServerRequest('POST', '/admin/products/create'))
            ->withParsedBody(['_csrf' => 'invalid-tampered-token', 'name' => 'Forged', 'price' => '10.0']);
        $resForged = $this->admin->handleStore($reqForged, new Response(), ['slug' => 'products']);
        $this->assertEquals(403, $resForged->getStatusCode());
    }

    public function testCreateAndStoreFlowWithCsrfAndMassAssignmentProtection(): void
    {
        // 1. Render create form
        $resForm = $this->admin->handleCreate(
            new ServerRequest('GET', '/admin/products/create'),
            new Response(),
            ['slug' => 'products']
        );
        $this->assertEquals(200, $resForm->getStatusCode());
        $this->assertStringContainsString('Create Products', (string)$resForm->getBody());
        $this->assertStringContainsString('name="_csrf"', (string)$resForm->getBody());

        // 2. Store valid product with valid CSRF token and attempt Mass-Assignment attack on sysCode
        $token = $this->admin->getCsrf()->generateToken();
        $reqStore = (new ServerRequest('POST', '/admin/products/create'))
            ->withParsedBody([
                '_csrf' => $token,
                'name' => 'Mechanical Keyboard',
                'price' => '85.0',
                'inStock' => '1',
                'sysCode' => 'HACKED_SYS_CODE', // Readonly: mass-assignment should ignore this!
            ]);

        $resStore = $this->admin->handleStore($reqStore, new Response(), ['slug' => 'products']);
        $this->assertEquals(302, $resStore->getStatusCode());
        $this->assertEquals('/admin/products', $resStore->getHeaderLine('Location'));

        // Verify entity persisted in SQLite and mass-assignment was thwarted
        $stored = $this->em->query(TestProduct::class)->where('name', 'Mechanical Keyboard')->first();
        $this->assertNotNull($stored);
        $this->assertEquals(85.0, $stored->price);
        $this->assertTrue($stored->inStock);
        $this->assertEquals('SYSTEM_PROTECTED', $stored->sysCode, 'Mass assignment of readonly sysCode must be ignored');
    }

    public function testValidationFailureOnStoreReturns422(): void
    {
        $token = $this->admin->getCsrf()->generateToken();
        $reqStore = (new ServerRequest('POST', '/admin/products/create'))
            ->withParsedBody([
                '_csrf' => $token,
                'name' => '', // Invalid! #[Required]
                'price' => '50.0',
            ]);

        $resStore = $this->admin->handleStore($reqStore, new Response(), ['slug' => 'products']);
        $this->assertEquals(422, $resStore->getStatusCode());
        $body = (string)$resStore->getBody();
        $this->assertStringContainsString('Please review the form for errors.', $body);
        $this->assertStringContainsString('name="_csrf"', $body); // CSRF token re-injected in form
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
        $this->assertStringContainsString('name="_csrf"', (string)$resEdit->getBody());

        // 3. Update product with valid CSRF via header
        $token = $this->admin->getCsrf()->generateToken();
        $reqUpdate = (new ServerRequest('POST', "/admin/products/edit/{$id}"))
            ->withHeader('X-CSRF-Token', $token)
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
        $this->assertStringContainsString('Details for Products #' . $id, $body);
        $this->assertStringContainsString('Gaming Monitor Ultra', $body);
        $this->assertStringContainsString('Audit Trail', $body);
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

        $token = $this->admin->getCsrf()->generateToken();
        $resDelete = $this->admin->handleDelete(
            (new ServerRequest('POST', "/admin/products/delete/{$id}"))->withParsedBody(['_csrf' => $token]),
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

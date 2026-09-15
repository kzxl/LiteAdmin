<?php

declare(strict_types=1);

namespace LiteAdmin\Tests;

use LiteAdmin\AdminDashboard;
use LiteAdmin\Attribute\{AdminColumn, AdminField, AdminResource, Authorize};
use LiteAdmin\Security\PermissionGate;
use LiteORM\Attribute\{AutoIncrement, Column, Entity, Id, Table};
use LiteORM\EntityManager;
use Nyholm\Psr7\{Response, ServerRequest};
use PHPUnit\Framework\TestCase;

#[Entity]
#[Table('secured_docs')]
#[AdminResource(title: 'Secured Documents', slug: 'documents', group: 'Legal')]
#[Authorize(roles: ['legal', 'auditor'], action: 'view')]
#[Authorize(roles: ['legal_officer'], action: 'create')]
#[Authorize(roles: ['legal_officer'], action: 'update')]
#[Authorize(permissions: ['documents.destroy'], action: 'delete')]
#[Authorize(roles: ['auditor'], action: 'export')]
class SecuredDocument
{
    #[Id, AutoIncrement]
    public int $id;

    #[Column(length: 100)]
    #[AdminColumn(label: 'Title')]
    #[AdminField(label: 'Title')]
    public string $title;
}

#[Entity]
#[Table('public_notices')]
#[AdminResource(title: 'Public Notices', slug: 'notices')]
class PublicNotice
{
    #[Id, AutoIncrement]
    public int $id;

    #[Column(length: 100)]
    #[AdminColumn(label: 'Content')]
    #[AdminField(label: 'Content')]
    public string $content;
}

final class PermissionGateTest extends TestCase
{
    private EntityManager $em;
    private AdminDashboard $admin;

    protected function setUp(): void
    {
        $this->em = new EntityManager('sqlite::memory:');
        $this->em->createTable(SecuredDocument::class);
        $this->em->createTable(PublicNotice::class);

        $this->admin = new AdminDashboard($this->em, prefix: '/admin');
        $this->admin->register(SecuredDocument::class);
        $this->admin->register(PublicNotice::class);
    }

    public function testPublicResourceAllowedByDefault(): void
    {
        $gate = new PermissionGate();
        $req = new ServerRequest('GET', '/admin/notices');

        $this->assertTrue($gate->can($req, PublicNotice::class, 'view'));
        $this->assertTrue($gate->can($req, PublicNotice::class, 'create'));
        $this->assertTrue($gate->can($req, PublicNotice::class, 'delete'));
    }

    public function testSecuredResourceDeniedIfNoResolverConfigured(): void
    {
        $gate = new PermissionGate();
        $req = new ServerRequest('GET', '/admin/documents');

        // Fails closed if rules exist but no resolver is provided
        $this->assertFalse($gate->can($req, SecuredDocument::class, 'view'));
    }

    public function testSuperadminBypass(): void
    {
        $gate = (new PermissionGate())->setUserResolver(fn() => [
            'roles' => ['superadmin'],
            'permissions' => [],
        ]);

        $req = new ServerRequest('GET', '/admin/documents');
        $this->assertTrue($gate->can($req, SecuredDocument::class, 'view'));
        $this->assertTrue($gate->can($req, SecuredDocument::class, 'create'));
        $this->assertTrue($gate->can($req, SecuredDocument::class, 'delete'));
        $this->assertTrue($gate->can($req, SecuredDocument::class, 'export'));
    }

    public function testRoleBasedAccessControl(): void
    {
        $gate = (new PermissionGate())->setUserResolver(fn() => [
            'roles' => ['legal'],
            'permissions' => [],
        ]);

        $req = new ServerRequest('GET', '/admin/documents');
        // 'legal' can view
        $this->assertTrue($gate->can($req, SecuredDocument::class, 'view'));
        // 'legal' cannot create or export
        $this->assertFalse($gate->can($req, SecuredDocument::class, 'create'));
        $this->assertFalse($gate->can($req, SecuredDocument::class, 'export'));
        $this->assertFalse($gate->can($req, SecuredDocument::class, 'delete'));
    }

    public function testPermissionBasedAccessControl(): void
    {
        $gate = (new PermissionGate())->setUserResolver(fn() => [
            'roles' => ['guest'],
            'permissions' => ['documents.destroy'],
        ]);

        $req = new ServerRequest('DELETE', '/admin/documents/delete/1');
        $this->assertTrue($gate->can($req, SecuredDocument::class, 'delete'));
        $this->assertFalse($gate->can($req, SecuredDocument::class, 'view'));
    }

    public function testDashboardIntegrationEnforcesForbiddenResponse(): void
    {
        // Configure dashboard user resolver as a read-only viewer
        $this->admin->setUserResolver(fn() => [
            'roles' => ['auditor'],
            'permissions' => [],
        ]);

        $reqView = new ServerRequest('GET', '/admin/documents');
        $resView = $this->admin->handleList($reqView, new Response(), ['slug' => 'documents']);
        $this->assertEquals(200, $resView->getStatusCode());

        // Auditor cannot create
        $reqCreate = new ServerRequest('GET', '/admin/documents/create');
        $resCreate = $this->admin->handleCreate($reqCreate, new Response(), ['slug' => 'documents']);
        $this->assertEquals(403, $resCreate->getStatusCode());
        $this->assertStringContainsString('Access Denied (403 Forbidden)', (string)$resCreate->getBody());

        // Auditor cannot delete
        $token = $this->admin->getCsrf()->generateToken();
        $reqDelete = (new ServerRequest('POST', '/admin/documents/delete/1'))->withParsedBody(['_csrf' => $token]);
        $resDelete = $this->admin->handleDelete($reqDelete, new Response(), ['slug' => 'documents', 'id' => '1']);
        $this->assertEquals(403, $resDelete->getStatusCode());
    }
}

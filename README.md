# LiteAdmin

[![Latest Version](https://img.shields.io/github/v/release/kzxl/LiteAdmin?label=version&color=blue)](https://github.com/kzxl/LiteAdmin/releases)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

[![PHP 8.2+](https://img.shields.io/badge/php-8.2%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)]()

Instant declarative Auto-CRUD Admin Dashboard generator for `LiteORM`, `LiteValidate`, `LiteExport`, and `LiteAudit` in PHP 8.2+. Zero npm, zero node_modules, and zero build step required.

---

## Key Features

- **Instant Auto-CRUD via Attributes**:
  - Simply annotate an entity with `#[AdminResource(title: 'Products', group: 'Sales')]` to instantly generate complete List, Search, Filter, Pagination, Create, Edit, and Delete views.
  - Fine-tune table columns via `#[AdminColumn(label: 'Price', sortable: true, format: 'currency')]`.
  - Customize form inputs via `#[AdminField(type: 'number', placeholder: 'Enter price')]`.
- **Integrated Ecosystem Synergy**:
  - **`LiteORM`**: Powers pagination (`Paginator`), cursor-driven batch streaming, and entity persistence.
  - **`LiteValidate`**: Automatically enforces `#[Required]`, `#[Email]`, etc., rendering inline validation errors with HTTP 422.
  - **`LiteExport`**: Built-in "📥 Export Excel" button streams XLSX spreadsheets directly to the browser with O(1) memory.
  - **`LiteAudit`**: Detail view displays an integrated chronological change history timeline ("Audit Trail") showing field deltas and author tracking.
- **RBAC & Action-Level Permission Guard**:
  - Declaratively protect resources using `#[Authorize(roles: ['admin', 'manager'])]` or granular action gates `#[Authorize(permissions: ['catalog.delete'], action: 'delete')]`.
  - Built-in `PermissionGate` with superadmin bypass, fail-closed security, and pluggable user resolver.
- **Modern Responsive Design**:
  - Embedded CSS stylesheet with automated Dark/Light theme switching (`prefers-color-scheme`).
  - No Webpack, no Vite, and no Node.js runtime required.

---

## 📦 Installation

### Option 1: Standard Composer (via Packagist)
```bash
composer require kzxl/lite-admin
```

### Option 2: Direct from Git Repository (VCS)
To pull directly from the official GitHub repository without waiting for Packagist synchronization, add the VCS repository to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/kzxl/LiteAdmin.git"
        }
    ],
    "require": {
        "kzxl/lite-admin": "^1.1.0"
    }
}
```
Or configure via CLI:
```bash
composer config repositories.lite-admin vcs https://github.com/kzxl/LiteAdmin.git
composer require kzxl/lite-admin:^1.1.0
```

### Option 3: Local Path Repository (Monorepo / Development)
For local development where changes should reflect immediately via symlink:
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../libs/LiteAdmin",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "kzxl/lite-admin": "@dev"
    }
}
```

---

## Usage Example

### 1. Annotate Your Entity

```php
use LiteAdmin\Attribute\{AdminResource, AdminColumn, AdminField, Authorize};
use LiteORM\Attribute\{Entity, Table, Id, AutoIncrement, Column};
use LiteValidate\Attribute\{Required, Range};
use LiteAudit\Attribute\Auditable;

#[Entity]
#[Table('products')]
#[Auditable(events: ['create', 'update', 'delete'], tag: 'catalog')]
#[AdminResource(title: 'Products', slug: 'products', icon: 'package', group: 'Sales', order: 1)]
#[Authorize(roles: ['manager', 'admin'], action: 'create')]
#[Authorize(permissions: ['catalog.delete'], action: 'delete')]
class Product
{
    #[Id, AutoIncrement]
    public int $id;

    #[Required]
    #[Column(length: 150)]
    #[AdminColumn(label: 'Product Name', sortable: true, searchable: true)]
    #[AdminField(label: 'Product Name', placeholder: 'Enter product name...')]
    public string $name;

    #[Range(min: 0)]
    #[Column]
    #[AdminColumn(label: 'Price', format: 'currency')]
    #[AdminField(label: 'Price', type: 'number')]
    public float $price;

    #[Column(nullable: true)]
    #[AdminColumn(label: 'In Stock', format: 'boolean')]
    #[AdminField(label: 'In Stock', type: 'checkbox')]
    public bool $inStock = true;
}
```

### 2. Register Dashboard in Slim 4

```php
use LiteAdmin\AdminDashboard;
use LiteAudit\AuditManager;
use LiteAudit\Bridge\LiteOrmAuditBridge;
use LiteAudit\Storage\PdoAuditStorage;
use LiteORM\EntityManager;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

// 1. Setup LiteORM & LiteAudit
$em = new EntityManager('sqlite:app.db');
$auditStorage = new PdoAuditStorage($em->getConnection()->getWriteConnection());
$auditStorage->createSchemaIfNotExists();
$auditManager = new AuditManager($auditStorage);
LiteOrmAuditBridge::register($em, $auditManager);

// 2. Setup LiteAdmin & User Security Context
$admin = new AdminDashboard($em, $auditManager, '/admin');
$admin->setUserResolver(function ($request): array {
    // Extract current authenticated user roles & permissions from session/JWT
    return [
        'roles' => $request->getAttribute('user_roles', ['manager']),
        'permissions' => $request->getAttribute('user_permissions', ['catalog.read', 'catalog.write']),
    ];
});

$admin->register(Product::class);
$admin->register(User::class);

// 3. Register routes
$admin->registerRoutes($app);

$app->run();
```

Visit `/admin` in your browser to immediately access your production-ready management backoffice!

---

## Security Features

- **RBAC & Permission Guard**: Enforces granular resource and action-level authorization (`view`, `create`, `update`, `delete`, `export`) with `#[Authorize]` attributes and fail-closed defense.
- **Stateless HMAC-SHA256 CSRF**: Enforces timing-safe token verification across all mutating actions (POST/DELETE) without session locking.
- **Mass-Assignment Immunity**: Automatically ignores primary keys and fields marked `readonly: true`.
- **SQL Injection Prevention**: Column sorting (`sort`) is strictly whitelisted against registered resource columns.

---

## Testing

```bash
composer test
```

Runs test suite using PHPUnit 11 with 100% pass rate.

---

## License

MIT License — see [LICENSE](LICENSE) for details.


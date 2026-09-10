# LiteAdmin

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
  - **`LiteExport`**: Built-in "📥 Xuất Excel" button streams XLSX spreadsheets directly to the browser with O(1) memory.
  - **`LiteAudit`**: Detail view displays an integrated chronological change history timeline ("Lịch sử thay đổi") showing field deltas and author tracking.
- **Modern Responsive Design**:
  - Embedded CSS stylesheet with automated Dark/Light theme switching (`prefers-color-scheme`).
  - No Webpack, no Vite, and no Node.js runtime required.

---

## Installation

```bash
composer require kzxl/lite-admin
```

---

## Usage Example

### 1. Annotate Your Entity

```php
use LiteAdmin\Attribute\{AdminResource, AdminColumn, AdminField};
use LiteORM\Attribute\{Entity, Table, Id, AutoIncrement, Column};
use LiteValidate\Attribute\{Required, Range};
use LiteAudit\Attribute\Auditable;

#[Entity]
#[Table('products')]
#[Auditable(events: ['create', 'update', 'delete'], tag: 'catalog')]
#[AdminResource(title: 'Sản phẩm', slug: 'products', icon: 'package', group: 'Kinh doanh', order: 1)]
class Product
{
    #[Id, AutoIncrement]
    public int $id;

    #[Required]
    #[Column(length: 150)]
    #[AdminColumn(label: 'Tên sản phẩm', sortable: true, searchable: true)]
    #[AdminField(label: 'Tên sản phẩm', placeholder: 'Nhập tên sản phẩm...')]
    public string $name;

    #[Range(min: 0)]
    #[Column]
    #[AdminColumn(label: 'Đơn giá', format: 'currency')]
    #[AdminField(label: 'Đơn giá', type: 'number')]
    public float $price;

    #[Column(nullable: true)]
    #[AdminColumn(label: 'Còn hàng', format: 'boolean')]
    #[AdminField(label: 'Còn hàng', type: 'checkbox')]
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

// 2. Setup LiteAdmin
$admin = new AdminDashboard($em, $auditManager, '/admin');
$admin->register(Product::class);
$admin->register(User::class);

// 3. Register routes
$admin->registerRoutes($app);

$app->run();
```

Visit `/admin` in your browser to immediately access your production-ready management backoffice!

---

## Security Features

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


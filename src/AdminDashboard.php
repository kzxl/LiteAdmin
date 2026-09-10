<?php

declare(strict_types=1);

namespace LiteAdmin;

use LiteAdmin\Resource\{ResourceManager, ResourceMetadata};
use LiteAdmin\UI\HtmlRenderer;
use LiteAudit\AuditManager;
use LiteExport\Exporter;
use LiteORM\EntityManager;
use LiteValidate\Validator;
use Nyholm\Psr7\Response;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use RuntimeException;

/**
 * Main coordinator and router for the LiteAdmin auto-CRUD dashboard.
 */
class AdminDashboard
{
    private EntityManager $em;
    private ?AuditManager $auditManager;
    private string $prefix;
    private ResourceManager $resources;
    private HtmlRenderer $renderer;

    public function __construct(
        EntityManager $em,
        ?AuditManager $auditManager = null,
        string $prefix = '/admin',
    ) {
        $this->em = $em;
        $this->auditManager = $auditManager;
        $this->prefix = rtrim($prefix, '/');
        $this->resources = new ResourceManager();
        $this->renderer = new HtmlRenderer($this->prefix, []);
    }

    /**
     * Register an auditable, validatable entity with the admin panel.
     */
    public function register(string $entityClass): self
    {
        $this->resources->register($entityClass);
        // Refresh renderer with updated menu groups
        $this->renderer = new HtmlRenderer($this->prefix, $this->resources->getResourcesByGroup());
        return $this;
    }

    public function getResourceManager(): ResourceManager
    {
        return $this->resources;
    }

    public function getHtmlRenderer(): HtmlRenderer
    {
        return $this->renderer;
    }

    /**
     * Register admin dashboard routes on a Slim 4 App.
     *
     * @param \Slim\App $app
     */
    public function registerRoutes(object $app): void
    {
        $prefix = $this->prefix;

        $app->get($prefix, [$this, 'handleIndex']);
        $app->get($prefix . '/{slug}', [$this, 'handleList']);
        $app->get($prefix . '/{slug}/create', [$this, 'handleCreate']);
        $app->post($prefix . '/{slug}/create', [$this, 'handleStore']);
        $app->get($prefix . '/{slug}/edit/{id}', [$this, 'handleEdit']);
        $app->post($prefix . '/{slug}/edit/{id}', [$this, 'handleUpdate']);
        $app->get($prefix . '/{slug}/detail/{id}', [$this, 'handleDetail']);
        $app->post($prefix . '/{slug}/delete/{id}', [$this, 'handleDelete']);
        $app->get($prefix . '/{slug}/export', [$this, 'handleExport']);
    }

    // ─── Route Handlers ───────────────────────────────────────────

    public function handleIndex(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $resources = $this->resources->getResources();
        if (!empty($resources)) {
            $firstSlug = array_key_first($resources);
            return $response->withHeader('Location', "{$this->prefix}/{$firstSlug}")->withStatus(302);
        }

        $html = $this->renderer->layout('Trang quản trị', '<div class="card">Chưa có Resource nào được đăng ký.</div>');
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function handleList(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $slug = $args['slug'] ?? '';
        $res = $this->resolveResource($slug);
        $queryParams = $request->getQueryParams();

        $page = max(1, (int)($queryParams['page'] ?? 1));
        $perPage = 15;
        $search = trim((string)($queryParams['search'] ?? ''));

        // Query entities using LiteORM QueryBuilder
        $qb = $this->em->query($res->entityClass);

        if ($search !== '') {
            $searchableCols = array_filter($res->columns, fn($c) => $c['searchable']);
            $first = true;
            foreach ($searchableCols as $prop => $col) {
                if ($first) {
                    $qb->where($prop, 'LIKE', "%{$search}%");
                    $first = false;
                } else {
                    $qb->orWhere($prop, 'LIKE', "%{$search}%");
                }
            }
        }

        $paginator = $qb->paginate($page, $perPage);

        $content = $this->renderer->renderList($res, $paginator, $queryParams);
        $html = $this->renderer->layout($res->title, $content, $res->slug);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function handleCreate(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $res = $this->resolveResource($args['slug'] ?? '');
        $content = $this->renderer->renderForm($res);
        $html = $this->renderer->layout("Tạo mới {$res->title}", $content, $res->slug);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function handleStore(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $res = $this->resolveResource($args['slug'] ?? '');
        $data = (array)$request->getParsedBody();

        // 1. Validate using LiteValidate if entity has attributes
        $entityClass = $res->entityClass;
        $errors = $this->validateInput($entityClass, $data);

        if (!empty($errors)) {
            $content = $this->renderer->renderForm($res, null, $errors, $data);
            $html = $this->renderer->layout("Tạo mới {$res->title}", $content, $res->slug, flashError: 'Vui lòng kiểm tra lại các trường dữ liệu.');
            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withStatus(422);
        }

        // 2. Hydrate & Persist via LiteORM
        $entity = new $entityClass();
        $this->hydrateProperties($entity, $res, $data);

        $this->em->persist($entity);
        $this->em->flush();

        return $response->withHeader('Location', "{$this->prefix}/{$res->slug}")->withStatus(302);
    }

    public function handleEdit(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $res = $this->resolveResource($args['slug'] ?? '');
        $id = $args['id'] ?? '';
        $entity = $this->em->find($res->entityClass, $id);

        if (!$entity) {
            return $response->withHeader('Location', "{$this->prefix}/{$res->slug}")->withStatus(302);
        }

        $content = $this->renderer->renderForm($res, $entity);
        $html = $this->renderer->layout("Chỉnh sửa {$res->title}", $content, $res->slug);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function handleUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $res = $this->resolveResource($args['slug'] ?? '');
        $id = $args['id'] ?? '';
        $entity = $this->em->find($res->entityClass, $id);

        if (!$entity) {
            return $response->withHeader('Location', "{$this->prefix}/{$res->slug}")->withStatus(302);
        }

        $data = (array)$request->getParsedBody();
        $errors = $this->validateInput($res->entityClass, $data);

        if (!empty($errors)) {
            $content = $this->renderer->renderForm($res, $entity, $errors, $data);
            $html = $this->renderer->layout("Chỉnh sửa {$res->title}", $content, $res->slug, flashError: 'Vui lòng kiểm tra lại các trường dữ liệu.');
            $response->getBody()->write($html);
            return $response->withHeader('Content-Type', 'text/html; charset=UTF-8')->withStatus(422);
        }

        $this->hydrateProperties($entity, $res, $data);
        $this->em->flush();

        return $response->withHeader('Location', "{$this->prefix}/{$res->slug}")->withStatus(302);
    }

    public function handleDetail(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $res = $this->resolveResource($args['slug'] ?? '');
        $id = $args['id'] ?? '';
        $entity = $this->em->find($res->entityClass, $id);

        if (!$entity) {
            return $response->withHeader('Location', "{$this->prefix}/{$res->slug}")->withStatus(302);
        }

        // Fetch audit history if LiteAudit is attached
        $auditHistory = [];
        if ($this->auditManager !== null) {
            $auditHistory = $this->auditManager->getHistory($res->entityClass, $id, limit: 20);
        }

        $content = $this->renderer->renderDetail($res, $entity, $auditHistory);
        $html = $this->renderer->layout("Chi tiết {$res->title} #{$id}", $content, $res->slug);

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function handleDelete(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $res = $this->resolveResource($args['slug'] ?? '');
        $id = $args['id'] ?? '';
        $entity = $this->em->find($res->entityClass, $id);

        if ($entity) {
            $this->em->remove($entity);
            $this->em->flush();
        }

        return $response->withHeader('Location', "{$this->prefix}/{$res->slug}")->withStatus(302);
    }

    public function handleExport(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $res = $this->resolveResource($args['slug'] ?? '');

        // Stream all entities via LiteORM cursor generator
        $cursor = $this->em->query($res->entityClass)->cursor();

        $rowsGenerator = (function () use ($cursor, $res) {
            foreach ($cursor as $item) {
                $row = [];
                foreach ($res->columns as $prop => $col) {
                    $val = $item->{$prop} ?? null;
                    if ($val instanceof \DateTimeInterface) {
                        $val = $val->format('Y-m-d H:i:s');
                    }
                    $row[] = (string)$val;
                }
                yield $row;
            }
        })();

        $headers = array_column($res->columns, 'label');
        $filename = "{$res->slug}_export_" . date('Ymd_His') . '.xlsx';

        $xlsxBinary = (string)Exporter::xlsx($rowsGenerator, headers: $headers);
        $response->getBody()->write($xlsxBinary);

        return $response
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    private function resolveResource(string $slug): ResourceMetadata
    {
        $res = $this->resources->getResource($slug);
        if (!$res) {
            throw new RuntimeException("Resource with slug '{$slug}' not found.");
        }
        return $res;
    }

    /**
     * @return array<string, string>
     */
    private function validateInput(string $entityClass, array $data): array
    {
        try {
            $result = Validator::validate($data, $entityClass);
            if ($result->isFailed()) {
                $flatErrors = [];
                foreach ($result->getErrors() as $field => $messages) {
                    $flatErrors[$field] = $messages[0] ?? 'Không hợp lệ';
                }
                return $flatErrors;
            }
        } catch (\Throwable $e) {
            // If class does not have validation rules, continue
        }

        return [];
    }

    private function hydrateProperties(object $entity, ResourceMetadata $res, array $data): void
    {
        foreach ($res->fields as $field) {
            $prop = $field['property'];
            if ($prop === $res->primaryKey) {
                continue; // Do not overwrite auto-increment PK
            }

            if (array_key_exists($prop, $data)) {
                $val = $data[$prop];
                $type = $field['type'];

                $entity->{$prop} = match ($type) {
                    'number' => is_numeric($val) ? (str_contains((string)$val, '.') ? (float)$val : (int)$val) : 0,
                    'checkbox' => (bool)$val,
                    'datetime' => !empty($val) ? new \DateTimeImmutable((string)$val) : null,
                    default => (string)$val,
                };
            } elseif ($field['type'] === 'checkbox') {
                $entity->{$prop} = false;
            }
        }
    }
}

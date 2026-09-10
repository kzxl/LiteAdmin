<?php

declare(strict_types=1);

namespace LiteAdmin\UI;

use LiteAdmin\Resource\ResourceMetadata;
use LiteORM\Query\Paginator;

/**
 * Renders modern, responsive HTML admin interfaces.
 */
class HtmlRenderer
{
    private string $prefix;
    /** @var array<string, list<ResourceMetadata>> */
    private array $menuGroups;

    /**
     * @param string $prefix URL prefix (e.g. '/admin')
     * @param array<string, list<ResourceMetadata>> $menuGroups
     */
    public function __construct(string $prefix, array $menuGroups)
    {
        $this->prefix = rtrim($prefix, '/');
        $this->menuGroups = $menuGroups;
    }

    /**
     * Wrap page content inside master layout.
     */
    public function layout(string $title, string $content, ?string $currentSlug = null, ?string $flashSuccess = null, ?string $flashError = null): string
    {
        $css = Theme::getStyles();
        $navHtml = $this->renderSidebar($currentSlug);

        $flashHtml = '';
        if ($flashSuccess) {
            $flashHtml .= '<div class="alert alert-success">' . htmlspecialchars($flashSuccess, ENT_QUOTES) . '</div>';
        }
        if ($flashError) {
            $flashHtml .= '<div class="alert alert-danger">' . htmlspecialchars($flashError, ENT_QUOTES) . '</div>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} | LiteAdmin</title>
    <style>{$css}</style>
</head>
<body>
    <aside class="sidebar">
        <h2>⚡ LiteAdmin</h2>
        {$navHtml}
    </aside>
    <div class="main-wrapper">
        <header class="topbar">
            <strong>{$title}</strong>
            <span style="font-size: 0.875rem; color: var(--text-muted);">Quản trị hệ thống</span>
        </header>
        <main class="content-body">
            {$flashHtml}
            {$content}
        </main>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render DataTable list view.
     *
     * @param array<string, mixed> $queryParams
     */
    public function renderList(ResourceMetadata $res, Paginator $paginator, array $queryParams = []): string
    {
        $createUrl = "{$this->prefix}/{$res->slug}/create";
        $exportUrl = "{$this->prefix}/{$res->slug}/export";
        $currentSearch = htmlspecialchars((string)($queryParams['search'] ?? ''), ENT_QUOTES);

        // Header and Actions
        $html = '<div class="page-header">';
        $html .= '<div><h1>' . htmlspecialchars($res->title) . '</h1>';
        $html .= '<p style="color: var(--text-muted); font-size: 0.875rem;">Tổng số: ' . $paginator->total . ' bản ghi</p></div>';
        $html .= '<div style="display: flex; gap: 0.5rem;">';
        $html .= '<a href="' . $exportUrl . '" class="btn btn-outline">📥 Xuất Excel</a>';
        $html .= '<a href="' . $createUrl . '" class="btn btn-primary">+ Thêm mới</a>';
        $html .= '</div></div>';

        // Search Bar
        $html .= '<div class="card" style="padding: 1rem;">';
        $html .= '<form method="GET" action="' . $this->prefix . '/' . $res->slug . '" style="display: flex; gap: 0.5rem;">';
        $html .= '<input type="text" name="search" value="' . $currentSearch . '" class="form-control" placeholder="Tìm kiếm nhanh..." style="max-width: 350px;">';
        $html .= '<button type="submit" class="btn btn-outline">Tìm</button>';
        if ($currentSearch !== '') {
            $html .= '<a href="' . $this->prefix . '/' . $res->slug . '" class="btn btn-outline">Xóa lọc</a>';
        }
        $html .= '</form></div>';

        // Table
        $html .= '<div class="card"><div class="table-responsive"><table class="data-table"><thead><tr>';
        foreach ($res->columns as $col) {
            $html .= '<th>' . htmlspecialchars($col['label']) . '</th>';
        }
        $html .= '<th style="text-align: right;">Thao tác</th></tr></thead><tbody>';

        if ($paginator->isEmpty()) {
            $html .= '<tr><td colspan="' . (count($res->columns) + 1) . '" style="text-align: center; padding: 2rem; color: var(--text-muted);">Không có dữ liệu</td></tr>';
        } else {
            foreach ($paginator->items as $item) {
                $id = $item->{$res->primaryKey} ?? null;
                $html .= '<tr>';
                foreach ($res->columns as $prop => $col) {
                    $val = $item->{$prop} ?? null;
                    $formatted = $this->formatValue($val, $col['format']);
                    $html .= '<td>' . $formatted . '</td>';
                }

                // Actions
                $editUrl = "{$this->prefix}/{$res->slug}/edit/{$id}";
                $detailUrl = "{$this->prefix}/{$res->slug}/detail/{$id}";
                $deleteUrl = "{$this->prefix}/{$res->slug}/delete/{$id}";

                $html .= '<td style="text-align: right; white-space: nowrap;">';
                $html .= '<a href="' . $detailUrl . '" class="btn btn-sm btn-outline" style="margin-right: 0.25rem;">Xem</a>';
                $html .= '<a href="' . $editUrl . '" class="btn btn-sm btn-outline" style="margin-right: 0.25rem;">Sửa</a>';
                $html .= '<form method="POST" action="' . $deleteUrl . '" style="display: inline;" onsubmit="return confirm(\'Bạn có chắc chắn muốn xóa bản ghi này?\');">';
                $html .= '<button type="submit" class="btn btn-sm btn-danger">Xóa</button>';
                $html .= '</form>';
                $html .= '</td></tr>';
            }
        }

        $html .= '</tbody></table></div>';

        // Pagination controls
        if ($paginator->lastPage > 1) {
            $html .= '<div class="pagination">';
            $html .= '<div>Trang ' . $paginator->currentPage . ' / ' . $paginator->lastPage . '</div>';
            $html .= '<div class="nav-links">';
            if ($paginator->currentPage > 1) {
                $prevUrl = $this->buildPageUrl($res->slug, $paginator->currentPage - 1, $queryParams);
                $html .= '<a href="' . $prevUrl . '" class="btn btn-sm btn-outline">« Trước</a>';
            }
            if ($paginator->hasMorePages()) {
                $nextUrl = $this->buildPageUrl($res->slug, $paginator->currentPage + 1, $queryParams);
                $html .= '<a href="' . $nextUrl . '" class="btn btn-sm btn-outline">Sau »</a>';
            }
            $html .= '</div></div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render Create / Edit Form.
     *
     * @param array<string, string> $errors Field validation errors
     * @param array<string, mixed> $old Old input values
     */
    public function renderForm(ResourceMetadata $res, ?object $entity = null, array $errors = [], array $old = []): string
    {
        $isEdit = ($entity !== null);
        $id = $isEdit ? ($entity->{$res->primaryKey} ?? '') : null;
        $title = $isEdit ? "Chỉnh sửa {$res->title} #{$id}" : "Tạo mới {$res->title}";
        $actionUrl = $isEdit ? "{$this->prefix}/{$res->slug}/edit/{$id}" : "{$this->prefix}/{$res->slug}/create";
        $cancelUrl = "{$this->prefix}/{$res->slug}";

        $html = '<div class="page-header">';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<a href="' . $cancelUrl . '" class="btn btn-outline">Quay lại</a>';
        $html .= '</div>';

        $html .= '<div class="card" style="max-width: 800px;">';
        $html .= '<form method="POST" action="' . $actionUrl . '">';

        foreach ($res->fields as $field) {
            $prop = $field['property'];
            if ($field['readonly'] && !$isEdit) {
                continue; // Skip primary key on create
            }

            $currentVal = $old[$prop] ?? ($entity->{$prop} ?? null);
            $fieldError = $errors[$prop] ?? null;

            $html .= '<div class="form-group">';
            $html .= '<label>' . htmlspecialchars($field['label']);
            if ($field['required']) {
                $html .= ' <span style="color: var(--danger);">*</span>';
            }
            $html .= '</label>';

            // Form control
            $html .= $this->renderInput($field, $currentVal);

            if ($fieldError) {
                $html .= '<div class="error-text">' . htmlspecialchars($fieldError) . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '<div style="margin-top: 2rem; display: flex; gap: 0.75rem;">';
        $html .= '<button type="submit" class="btn btn-primary">Lưu bản ghi</button>';
        $html .= '<a href="' . $cancelUrl . '" class="btn btn-outline">Hủy bỏ</a>';
        $html .= '</div>';

        $html .= '</form></div>';

        return $html;
    }

    /**
     * Render Entity Detail View with Audit Trail Timeline.
     *
     * @param list<\LiteAudit\Model\AuditRecord> $auditHistory
     */
    public function renderDetail(ResourceMetadata $res, object $entity, array $auditHistory = []): string
    {
        $id = $entity->{$res->primaryKey} ?? '';
        $title = "Chi tiết {$res->title} #{$id}";
        $editUrl = "{$this->prefix}/{$res->slug}/edit/{$id}";
        $listUrl = "{$this->prefix}/{$res->slug}";

        $html = '<div class="page-header">';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<div style="display: flex; gap: 0.5rem;">';
        $html .= '<a href="' . $editUrl . '" class="btn btn-primary">Chỉnh sửa</a>';
        $html .= '<a href="' . $listUrl . '" class="btn btn-outline">Danh sách</a>';
        $html .= '</div></div>';

        // Details Card
        $html .= '<div class="card"><table class="data-table"><tbody>';
        foreach ($res->columns as $prop => $col) {
            $val = $entity->{$prop} ?? null;
            $html .= '<tr><th style="width: 220px;">' . htmlspecialchars($col['label']) . '</th>';
            $html .= '<td>' . $this->formatValue($val, $col['format']) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';

        // Audit Trail Card
        if (!empty($auditHistory)) {
            $html .= '<div class="card">';
            $html .= '<h3 style="margin-bottom: 1rem;">📜 Lịch sử thay đổi (Audit Trail)</h3>';
            $html .= '<table class="data-table"><thead><tr><th>Thời gian</th><th>Thao tác</th><th>Người thực hiện</th><th>Nội dung thay đổi</th></tr></thead><tbody>';
            foreach ($auditHistory as $record) {
                $html .= '<tr>';
                $html .= '<td>' . $record->timestamp->format('Y-m-d H:i:s') . '</td>';
                $badgeClass = match ($record->action->value) {
                    'create' => 'badge-success',
                    'delete' => 'badge-danger',
                    default => 'badge',
                };
                $html .= '<td><span class="badge ' . $badgeClass . '">' . strtoupper($record->action->value) . '</span></td>';
                $html .= '<td>' . htmlspecialchars($record->actorId ?? 'Hệ thống') . '</td>';

                // Diff summary
                $diffText = '';
                foreach ($record->diff as $field => $delta) {
                    $diffText .= '<div><strong>' . htmlspecialchars($field) . ':</strong> '
                        . htmlspecialchars((string)($delta['old'] ?? 'null')) . ' ➔ '
                        . htmlspecialchars((string)($delta['new'] ?? 'null')) . '</div>';
                }
                $html .= '<td>' . ($diffText ?: '<em>Không có thay đổi trường</em>') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
        }

        return $html;
    }

    private function renderInput(array $field, mixed $val): string
    {
        $name = htmlspecialchars($field['property']);
        $readonly = $field['readonly'] ? 'readonly style="background: var(--bg-main); cursor: not-allowed;"' : '';
        $req = $field['required'] ? 'required' : '';
        $placeholder = $field['placeholder'] ? 'placeholder="' . htmlspecialchars($field['placeholder']) . '"' : '';

        return match ($field['type']) {
            'textarea' => '<textarea name="' . $name . '" class="form-control" rows="4" ' . $placeholder . ' ' . $readonly . '>' . htmlspecialchars((string)$val) . '</textarea>',
            'checkbox' => '<input type="checkbox" name="' . $name . '" value="1" ' . ($val ? 'checked' : '') . ' ' . $readonly . ' style="width: 18px; height: 18px;">',
            'number' => '<input type="number" step="any" name="' . $name . '" value="' . htmlspecialchars((string)$val) . '" class="form-control" ' . $placeholder . ' ' . $readonly . ' ' . $req . '>',
            'datetime' => '<input type="datetime-local" name="' . $name . '" value="' . ($val instanceof \DateTimeInterface ? $val->format('Y-m-d\TH:i') : htmlspecialchars((string)$val)) . '" class="form-control" ' . $readonly . '>',
            default => '<input type="text" name="' . $name . '" value="' . htmlspecialchars((string)$val) . '" class="form-control" ' . $placeholder . ' ' . $readonly . ' ' . $req . '>',
        };
    }

    private function renderSidebar(?string $currentSlug): string
    {
        $html = '';
        foreach ($this->menuGroups as $group => $resources) {
            $html .= '<div class="group-title">' . htmlspecialchars($group) . '</div>';
            foreach ($resources as $res) {
                $active = ($currentSlug === $res->slug) ? 'active' : '';
                $url = "{$this->prefix}/{$res->slug}";
                $html .= '<a href="' . $url . '" class="' . $active . '">';
                $html .= '<span>' . htmlspecialchars($res->title) . '</span>';
                $html .= '</a>';
            }
        }
        return $html;
    }

    private function formatValue(mixed $val, ?string $format): string
    {
        if ($val === null) {
            return '<span style="color: var(--text-muted);">-</span>';
        }
        if ($val instanceof \DateTimeInterface) {
            return $val->format('Y-m-d H:i:s');
        }
        if (is_bool($val) || $format === 'boolean') {
            return $val ? '<span class="badge badge-success">Có</span>' : '<span class="badge badge-danger">Không</span>';
        }

        return htmlspecialchars((string)$val);
    }

    private function buildPageUrl(string $slug, int $page, array $queryParams): string
    {
        $params = array_merge($queryParams, ['page' => $page]);
        return "{$this->prefix}/{$slug}?" . http_build_query($params);
    }
}

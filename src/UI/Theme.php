<?php

declare(strict_types=1);

namespace LiteAdmin\UI;

/**
 * Built-in zero-dependency CSS styling for LiteAdmin.
 */
final class Theme
{
    public static function getStyles(): string
    {
        return <<<'CSS'
:root {
    --bg-main: #f8fafc;
    --bg-card: #ffffff;
    --border: #e2e8f0;
    --text-main: #0f172a;
    --text-muted: #64748b;
    --primary: #2563eb;
    --primary-hover: #1d4ed8;
    --danger: #ef4444;
    --danger-hover: #dc2626;
    --success: #10b981;
    --warning: #f59e0b;
    --sidebar-bg: #0f172a;
    --sidebar-text: #f1f5f9;
    --sidebar-hover: #1e293b;
    --radius: 8px;
    --font: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
}

@media (prefers-color-scheme: dark) {
    :root {
        --bg-main: #090d16;
        --bg-card: #111827;
        --border: #1f2937;
        --text-main: #f3f4f6;
        --text-muted: #9ca3af;
        --sidebar-bg: #030712;
        --sidebar-hover: #111827;
    }
}

* { box-sizing: border-box; margin: 0; padding: 0; font-family: var(--font); }
body { background: var(--bg-main); color: var(--text-main); display: flex; min-height: 100vh; }

/* Sidebar */
.sidebar { width: 260px; background: var(--sidebar-bg); color: var(--sidebar-text); padding: 1.5rem 1rem; flex-shrink: 0; display: flex; flex-direction: column; }
.sidebar h2 { font-size: 1.25rem; font-weight: 700; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.5rem; color: #60a5fa; }
.sidebar .group-title { font-size: 0.75rem; text-transform: uppercase; color: #94a3b8; margin: 1.25rem 0.5rem 0.5rem; font-weight: 600; letter-spacing: 0.05em; }
.sidebar a { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem; color: #cbd5e1; text-decoration: none; border-radius: var(--radius); font-size: 0.9rem; transition: background 0.15s; }
.sidebar a:hover, .sidebar a.active { background: var(--sidebar-hover); color: #ffffff; }

/* Main layout */
.main-wrapper { flex: 1; display: flex; flex-direction: column; overflow-x: hidden; }
header.topbar { background: var(--bg-card); border-bottom: 1px solid var(--border); padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
.content-body { padding: 2rem; flex: 1; max-width: 1400px; width: 100%; margin: 0 auto; }

/* Cards & Containers */
.card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
.page-header h1 { font-size: 1.5rem; font-weight: 700; }

/* Controls & Buttons */
.btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: var(--radius); text-decoration: none; cursor: pointer; border: 1px solid transparent; transition: all 0.15s; }
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-hover); }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: var(--danger-hover); }
.btn-outline { background: transparent; border-color: var(--border); color: var(--text-main); }
.btn-outline:hover { background: var(--border); }
.btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }

/* Table */
.table-responsive { overflow-x: auto; }
table.data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem; }
table.data-table th, table.data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); }
table.data-table th { background: var(--bg-main); font-weight: 600; color: var(--text-muted); }
table.data-table tr:hover { background: rgba(0,0,0,0.02); }

/* Form inputs */
.form-group { margin-bottom: 1.25rem; }
.form-group label { display: block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.4rem; }
.form-control { width: 100%; padding: 0.6rem 0.75rem; font-size: 0.875rem; border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg-card); color: var(--text-main); }
.form-control:focus { outline: none; border-color: var(--primary); ring: 2px var(--primary); }
.error-text { color: var(--danger); font-size: 0.75rem; margin-top: 0.25rem; }

/* Badges */
.badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
.badge-success { background: #dcfce7; color: #166534; }
.badge-danger { background: #fee2e2; color: #991b1b; }

/* Pagination */
.pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--text-muted); }
.pagination .nav-links { display: flex; gap: 0.5rem; }

/* Flash message */
.alert { padding: 0.75rem 1rem; border-radius: var(--radius); margin-bottom: 1rem; font-size: 0.875rem; }
.alert-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
.alert-danger { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
CSS;
    }
}

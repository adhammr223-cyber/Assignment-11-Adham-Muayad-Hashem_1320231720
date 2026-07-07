<?php
require_once 'db.php';

$pdo = getDB();

$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * RECORDS_PER_PAGE;

$totalStmt = $pdo->query("SELECT COUNT(*) FROM students");
$totalRows = (int)$totalStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / RECORDS_PER_PAGE);
$currentPage = min($currentPage, max(1, $totalPages));
$offset = ($currentPage - 1) * RECORDS_PER_PAGE;

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, phone, major, gpa, enroll_date
    FROM students
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit',  RECORDS_PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,          PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
if (isset($_SESSION['flash'])) {
    unset($_SESSION['flash']);
}
session_start();
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg-base:      #0f0f1a;
            --bg-surface:   #161625;
            --bg-elevated:  #1c1c30;
            --accent:       #ce93d8;
            --accent-dim:   rgba(206,147,216,0.15);
            --accent-hover: #d8a4e0;
            --text-primary: #e0e0e0;
            --text-muted:   #7a7a9a;
            --text-inverse: #1a1a2e;
            --border:       rgba(255,255,255,0.08);
            --border-accent:rgba(206,147,216,0.3);
            --success:      #4caf50;
            --success-dim:  rgba(76,175,80,0.15);
            --error:        #f44336;
            --error-dim:    rgba(244,67,54,0.15);
            --radius:       10px;
            --shadow:       0 4px 24px rgba(0,0,0,0.4);
        }

        html { scroll-behavior: smooth; }

        body {
            background: var(--bg-base);
            color: var(--text-primary);
            font-family: 'Sora', sans-serif;
            font-size: 15px;
            line-height: 1.6;
            min-height: 100vh;
        }

        /* ── Header ── */
        .site-header {
            background: linear-gradient(135deg, #1a0a2e 0%, #16213e 40%, #0f3460 100%);
            border-bottom: 1px solid var(--border-accent);
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }
        .header-inner {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 66px;
            gap: 1.5rem;
        }
        .site-logo {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent);
            letter-spacing: -0.3px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            white-space: nowrap;
        }
        .site-logo svg { flex-shrink: 0; }
        .header-nav {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .nav-link {
            color: var(--text-muted);
            text-decoration: none;
            padding: 0.45rem 0.9rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.2s, background 0.2s;
            white-space: nowrap;
        }
        .nav-link:hover,
        .nav-link.active { color: var(--accent); background: var(--accent-dim); }

        /* ── Layout ── */
        .page-wrap {
            max-width: 1300px;
            margin: 0 auto;
            padding: 2.5rem 2rem 4rem;
        }
        .page-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.5px;
        }
        .page-title span { color: var(--accent); }
        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* ── Alert ── */
        .alert {
            padding: 0.85rem 1.2rem;
            border-radius: var(--radius);
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border: 1px solid;
        }
        .alert-success {
            background: var(--success-dim);
            color: var(--success);
            border-color: rgba(76,175,80,0.3);
        }
        .alert-error {
            background: var(--error-dim);
            color: var(--error);
            border-color: rgba(244,67,54,0.3);
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.55rem 1.2rem;
            border-radius: 7px;
            font-family: 'Sora', sans-serif;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
            white-space: nowrap;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary {
            background: var(--accent);
            color: var(--text-inverse);
            box-shadow: 0 2px 12px rgba(206,147,216,0.3);
        }
        .btn-primary:hover {
            background: var(--accent-hover);
            box-shadow: 0 4px 20px rgba(206,147,216,0.45);
        }
        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }
        .btn-ghost {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { color: var(--text-primary); border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.04); }
        .btn-danger {
            background: rgba(244,67,54,0.15);
            color: var(--error);
            border: 1px solid rgba(244,67,54,0.25);
        }
        .btn-danger:hover { background: rgba(244,67,54,0.25); }
        .btn-edit {
            background: var(--accent-dim);
            color: var(--accent);
            border: 1px solid var(--border-accent);
        }
        .btn-edit:hover { background: rgba(206,147,216,0.22); }

        /* ── Stats bar ── */
        .stats-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.8rem;
            flex-wrap: wrap;
        }
        .stat-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.4rem;
            min-width: 150px;
            flex: 1;
        }
        .stat-label { font-size: 0.78rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; font-weight: 600; }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: #fff; margin-top: 0.25rem; font-family: 'JetBrains Mono', monospace; }
        .stat-value.accent { color: var(--accent); }

        /* ── Table Card ── */
        .table-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        .table-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.4rem;
            border-bottom: 1px solid var(--border);
            gap: 1rem;
            flex-wrap: wrap;
        }
        .table-toolbar-title {
            font-weight: 600;
            font-size: 0.95rem;
            color: #fff;
        }
        .table-responsive { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        thead tr {
            background: var(--bg-elevated);
            border-bottom: 1px solid var(--border);
        }
        th {
            padding: 0.85rem 1.1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--text-muted);
            white-space: nowrap;
        }
        td {
            padding: 0.9rem 1.1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: rgba(255,255,255,0.03); }

        .student-name { font-weight: 600; color: #fff; }
        .student-email { color: var(--text-muted); font-family: 'JetBrains Mono', monospace; font-size: 0.82rem; }

        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-major {
            background: rgba(100,160,255,0.12);
            color: #82b1ff;
            border: 1px solid rgba(100,160,255,0.2);
        }
        .gpa-value {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            font-size: 0.88rem;
        }
        .gpa-high   { color: #69f0ae; }
        .gpa-mid    { color: #ffd740; }
        .gpa-low    { color: var(--error); }

        .action-cell { display: flex; gap: 0.4rem; }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }
        .empty-state svg { opacity: 0.3; margin-bottom: 1rem; }
        .empty-state h3 { color: var(--text-primary); font-size: 1.1rem; margin-bottom: 0.5rem; }

        /* ── Pagination ── */
        .pagination-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.4rem;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .pagination-info { font-size: 0.82rem; color: var(--text-muted); }
        .pagination { display: flex; gap: 0.3rem; align-items: center; }
        .page-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 7px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--text-muted);
            border: 1px solid var(--border);
            background: transparent;
            transition: all 0.15s;
        }
        .page-item:hover { color: var(--accent); border-color: var(--border-accent); background: var(--accent-dim); }
        .page-item.active { background: var(--accent); color: var(--text-inverse); border-color: var(--accent); }
        .page-item.disabled { opacity: 0.35; pointer-events: none; }
        .page-dots { color: var(--text-muted); padding: 0 0.25rem; font-size: 0.85rem; }
    </style>
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <a href="index.php" class="site-logo">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                <path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3z" fill="currentColor"/>
                <path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z" fill="currentColor" opacity=".6"/>
            </svg>
            StudentMS
        </a>
        <nav class="header-nav">
            <a href="index.php"  class="nav-link active">All Students</a>
            <a href="add.php"    class="nav-link">Add Student</a>
            <a href="search.php" class="nav-link">Search</a>
        </nav>
    </div>
</header>

<main class="page-wrap">

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
            <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="page-title-row">
        <div>
            <h1 class="page-title">Student <span>Directory</span></h1>
            <p class="page-subtitle">Manage enrolled students — edit, add, or remove records.</p>
        </div>
        <a href="add.php" class="btn btn-primary">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"/></svg>
            Add Student
        </a>
    </div>

    <?php
        $avgGpaStmt = $pdo->query("SELECT AVG(gpa) FROM students");
        $avgGpa = (float)$avgGpaStmt->fetchColumn();
        $majorStmt = $pdo->query("SELECT COUNT(DISTINCT major) FROM students");
        $totalMajors = (int)$majorStmt->fetchColumn();
    ?>
    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-label">Total Students</div>
            <div class="stat-value accent"><?= number_format($totalRows) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Average GPA</div>
            <div class="stat-value"><?= $totalRows > 0 ? number_format($avgGpa, 2) : '—' ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Majors</div>
            <div class="stat-value"><?= $totalMajors ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Current Page</div>
            <div class="stat-value"><?= $currentPage ?> <span style="font-size:1rem;color:var(--text-muted);">/ <?= max(1,$totalPages) ?></span></div>
        </div>
    </div>

    <div class="table-card">
        <div class="table-toolbar">
            <span class="table-toolbar-title">All Students</span>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                <a href="search.php" class="btn btn-ghost btn-sm">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    Search
                </a>
                <a href="add.php" class="btn btn-primary btn-sm">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"/></svg>
                    New
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Major</th>
                        <th>GPA</th>
                        <th>Phone</th>
                        <th>Enrolled</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="var(--text-muted)"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                <h3>No students found</h3>
                                <p>Get started by adding your first student record.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $index => $s): ?>
                        <?php
                            $rowNum = $offset + $index + 1;
                            $gpaFloat = (float)$s['gpa'];
                            $gpaClass = $gpaFloat >= 3.5 ? 'gpa-high' : ($gpaFloat >= 2.5 ? 'gpa-mid' : 'gpa-low');
                        ?>
                        <tr>
                            <td style="color:var(--text-muted);font-family:'JetBrains Mono',monospace;font-size:0.8rem;"><?= $rowNum ?></td>
                            <td>
                                <div class="student-name"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="student-email"><?= htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td><span class="badge badge-major"><?= htmlspecialchars($s['major'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="gpa-value <?= $gpaClass ?>"><?= htmlspecialchars($s['gpa'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td style="color:var(--text-muted);font-size:0.85rem;"><?= $s['phone'] ? htmlspecialchars($s['phone'], ENT_QUOTES, 'UTF-8') : '<span style="color:var(--border)">—</span>' ?></td>
                            <td style="color:var(--text-muted);font-size:0.85rem;font-family:'JetBrains Mono',monospace;"><?= htmlspecialchars($s['enroll_date'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="action-cell">
                                    <a href="edit.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-edit">Edit</a>
                                    <a href="delete.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-danger">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrap">
            <span class="pagination-info">
                Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + RECORDS_PER_PAGE, $totalRows)) ?> of <?= number_format($totalRows) ?> records
            </span>
            <nav class="pagination">
                <a href="?page=<?= $currentPage - 1 ?>" class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
                </a>
                <?php
                    $range   = 2;
                    $start   = max(1, $currentPage - $range);
                    $end     = min($totalPages, $currentPage + $range);
                    if ($start > 1): ?>
                        <a href="?page=1" class="page-item">1</a>
                        <?php if ($start > 2): ?><span class="page-dots">...</span><?php endif; ?>
                    <?php endif;
                    for ($i = $start; $i <= $end; $i++): ?>
                        <a href="?page=<?= $i ?>" class="page-item <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor;
                    if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?><span class="page-dots">...</span><?php endif; ?>
                        <a href="?page=<?= $totalPages ?>" class="page-item"><?= $totalPages ?></a>
                    <?php endif; ?>
                <a href="?page=<?= $currentPage + 1 ?>" class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
                </a>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</main>
</body>
</html>

<?php
require_once 'db.php';

$pdo = getDB();

$query    = trim($_GET['q'] ?? '');
$field    = $_GET['field'] ?? 'all';
$students = [];
$searched = false;
$totalFound = 0;

$allowedFields = ['all', 'first_name', 'last_name', 'email', 'major'];
if (!in_array($field, $allowedFields, true)) {
    $field = 'all';
}

if ($query !== '') {
    $searched = true;
    $like     = '%' . $query . '%';

    if ($field === 'all') {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, phone, major, gpa, enroll_date
            FROM students
            WHERE first_name  LIKE :q1
               OR last_name   LIKE :q2
               OR email       LIKE :q3
               OR major       LIKE :q4
            ORDER BY last_name ASC, first_name ASC
        ");
        $stmt->execute([':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, phone, major, gpa, enroll_date
            FROM students
            WHERE {$field} LIKE :q
            ORDER BY last_name ASC, first_name ASC
        ");
        $stmt->execute([':q' => $like]);
    }

    $students   = $stmt->fetchAll();
    $totalFound = count($students);
}

function highlightMatch(string $text, string $query): string {
    if ($query === '') return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $safe    = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $safeQ   = preg_quote(htmlspecialchars($query, ENT_QUOTES, 'UTF-8'), '/');
    return preg_replace('/(' . $safeQ . ')/i', '<mark>$1</mark>', $safe);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Students — StudentMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --bg-base:       #0f0f1a;
            --bg-surface:    #161625;
            --bg-elevated:   #1c1c30;
            --accent:        #ce93d8;
            --accent-dim:    rgba(206,147,216,0.15);
            --accent-hover:  #d8a4e0;
            --text-primary:  #e0e0e0;
            --text-muted:    #7a7a9a;
            --text-inverse:  #1a1a2e;
            --border:        rgba(255,255,255,0.08);
            --border-accent: rgba(206,147,216,0.3);
            --border-focus:  rgba(206,147,216,0.6);
            --input-bg:      rgba(255,255,255,0.05);
            --error:         #f44336;
            --error-dim:     rgba(244,67,54,0.15);
            --radius:        10px;
            --shadow:        0 4px 24px rgba(0,0,0,0.4);
        }
        html { scroll-behavior: smooth; }
        body { background: var(--bg-base); color: var(--text-primary); font-family: 'Sora', sans-serif; font-size: 15px; line-height: 1.6; min-height: 100vh; }

        .site-header { background: linear-gradient(135deg, #1a0a2e 0%, #16213e 40%, #0f3460 100%); border-bottom: 1px solid var(--border-accent); padding: 0 2rem; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 20px rgba(0,0,0,0.5); }
        .header-inner { max-width: 1300px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 66px; gap: 1.5rem; }
        .site-logo { font-size: 1.2rem; font-weight: 700; color: var(--accent); letter-spacing: -0.3px; text-decoration: none; display: flex; align-items: center; gap: 0.6rem; }
        .header-nav { display: flex; align-items: center; gap: 0.4rem; }
        .nav-link { color: var(--text-muted); text-decoration: none; padding: 0.45rem 0.9rem; border-radius: 6px; font-size: 0.875rem; font-weight: 500; transition: color 0.2s, background 0.2s; }
        .nav-link:hover, .nav-link.active { color: var(--accent); background: var(--accent-dim); }

        .page-wrap { max-width: 1300px; margin: 0 auto; padding: 2.5rem 2rem 4rem; }
        .breadcrumb { display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.8rem; }
        .breadcrumb a { color: var(--text-muted); text-decoration: none; transition: color 0.15s; }
        .breadcrumb a:hover { color: var(--accent); }
        .breadcrumb-sep { opacity: 0.4; }

        .page-title { font-size: 1.6rem; font-weight: 700; color: #fff; letter-spacing: -0.5px; margin-bottom: 0.3rem; }
        .page-title span { color: var(--accent); }
        .page-subtitle { color: var(--text-muted); font-size: 0.875rem; margin-bottom: 2rem; }

        .search-panel { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.6rem; margin-bottom: 2rem; box-shadow: var(--shadow); }
        .search-row { display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap; }
        .search-input-wrap { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: 0.4rem; }
        .search-input-label { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .search-input-group { position: relative; display: flex; align-items: center; }
        .search-icon { position: absolute; left: 0.85rem; color: var(--text-muted); pointer-events: none; display: flex; }
        .search-input { background: var(--input-bg); border: 1px solid var(--border); border-radius: 7px; color: var(--text-primary); font-family: 'Sora', sans-serif; font-size: 0.95rem; padding: 0.7rem 0.9rem 0.7rem 2.5rem; width: 100%; outline: none; transition: border-color 0.2s, background 0.2s, box-shadow 0.2s; -webkit-appearance: none; }
        .search-input:focus { border-color: var(--border-focus); background: rgba(255,255,255,0.08); box-shadow: 0 0 0 3px rgba(206,147,216,0.1); }
        .search-input::placeholder { color: var(--text-muted); opacity: 0.7; }

        .filter-wrap { display: flex; flex-direction: column; gap: 0.4rem; }
        .filter-label { font-size: 0.78rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-select { background: var(--input-bg); border: 1px solid var(--border); border-radius: 7px; color: var(--text-primary); font-family: 'Sora', sans-serif; font-size: 0.875rem; padding: 0.7rem 2rem 0.7rem 0.9rem; outline: none; cursor: pointer; -webkit-appearance: none; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='%237a7a9a'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 0.7rem center; min-width: 170px; transition: border-color 0.2s; }
        .filter-select:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px rgba(206,147,216,0.1); }

        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.7rem 1.4rem; border-radius: 7px; font-family: 'Sora', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: transform 0.15s, box-shadow 0.15s, background 0.15s; }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--accent); color: var(--text-inverse); box-shadow: 0 2px 12px rgba(206,147,216,0.3); }
        .btn-primary:hover { background: var(--accent-hover); box-shadow: 0 4px 20px rgba(206,147,216,0.45); }
        .btn-sm { padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 6px; }
        .btn-ghost { background: transparent; color: var(--text-muted); border: 1px solid var(--border); }
        .btn-ghost:hover { color: var(--text-primary); border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.04); }
        .btn-edit { background: var(--accent-dim); color: var(--accent); border: 1px solid var(--border-accent); }
        .btn-edit:hover { background: rgba(206,147,216,0.22); }
        .btn-danger { background: rgba(244,67,54,0.15); color: var(--error); border: 1px solid rgba(244,67,54,0.25); }
        .btn-danger:hover { background: rgba(244,67,54,0.25); }

        .results-meta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem; }
        .results-count { font-size: 0.875rem; color: var(--text-muted); }
        .results-count strong { color: var(--accent); }
        .results-query { font-family: 'JetBrains Mono', monospace; background: var(--accent-dim); color: var(--accent); border: 1px solid var(--border-accent); border-radius: 5px; padding: 0.15rem 0.5rem; font-size: 0.82rem; }

        .table-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); }
        .table-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.4rem; border-bottom: 1px solid var(--border); gap: 1rem; flex-wrap: wrap; }
        .table-toolbar-title { font-weight: 600; font-size: 0.95rem; color: #fff; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        thead tr { background: var(--bg-elevated); border-bottom: 1px solid var(--border); }
        th { padding: 0.85rem 1.1rem; text-align: left; font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.7px; color: var(--text-muted); white-space: nowrap; }
        td { padding: 0.9rem 1.1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr { transition: background 0.15s; }
        tbody tr:hover { background: rgba(255,255,255,0.03); }

        .student-name { font-weight: 600; color: #fff; }
        .student-email { color: var(--text-muted); font-family: 'JetBrains Mono', monospace; font-size: 0.82rem; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-major { background: rgba(100,160,255,0.12); color: #82b1ff; border: 1px solid rgba(100,160,255,0.2); }
        .gpa-value { font-family: 'JetBrains Mono', monospace; font-weight: 600; font-size: 0.88rem; }
        .gpa-high   { color: #69f0ae; }
        .gpa-mid    { color: #ffd740; }
        .gpa-low    { color: var(--error); }
        .action-cell { display: flex; gap: 0.4rem; }

        mark { background: rgba(206,147,216,0.3); color: var(--accent); border-radius: 3px; padding: 0 2px; font-style: normal; }

        .state-box { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
        .state-box svg { opacity: 0.3; margin-bottom: 1rem; }
        .state-box h3 { color: var(--text-primary); font-size: 1.1rem; margin-bottom: 0.5rem; }
        .state-box p { font-size: 0.875rem; }
    </style>
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <a href="index.php" class="site-logo">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3z" fill="currentColor"/><path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z" fill="currentColor" opacity=".6"/></svg>
            StudentMS
        </a>
        <nav class="header-nav">
            <a href="index.php"  class="nav-link">All Students</a>
            <a href="add.php"    class="nav-link">Add Student</a>
            <a href="search.php" class="nav-link active">Search</a>
        </nav>
    </div>
</header>

<main class="page-wrap">

    <div class="breadcrumb">
        <a href="index.php">Dashboard</a>
        <span class="breadcrumb-sep">/</span>
        <span>Search Students</span>
    </div>

    <h1 class="page-title">Search <span>Students</span></h1>
    <p class="page-subtitle">Filter records by name, email address, or academic major using live queries.</p>

    <div class="search-panel">
        <form method="GET" action="search.php" role="search">
            <div class="search-row">
                <div class="search-input-wrap">
                    <label class="search-input-label" for="q">Search Query</label>
                    <div class="search-input-group">
                        <span class="search-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                        </span>
                        <input type="search" id="q" name="q" class="search-input" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. John, Computer Science, student@uni.edu..." autofocus autocomplete="off">
                    </div>
                </div>

                <div class="filter-wrap">
                    <label class="filter-label" for="field">Search In</label>
                    <select id="field" name="field" class="filter-select">
                        <option value="all"        <?= $field === 'all'        ? 'selected' : '' ?>>All Fields</option>
                        <option value="first_name" <?= $field === 'first_name' ? 'selected' : '' ?>>First Name</option>
                        <option value="last_name"  <?= $field === 'last_name'  ? 'selected' : '' ?>>Last Name</option>
                        <option value="email"      <?= $field === 'email'      ? 'selected' : '' ?>>Email</option>
                        <option value="major"      <?= $field === 'major'      ? 'selected' : '' ?>>Major</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    Search
                </button>

                <?php if ($query !== ''): ?>
                    <a href="search.php" class="btn btn-ghost">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($searched): ?>

        <div class="results-meta">
            <span class="results-count">
                Found <strong><?= number_format($totalFound) ?></strong>
                <?= $totalFound === 1 ? 'result' : 'results' ?>
                for <span class="results-query"><?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($field !== 'all'): ?>
                    in <span class="results-query"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $field)), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </span>
            <a href="index.php" class="btn btn-ghost btn-sm">← Back to All Students</a>
        </div>

        <div class="table-card">
            <div class="table-toolbar">
                <span class="table-toolbar-title">Search Results</span>
                <a href="add.php" class="btn btn-primary btn-sm">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z"/></svg>
                    New
                </a>
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
                                <div class="state-box">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="var(--text-muted)"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                                    <h3>No matching students</h3>
                                    <p>Try a different keyword or search in all fields.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $index => $s): ?>
                            <?php
                                $gpaFloat = (float)$s['gpa'];
                                $gpaClass = $gpaFloat >= 3.5 ? 'gpa-high' : ($gpaFloat >= 2.5 ? 'gpa-mid' : 'gpa-low');
                            ?>
                            <tr>
                                <td style="color:var(--text-muted);font-family:'JetBrains Mono',monospace;font-size:0.8rem;"><?= $index + 1 ?></td>
                                <td>
                                    <div class="student-name"><?= highlightMatch($s['first_name'] . ' ' . $s['last_name'], $query) ?></div>
                                    <div class="student-email"><?= highlightMatch($s['email'], $query) ?></div>
                                </td>
                                <td><span class="badge badge-major"><?= highlightMatch($s['major'], $query) ?></span></td>
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
        </div>

    <?php else: ?>

        <div class="table-card">
            <div class="state-box">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="var(--text-muted)"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                <h3>Start your search</h3>
                <p>Enter a keyword above to find students by name, email, or major.</p>
            </div>
        </div>

    <?php endif; ?>

</main>
</body>
</html>

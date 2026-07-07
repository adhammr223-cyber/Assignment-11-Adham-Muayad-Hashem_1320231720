<?php
require_once 'db.php';
session_start();

$pdo = getDB();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
   ?? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: index.php');
    exit;
}

$fetchStmt = $pdo->prepare("SELECT id, first_name, last_name, email, major, gpa, enroll_date FROM students WHERE id = :id LIMIT 1");
$fetchStmt->execute([':id' => $id]);
$student = $fetchStmt->fetch();

if (!$student) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Student record not found or already deleted.'];
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $csrfOk = isset($_POST['csrf_token']) && $_POST['csrf_token'] === ($_SESSION['csrf_token'] ?? '');
    $confirmed = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === '1';

    if (!$csrfOk || !$confirmed || $postId !== $id) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Deletion aborted — invalid request.'];
        header('Location: index.php');
        exit;
    }

    $del = $pdo->prepare("DELETE FROM students WHERE id = :id");
    $del->execute([':id' => $id]);

    $name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8');
    $_SESSION['flash'] = ['type' => 'success', 'message' => "Student \"{$name}\" has been permanently deleted."];
    header('Location: index.php');
    exit;
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Student — StudentMS</title>
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
            --text-primary:  #e0e0e0;
            --text-muted:    #7a7a9a;
            --text-inverse:  #1a1a2e;
            --border:        rgba(255,255,255,0.08);
            --border-accent: rgba(206,147,216,0.3);
            --error:         #f44336;
            --error-dim:     rgba(244,67,54,0.12);
            --error-border:  rgba(244,67,54,0.3);
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
        .nav-link:hover { color: var(--accent); background: var(--accent-dim); }

        .page-wrap { max-width: 600px; margin: 0 auto; padding: 3rem 2rem 4rem; }
        .breadcrumb { display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: var(--text-muted); margin-bottom: 2rem; }
        .breadcrumb a { color: var(--text-muted); text-decoration: none; transition: color 0.15s; }
        .breadcrumb a:hover { color: var(--accent); }
        .breadcrumb-sep { opacity: 0.4; }

        /* Warning banner */
        .danger-banner {
            background: var(--error-dim);
            border: 1px solid var(--error-border);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 1.8rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        .danger-banner-icon {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(244,67,54,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .danger-banner-text h3 { color: var(--error); font-size: 1rem; font-weight: 700; margin-bottom: 0.35rem; }
        .danger-banner-text p { color: var(--text-muted); font-size: 0.875rem; }

        /* Record card */
        .record-card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 1.8rem;
        }
        .record-card-header {
            padding: 1rem 1.4rem;
            background: var(--bg-elevated);
            border-bottom: 1px solid var(--border);
        }
        .record-card-header h2 { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.6px; }
        .record-fields { padding: 1.2rem 1.4rem; display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem 1.5rem; }
        .record-field-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.15rem; }
        .record-field-value { font-size: 0.9rem; color: #fff; font-weight: 500; }
        .record-field-value.mono { font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; color: var(--text-primary); }
        .record-field-full { grid-column: 1 / -1; }

        /* Confirmation form */
        .confirm-form {
            background: var(--bg-surface);
            border: 1px solid var(--error-border);
            border-radius: var(--radius);
            padding: 1.5rem;
        }
        .confirm-form h3 { font-size: 0.95rem; font-weight: 600; color: #fff; margin-bottom: 0.6rem; }
        .confirm-form p { font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.4rem; }
        .confirm-form p strong { color: var(--error); }

        .form-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }

        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.65rem 1.4rem; border-radius: 7px; font-family: 'Sora', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: transform 0.15s, box-shadow 0.15s, background 0.15s; }
        .btn:active { transform: translateY(1px); }
        .btn-danger { background: var(--error); color: #fff; box-shadow: 0 2px 12px rgba(244,67,54,0.3); }
        .btn-danger:hover { background: #e53935; box-shadow: 0 4px 20px rgba(244,67,54,0.45); }
        .btn-ghost { background: transparent; color: var(--text-muted); border: 1px solid var(--border); }
        .btn-ghost:hover { color: var(--text-primary); border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.04); }
        .btn-accent { background: var(--accent); color: var(--text-inverse); }
        .btn-accent:hover { background: #d8a4e0; }
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
            <a href="search.php" class="nav-link">Search</a>
        </nav>
    </div>
</header>

<main class="page-wrap">

    <div class="breadcrumb">
        <a href="index.php">Dashboard</a>
        <span class="breadcrumb-sep">/</span>
        <span>Delete Student</span>
    </div>

    <div class="danger-banner">
        <div class="danger-banner-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="#f44336">
                <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
            </svg>
        </div>
        <div class="danger-banner-text">
            <h3>Permanent Deletion Warning</h3>
            <p>This action cannot be undone. The student record and all associated data will be permanently removed from the database.</p>
        </div>
    </div>

    <div class="record-card">
        <div class="record-card-header">
            <h2>Record to be Deleted</h2>
        </div>
        <div class="record-fields">
            <div>
                <div class="record-field-label">Full Name</div>
                <div class="record-field-value"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
                <div class="record-field-label">Student ID</div>
                <div class="record-field-value mono">#<?= (int)$student['id'] ?></div>
            </div>
            <div class="record-field-full">
                <div class="record-field-label">Email Address</div>
                <div class="record-field-value mono"><?= htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
                <div class="record-field-label">Major</div>
                <div class="record-field-value"><?= htmlspecialchars($student['major'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
                <div class="record-field-label">GPA</div>
                <div class="record-field-value mono"><?= htmlspecialchars($student['gpa'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
                <div class="record-field-label">Enrollment Date</div>
                <div class="record-field-value mono"><?= htmlspecialchars($student['enroll_date'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
    </div>

    <div class="confirm-form">
        <h3>Confirm Deletion</h3>
        <p>You are about to permanently delete the record for <strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8') ?></strong>. This action is irreversible.</p>

        <div class="form-actions">
            <form method="POST" action="delete.php?id=<?= (int)$id ?>" style="display:inline;">
                <input type="hidden" name="csrf_token"     value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id"             value="<?= (int)$id ?>">
                <input type="hidden" name="confirm_delete" value="1">
                <button type="submit" class="btn btn-danger">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    Yes, Delete Permanently
                </button>
            </form>
            <a href="edit.php?id=<?= (int)$id ?>" class="btn btn-accent">Edit Instead</a>
            <a href="index.php" class="btn btn-ghost">Cancel</a>
        </div>
    </div>

</main>
</body>
</html>

<?php
require_once 'db.php';
session_start();

$pdo = getDB();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: index.php');
    exit;
}

$fetchStmt = $pdo->prepare("SELECT * FROM students WHERE id = :id LIMIT 1");
$fetchStmt->execute([':id' => $id]);
$student = $fetchStmt->fetch();

if (!$student) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Student record not found.'];
    header('Location: index.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        $postId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($postId !== $id) {
            $errors[] = 'Record ID mismatch.';
        } else {
            $result = validateStudentInput($_POST);
            $errors = $result['errors'];
            $data   = $result['data'];

            if (empty($errors)) {
                $dupStmt = $pdo->prepare("SELECT id FROM students WHERE email = :email AND id != :id");
                $dupStmt->execute([':email' => $data['email'], ':id' => $id]);
                if ($dupStmt->fetch()) {
                    $errors[] = 'Another student is already registered with that email address.';
                } else {
                    $upd = $pdo->prepare("
                        UPDATE students
                        SET first_name  = :first_name,
                            last_name   = :last_name,
                            email       = :email,
                            phone       = :phone,
                            major       = :major,
                            gpa         = :gpa,
                            enroll_date = :enroll_date
                        WHERE id = :id
                    ");
                    $upd->execute([
                        ':first_name'  => $data['first_name'],
                        ':last_name'   => $data['last_name'],
                        ':email'       => $data['email'],
                        ':phone'       => $data['phone'],
                        ':major'       => $data['major'],
                        ':gpa'         => $data['gpa'],
                        ':enroll_date' => $data['enroll_date'],
                        ':id'          => $id,
                    ]);
                    $student = array_merge($student, $data);
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Student record updated successfully.'];
                    header('Location: index.php');
                    exit;
                }
            }
            if (!empty($errors)) {
                $student = array_merge($student, $data);
            }
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

function val(string $key, array $s): string {
    return htmlspecialchars($s[$key] ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student — StudentMS</title>
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
            --success:       #4caf50;
            --success-dim:   rgba(76,175,80,0.15);
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

        .page-wrap { max-width: 820px; margin: 0 auto; padding: 2.5rem 2rem 4rem; }
        .breadcrumb { display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.8rem; }
        .breadcrumb a { color: var(--text-muted); text-decoration: none; transition: color 0.15s; }
        .breadcrumb a:hover { color: var(--accent); }
        .breadcrumb-sep { opacity: 0.4; }

        .page-title { font-size: 1.6rem; font-weight: 700; color: #fff; letter-spacing: -0.5px; margin-bottom: 0.3rem; }
        .page-title span { color: var(--accent); }
        .page-subtitle { color: var(--text-muted); font-size: 0.875rem; margin-bottom: 2rem; }
        .student-id-tag { display: inline-block; background: var(--accent-dim); color: var(--accent); border: 1px solid var(--border-accent); border-radius: 6px; padding: 0.15rem 0.5rem; font-size: 0.78rem; font-family: 'JetBrains Mono', monospace; font-weight: 600; margin-left: 0.5rem; vertical-align: middle; }

        .alert { padding: 0.85rem 1.2rem; border-radius: var(--radius); font-size: 0.88rem; font-weight: 500; margin-bottom: 1.5rem; border: 1px solid; }
        .alert-success { background: var(--success-dim); color: var(--success); border-color: rgba(76,175,80,0.3); }
        .alert-error { background: var(--error-dim); color: var(--error); border-color: rgba(244,67,54,0.3); }
        .alert ul { margin: 0.4rem 0 0 1.2rem; }
        .alert li { margin-top: 0.2rem; }

        .form-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); }
        .form-card-header { padding: 1.4rem 1.8rem; border-bottom: 1px solid var(--border); background: var(--bg-elevated); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        .form-card-header h2 { font-size: 1rem; font-weight: 600; color: #fff; }
        .meta-info { font-size: 0.78rem; color: var(--text-muted); font-family: 'JetBrains Mono', monospace; }
        .form-body { padding: 1.8rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem 1.5rem; }
        .form-grid .full { grid-column: 1 / -1; }
        .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .form-label { font-size: 0.82rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-label .required { color: var(--accent); margin-left: 2px; }
        .form-control { background: var(--input-bg); border: 1px solid var(--border); border-radius: 7px; color: var(--text-primary); font-family: 'Sora', sans-serif; font-size: 0.9rem; padding: 0.65rem 0.9rem; width: 100%; transition: border-color 0.2s, background 0.2s, box-shadow 0.2s; outline: none; -webkit-appearance: none; }
        .form-control:focus { border-color: var(--border-focus); background: rgba(255,255,255,0.08); box-shadow: 0 0 0 3px rgba(206,147,216,0.1); }
        .form-control::placeholder { color: var(--text-muted); opacity: 0.6; }
        .form-hint { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem; }
        .form-footer { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 1.2rem 1.8rem; border-top: 1px solid var(--border); background: var(--bg-elevated); flex-wrap: wrap; }
        .form-footer-right { display: flex; gap: 0.75rem; }

        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.4rem; border-radius: 7px; font-family: 'Sora', sans-serif; font-size: 0.875rem; font-weight: 600; cursor: pointer; text-decoration: none; border: none; transition: transform 0.15s, box-shadow 0.15s, background 0.15s; }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: var(--accent); color: var(--text-inverse); box-shadow: 0 2px 12px rgba(206,147,216,0.3); }
        .btn-primary:hover { background: var(--accent-hover); box-shadow: 0 4px 20px rgba(206,147,216,0.45); }
        .btn-ghost { background: transparent; color: var(--text-muted); border: 1px solid var(--border); }
        .btn-ghost:hover { color: var(--text-primary); border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.04); }
        .btn-danger-outline { background: transparent; color: var(--error); border: 1px solid rgba(244,67,54,0.3); }
        .btn-danger-outline:hover { background: var(--error-dim); border-color: rgba(244,67,54,0.5); }

        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } .form-grid .full { grid-column: 1; } }
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
        <span>Edit Student</span>
    </div>

    <h1 class="page-title">
        Edit <span>Student</span>
        <span class="student-id-tag">ID #<?= (int)$student['id'] ?></span>
    </h1>
    <p class="page-subtitle">
        Modifying record for <strong style="color:#fff"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8') ?></strong>
    </p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Please correct the following errors:</strong>
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-card-header">
            <h2>Student Information</h2>
            <div class="meta-info">
                Created: <?= htmlspecialchars($student['created_at'], ENT_QUOTES, 'UTF-8') ?>
                &nbsp;|&nbsp;
                Updated: <?= htmlspecialchars($student['updated_at'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        </div>

        <form method="POST" action="edit.php?id=<?= (int)$id ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="form-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control" value="<?= val('first_name', $student) ?>" maxlength="100" placeholder="First name" autocomplete="given-name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control" value="<?= val('last_name', $student) ?>" maxlength="100" placeholder="Last name" autocomplete="family-name" required>
                    </div>

                    <div class="form-group full">
                        <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= val('email', $student) ?>" maxlength="255" placeholder="Email address" autocomplete="email" required>
                        <span class="form-hint">Changing the email will update the unique identifier.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="<?= val('phone', $student) ?>" maxlength="30" placeholder="Phone number" autocomplete="tel">
                        <span class="form-hint">Optional field.</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="major">Major / Program <span class="required">*</span></label>
                        <input type="text" id="major" name="major" class="form-control" value="<?= val('major', $student) ?>" maxlength="150" placeholder="e.g. Computer Science" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="gpa">GPA <span class="required">*</span></label>
                        <input type="number" id="gpa" name="gpa" class="form-control" value="<?= val('gpa', $student) ?>" min="0.00" max="4.00" step="0.01" placeholder="0.00" required>
                        <span class="form-hint">Range: 0.00 to 4.00</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="enroll_date">Enrollment Date <span class="required">*</span></label>
                        <input type="date" id="enroll_date" name="enroll_date" class="form-control" value="<?= val('enroll_date', $student) ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <a href="delete.php?id=<?= (int)$id ?>" class="btn btn-danger-outline">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                    Delete Record
                </a>
                <div class="form-footer-right">
                    <a href="index.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm2 16H5V5h11.17L19 7.83V19zm-7-7c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3zM6 6h9v4H6z"/></svg>
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>

</main>
</body>
</html>

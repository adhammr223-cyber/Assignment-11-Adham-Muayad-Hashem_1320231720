<?php
/**
 * Database configuration and PDO connection bootstrap.
 * All database interactions across the system use this singleton connection.
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_NAME', 'student_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('RECORDS_PER_PAGE', 10);

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(renderFatalError('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')));
        }
    }

    return $pdo;
}

/**
 * Bootstraps the required database schema on first run.
 */
function initSchema(): void
{
    $pdo = getDB();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS students (
            id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            first_name  VARCHAR(100)    NOT NULL,
            last_name   VARCHAR(100)    NOT NULL,
            email       VARCHAR(255)    NOT NULL UNIQUE,
            phone       VARCHAR(30)     NULL,
            major       VARCHAR(150)    NOT NULL,
            gpa         DECIMAL(3,2)    NOT NULL DEFAULT 0.00,
            enroll_date DATE            NOT NULL,
            created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_email (email),
            INDEX idx_major (major),
            INDEX idx_last_name (last_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

function renderFatalError(string $message): string
{
    return <<<HTML
    <!DOCTYPE html><html><head><meta charset="UTF-8"><title>System Error</title>
    <style>body{background:#0f0f1a;color:#f44336;font-family:monospace;display:flex;
    align-items:center;justify-content:center;height:100vh;margin:0;}
    .box{border:1px solid #f44336;padding:2rem 3rem;border-radius:8px;}</style>
    </head><body><div class="box"><h2>System Error</h2><p>{$message}</p></div></body></html>
    HTML;
}

/**
 * Sanitizes and validates student form input.
 * Returns ['data' => [...], 'errors' => [...]]
 */
function validateStudentInput(array $post): array
{
    $errors = [];
    $data   = [];

    $data['first_name'] = trim($post['first_name'] ?? '');
    $data['last_name']  = trim($post['last_name']  ?? '');
    $data['email']      = trim(strtolower($post['email'] ?? ''));
    $data['phone']      = trim($post['phone']      ?? '') ?: null;
    $data['major']      = trim($post['major']      ?? '');
    $data['gpa']        = trim($post['gpa']        ?? '');
    $data['enroll_date']= trim($post['enroll_date']?? '');

    if ($data['first_name'] === '' || strlen($data['first_name']) > 100) {
        $errors[] = 'First name is required and must not exceed 100 characters.';
    }
    if ($data['last_name'] === '' || strlen($data['last_name']) > 100) {
        $errors[] = 'Last name is required and must not exceed 100 characters.';
    }
    if ($data['email'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if ($data['phone'] !== null && !preg_match('/^[+\d\s\-().]{7,30}$/', $data['phone'])) {
        $errors[] = 'Phone number format is invalid.';
    }
    if ($data['major'] === '' || strlen($data['major']) > 150) {
        $errors[] = 'Major is required and must not exceed 150 characters.';
    }
    if (!is_numeric($data['gpa']) || (float)$data['gpa'] < 0.00 || (float)$data['gpa'] > 4.00) {
        $errors[] = 'GPA must be a number between 0.00 and 4.00.';
    } else {
        $data['gpa'] = number_format((float)$data['gpa'], 2, '.', '');
    }
    if ($data['enroll_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['enroll_date'])) {
        $errors[] = 'Enrollment date is required (YYYY-MM-DD).';
    }

    return ['data' => $data, 'errors' => $errors];
}

initSchema();
<?php
require_once __DIR__ . '/../config.php';

function get_db(): PDO
{
    static $db = null;

    if ($db === null) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $db = new PDO(DB_DSN, DB_USER, DB_PASS, $options);

        if (stripos(DB_DSN, 'sqlite:') === 0) {
            $db->exec('PRAGMA foreign_keys = ON');
        }
    }

    return $db;
}

function ensure_user_profile_photo_column(): void
{
    $db = get_db();

    try {
        $columns = $db->query('PRAGMA table_info(users)')->fetchAll();
    } catch (Exception $e) {
        return;
    }

    $hasColumn = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'profile_photo') {
            $hasColumn = true;
            break;
        }
    }

    if (!$hasColumn) {
        $db->exec('ALTER TABLE users ADD COLUMN profile_photo TEXT');
    }
}

function ensure_book_pdf_column(): void
{
    $db = get_db();

    try {
        $columns = $db->query('PRAGMA table_info(books)')->fetchAll();
    } catch (Exception $e) {
        return;
    }

    $hasColumn = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'pdf_path') {
            $hasColumn = true;
            break;
        }
    }

    if (!$hasColumn) {
        $db->exec('ALTER TABLE books ADD COLUMN pdf_path TEXT');
    }
}

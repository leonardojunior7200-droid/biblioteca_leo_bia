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

function ensure_user_turma_column(): void
{
    $db = get_db();

    try {
        $columns = $db->query('PRAGMA table_info(users)')->fetchAll();
    } catch (Exception $e) {
        return;
    }

    $hasColumn = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'turma') {
            $hasColumn = true;
            break;
        }
    }

    if (!$hasColumn) {
        $db->exec('ALTER TABLE users ADD COLUMN turma TEXT');
    }
}

function ensure_user_turno_column(): void
{
    $db = get_db();

    try {
        $columns = $db->query('PRAGMA table_info(users)')->fetchAll();
    } catch (Exception $e) {
        return;
    }

    $hasColumn = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'turno') {
            $hasColumn = true;
            break;
        }
    }

    if (!$hasColumn) {
        $db->exec('ALTER TABLE users ADD COLUMN turno TEXT');
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

function ensure_book_barcode_column(): void
{
    $db = get_db();

    try {
        $columns = $db->query('PRAGMA table_info(books)')->fetchAll();
    } catch (Exception $e) {
        return;
    }

    $hasColumn = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'barcode') {
            $hasColumn = true;
            break;
        }
    }

    if (!$hasColumn) {
        $db->exec('ALTER TABLE books ADD COLUMN barcode TEXT');
    }
}

function ensure_book_shelf_column(): void
{
    $db = get_db();

    try {
        $columns = $db->query('PRAGMA table_info(books)')->fetchAll();
    } catch (Exception $e) {
        return;
    }

    $hasColumn = false;
    foreach ($columns as $column) {
        if (($column['name'] ?? '') === 'shelf') {
            $hasColumn = true;
            break;
        }
    }

    if (!$hasColumn) {
        $db->exec('ALTER TABLE books ADD COLUMN shelf TEXT');
    }
}

function ensure_parent_columns(): void
{
    $db = get_db();

    try {
        $userCols = $db->query('PRAGMA table_info(users)')->fetchAll();
        $userColNames = array_column($userCols, 'name');

        if (!in_array('parent_name', $userColNames, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN parent_name TEXT');
        }
        if (!in_array('parent_phone', $userColNames, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN parent_phone TEXT');
        }
        if (!in_array('parent_email', $userColNames, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN parent_email TEXT');
        }
        if (!in_array('parent_document', $userColNames, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN parent_document TEXT');
        }
    } catch (Exception $e) {
        // Ignora caso tabela não exista ainda
    }

    try {
        $loanCols = $db->query('PRAGMA table_info(loans)')->fetchAll();
        $loanColNames = array_column($loanCols, 'name');

        if (!in_array('parent_name', $loanColNames, true)) {
            $db->exec('ALTER TABLE loans ADD COLUMN parent_name TEXT');
        }
        if (!in_array('parent_signature_status', $loanColNames, true)) {
            $db->exec('ALTER TABLE loans ADD COLUMN parent_signature_status TEXT DEFAULT "pendente"');
        }
    } catch (Exception $e) {
        // Ignora caso tabela não exista ainda
    }
}

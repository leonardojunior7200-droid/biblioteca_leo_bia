<?php
require_once __DIR__ . '/auth.php';

function base_url(string $path = ''): string
{
    $base = BASE_URL;
    if ($base === '' || $base === '/') {
        return '/' . ltrim($path, '/');
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    if (preg_match('#^(https?:)?//#i', $path) || strpos($path, '/') === 0) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . base_url($path));
    }
    exit;
}

function ensure_upload_directory(string $relativePath): string
{
    $directory = __DIR__ . '/../' . ltrim($relativePath, '/');
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new Exception('Não foi possível criar a pasta de uploads.');
    }

    return $directory;
}

function get_backup_directory(): string
{
    return ensure_upload_directory('backups');
}

function profile_photo_url(?string $photoPath): string
{
    if (empty($photoPath)) {
        return base_url('img/avatars/avatar-biblioteca.svg');
    }

    if (strpos($photoPath, 'http') === 0) {
        return $photoPath;
    }

    $normalizedPath = ltrim($photoPath, '/');
    $absolutePath = __DIR__ . '/../' . $normalizedPath;

    if (preg_match('#^(img/|uploads/)#', $normalizedPath) && file_exists($absolutePath)) {
        return base_url($normalizedPath);
    }

    return base_url('img/avatars/avatar-biblioteca.svg');
}

function set_flash(string $message, string $type = 'success'): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
}

function get_flash(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (isset($_SESSION['_flash'])) {
        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $flash;
    }

    return null;
}


function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_positive_int(mixed $value): ?int
{
    if (!is_scalar($value)) {
        return null;
    }

    $normalized = trim((string)$value);
    if ($normalized === '' || !preg_match('/^\d+$/', $normalized)) {
        return null;
    }

    return (int)$normalized;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_date(string $value): string
{
    return date('d/m/Y', strtotime($value));
}

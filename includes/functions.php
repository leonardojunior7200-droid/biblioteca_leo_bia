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

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_date(string $value): string
{
    return date('d/m/Y', strtotime($value));
}

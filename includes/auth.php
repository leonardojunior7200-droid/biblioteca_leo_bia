<?php
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function login_user(int $userId): void
{
    $db = get_db();
    $stmt = $db->prepare('SELECT u.id, u.name, u.email, u.role_id, r.name AS role_name, u.blocked FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return;
    }

    $_SESSION['user'] = $user;
}

function logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    unset($_SESSION['user']);
}

function current_user(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function user_has_role(string|array $role): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    $roles = is_array($role) ? $role : [$role];
    return in_array($user['role_name'], $roles, true);
}

function require_role(string|array $role): void
{
    if (!user_has_role($role)) {
        http_response_code(403);
        echo '<h1>Acesso negado</h1><p>Você não tem permissão para acessar esta página.</p>';
        exit;
    }
}

function current_user_blocked(): bool
{
    $user = current_user();
    return $user['blocked'] === '1' || $user['blocked'] === 1;
}

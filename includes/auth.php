<?php
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}
ensure_user_profile_photo_column();
ensure_user_turma_column();
ensure_user_turno_column();
ensure_user_matricula_column();
ensure_user_profile_completed_column();
ensure_parent_columns();

function login_user(int $userId): void
{
    $db = get_db();
    $stmt = $db->prepare('SELECT u.id, u.name, u.email, u.role_id, r.name AS role_name, u.blocked, u.profile_photo, u.matricula, u.profile_completed, u.turma, u.turno, u.parent_name, u.parent_phone, u.parent_phone_2, u.parent_email, u.parent_document FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
    $_SESSION['authenticated_at'] = time();
}

function logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
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
    return !empty($user['blocked']);
}
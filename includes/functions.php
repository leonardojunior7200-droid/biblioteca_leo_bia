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
    $directory = PRIVATE_STORAGE_PATH . DIRECTORY_SEPARATOR . trim($relativePath, '/\\');
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new Exception('Não foi possível criar a pasta de uploads.');
    }

    return $directory;
}

function get_backup_directory(): string
{
    return ensure_upload_directory('backups');
}

function get_backup_encryption_key(): string
{
    $key = getenv('BIBLIOTECA_BACKUP_KEY') ?: '';
    $keyFile = get_private_storage_path('backup.key');
    if ($key === '' && is_file($keyFile)) {
        $key = trim((string)file_get_contents($keyFile));
    }
    if ($key === '') {
        $key = base64_encode(random_bytes(32));
        file_put_contents($keyFile, $key, LOCK_EX);
    }
    $decoded = base64_decode($key, true);
    if ($decoded === false || strlen($decoded) !== 32) {
        throw new RuntimeException('Chave de criptografia de backup inválida.');
    }
    return $decoded;
}

function encrypt_backup_file(string $sourcePath, string $targetPath): void
{
    $plaintext = file_get_contents($sourcePath);
    if ($plaintext === false) {
        throw new RuntimeException('Não foi possível ler o backup temporário.');
    }
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', get_backup_encryption_key(), OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ciphertext === false || file_put_contents($targetPath, "BKP1" . $nonce . $tag . $ciphertext, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível criptografar o backup.');
    }
}

function decrypt_backup_file(string $sourcePath): string
{
    $payload = file_get_contents($sourcePath);
    if ($payload === false || strlen($payload) < 32 || substr($payload, 0, 4) !== 'BKP1') {
        throw new RuntimeException('Formato de backup criptografado inválido.');
    }
    $nonce = substr($payload, 4, 12);
    $tag = substr($payload, 16, 16);
    $ciphertext = substr($payload, 32);
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', get_backup_encryption_key(), OPENSSL_RAW_DATA, $nonce, $tag);
    if ($plaintext === false) {
        throw new RuntimeException('Não foi possível descriptografar o backup.');
    }
    $temporaryPath = tempnam(sys_get_temp_dir(), 'biblioteca-backup-');
    if ($temporaryPath === false || file_put_contents($temporaryPath, $plaintext, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível preparar o backup para restauração.');
    }
    return $temporaryPath;
}

function get_private_storage_path(string $relativePath = ''): string
{
    return PRIVATE_STORAGE_PATH . ($relativePath !== '' ? DIRECTORY_SEPARATOR . trim($relativePath, '/\\') : '');
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

function format_phone(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    $digits = substr($digits, 0, 11);

    if (strlen($digits) <= 2) {
        return $digits;
    }

    $areaCode = substr($digits, 0, 2);
    $number = substr($digits, 2);
    if (strlen($number) > 8) {
        return '(' . $areaCode . ') ' . substr($number, 0, 5) . '-' . substr($number, 5);
    }

    return '(' . $areaCode . ') ' . substr($number, 0, 4) . (strlen($number) > 4 ? '-' . substr($number, 4) : '');
}

function phone_is_valid(string $value): bool
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    return in_array(strlen($digits), [10, 11], true);
}

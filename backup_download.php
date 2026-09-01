<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_role(['Administrador', 'Bibliotecário']);

$filename = $_GET['file'] ?? '';
$filename = basename($filename);
$backupDirectory = get_backup_directory();
$filePath = $backupDirectory . DIRECTORY_SEPARATOR . $filename;

if (!file_exists($filePath) || pathinfo($filePath, PATHINFO_EXTENSION) !== 'zip') {
    http_response_code(404);
    echo 'Arquivo de backup não encontrado.';
    exit;
}

header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;

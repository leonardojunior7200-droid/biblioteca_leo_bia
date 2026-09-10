<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_role(['Administrador']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('backup.php');
}

if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(419);
    exit('Sessão expirada ou token inválido.');
}

$filename = basename($_POST['file'] ?? '');
$backupDirectory = get_backup_directory();
$backupPath = $backupDirectory . DIRECTORY_SEPARATOR . $filename;

try {
    if (!preg_match('/^backup-\d{8}-\d{6}\.enc$/', $filename) || !is_file($backupPath)) {
        throw new Exception('O arquivo de backup selecionado não foi encontrado.');
    }

    if (!class_exists('ZipArchive') || !extension_loaded('zip')) {
        throw new Exception('A extensão PHP Zip não está habilitada.');
    }

    $temporaryZip = decrypt_backup_file($backupPath);

    $zip = new ZipArchive();
    if ($zip->open($temporaryZip) !== true) {
        throw new Exception('Não foi possível abrir o arquivo de backup.');
    }

    $databaseRestored = false;
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $entry = $zip->getNameIndex($index);
        if ($entry === 'data/library.db') {
            $databaseRestored = true;
            continue;
        }
        if (strpos($entry, 'uploads/') !== 0 || strpos($entry, '..') !== false) {
            throw new Exception('O arquivo de backup contém um caminho inválido.');
        }
    }

    if (!$databaseRestored) {
        throw new Exception('O banco de dados não foi encontrado no backup.');
    }

    $databasePath = get_private_storage_path('library.db');
    $databaseContents = $zip->getFromName('data/library.db');
    if ($databaseContents === false || file_put_contents($databasePath, $databaseContents, LOCK_EX) === false) {
        throw new Exception('Não foi possível restaurar o banco de dados.');
    }

    $privateUploadsDirectory = get_private_storage_path('uploads');
    if (!is_dir($privateUploadsDirectory) && !mkdir($privateUploadsDirectory, 0750, true) && !is_dir($privateUploadsDirectory)) {
        throw new Exception('Não foi possível preparar a pasta privada de uploads.');
    }
    $zip->extractTo(get_private_storage_path(), array_filter(array_map(fn($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1)), fn($entry) => strpos($entry, 'uploads/') === 0));
    $zip->close();
    @unlink($temporaryZip);
    set_flash('Backup restaurado com sucesso.', 'success');
} catch (Exception $e) {
    if (isset($zip) && $zip instanceof ZipArchive) {
        $zip->close();
    }
    set_flash('Falha ao restaurar o backup: ' . $e->getMessage(), 'error');
}

redirect('backup.php');

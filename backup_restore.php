<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_role(['Administrador']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('backup.php');
}

$filename = basename($_POST['file'] ?? '');
$backupDirectory = get_backup_directory();
$backupPath = $backupDirectory . DIRECTORY_SEPARATOR . $filename;

try {
    if (!preg_match('/^backup-\d{8}-\d{6}\.zip$/', $filename) || !is_file($backupPath)) {
        throw new Exception('O arquivo de backup selecionado não foi encontrado.');
    }

    if (!class_exists('ZipArchive') || !extension_loaded('zip')) {
        throw new Exception('A extensão PHP Zip não está habilitada.');
    }

    $zip = new ZipArchive();
    if ($zip->open($backupPath) !== true) {
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

    $databasePath = __DIR__ . '/data/library.db';
    $databaseContents = $zip->getFromName('data/library.db');
    if ($databaseContents === false || file_put_contents($databasePath, $databaseContents, LOCK_EX) === false) {
        throw new Exception('Não foi possível restaurar o banco de dados.');
    }

    $zip->extractTo(__DIR__, array_filter(array_map(fn($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1)), fn($entry) => strpos($entry, 'uploads/') === 0));
    $zip->close();
    set_flash('Backup restaurado com sucesso.', 'success');
} catch (Exception $e) {
    if (isset($zip) && $zip instanceof ZipArchive) {
        $zip->close();
    }
    set_flash('Falha ao restaurar o backup: ' . $e->getMessage(), 'error');
}

redirect('backup.php');

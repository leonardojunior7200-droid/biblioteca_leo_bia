<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
require_role('Administrador');

$db = get_db();

$backupDirectory = get_backup_directory();
$csrfToken = csrf_token();
$backupFile = null;
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_now'])) {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Sessão expirada ou token inválido.');
    }
    try {
        if (!class_exists('ZipArchive') || !extension_loaded('zip')) {
            throw new Exception('A extensão PHP Zip não está habilitada. Ative "extension=zip" no php.ini e reinicie o servidor.');
        }

        $timestamp = date('Ymd-His');
        $filename = "backup-{$timestamp}.enc";
        $temporaryZip = $backupDirectory . DIRECTORY_SEPARATOR . "backup-{$timestamp}.zip.tmp";
        $backupFile = $backupDirectory . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($temporaryZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Não foi possível criar o arquivo de backup.');
        }

        $dbPath = get_private_storage_path('library.db');
        if (file_exists($dbPath)) {
            $zip->addFile($dbPath, 'data/library.db');
        }

        $pathsToInclude = [
            get_private_storage_path('uploads')
        ];

        foreach ($pathsToInclude as $path) {
            if (!file_exists($path)) {
                continue;
            }
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $relativePath = substr($file->getPathname(), strlen(get_private_storage_path()) + 1);
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }

        $zip->close();
        encrypt_backup_file($temporaryZip, $backupFile);
        unlink($temporaryZip);
        $message = 'Backup criado com sucesso: ' . basename($backupFile);

        // Remove backups antigos mantendo os 10 mais recentes
        $files = glob($backupDirectory . DIRECTORY_SEPARATOR . 'backup-*.enc');
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, 10) as $oldFile) {
            @unlink($oldFile);
        }
    } catch (Exception $e) {
        $error = 'Falha ao criar backup: ' . $e->getMessage();
    }
}

$backups = glob($backupDirectory . DIRECTORY_SEPARATOR . 'backup-*.enc');
usort($backups, fn($a, $b) => filemtime($b) <=> filemtime($a));
$canRestore = user_has_role('Administrador');

require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell">
    <?php $sidebarActive = 'backup.php'; $sidebarSubtitle = 'Administração do sistema'; require __DIR__ . '/includes/admin_sidebar.php'; ?>
    <div class="dashboard-main-panel">
        <div class="backup-shell">
    <section class="backup-hero" aria-labelledby="backup-title">
        <div>
            <p class="eyebrow">Administração do sistema</p>
            <h1 id="backup-title">Backups</h1>
            <p>Proteja os dados da biblioteca criando uma cópia completa do sistema.</p>
        </div>
        <div class="backup-actions">
            <a class="backup-exit-button" href="<?php echo h(base_url('dashboard.php')); ?>">Voltar ao painel</a>
            <form method="post" action="<?php echo h(base_url('backup.php')); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <button type="submit" name="backup_now" class="backup-create-button">Fazer backup agora</button>
            </form>
        </div>
    </section>

    <?php if ($message): ?>
        <div class="flash success"><?php echo h($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flash error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <section class="panel backup-panel" aria-labelledby="backup-list-title">
        <div class="panel-header">
            <div>
                <p class="panel-eyebrow">Arquivos disponíveis</p>
                <h2 id="backup-list-title">Backups existentes</h2>
            </div>
            <span class="backup-count"><?php echo count($backups); ?> arquivo(s)</span>
        </div>
    <?php if (empty($backups)): ?>
        <p>Nenhum backup encontrado.</p>
    <?php else: ?>
        <div class="table-wrapper">
        <table class="backup-table">
            <thead>
                <tr>
                    <th>Arquivo</th>
                    <th>Criado em</th>
                    <th>Tamanho</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $file): ?>
                    <tr>
                        <td><?php echo h(basename($file)); ?></td>
                        <td><?php echo h(date('d/m/Y H:i:s', filemtime($file))); ?></td>
                        <td><?php echo h(number_format(filesize($file) / 1024, 2)); ?> KB</td>
                        <td class="backup-table-actions">
                            <a href="<?php echo h(base_url('backup_download.php?file=' . urlencode(basename($file)))); ?>">Baixar</a>
                            <?php if ($canRestore): ?>
                                <form method="post" action="<?php echo h(base_url('backup_restore.php')); ?>" onsubmit="return confirm('Restaurar este backup substituirá os dados atuais do sistema. Deseja continuar?');">
                                    <input type="hidden" name="file" value="<?php echo h(basename($file)); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                    <button type="submit" class="backup-restore-button">Restaurar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
    </section>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';

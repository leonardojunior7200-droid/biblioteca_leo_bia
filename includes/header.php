<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user = current_user();
$flash = get_flash();
$headerPhoto = '';
if ($user) {
    $headerPhoto = profile_photo_url($user['profile_photo'] ?? null);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(SITE_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo h(base_url('css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(base_url('css/background-remover.css')); ?>">
</head>
<?php
$pageName = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$dashboardPages = ['index.php', 'dashboard.php', 'student_dashboard.php', 'books.php', 'loans.php', 'reservations.php', 'reports.php', 'register.php', 'users.php', 'backup.php'];
$bodyClass = in_array($pageName, $dashboardPages, true) ? 'dashboard-page' : '';
if ($pageName === 'login.php') {
    $bodyClass = 'login-landing-page';
}
?>
<body class="<?php echo h($bodyClass . ($pageName === 'student_dashboard.php' ? ' student-dashboard-page' : '')); ?>">
<?php if ($pageName !== 'login.php'): ?>
<header>
    <div class="brand"><a href="<?php echo h(base_url('index.php')); ?>"><?php echo h(SITE_NAME); ?></a></div>
    <nav>
        <?php if ($user): ?>
            <a href="<?php echo h(base_url('logout.php')); ?>" class="nav-logout">Sair</a>
        <?php else: ?>
            <a href="<?php echo h(base_url('login.php')); ?>">Login</a>
            <a href="<?php echo h(base_url('register.php')); ?>">Cadastro</a>
        <?php endif; ?>
        <?php if ($pageName !== 'login.php'): ?>
            <button id="theme-toggle" type="button">Tema escuro</button>
            <button id="background-toggle" type="button">Fundo: padrão</button>
            <?php if (!$user || !user_has_role('Aluno')): ?>
                <button id="background-photo-button" type="button">Fundo personalizado</button>
                <button id="background-gallery-toggle" type="button">Meus fundos</button>
                <button id="background-remover-toggle" type="button">Remover fundo</button>
                <input id="background-photo-input" type="file" accept="image/*" style="display: none;">
            <?php endif; ?>
        <?php endif; ?>
        <a href="<?php echo h(base_url('index.php')); ?>">Catálogo</a>
        <?php if ($user): ?>
            <?php if (user_has_role('Aluno')): ?>
                <a href="<?php echo h(base_url('student_dashboard.php')); ?>">Painel do Aluno</a>
                <a href="<?php echo h(base_url('student_dashboard.php?section=perfil')); ?>">Meu perfil</a>
            <?php else: ?>
                <a href="<?php echo h(base_url('dashboard.php')); ?>">Painel</a>
            <?php endif; ?>
            <?php if (user_has_role(['Administrador', 'Bibliotecário'])): ?>
                <a href="<?php echo h(base_url('books.php')); ?>">Livros</a>
                <a href="<?php echo h(base_url('loans.php')); ?>">Empréstimos</a>
                <a href="<?php echo h(base_url('reports.php')); ?>">Relatórios</a>
                <a href="<?php echo h(base_url('backup.php')); ?>">Backups</a>
            <?php endif; ?>
            <a href="<?php echo h(base_url('reservations.php')); ?>">Reservas</a>
            <div class="nav-user">
                <img src="<?php echo h($headerPhoto); ?>" alt="Foto de perfil" class="nav-user-avatar">
                <span>Olá, <?php echo h($user['name']); ?> (<?php echo h($user['role_name']); ?>)</span>
            </div>
        <?php endif; ?>
    </nav>
</header>
<?php endif; ?>
<div id="background-gallery" class="background-gallery hidden">
    <div class="background-gallery-inner">
        <div class="background-gallery-header">
            <h2>Fundos salvos</h2>
            <button id="background-gallery-close" type="button">×</button>
        </div>
        <p>Escolha um fundo que você carregou antes.</p>
        <div id="background-gallery-list" class="background-gallery-list"></div>
        <div class="background-gallery-empty">Nenhum fundo salvo. Faça upload de um fundo para salvá-lo.</div>
    </div>
</div>
<div id="background-remover-modal" class="bg-remover-panel hidden">
    <div class="bg-remover-inner">
        <div class="bg-remover-header">
            <h2>Remover Fundo da Imagem</h2>
            <button class="bg-remover-close" type="button">×</button>
        </div>
        <div class="bg-remover-body">
            <div class="bg-remover-preview bg-remover-preview-empty">
                <p class="bg-remover-preview-empty-text">
                    Carregue uma imagem para remover o fundo. Use o botão "Fundo personalizado" para selecionar uma imagem.
                </p>
            </div>
            <div class="bg-remover-controls" style="display:none;">
                <div class="bg-remover-control">
                    <label for="bg-remover-threshold">Sensibilidade:</label>
                    <input type="range" id="bg-remover-threshold" min="10" max="50" value="30" step="1">
                    <span id="bg-remover-threshold-value" style="font-size:0.85rem;color:var(--footer-text);">30</span>
                </div>
                <div class="bg-remover-control">
                    <label for="bg-remover-margin">Margem (px):</label>
                    <input type="number" id="bg-remover-margin" min="0" max="20" value="0" step="1" style="width:100%;padding:10px 12px;border:1px solid var(--input-border);border-radius:8px;font-size:1rem;background:var(--input-bg);color:var(--text);">
                </div>
            </div>
            <div class="bg-remover-actions">
                <button class="btn btn-cancel-bg" type="button" id="bg-remover-cancel">Cancelar</button>
                <button class="btn btn-apply-bg" type="button" id="bg-remover-apply" disabled>Aplicar Fundo</button>
            </div>
            <div class="bg-remover-info">
                <strong>Como usar:</strong> Carregue uma imagem, ajuste a sensibilidade e clique em "Aplicar Fundo" para salvar como fundo personalizado.
            </div>
        </div>
    </div>
</div>
<div id="confirmation-modal" class="confirmation-modal hidden" role="dialog" aria-modal="true" aria-labelledby="confirmation-title">
    <div class="confirmation-modal-inner">
        <h2 id="confirmation-title">Confirmar ação</h2>
        <p id="confirmation-message"></p>
        <div class="confirmation-actions">
            <button type="button" class="secondary-btn" id="confirmation-cancel">Cancelar</button>
            <button type="button" class="primary-btn" id="confirmation-accept">Confirmar</button>
        </div>
    </div>
</div>
<main>
    <?php if ($flash): ?>
        <div class="flash <?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>
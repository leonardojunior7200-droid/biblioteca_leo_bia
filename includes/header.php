<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$user = current_user();
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(SITE_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo h(base_url('css/style.css')); ?>">
</head>
<body>
<header>
    <div class="brand"><a href="<?php echo h(base_url('index.php')); ?>"><?php echo h(SITE_NAME); ?></a></div>
    <nav>
        <a href="<?php echo h(base_url('index.php')); ?>">Catálogo</a>
        <?php if ($user): ?>
            <a href="<?php echo h(base_url('dashboard.php')); ?>">Painel</a>
            <?php if (user_has_role(['Administrador', 'Bibliotecário'])): ?>
                <a href="<?php echo h(base_url('books.php')); ?>">Livros</a>
                <a href="<?php echo h(base_url('loans.php')); ?>">Empréstimos</a>
                <a href="<?php echo h(base_url('reports.php')); ?>">Relatórios</a>
            <?php endif; ?>
            <a href="<?php echo h(base_url('reservations.php')); ?>">Reservas</a>
            <a href="<?php echo h(base_url('logout.php')); ?>">Sair</a>
            <span class="nav-user">Olá, <?php echo h($user['name']); ?> (<?php echo h($user['role_name']); ?>)</span>
        <?php else: ?>
            <a href="<?php echo h(base_url('login.php')); ?>">Login</a>
        <?php endif; ?>
    </nav>
</header>
<main>
    <?php if ($flash): ?>
        <div class="flash <?php echo h($flash['type']); ?>"><?php echo h($flash['message']); ?></div>
    <?php endif; ?>

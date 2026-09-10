<?php
$sidebarActive = $sidebarActive ?? basename($_SERVER['PHP_SELF'] ?? 'dashboard.php');
$sidebarSubtitle = $sidebarSubtitle ?? 'Admin Console';

$sidebarLinks = [
    ['key' => 'dashboard.php', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
    ['key' => 'index.php', 'label' => 'Catálogo', 'href' => 'index.php'],
    ['key' => 'loans.php', 'label' => 'Empréstimos', 'href' => 'loans.php'],
    ['key' => 'reservations.php', 'label' => 'Reservas', 'href' => 'reservations.php'],
    ['key' => 'reports.php', 'label' => 'Relatórios', 'href' => 'reports.php'],
    ['key' => 'books.php', 'label' => 'Livros', 'href' => 'books.php'],
    ['key' => 'users.php', 'label' => 'Usuários e Responsáveis', 'href' => 'users.php'],
];
if (user_has_role('Administrador')) {
    $sidebarLinks[] = ['key' => 'backup.php', 'label' => 'Backups', 'href' => 'backup.php'];
}
?>
<aside class="dashboard-sidebar">
    <div>
        <div class="sidebar-brand">
            <div class="brand-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h8.25A2.5 2.5 0 0 1 17.25 6.5v11A2.5 2.5 0 0 1 14.75 20H6.5A2.5 2.5 0 0 1 4 17.5z"></path>
                    <path d="M9 4v16"></path>
                    <path d="M20 7v10"></path>
                </svg>
            </div>
            <div>
                <h2>Biblioteca Escolar</h2>
                <p><?php echo h($sidebarSubtitle); ?></p>
            </div>
        </div>
        <nav class="sidebar-nav" aria-label="Menu principal">
            <?php foreach ($sidebarLinks as $link): ?>
                <a class="nav-item <?php echo $sidebarActive === $link['key'] ? 'active' : ''; ?>" href="<?php echo h(base_url($link['href'])); ?>">
                    <span class="nav-icon"><?php echo $link['key'] === 'dashboard.php' && $sidebarActive === 'dashboard.php' ? '◉' : '◌'; ?></span>
                    <span><?php echo h($link['label']); ?></span>
                </a>
            <?php endforeach; ?>
            <a class="sidebar-logout nav-item" href="<?php echo h(base_url('logout.php')); ?>">
                <span class="nav-icon">↩</span>
                <span>Sair</span>
            </a>
        </nav>
    </div>
</aside>

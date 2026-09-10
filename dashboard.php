<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

if (user_has_role('Aluno')) {
    redirect('student_dashboard.php');
}

$db = get_db();
$user = current_user();
$userId = (int)($user['id'] ?? 0);
$displayPhoto = profile_photo_url($user['profile_photo'] ?? null);
$roleLabel = $user['role_name'] ?? 'Administrador';

$stats = [
    'books' => 0,
    'loans' => 0,
    'reservations' => 0,
    'users' => 0,
];

$users = [];
$books = [];
$recentLoans = [];

try {
    $stats['books'] = (int)$db->query('SELECT COUNT(*) FROM books')->fetchColumn();
    $stats['loans'] = (int)$db->query('SELECT COUNT(*) FROM loans WHERE returned_at IS NULL')->fetchColumn();
    $stats['reservations'] = (int)$db->query('SELECT COUNT(*) FROM reservations WHERE fulfilled_at IS NULL')->fetchColumn();
    $stats['users'] = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();

    $users = $db->query('SELECT id, name, email, profile_photo FROM users ORDER BY name ASC')->fetchAll();
    $books = $db->query('SELECT id, title, author, cover_path FROM books ORDER BY title ASC')->fetchAll();

    $recentLoans = $db->query('SELECT l.id, l.loaned_at, l.due_date, l.returned_at, u.id AS user_id, u.name AS user_name, u.email AS user_email, u.profile_photo AS user_photo, b.id AS book_id, b.title AS book_title, b.author AS book_author, b.cover_path AS book_cover FROM loans l JOIN users u ON u.id = l.user_id JOIN books b ON b.id = l.book_id ORDER BY l.loaned_at DESC LIMIT 6')->fetchAll();
} catch (Exception $e) {
    $users = [];
    $books = [];
    $recentLoans = [];
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell">
    <?php $sidebarActive = 'dashboard.php'; $sidebarSubtitle = 'Admin Console'; require __DIR__ . '/includes/admin_sidebar.php'; ?>

    <div class="dashboard-main-panel">
        <header class="dashboard-topbar">
            <div>
                <p class="eyebrow">Painel administrativo</p>
                <h1>Olá, <?php echo h($user['name']); ?> 👋</h1>
                <p class="topbar-subtitle">Bem-vindo ao sistema da biblioteca</p>
            </div>
            <div class="topbar-actions">
                <a href="<?php echo h(base_url('users.php?new_student=1')); ?>" class="primary-btn" style="text-decoration: none;">+ Cadastrar aluno</a>
                <button class="topbar-icon" type="button" aria-label="Notificações">🔔</button>
                <div class="topbar-user">
                    <img src="<?php echo h($displayPhoto); ?>" alt="Avatar do usuário">
                    <div>
                        <strong><?php echo h($user['name']); ?></strong>
                        <span><?php echo h($roleLabel); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <section class="stats-grid" aria-label="Resumo geral">
            <article class="stat-card stat-books">
                <div class="stat-icon">📚</div>
                <div>
                    <p class="stat-label">Livros</p>
                    <h3><?php echo h((string)$stats['books']); ?></h3>
                    <p class="stat-meta">Livros cadastrados</p>
                </div>
                <a href="<?php echo h(base_url('books.php')); ?>">Ver catálogo</a>
            </article>
            <article class="stat-card stat-loans">
                <div class="stat-icon">🗂️</div>
                <div>
                    <p class="stat-label">Empréstimos</p>
                    <h3><?php echo h((string)$stats['loans']); ?></h3>
                    <p class="stat-meta">Empréstimos ativos</p>
                </div>
                <a href="<?php echo h(base_url('loans.php')); ?>">Gerenciar</a>
            </article>
            <article class="stat-card stat-reservations">
                <div class="stat-icon">📝</div>
                <div>
                    <p class="stat-label">Reservas</p>
                    <h3><?php echo h((string)$stats['reservations']); ?></h3>
                    <p class="stat-meta">Reservas ativas</p>
                </div>
                <a href="<?php echo h(base_url('reservations.php')); ?>">Abrir fila</a>
            </article>
            <article class="stat-card stat-users">
                <div class="stat-icon">👥</div>
                <div>
                    <p class="stat-label">Usuários</p>
                    <h3><?php echo h((string)$stats['users']); ?></h3>
                    <p class="stat-meta">Usuários ativos</p>
                </div>
                <a href="<?php echo h(base_url('users.php')); ?>">Ver usuários</a>
            </article>
        </section>

        <section class="content-grid">
            <div class="panel loan-panel">
                <div class="panel-header">
                    <div>
                        <p class="panel-eyebrow">Operação rápida</p>
                        <h2>Novo empréstimo</h2>
                        <p class="panel-subtitle">Preencha os dados para realizar um novo empréstimo</p>
                    </div>
                </div>
                <form method="post" action="loans.php" class="loan-form">
                    <input type="hidden" name="user_search" value="">
                    <input type="hidden" name="book_search" value="">
                    <div class="field-group">
                        <label for="user_id">Usuário</label>
                        <select id="user_id" name="user_id" required>
                            <option value="">Selecione um usuário</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo (int)$user['id']; ?>"><?php echo h($user['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="book_id">Livro</label>
                        <select id="book_id" name="book_id" required>
                            <option value="">Selecione um livro</option>
                            <?php foreach ($books as $book): ?>
                                <option value="<?php echo (int)$book['id']; ?>"><?php echo h($book['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="primary-btn">Registrar empréstimo</button>
                </form>
            </div>

            <div class="panel shortcuts-panel">
                <div class="panel-header">
                    <div>
                        <p class="panel-eyebrow">Produtividade</p>
                        <h2>Atalhos rápidos</h2>
                    </div>
                </div>
                <div class="shortcut-grid">
                    <a class="shortcut-card" href="<?php echo h(base_url('loans.php')); ?>">
                        <span class="shortcut-icon">↗</span>
                        <strong>Novo empréstimo</strong>
                        <p>Cadastre uma nova movimentação com rapidez.</p>
                    </a>
                    <a class="shortcut-card" href="<?php echo h(base_url('reservations.php')); ?>">
                        <span class="shortcut-icon">✦</span>
                        <strong>Nova reserva</strong>
                        <p>Organize reservas com visão geral do fluxo.</p>
                    </a>
                    <a class="shortcut-card" href="<?php echo h(base_url('reports.php')); ?>">
                        <span class="shortcut-icon">◫</span>
                        <strong>Ver relatórios</strong>
                        <p>Acompanhe métricas e indicadores de uso.</p>
                    </a>
                    <a class="shortcut-card" href="<?php echo h(base_url('index.php')); ?>">
                        <span class="shortcut-icon">▣</span>
                        <strong>Catálogo</strong>
                        <p>Explore acervo completo com facilidade.</p>
                    </a>
                </div>
            </div>
        </section>

        <section class="panel table-panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Atividade recente</p>
                    <h2>Empréstimos recentes</h2>
                    <p class="panel-subtitle">Últimos empréstimos realizados</p>
                </div>
                <a class="soft-link" href="<?php echo h(base_url('loans.php')); ?>">Ver todos</a>
            </div>
            <div class="table-wrapper">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Livro</th>
                            <th>Emprestado em</th>
                            <th>Devolução</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLoans)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">Nenhum empréstimo registrado até o momento.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentLoans as $loan): ?>
                                <?php
                                    $loanStatus = 'Ativo';
                                    $loanClass = 'status-active';
                                    if (!empty($loan['returned_at'])) {
                                        $loanStatus = 'Devolvido';
                                        $loanClass = 'status-returned';
                                    } elseif (strtotime($loan['due_date']) < strtotime('today')) {
                                        $loanStatus = 'Atrasado';
                                        $loanClass = 'status-overdue';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <img src="<?php echo h(profile_photo_url($loan['user_photo'] ?? null)); ?>" alt="Avatar do usuário">
                                            <div>
                                                <strong><?php echo h($loan['user_name']); ?></strong>
                                                <span><?php echo h($loan['user_email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="book-cell">
                                            <div class="book-cover"><?php echo h(strtoupper(substr($loan['book_title'], 0, 1))); ?></div>
                                            <div>
                                                <strong><?php echo h($loan['book_title']); ?></strong>
                                                <span><?php echo h($loan['book_author']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo h(format_date($loan['loaned_at'])); ?></td>
                                    <td><?php echo h(format_date($loan['due_date'])); ?></td>
                                    <td><span class="status-pill <?php echo h($loanClass); ?>"><?php echo h($loanStatus); ?></span></td>
                                    <td>
                                        <div class="table-actions">
                                            <button type="button" class="icon-btn" aria-label="Visualizar">⋯</button>
                                            <button type="button" class="icon-btn" aria-label="Editar">✎</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php';

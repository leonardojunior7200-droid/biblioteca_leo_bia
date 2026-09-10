<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$books = [];
$hasData = true;
$totalBooks = 0;
$availableBooks = 0;
$categoryCount = 0;

try {
    $db = get_db();
    $stmt = $db->query('SELECT id, title, author, category, quantity, cover_path FROM books ORDER BY title LIMIT 10');
    $books = $stmt->fetchAll();

    $statsStmt = $db->query('SELECT COUNT(*) AS total_books, SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END) AS available_books, COUNT(DISTINCT category) AS category_count FROM books');
    $stats = $statsStmt->fetch();
    $totalBooks = (int)($stats['total_books'] ?? 0);
    $availableBooks = (int)($stats['available_books'] ?? 0);
    $categoryCount = (int)($stats['category_count'] ?? 0);
} catch (Exception $e) {
    $hasData = false;
}
?>
<div class="dashboard-shell">
    <?php if (is_logged_in() && user_has_role('Aluno')): ?>
    <aside class="dashboard-sidebar">
        <div>
            <div class="sidebar-brand">
                <div class="brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h8.25A2.5 2.5 0 0 1 17.25 6.5v11A2.5 2.5 0 0 1 14.75 20H6.5A2.5 2.5 0 0 1 4 17.5z"></path>
                        <path d="M9 4v16"></path>
                        <path d="M20 7v10"></path>
                    </svg>
                </div>
                <div>
                    <h2>Biblioteca Escolar</h2>
                    <p><?php echo is_logged_in() && user_has_role('Aluno') ? 'Área do aluno' : 'Catálogo da biblioteca'; ?></p>
                </div>
            </div>
            <nav class="sidebar-nav" aria-label="Menu principal">
                    <a class="nav-item" href="student_dashboard.php"><span class="nav-icon">◉</span><span>Início</span></a>
                    <a class="nav-item active" href="index.php"><span class="nav-icon">◌</span><span>Livros</span></a>
                    <a class="nav-item" href="student_dashboard.php?section=emprestimos"><span class="nav-icon">◌</span><span>Meus empréstimos</span></a>
                    <a class="nav-item" href="reservations.php"><span class="nav-icon">◌</span><span>Minhas reservas</span></a>
                    <a class="nav-item" href="student_dashboard.php?section=perfil"><span class="nav-icon">◌</span><span>Meu perfil</span></a>
                <a class="sidebar-logout nav-item" href="logout.php"><span class="nav-icon">↩</span><span>Sair</span></a>
            </nav>
        </div>
    </aside>
    <?php else: ?>
        <?php $sidebarActive = 'index.php'; $sidebarSubtitle = 'Catálogo da biblioteca'; require __DIR__ . '/includes/admin_sidebar.php'; ?>
    <?php endif; ?>

    <div class="dashboard-main-panel">
        <header class="dashboard-topbar">
            <div>
                <p class="eyebrow">Acervo</p>
                <h1>Catálogo</h1>
                <p class="topbar-subtitle">Explore o acervo da biblioteca com o mesmo visual do painel.</p>
            </div>
            <div class="topbar-actions">
                <?php if (is_logged_in()): ?>
                    <a class="primary-btn" href="dashboard.php">Ir para o painel</a>
                <?php else: ?>
                    <a class="primary-btn" href="login.php">Entrar no sistema</a>
                <?php endif; ?>
            </div>
        </header>

        <section class="stats-grid" aria-label="Resumo do catálogo">
            <article class="stat-card stat-books">
                <div class="stat-icon">📚</div>
                <div>
                    <p class="stat-label">Livros</p>
                    <h3><?php echo (int)$totalBooks; ?></h3>
                    <p class="stat-meta">Cadastrados no acervo</p>
                </div>
                <a href="books.php">Gerenciar</a>
            </article>
            <article class="stat-card stat-loans">
                <div class="stat-icon">✅</div>
                <div>
                    <p class="stat-label">Disponíveis</p>
                    <h3><?php echo (int)$availableBooks; ?></h3>
                    <p class="stat-meta">Com estoque ativo</p>
                </div>
                <a href="reservations.php">Reservar</a>
            </article>
            <article class="stat-card stat-reservations">
                <div class="stat-icon">🗂️</div>
                <div>
                    <p class="stat-label">Categorias</p>
                    <h3><?php echo (int)$categoryCount; ?></h3>
                    <p class="stat-meta">Tipos de obra</p>
                </div>
                <a href="reports.php">Ver relatórios</a>
            </article>
        </section>

        <section class="panel table-panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Catálogo rápido</p>
                    <h2>Livros em destaque</h2>
                    <p class="panel-subtitle">Uma visão rápida do acervo disponível para consulta.</p>
                </div>
            </div>

            <?php if (!$hasData): ?>
                <p class="empty-state">O banco de dados ainda não foi inicializado. Execute <a href="setup.php">setup.php</a> para criar o esquema e dados iniciais.</p>
            <?php elseif (empty($books)): ?>
                <p class="empty-state">Nenhum livro encontrado no catálogo.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Título</th>
                                <th>Autor</th>
                                <th>Categoria</th>
                                <th>Disponível</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($books as $book): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($book['cover_path'])): ?>
                                            <img src="<?php echo h(base_url($book['cover_path'])); ?>" alt="Foto do livro" style="max-width: 60px; max-height: 60px; object-fit: cover; border-radius: 12px;">
                                        <?php else: ?>
                                            <span class="muted">Sem foto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo h($book['title']); ?></td>
                                    <td><?php echo h($book['author']); ?></td>
                                    <td><?php echo h($book['category']); ?></td>
                                    <td><?php echo (int)$book['quantity']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php';

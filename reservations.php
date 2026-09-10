<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$db = get_db();
$csrfToken = csrf_token();
$action = $_POST['action'] ?? '';
$id = require_positive_int($_POST['id'] ?? null);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(419);
    exit('Sessão expirada ou token inválido.');
}

if ($action === 'reserve' && ($bookId = require_positive_int($_POST['book_id'] ?? null))) {
    $user = current_user();

    if ($bookId <= 0) {
        $error = 'Livro inválido.';
    } else {
        $stmt = $db->prepare('INSERT INTO reservations (user_id, book_id) VALUES (:user_id, :book_id)');
        $stmt->execute([':user_id' => $user['id'], ':book_id' => $bookId]);
        set_flash('Reserva registrada com sucesso.');
        redirect('reservations.php');
    }
}

if ($action === 'fulfill' && $id) {
    require_role(['Administrador', 'Bibliotecário']);
    $reservation = $db->prepare('SELECT r.id, r.book_id, b.quantity FROM reservations r JOIN books b ON r.book_id = b.id WHERE r.id = :id AND r.fulfilled_at IS NULL');
    $reservation->execute([':id' => $id]);
    $reservation = $reservation->fetch();

    if ($reservation && $reservation['quantity'] > 0) {
        $db->beginTransaction();
        $db->prepare('UPDATE reservations SET fulfilled_at = DATE("now") WHERE id = :id')->execute([':id' => $id]);
        $db->prepare('UPDATE books SET quantity = quantity - 1 WHERE id = :book_id')->execute([':book_id' => $reservation['book_id']]);
        $db->commit();
        set_flash('Reserva cumprida e livro reservado para o usuário.');
    } else {
        $error = 'Não foi possível cumprir a reserva. Verifique se o livro está disponível.';
    }
    redirect('reservations.php');
}

$reservationsQuery = 'SELECT r.id, u.name AS user_name, b.title AS book_title, b.shelf AS book_shelf, r.reserved_at, r.fulfilled_at FROM reservations r JOIN users u ON r.user_id = u.id JOIN books b ON r.book_id = b.id';
if (!user_has_role(['Administrador', 'Bibliotecário'])) {
    $reservationsQuery .= ' WHERE u.id = ' . (int)current_user()['id'];
}
$reservationsQuery .= ' ORDER BY r.reserved_at DESC';
$reservations = $db->query($reservationsQuery)->fetchAll();
$books = $db->query('SELECT id, title, quantity, cover_path FROM books ORDER BY title')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell">
    <?php if (user_has_role('Aluno')): ?>
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
                    <p><?php echo user_has_role('Aluno') ? 'Área do aluno' : 'Gestão de reservas'; ?></p>
                </div>
            </div>
            <nav class="sidebar-nav" aria-label="Menu principal">
                    <a class="nav-item" href="student_dashboard.php"><span class="nav-icon">◉</span><span>Início</span></a>
                    <a class="nav-item" href="index.php"><span class="nav-icon">◌</span><span>Livros</span></a>
                    <a class="nav-item" href="student_dashboard.php?section=emprestimos"><span class="nav-icon">◌</span><span>Meus empréstimos</span></a>
                    <a class="nav-item active" href="reservations.php"><span class="nav-icon">◌</span><span>Minhas reservas</span></a>
                    <a class="nav-item" href="student_dashboard.php?section=perfil"><span class="nav-icon">◌</span><span>Meu perfil</span></a>
                <a class="sidebar-logout nav-item" href="logout.php"><span class="nav-icon">↩</span><span>Sair</span></a>
            </nav>
        </div>
    </aside>
    <?php else: ?>
        <?php $sidebarActive = 'reservations.php'; $sidebarSubtitle = 'Gestão de reservas'; require __DIR__ . '/includes/admin_sidebar.php'; ?>
    <?php endif; ?>

    <div class="dashboard-main-panel">
        <header class="dashboard-topbar">
            <div>
                <p class="eyebrow">Fila</p>
                <h1>Reservas</h1>
                <p class="topbar-subtitle">Organize as reservas dos usuários e acompanhe o status.</p>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="flash error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Nova reserva</p>
                    <h2>Escolha um livro para reservar</h2>
                    <p class="panel-subtitle">Os livros disponíveis aparecem aqui para uma reserva rápida.</p>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Título</th>
                            <th>Disponível</th>
                            <th>Ação</th>
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
                                <td><?php echo (int)$book['quantity']; ?></td>
                                <td>
                                    <form method="post" action="reservations.php" style="display:inline; margin:0;">
                                        <input type="hidden" name="action" value="reserve">
                                        <input type="hidden" name="book_id" value="<?php echo (int)$book['id']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                        <button type="submit" class="action-link" style="border:0; background:none; padding:0; cursor:pointer;">Reservar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Lista</p>
                    <h2>Reservas registradas</h2>
                    <p class="panel-subtitle">Acompanhe o estado das reservas e conclua as pendências.</p>
                </div>
            </div>
            <?php if (empty($reservations)): ?>
                <p class="empty-state">Sem reservas registradas.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Livro</th>
                                <th>Patilheira</th>
                                <th>Reservado em</th>
                                <th>Status</th>
                                <?php if (user_has_role(['Administrador', 'Bibliotecário'])): ?><th>Ações</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?php echo h($reservation['user_name']); ?></td>
                                    <td><?php echo h($reservation['book_title']); ?></td>
                                    <td><?php echo h($reservation['book_shelf']); ?></td>
                                    <td><?php echo h(format_date($reservation['reserved_at'])); ?></td>
                                    <td>Cumprida em <?php echo h(format_date($reservation['fulfilled_at'])) ?> ou Pendente</td>
                                    <?php if (user_has_role(['Administrador', 'Bibliotecário'])): ?>
                                        <td>
                                            <?php if (!$reservation['fulfilled_at']): ?>
                                                <form method="post" action="reservations.php" style="display:inline; margin:0;">
                                                    <input type="hidden" name="action" value="fulfill">
                                                    <input type="hidden" name="id" value="<?php echo (int)$reservation['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                    <button type="submit" class="action-link" style="border:0; background:none; padding:0; cursor:pointer;">Cumprir</button>
                                                </form>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
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

<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role('Aluno');

$currentStudent = current_user();
if (empty($currentStudent['profile_completed'])) {
    redirect('complete_profile.php');
}

$db = get_db();
ensure_user_profile_photo_column();
ensure_book_pdf_column();
ensure_book_shelf_column();

$search = trim($_GET['search'] ?? '');
$section = $_GET['section'] ?? 'inicio';
$allowedSections = ['inicio', 'livros', 'emprestimos', 'perfil'];
if (!in_array($section, $allowedSections, true)) {
    $section = 'inicio';
}
$searchQuery = '';
$params = [];

if ($search !== '') {
    $searchQuery = 'WHERE title LIKE :search OR author LIKE :search OR category LIKE :search OR shelf LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$books = $db->prepare('SELECT id, title, author, category, quantity, shelf, cover_path, pdf_path FROM books ' . $searchQuery . ' ORDER BY title');
$books->execute($params);
$books = $books->fetchAll();

$user = current_user();
$userId = (int)$user['id'];
$matricula = $user['matricula'] ?? '';
$turma = $user['turma'] ?? '';
$turno = $user['turno'] ?? '';

$avatarOptions = [
    ['value' => 'img/avatars/avatar-feminino.svg', 'label' => 'Feminino'],
    ['value' => 'img/avatars/avatar-masculino.svg', 'label' => 'Masculino'],
    ['value' => 'img/avatars/avatar-biblioteca.svg', 'label' => 'Biblioteca'],
];

$profilePhoto = $user['profile_photo'] ?? '';
$profileMessage = null;
$profileMessageType = 'success';
$selectedAvatar = in_array($profilePhoto, array_column($avatarOptions, 'value'), true) ? $profilePhoto : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_photo_form'])) {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Sessão expirada ou token inválido.');
    }
    try {
        $photoPath = $profilePhoto;
        if (isset($_POST['remove_photo'])) {
            $photoPath = null;
        } elseif (isset($_POST['avatar_choice'])) {
            if ($_POST['avatar_choice'] === '' || in_array($_POST['avatar_choice'], array_column($avatarOptions, 'value'), true)) {
                $photoPath = $_POST['avatar_choice'];
            }
        }

        $stmt = $db->prepare('UPDATE users SET profile_photo = :profile_photo WHERE id = :id');
        $stmt->execute([':profile_photo' => $photoPath, ':id' => $userId]);
        $_SESSION['user']['profile_photo'] = $photoPath;
        $user['profile_photo'] = $photoPath;
        $profilePhoto = $photoPath;
        $selectedAvatar = in_array($profilePhoto, array_column($avatarOptions, 'value'), true) ? $profilePhoto : '';
        $profileMessage = 'Perfil atualizado com sucesso.';
    } catch (Exception $e) {
        $profileMessage = $e->getMessage();
        $profileMessageType = 'error';
    }
}

$displayPhoto = profile_photo_url($profilePhoto);

$history = $db->prepare('SELECT l.id, b.title, l.loaned_at, l.due_date, l.returned_at FROM loans l JOIN books b ON l.book_id = b.id WHERE l.user_id = :user_id ORDER BY l.loaned_at DESC');
$history->execute([':user_id' => $userId]);
$history = $history->fetchAll();

$dueSoon = $db->prepare('SELECT l.id, b.title, l.loaned_at, l.due_date FROM loans l JOIN books b ON l.book_id = b.id WHERE l.user_id = :user_id AND l.returned_at IS NULL AND l.due_date >= DATE("now") AND l.due_date <= DATE("now", "+2 days") ORDER BY l.due_date ASC');
$dueSoon->execute([':user_id' => $userId]);
$dueSoon = $dueSoon->fetchAll();

$overdue = $db->prepare('SELECT l.id, b.title, l.loaned_at, l.due_date FROM loans l JOIN books b ON l.book_id = b.id WHERE l.user_id = :user_id AND l.returned_at IS NULL AND l.due_date < DATE("now") ORDER BY l.due_date ASC');
$overdue->execute([':user_id' => $userId]);
$overdue = $overdue->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell student-dashboard-shell">
    <aside class="dashboard-sidebar">
        <div>
            <div class="sidebar-brand">
                <div class="brand-mark">▣</div>
                <div>
                    <h2>Biblioteca Escolar</h2>
                    <p>Área do aluno</p>
                </div>
            </div>
            <nav class="sidebar-nav" aria-label="Menu do aluno">
                <a class="nav-item <?php echo $section === 'inicio' ? 'active' : ''; ?>" href="student_dashboard.php?section=inicio"><span class="nav-icon">◉</span><span>Início</span></a>
                <a class="nav-item <?php echo $section === 'livros' ? 'active' : ''; ?>" href="student_dashboard.php?section=livros"><span class="nav-icon">◌</span><span>Livros</span></a>
                <a class="nav-item <?php echo $section === 'emprestimos' ? 'active' : ''; ?>" href="student_dashboard.php?section=emprestimos"><span class="nav-icon">◌</span><span>Meus empréstimos</span></a>
                <a class="nav-item" href="reservations.php"><span class="nav-icon">◌</span><span>Minhas reservas</span></a>
                <a class="nav-item <?php echo $section === 'perfil' ? 'active' : ''; ?>" href="student_dashboard.php?section=perfil"><span class="nav-icon">◌</span><span>Meu perfil</span></a>
                <a class="sidebar-logout nav-item" href="logout.php"><span class="nav-icon">↩</span><span>Sair</span></a>
            </nav>
        </div>
    </aside>
    <div class="dashboard-main-panel">
        <header class="dashboard-topbar">
            <div>
                <p class="eyebrow">Área do aluno</p>
                <h1>Olá, <?php echo h($user['name']); ?></h1>
                <p class="topbar-subtitle">Acompanhe seus livros, empréstimos e reservas.</p>
            </div>
            <div class="topbar-user">
                <img src="<?php echo h($displayPhoto); ?>" alt="Avatar do aluno">
                <div>
                    <strong><?php echo h($user['name']); ?></strong>
                    <span>Matrícula <?php echo h($matricula); ?></span>
                </div>
            </div>
        </header>
<?php if ($section === 'inicio'): ?>
<section class="panel student-welcome-panel">
    <p class="panel-eyebrow">Sua biblioteca</p>
    <h1>Bem-vindo de volta, <?php echo h($user['name']); ?>!</h1>
    <p class="panel-subtitle">Encontre sua próxima leitura e acompanhe tudo o que está acontecendo com seus empréstimos.</p>
    <div class="student-quick-actions">
        <a class="primary-btn" href="student_dashboard.php?section=livros">Explorar livros</a>
        <a class="secondary-btn" href="student_dashboard.php?section=emprestimos">Ver meus empréstimos</a>
        <a class="secondary-btn" href="reservations.php">Minhas reservas</a>
    </div>
</section>
<section class="stats-grid student-stats-grid" aria-label="Resumo do aluno">
    <article class="stat-card stat-books">
        <div class="stat-icon">📚</div>
        <div><p class="stat-label">Histórico</p><h3><?php echo count($history); ?></h3><p class="stat-meta">Empréstimos registrados</p></div>
    </article>
    <article class="stat-card stat-loans">
        <div class="stat-icon">⏱</div>
        <div><p class="stat-label">Próximos</p><h3><?php echo count($dueSoon); ?></h3><p class="stat-meta">Vencem em até 2 dias</p></div>
    </article>
    <article class="stat-card stat-reservations">
        <div class="stat-icon">✓</div>
        <div><p class="stat-label">Pendências</p><h3><?php echo count($overdue); ?></h3><p class="stat-meta">Livros em atraso</p></div>
    </article>
</section>
<div class="panel student-reading-panel">
    <div class="panel-header">
        <div><p class="panel-eyebrow">Continue sua jornada</p><h2>O que você deseja fazer?</h2></div>
    </div>
    <div class="shortcut-grid">
        <a class="shortcut-card" href="student_dashboard.php?section=livros"><span class="shortcut-icon">📖</span><strong>Pesquisar livros</strong><p>Explore o catálogo e encontre uma nova história.</p></a>
        <a class="shortcut-card" href="reservations.php"><span class="shortcut-icon">✦</span><strong>Reservar um livro</strong><p>Garanta seu lugar na fila de leitura.</p></a>
        <a class="shortcut-card" href="student_dashboard.php?section=perfil"><span class="shortcut-icon">◉</span><strong>Atualizar perfil</strong><p>Escolha um avatar e confira seus dados.</p></a>
    </div>
</div>
<?php endif; ?>

<?php if ($section === 'inicio' && !empty($dueSoon)): ?>
<div class="card">
    <div class="flash warning">
        <strong>Atenção!</strong> Você possui <?php echo count($dueSoon); ?> empréstimo(s) vencendo em até 2 dias.
    </div>
    <ul class="alert-list">
        <?php foreach ($dueSoon as $item): ?>
            <li><strong><?php echo h($item['title']); ?></strong> — vence em <?php echo h(format_date($item['due_date'])); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($section === 'perfil'): ?>
<div class="card profile-card" id="perfil">
    <div class="profile-summary">
        <img src="<?php echo h($displayPhoto); ?>" alt="Foto de perfil" class="profile-avatar">
        <div>
            <h2>Seu perfil</h2>
            <p>Matrícula: <?php echo h($matricula !== '' ? $matricula : 'Não informada'); ?></p>
            <p>Turma: <?php echo h($turma !== '' ? $turma : 'Não informada'); ?></p>
            <p>Turno: <?php echo h($turno !== '' ? $turno : 'Não informado'); ?></p>
            <p>Escolha um avatar padrão para representar seu perfil no sistema.</p>
        </div>
    </div>
    <?php if ($profileMessage): ?>
        <div class="flash <?php echo h($profileMessageType); ?>"><?php echo h($profileMessage); ?></div>
    <?php endif; ?>
    <form method="post" class="profile-form">
        <input type="hidden" name="profile_photo_form" value="1">
        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
        <div class="form-group">
            <label>Avatares padrão</label>
            <div class="avatar-options">
                <?php foreach ($avatarOptions as $option): ?>
                    <label class="avatar-option">
                        <input type="radio" name="avatar_choice" value="<?php echo h($option['value']); ?>" <?php echo ($selectedAvatar === $option['value'] ? 'checked' : ''); ?>>
                        <img src="<?php echo h(base_url($option['value'])); ?>" alt="<?php echo h($option['label']); ?>">
                        <span><?php echo h($option['label']); ?></span>
                    </label>
                <?php endforeach; ?>
                <label class="avatar-option">
                    <input type="radio" name="avatar_choice" value="" <?php echo ($selectedAvatar === '' ? 'checked' : ''); ?>>
                    <img src="<?php echo h(base_url('img/avatars/avatar-biblioteca.svg')); ?>" alt="Sem foto">
                    <span>Sem foto</span>
                </label>
            </div>
        </div>
        <div class="actions">
            <input type="submit" value="Salvar perfil">
            <button type="submit" name="remove_photo" value="1">Remover foto</button>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($section === 'livros'): ?>
<div class="card" id="catalogo">
    <div class="student-books-heading">
        <h2>Pesquisar livros</h2>
        <?php if ($books !== []): ?>
            <div class="view-toggle" aria-label="Alternar visualização">
                <button type="button" class="toggle-btn active" data-student-view="shelf">🗃️ Estante</button>
                <button type="button" class="toggle-btn" data-student-view="list">📋 Lista</button>
            </div>
        <?php endif; ?>
    </div>
    <form method="get" action="student_dashboard.php">
        <input type="hidden" name="section" value="livros">
        <div class="form-group">
            <label for="search">Título, autor, categoria ou estante</label>
            <input type="text" id="search" name="search" value="<?php echo h($search); ?>" placeholder="Buscar no catálogo...">
        </div>
        <input type="submit" value="Pesquisar">
    </form>
    <?php if ($books === []): ?>
        <p>Nenhum livro encontrado.</p>
    <?php else: ?>
        <div id="student-shelf-view" class="virtual-bookshelf student-virtual-bookshelf">
            <?php
            $booksByShelf = [];
            foreach ($books as $book) {
                $shelfName = trim((string)($book['shelf'] ?? '')) ?: 'Estante não informada';
                $booksByShelf[$shelfName][] = $book;
            }
            ksort($booksByShelf, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($booksByShelf as $shelfName => $shelfBooks):
            ?>
                <div class="shelf">
                    <div class="shelf-label"><?php echo h($shelfName); ?> <span class="shelf-count">(<?php echo count($shelfBooks); ?>)</span></div>
                    <?php foreach ($shelfBooks as $book): ?>
                        <?php $coverPath = !empty($book['cover_path']) ? base_url($book['cover_path']) : ''; ?>
                        <div class="book-item" title="<?php echo h($book['title']); ?> - <?php echo h($book['author']); ?>">
                            <div class="book-badge <?php echo (int)$book['quantity'] > 1 ? 'available' : ((int)$book['quantity'] > 0 ? 'low' : 'empty'); ?>"><?php echo (int)$book['quantity']; ?></div>
                            <?php if ($coverPath !== ''): ?>
                                <img class="book-cover-img" src="<?php echo h($coverPath); ?>" alt="Capa do livro <?php echo h($book['title']); ?>">
                            <?php else: ?>
                                <div class="book-spine">
                                    <div class="book-spine-title"><?php echo h($book['title']); ?></div>
                                    <div class="book-spine-author"><?php echo h($book['author']); ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="book-tooltip student-book-tooltip">
                                <strong><?php echo h($book['title']); ?></strong>
                                <div><?php echo h($book['author']); ?></div>
                                <span class="tooltip-category"><?php echo h($book['category']); ?></span>
                                <div>Estante: <?php echo h($shelfName); ?></div>
                                <?php if (!empty($book['pdf_path'])): ?>
                                    <a class="action-link" href="view_book_pdf.php?id=<?php echo (int)$book['id']; ?>" target="_blank" rel="noopener">Ver PDF</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="student-list-view" class="table-wrapper" style="display:none;">
            <table class="dashboard-table student-books-table">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Título</th>
                        <th>Autor</th>
                        <th>Categoria</th>
                        <th>Estante</th>
                        <th>Disponível</th>
                        <th>PDF</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td>
                                <?php if (!empty($book['cover_path'])): ?>
                                    <img src="<?php echo h(base_url($book['cover_path'])); ?>" alt="Capa de <?php echo h($book['title']); ?>" class="student-book-cover">
                                <?php else: ?>
                                    <span class="muted">Sem foto</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo h($book['title']); ?></strong></td>
                            <td><?php echo h($book['author']); ?></td>
                            <td><?php echo h($book['category']); ?></td>
                            <td><span class="shelf-pill"><?php echo h($book['shelf'] ?: 'Não informada'); ?></span></td>
                            <td><?php echo (int)$book['quantity']; ?></td>
                            <td>
                                <?php if (!empty($book['pdf_path'])): ?>
                                    <a class="action-link" href="view_book_pdf.php?id=<?php echo (int)$book['id']; ?>" target="_blank" rel="noopener">Ver PDF</a>
                                <?php else: ?>
                                    <span class="muted">Sem PDF</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const buttons = document.querySelectorAll('[data-student-view]');
        const shelfView = document.getElementById('student-shelf-view');
        const listView = document.getElementById('student-list-view');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                const isShelf = button.dataset.studentView === 'shelf';
                buttons.forEach(function (item) { item.classList.toggle('active', item === button); });
                shelfView.style.display = isShelf ? 'block' : 'none';
                listView.style.display = isShelf ? 'none' : 'block';
            });
        });
    });
</script>

<?php if ($section === 'emprestimos'): ?>
<div class="card" id="historico">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
        <h2 style="margin: 0;">Histórico de empréstimos e Autorizações</h2>
        <span class="muted" style="font-size: 0.85rem;">📄 Baixe a autorização em PDF para assinatura dos responsáveis</span>
    </div>
    <?php if (empty($history)): ?>
        <p>Você ainda não possui histórico de empréstimos.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Livro</th>
                    <th>Emprestado</th>
                    <th>Devolução</th>
                    <th>Status</th>
                    <th>Termo dos Pais</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $item): ?>
                    <tr>
                        <td><strong><?php echo h($item['title']); ?></strong></td>
                        <td><?php echo h(format_date($item['loaned_at'])); ?></td>
                        <td><?php echo h(format_date($item['due_date'])); ?></td>
                        <td>
                            <?php if ($item['returned_at']): ?>
                                <span class="status-pill status-returned">Devolvido</span>
                            <?php else: ?>
                                <?php $today = strtotime(date('Y-m-d')); $dueTimestamp = strtotime($item['due_date']); ?>
                                <?php if ($dueTimestamp < $today): ?>
                                    <span class="status-pill status-overdue">Atrasado</span>
                                <?php elseif ($dueTimestamp <= strtotime('+2 days', $today)): ?>
                                    <span class="status-pill status-warning">Vence em até 2 dias</span>
                                <?php else: ?>
                                    <span class="status-pill status-active">Ativo</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="loan_authorization_pdf.php?id=<?php echo (int)$item['id']; ?>" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600;">
                                📄 Baixar Termo PDF
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($section === 'emprestimos'): ?>
<div class="card">
    <h2>Pendências</h2>
    <?php if (empty($overdue)): ?>
        <p>Não há pendências de devolução.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Livro</th>
                    <th>Emprestado</th>
                    <th>Vencimento</th>
                    <th>Dias de atraso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overdue as $item): ?>
                    <tr>
                        <td><?php echo h($item['title']); ?></td>
                        <td><?php echo h(format_date($item['loaned_at'])); ?></td>
                        <td><?php echo h(format_date($item['due_date'])); ?></td>
                        <td><?php echo max(0, (int)floor((time() - strtotime($item['due_date'])) / 86400)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php endif; ?>

    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';

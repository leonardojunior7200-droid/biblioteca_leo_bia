<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/barcode.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

$db = get_db();
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$error = null;
$selectedUserId = 0;
$selectedBookId = 0;
$selectedLoanDays = LOAN_DAYS;

$userSearch = trim($_REQUEST['user_search'] ?? '');
$bookSearch = trim($_REQUEST['book_search'] ?? '');

if ($action === 'return' && $id) {
    $loan = $db->prepare('SELECT l.*, u.id AS user_id, u.blocked FROM loans l JOIN users u ON l.user_id = u.id WHERE l.id = :id AND l.returned_at IS NULL');
    $loan->execute([':id' => $id]);
    $loan = $loan->fetch();

    if ($loan) {
        $db->beginTransaction();
        $update = $db->prepare('UPDATE loans SET returned_at = DATE("now") WHERE id = :id');
        $update->execute([':id' => $id]);

        $db->prepare('UPDATE books SET quantity = quantity + 1 WHERE id = :book_id')->execute([':book_id' => $loan['book_id']]);

        if (strtotime($loan['due_date']) < strtotime(date('Y-m-d'))) {
            $db->prepare('UPDATE users SET blocked = 1 WHERE id = :user_id')->execute([':user_id' => $loan['user_id']]);
        }
        $db->commit();
        set_flash('Empréstimo devolvido com sucesso.');
    }

    redirect('loans.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $bookId = (int)($_POST['book_id'] ?? 0);
    $loanDays = (int)($_POST['loan_days'] ?? LOAN_DAYS);
    $selectedUserId = $userId;
    $selectedBookId = $bookId;
    $selectedLoanDays = $loanDays;

    if (!$userId || !$bookId) {
        $error = 'Usuário e livro são obrigatórios.';
    } elseif (!in_array($loanDays, LOAN_DAY_OPTIONS, true)) {
        $error = 'O tempo de empréstimo selecionado é inválido.';
    } else {
        $userStmt = $db->prepare('SELECT id, blocked, parent_name FROM users WHERE id = :id');
        $userStmt->execute([':id' => $userId]);
        $user = $userStmt->fetch();

        $bookStmt = $db->prepare('SELECT id, quantity FROM books WHERE id = :id');
        $bookStmt->execute([':id' => $bookId]);
        $book = $bookStmt->fetch();

        if (!$user || !$book) {
            $error = 'Usuário ou livro inválido.';
        } elseif ($user['blocked']) {
            $error = 'Usuário bloqueado não pode realizar novos empréstimos.';
        } elseif ($book['quantity'] <= 0) {
            $error = 'Livro indisponível no momento.';
        } else {
            $loanCountStmt = $db->prepare('SELECT COUNT(*) FROM loans WHERE user_id = :user_id AND returned_at IS NULL');
            $loanCountStmt->execute([':user_id' => $userId]);
            $loanCount = (int)$loanCountStmt->fetchColumn();

            if ($loanCount >= MAX_LOANS_PER_USER) {
                $error = 'Usuário já atingiu o limite de empréstimos.';
            } else {
                $db->beginTransaction();
                $dueDate = date('Y-m-d', strtotime('+' . $loanDays . ' days'));

                // Obter nome do responsável do usuário se já cadastrado
                $userParentName = $user['parent_name'] ?? null;

                $insert = $db->prepare('INSERT INTO loans (user_id, book_id, due_date, parent_name, parent_signature_status) VALUES (:user_id, :book_id, :due_date, :parent_name, "pendente")');
                $insert->execute([
                    ':user_id' => $userId,
                    ':book_id' => $bookId,
                    ':due_date' => $dueDate,
                    ':parent_name' => $userParentName,
                ]);
                $newLoanId = (int)$db->lastInsertId();
                $db->prepare('UPDATE books SET quantity = quantity - 1 WHERE id = :id')->execute([':id' => $bookId]);
                $db->commit();
                set_flash('Empréstimo registrado com sucesso! <a href="loan_authorization_pdf.php?id=' . $newLoanId . '" target="_blank" style="color: #fff; text-decoration: underline; font-weight: bold; margin-left: 8px;">📄 Imprimir Autorização dos Pais (PDF)</a>');
                $queryParams = [];
                if ($userSearch !== '') {
                    $queryParams['user_search'] = $userSearch;
                }
                if ($bookSearch !== '') {
                    $queryParams['book_search'] = $bookSearch;
                }
                $redirectUrl = 'loans.php' . ($queryParams ? '?' . http_build_query($queryParams) : '');
                redirect($redirectUrl);
            }
        }
    }
}

$loans = $db->query('SELECT l.id, u.name AS user_name, b.title AS book_title, b.shelf AS book_shelf, l.loaned_at, l.due_date, l.returned_at FROM loans l JOIN users u ON l.user_id = u.id JOIN books b ON l.book_id = b.id ORDER BY l.loaned_at DESC')->fetchAll();

$userQuery = 'SELECT id, name, blocked FROM users';
$userParams = [];
if ($userSearch !== '') {
    $userQuery .= ' WHERE name LIKE :user_search';
    $userParams[':user_search'] = '%' . $userSearch . '%';
}
$userQuery .= ' ORDER BY name';
$userStmt = $db->prepare($userQuery);
$userStmt->execute($userParams);
$users = $userStmt->fetchAll();

$bookQuery = 'SELECT id, title, quantity, barcode, internal_code FROM books';
$bookParams = [];
if ($bookSearch !== '') {
    $bookQuery .= ' WHERE title LIKE :book_search OR barcode LIKE :book_search OR internal_code LIKE :book_search OR isbn LIKE :book_search';
    $bookParams[':book_search'] = '%' . $bookSearch . '%';
}
$bookQuery .= ' ORDER BY title';
$bookStmt = $db->prepare($bookQuery);
$bookStmt->execute($bookParams);
$books = $bookStmt->fetchAll();

$totalUsers = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeLoans = (int)$db->query('SELECT COUNT(*) FROM loans WHERE returned_at IS NULL')->fetchColumn();
$overdueLoans = (int)$db->query('SELECT COUNT(*) FROM loans WHERE returned_at IS NULL AND due_date < DATE("now")')->fetchColumn();
$availableBooks = (int)$db->query('SELECT COUNT(*) FROM books WHERE quantity > 0')->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell">
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
                    <p>Gestão de empréstimos</p>
                </div>
            </div>
            <nav class="sidebar-nav" aria-label="Menu principal">
                <a class="nav-item" href="dashboard.php"><span class="nav-icon">◉</span><span>Dashboard</span></a>
                <a class="nav-item" href="index.php"><span class="nav-icon">◌</span><span>Catálogo</span></a>
                <a class="nav-item active" href="loans.php"><span class="nav-icon">◌</span><span>Empréstimos</span></a>
                <a class="nav-item" href="reservations.php"><span class="nav-icon">◌</span><span>Reservas</span></a>
                <a class="nav-item" href="reports.php"><span class="nav-icon">◌</span><span>Relatórios</span></a>
                <a class="nav-item" href="books.php"><span class="nav-icon">◌</span><span>Livros</span></a>
                <a class="nav-item" href="users.php"><span class="nav-icon">◌</span><span>Usuários & Pais</span></a>
                <a class="sidebar-logout nav-item" href="logout.php"><span class="nav-icon">↩</span><span>Sair</span></a>
            </nav>
        </div>
    </aside>

    <div class="dashboard-main-panel">
        <header class="dashboard-topbar">
            <div>
                <p class="eyebrow">Operação</p>
                <h1>Empréstimos</h1>
                <p class="topbar-subtitle">Controle de circulação e devolução dos livros.</p>
            </div>
        </header>

        <section class="stats-grid" aria-label="Resumo de empréstimos">
            <article class="stat-card stat-books">
                <div class="stat-icon">📚</div>
                <div>
                    <p class="stat-label">Disponíveis</p>
                    <h3><?php echo (int)$availableBooks; ?></h3>
                    <p class="stat-meta">Livros disponíveis</p>
                </div>
                <a href="books.php">Ver catálogo</a>
            </article>
            <article class="stat-card stat-loans">
                <div class="stat-icon">🗂️</div>
                <div>
                    <p class="stat-label">Ativos</p>
                    <h3><?php echo (int)$activeLoans; ?></h3>
                    <p class="stat-meta">Empréstimos em andamento</p>
                </div>
                <a href="loans.php">Ver empréstimos</a>
            </article>
            <article class="stat-card stat-reservations">
                <div class="stat-icon">⏰</div>
                <div>
                    <p class="stat-label">Atrasados</p>
                    <h3><?php echo (int)$overdueLoans; ?></h3>
                    <p class="stat-meta">Vencidos</p>
                </div>
                <a href="reports.php">Ver relatórios</a>
            </article>
            <article class="stat-card stat-users">
                <div class="stat-icon">👥</div>
                <div>
                    <p class="stat-label">Usuários</p>
                    <h3><?php echo (int)$totalUsers; ?></h3>
                    <p class="stat-meta">Usuários cadastrados</p>
                </div>
                <a href="users.php">Ver usuários</a>
            </article>
        </section>

        <div class="content-grid">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <p class="panel-eyebrow">Operação</p>
                        <h2>Novo empréstimo</h2>
                        <p class="panel-subtitle">Preencha os dados para realizar um novo empréstimo.</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="flash error"><?php echo h($error); ?></div>
                <?php endif; ?>

                <form method="get" action="loans.php" class="loan-form">
                    <div class="field-group">
                        <label for="user_search">Pesquisar aluno</label>
                        <input type="text" id="user_search" name="user_search" value="<?php echo h($userSearch); ?>" placeholder="Nome do aluno">
                    </div>
                    <div class="field-group">
                        <label for="book_search">Pesquisar livro</label>
                        <input type="text" id="book_search" name="book_search" value="<?php echo h($bookSearch); ?>" placeholder="Título ou código de barras" inputmode="numeric" autocomplete="off" autofocus>
                        <small class="muted">Leitor USB/Bluetooth: aponte, aguarde o bip e pressione Enter.</small>
                    </div>
                    <button type="submit" class="primary-btn">Filtrar</button>
                </form>

                <form method="post" action="loans.php" class="loan-form">
                    <input type="hidden" name="user_search" value="<?php echo h($userSearch); ?>">
                    <input type="hidden" name="book_search" value="<?php echo h($bookSearch); ?>">
                    <div class="field-group">
                        <label for="user_id">Usuário</label>
                        <select id="user_id" name="user_id" required>
                            <option value="">Selecione um usuário</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo (int)$user['id']; ?>"<?php echo $selectedUserId === (int)$user['id'] ? ' selected' : ''; ?>><?php echo h($user['name']); ?><?php echo $user['blocked'] ? ' (bloqueado)' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="book_id">Livro</label>
                        <select id="book_id" name="book_id" required>
                            <option value="">Selecione um livro</option>
                            <?php foreach ($books as $book): ?>
                                <option value="<?php echo (int)$book['id']; ?>"<?php echo $selectedBookId === (int)$book['id'] ? ' selected' : ''; ?>><?php echo h($book['title']); ?><?php echo !empty($book['barcode']) ? ' [' . h($book['barcode']) . ']' : ''; ?> (<?php echo (int)$book['quantity']; ?> disponíveis)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="loan_days">Tempo de empréstimo</label>
                        <select id="loan_days" name="loan_days" required>
                            <?php foreach (LOAN_DAY_OPTIONS as $days): ?>
                                <option value="<?php echo (int)$days; ?>"<?php echo $selectedLoanDays === (int)$days ? ' selected' : ''; ?>><?php echo (int)$days; ?> dias</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="primary-btn">Registrar empréstimo</button>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <p class="panel-eyebrow">Atalhos</p>
                        <h2>Ações rápidas</h2>
                    </div>
                </div>
                <div class="shortcut-grid">
                    <a href="loans.php" class="shortcut-card">
                        <span class="shortcut-icon">📚</span>
                        <strong>Novo empréstimo</strong>
                        <p>Registrar empréstimo</p>
                    </a>
                    <a href="reservations.php" class="shortcut-card">
                        <span class="shortcut-icon">⏳</span>
                        <strong>Nova reserva</strong>
                        <p>Reservar um livro</p>
                    </a>
                    <a href="reports.php" class="shortcut-card">
                        <span class="shortcut-icon">📈</span>
                        <strong>Ver relatórios</strong>
                        <p>Gerar relatórios</p>
                    </a>
                    <a href="books.php" class="shortcut-card">
                        <span class="shortcut-icon">📖</span>
                        <strong>Catálogo</strong>
                        <p>Ver todos os livros</p>
                    </a>
                </div>
            </section>
        </div>

        <section class="panel table-panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Histórico</p>
                    <h2>Últimos empréstimos</h2>
                    <p class="panel-subtitle">Movimentações recentes do acervo.</p>
                </div>
                <a class="soft-link" href="loans.php">Ver todos</a>
            </div>
            <div class="table-wrapper">
                <?php if (empty($loans)): ?>
                    <p class="empty-state">Sem empréstimos registrados.</p>
                <?php else: ?>
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Livro</th>
                                <th>Estante</th>
                                <th>Emprestado</th>
                                <th>Devolução</th>
                                <th>Status</th>
                                <th>Autorização Pais</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td><?php echo h($loan['user_name']); ?></td>
                                    <td><?php echo h($loan['book_title']); ?></td>
                                    <td><?php echo h($loan['book_shelf']); ?></td>
                                    <td><?php echo h(format_date($loan['loaned_at'])); ?></td>
                                    <td><?php echo h(format_date($loan['due_date'])); ?></td>
                                    <td>
                                        <?php if ($loan['returned_at']): ?>
                                            <span class="status-pill status-returned">Devolvido</span>
                                        <?php else: ?>
                                            <?php $today = strtotime(date('Y-m-d')); $dueTimestamp = strtotime($loan['due_date']); ?>
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
                                        <a class="soft-link" href="loan_authorization_pdf.php?id=<?php echo (int)$loan['id']; ?>" target="_blank" rel="noopener" style="font-size: 0.85rem; font-weight: bold; color: #1d4ed8; display: inline-flex; align-items: center; gap: 4px;">
                                            📄 PDF Pais
                                        </a>
                                    </td>
                                    <td>
                                        <?php if (!$loan['returned_at']): ?>
                                            <a class="action-link" href="loans.php?action=return&id=<?php echo (int)$loan['id']; ?>" onclick="return confirm('Registrar devolução?');">Devolver</a>
                                        <?php else: ?>
                                            <span class="action-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php';
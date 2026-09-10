<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

$db = get_db();

// Obter mapeamento de roles
$roleStmt = $db->query('SELECT id, name FROM roles');
$rolesMap = [];
foreach ($roleStmt->fetchAll() as $row) {
    $rolesMap[$row['name']] = $row['id'];
}

$reportType = $_GET['report'] ?? 'loans';
if (!in_array($reportType, ['loans', 'books', 'users'], true)) {
    $reportType = 'loans';
}
$export = $_GET['export'] ?? '';

// Capturar filtros
$filterStudent = isset($_GET['student']) && is_numeric($_GET['student']) ? (int)$_GET['student'] : null;
$filterCategory = isset($_GET['category']) ? trim($_GET['category']) : '';

// Construir condições SQL dinâmicas para empréstimos
$loanConditions = [];
$loanParams = [];

if ($filterStudent) {
    $loanConditions[] = 'l.user_id = :student_id';
    $loanParams[':student_id'] = $filterStudent;
}

if ($filterCategory) {
    $loanConditions[] = 'b.category = :category';
    $loanParams[':category'] = $filterCategory;
}

$whereClause = !empty($loanConditions) ? 'WHERE ' . implode(' AND ', $loanConditions) . ' AND ' : 'WHERE ';
$activeLoans = $db->prepare("SELECT l.id, u.name AS user_name, u.id AS user_id, b.title AS book_title, b.category AS book_category, l.loaned_at, l.due_date FROM loans l JOIN users u ON l.user_id = u.id JOIN books b ON l.book_id = b.id {$whereClause} l.returned_at IS NULL ORDER BY l.due_date ASC");
$activeLoans->execute($loanParams);
$activeLoans = $activeLoans->fetchAll();

$overdueLoans = $db->prepare("SELECT l.id, u.name AS user_name, u.id AS user_id, b.title AS book_title, b.category AS book_category, l.loaned_at, l.due_date FROM loans l JOIN users u ON l.user_id = u.id JOIN books b ON l.book_id = b.id {$whereClause} l.returned_at IS NULL AND l.due_date < DATE('now') ORDER BY l.due_date ASC");
$overdueLoans->execute($loanParams);
$overdueLoans = $overdueLoans->fetchAll();

// Queries para livros e usuários (com filtros)
$bookConditions = [];
$bookParams = [];
if ($filterCategory) {
    $bookConditions[] = 'category = :category';
    $bookParams[':category'] = $filterCategory;
}
$bookWhere = !empty($bookConditions) ? 'WHERE ' . implode(' AND ', $bookConditions) : '';
$allBooks = $db->prepare("SELECT id, title, author, category, quantity, shelf FROM books {$bookWhere} ORDER BY title");
if (!empty($bookParams)) {
    $allBooks->execute($bookParams);
} else {
    $allBooks = $db->query("SELECT id, title, author, category, quantity, shelf FROM books ORDER BY title")->fetchAll();
}

// Estatísticas
$stats = [
    'total_loans' => count($activeLoans),
    'total_overdue' => count($overdueLoans),
    'total_books_taken' => 0,
    'by_student' => [],
    'by_category' => []
];

// Calcular estatísticas por aluno e categoria
foreach ($activeLoans as $loan) {
    $stats['total_books_taken']++;
    
    // Por aluno
    if (!isset($stats['by_student'][$loan['user_id']])) {
        $stats['by_student'][$loan['user_id']] = [
            'name' => $loan['user_name'],
            'count' => 0
        ];
    }
    $stats['by_student'][$loan['user_id']]['count']++;
    
    // Por categoria
    if (!isset($stats['by_category'][$loan['book_category']])) {
        $stats['by_category'][$loan['book_category']] = 0;
    }
    $stats['by_category'][$loan['book_category']]++;
}

// Se filtrou por aluno específico, obter informações
$selectedStudent = null;
if ($filterStudent) {
    $stmt = $db->prepare('SELECT id, name, email FROM users WHERE id = :id');
    $stmt->execute([':id' => $filterStudent]);
    $selectedStudent = $stmt->fetch();
}

$lowStock = $db->query('SELECT id, title, quantity FROM books WHERE quantity <= 2 ORDER BY quantity ASC')->fetchAll();
$blockedUsers = $db->query('SELECT id, name, email FROM users WHERE blocked = 1 ORDER BY name')->fetchAll();
$allUsers = $db->query('SELECT id, name, email, blocked, role_id FROM users ORDER BY name')->fetchAll();

if ($export === 'csv') {
    $csvSafe = static function (mixed $value): string {
        $value = (string)$value;
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    };
    $rows = [];
    if ($reportType === 'books') {
        $rows = $allBooks;
        $headers = ['id', 'titulo', 'autor', 'categoria', 'quantidade', 'prateleira'];
    } elseif ($reportType === 'users') {
        $rows = $allUsers;
        $headers = ['id', 'nome', 'email', 'bloqueado'];
    } else {
        $rows = $activeLoans;
        $headers = ['id', 'usuario', 'livro', 'emprestado', 'devolucao'];
    }

    $output = fopen('php://temp', 'r+');
    fputcsv($output, $headers);
    foreach ($rows as $row) {
        if ($reportType === 'books') {
            fputcsv($output, array_map($csvSafe, [$row['id'], $row['title'], $row['author'], $row['category'], $row['quantity'], $row['shelf']]));
        } elseif ($reportType === 'users') {
            fputcsv($output, array_map($csvSafe, [$row['id'], $row['name'], $row['email'], $row['blocked'] ? 'Sim' : 'Não']));
        } else {
            fputcsv($output, array_map($csvSafe, [$row['id'], $row['user_name'], $row['book_title'], $row['loaned_at'], $row['due_date']]));
        }
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $reportType . '-report.csv');
    echo $csv;
    exit;
}

if ($export === 'pdf') {
    $title = 'Relatório';
    $content = '';
    if ($reportType === 'books') {
        $title = 'Relatório de Livros';
        foreach ($allBooks as $book) {
            $content .= '• ' . $book['title'] . ' | ' . $book['author'] . ' | Estoque: ' . $book['quantity'] . PHP_EOL;
        }
    } elseif ($reportType === 'users') {
        $title = 'Relatório de Usuários';
        foreach ($allUsers as $user) {
            $content .= '• ' . $user['name'] . ' | ' . $user['email'] . ' | Bloqueado: ' . ($user['blocked'] ? 'Sim' : 'Não') . PHP_EOL;
        }
    } else {
        $title = 'Relatório de Empréstimos';
        foreach ($activeLoans as $loan) {
            $content .= '• ' . $loan['user_name'] . ' | ' . $loan['book_title'] . ' | Vencimento: ' . $loan['due_date'] . PHP_EOL;
        }
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $reportType . '-report.txt');
    echo $title . PHP_EOL . 'Gerado em: ' . date('d/m/Y H:i') . PHP_EOL . PHP_EOL . $content;
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell">
    <?php $sidebarActive = 'reports.php'; $sidebarSubtitle = 'Relatórios e métricas'; require __DIR__ . '/includes/admin_sidebar.php'; ?>

    <div class="dashboard-main-panel">
        <header class="dashboard-topbar">
            <div>
                <p class="eyebrow">Indicadores</p>
                <h1>Relatórios</h1>
                <p class="topbar-subtitle">Visualize os dados principais da biblioteca e exporte os relatórios.</p>
            </div>
        </header>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Exportar</p>
                    <h2>Gerar relatórios</h2>
                    <p class="panel-subtitle">Selecione o tipo de relatório e aplique filtros para refinar os resultados.</p>
                </div>
            </div>
            <form method="get" action="reports.php" class="loan-form">
                <div class="field-group">
                    <label for="report">Tipo de relatório</label>
                    <select id="report" name="report">
                        <option value="loans" <?php echo $reportType === 'loans' ? 'selected' : ''; ?>>Empréstimos ativos</option>
                        <option value="books" <?php echo $reportType === 'books' ? 'selected' : ''; ?>>Livros</option>
                        <option value="users" <?php echo $reportType === 'users' ? 'selected' : ''; ?>>Usuários</option>
                    </select>
                </div>
                
                <!-- Filtros adicionais -->
                <div class="field-group" id="student-filter" style="display: none;">
                    <label for="student">Filtrar por aluno</label>
                    <select id="student" name="student">
                        <option value="">Todos os alunos</option>
                        <?php foreach ($allUsers as $user): ?>
                            <?php if ($user['role_id'] == $rolesMap['Aluno'] || $user['role_id'] == $rolesMap['Visitante']): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo isset($_GET['student']) && $_GET['student'] == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($user['name']); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="field-group" id="category-filter" style="display: none;">
                    <label for="category">Filtrar por categoria</label>
                    <select id="category" name="category">
                        <option value="">Todas as categorias</option>
                        <?php 
                        $categories = $db->query('SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category != "" ORDER BY category')->fetchAll();
                        foreach ($categories as $cat): ?>
                            <option value="<?php echo h($cat['category']); ?>" <?php echo isset($_GET['category']) && $_GET['category'] == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo h($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="table-actions">
                    <button type="submit" class="primary-btn">Visualizar</button>
                    <a class="primary-btn" href="reports.php?report=<?php echo h($reportType); ?><?php echo isset($_GET['student']) ? '&student=' . h($_GET['student']) : ''; ?><?php echo isset($_GET['category']) ? '&category=' . h($_GET['category']) : ''; ?>&export=csv">Baixar CSV</a>
                    <a class="primary-btn" href="reports.php?report=<?php echo h($reportType); ?><?php echo isset($_GET['student']) ? '&student=' . h($_GET['student']) : ''; ?><?php echo isset($_GET['category']) ? '&category=' . h($_GET['category']) : ''; ?>&export=pdf">Baixar TXT</a>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Visualização</p>
                    <h2>Dados do relatório</h2>
                </div>
            </div>
            <?php if ($reportType === 'books'): ?>
                <?php if (empty($allBooks)): ?>
                    <p class="empty-state">Sem livros cadastrados.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Autor</th>
                                    <th>Categoria</th>
                                    <th>Quantidade</th>
                                    <th>Prateleira</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allBooks as $book): ?>
                                    <tr>
                                        <td><?php echo h($book['title']); ?></td>
                                        <td><?php echo h($book['author']); ?></td>
                                        <td><?php echo h($book['category']); ?></td>
                                        <td><?php echo (int)$book['quantity']; ?></td>
                                        <td><?php echo h($book['shelf'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php elseif ($reportType === 'users'): ?>
                <?php if (empty($allUsers)): ?>
                    <p class="empty-state">Sem usuários cadastrados.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUsers as $user): ?>
                                    <tr>
                                        <td><?php echo h($user['name']); ?></td>
                                        <td><?php echo h($user['email']); ?></td>
                                        <td><?php echo $user['blocked'] ? 'Bloqueado' : 'Ativo'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (empty($activeLoans)): ?>
                    <p class="empty-state">Sem empréstimos ativos.</p>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Usuário</th>
                                    <th>Livro</th>
                                    <th>Emprestado</th>
                                    <th>Devolução</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeLoans as $loan): ?>
                                    <tr>
                                        <td><?php echo h($loan['user_name']); ?></td>
                                        <td><?php echo h($loan['book_title']); ?></td>
                                        <td><?php echo h(format_date($loan['loaned_at'])); ?></td>
                                        <td><?php echo h(format_date($loan['due_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Resumo</p>
                    <h2>Empréstimos atrasados</h2>
                </div>
            </div>
            <?php if (empty($overdueLoans)): ?>
                <p class="empty-state">Sem empréstimos atrasados.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Livro</th>
                                <th>Emprestado</th>
                                <th>Devolução</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdueLoans as $loan): ?>
                                <tr>
                                    <td><?php echo h($loan['user_name']); ?></td>
                                    <td><?php echo h($loan['book_title']); ?></td>
                                    <td><?php echo h(format_date($loan['loaned_at'])); ?></td>
                                    <td><?php echo h(format_date($loan['due_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Monitoramento</p>
                    <h2>Estoque baixo</h2>
                </div>
            </div>
            <?php if (empty($lowStock)): ?>
                <p class="empty-state">Sem livros com estoque baixo.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Quantidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStock as $book): ?>
                                <tr>
                                    <td><?php echo h($book['title']); ?></td>
                                    <td><?php echo (int)$book['quantity']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Usuários</p>
                    <h2>Usuários bloqueados</h2>
                </div>
            </div>
            <?php if (empty($blockedUsers)): ?>
                <p class="empty-state">Sem usuários bloqueados.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blockedUsers as $user): ?>
                                <tr>
                                    <td><?php echo h($user['name']); ?></td>
                                    <td><?php echo h($user['email']); ?></td>
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

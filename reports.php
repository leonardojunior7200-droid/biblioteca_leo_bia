<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

$db = get_db();

$reportType = $_GET['report'] ?? 'loans';
if (!in_array($reportType, ['loans', 'books', 'users'], true)) {
    $reportType = 'loans';
}
$export = $_GET['export'] ?? '';

$activeLoans = $db->query('SELECT l.id, u.name AS user_name, b.title AS book_title, l.loaned_at, l.due_date FROM loans l JOIN users u ON l.user_id = u.id JOIN books b ON l.book_id = b.id WHERE l.returned_at IS NULL ORDER BY l.due_date ASC')->fetchAll();
$overdueLoans = $db->query('SELECT l.id, u.name AS user_name, b.title AS book_title, l.loaned_at, l.due_date FROM loans l JOIN users u ON l.user_id = u.id JOIN books b ON l.book_id = b.id WHERE l.returned_at IS NULL AND l.due_date < DATE("now") ORDER BY l.due_date ASC')->fetchAll();
$lowStock = $db->query('SELECT id, title, quantity FROM books WHERE quantity <= 2 ORDER BY quantity ASC')->fetchAll();
$blockedUsers = $db->query('SELECT id, name, email FROM users WHERE blocked = 1 ORDER BY name')->fetchAll();
$allBooks = $db->query('SELECT id, title, author, category, quantity, shelf FROM books ORDER BY title')->fetchAll();
$allUsers = $db->query('SELECT id, name, email, blocked FROM users ORDER BY name')->fetchAll();

if ($export === 'csv') {
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
            fputcsv($output, [$row['id'], $row['title'], $row['author'], $row['category'], $row['quantity'], $row['shelf']]);
        } elseif ($reportType === 'users') {
            fputcsv($output, [$row['id'], $row['name'], $row['email'], $row['blocked'] ? 'Sim' : 'Não']);
        } else {
            fputcsv($output, [$row['id'], $row['user_name'], $row['book_title'], $row['loaned_at'], $row['due_date']]);
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
<div class="card">
    <h1>Relatórios</h1>
    <p>Gere relatórios rápidos do sistema em formato de planilha ou texto para download.</p>
</div>

<div class="card">
    <h2>Exportar relatórios</h2>
    <form method="get" action="reports.php" class="report-filters">
        <div class="form-group">
            <label for="report">Tipo de relatório</label>
            <select id="report" name="report">
                <option value="loans" <?php echo $reportType === 'loans' ? 'selected' : ''; ?>>Empréstimos ativos</option>
                <option value="books" <?php echo $reportType === 'books' ? 'selected' : ''; ?>>Livros</option>
                <option value="users" <?php echo $reportType === 'users' ? 'selected' : ''; ?>>Usuários</option>
            </select>
        </div>
        <div class="actions">
            <button type="submit" class="btn-panel">Visualizar</button>
            <a class="btn-panel" href="reports.php?report=<?php echo h($reportType); ?>&export=csv">Baixar CSV</a>
            <a class="btn-panel" href="reports.php?report=<?php echo h($reportType); ?>&export=pdf">Baixar PDF</a>
        </div>
    </form>
</div>

<div class="card">
    <h2>Visualização do relatório</h2>
    <?php if ($reportType === 'books'): ?>
        <?php if (empty($allBooks)): ?>
            <p>Sem livros cadastrados.</p>
        <?php else: ?>
            <table>
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
        <?php endif; ?>
    <?php elseif ($reportType === 'users'): ?>
        <?php if (empty($allUsers)): ?>
            <p>Sem usuários cadastrados.</p>
        <?php else: ?>
            <table>
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
        <?php endif; ?>
    <?php else: ?>
        <?php if (empty($activeLoans)): ?>
            <p>Sem empréstimos ativos.</p>
        <?php else: ?>
            <table>
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
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Empréstimos atrasados</h2>
    <?php if (empty($overdueLoans)): ?>
        <p>Sem empréstimos atrasados.</p>
    <?php else: ?>
        <table>
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
    <?php endif; ?>
</div>

<div class="card">
    <h2>Estoque baixo</h2>
    <?php if (empty($lowStock)): ?>
        <p>Sem livros com estoque baixo.</p>
    <?php else: ?>
        <table>
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
    <?php endif; ?>
</div>

<div class="card">
    <h2>Usuários bloqueados</h2>
    <?php if (empty($blockedUsers)): ?>
        <p>Sem usuários bloqueados.</p>
    <?php else: ?>
        <table>
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php';

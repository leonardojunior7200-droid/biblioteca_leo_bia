<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

$db = get_db();
$activeLoans = $db->query('SELECT l.id, u.name AS user_name, b.title AS book_title, l.loaned_at, l.due_date FROM loans l JOIN users u ON l.user_id = u.id JOIN books b ON l.book_id = b.id WHERE l.returned_at IS NULL ORDER BY l.due_date ASC')->fetchAll();
$overdueLoans = $db->query('SELECT l.id, u.name AS user_name, b.title AS book_title, l.loaned_at, l.due_date FROM loans l JOIN users u ON l.user_id = u.id JOIN books b ON l.book_id = b.id WHERE l.returned_at IS NULL AND l.due_date < DATE("now") ORDER BY l.due_date ASC')->fetchAll();
$lowStock = $db->query('SELECT id, title, quantity FROM books WHERE quantity <= 2 ORDER BY quantity ASC')->fetchAll();
$blockedUsers = $db->query('SELECT id, name, email FROM users WHERE blocked = 1 ORDER BY name')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Relatórios</h1>
</div>

<div class="card">
    <h2>Empréstimos ativos</h2>
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

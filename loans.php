<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

$db = get_db();
action:
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$error = null;

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

    if (!$userId || !$bookId) {
        $error = 'Usuário e livro são obrigatórios.';
    } else {
        $userStmt = $db->prepare('SELECT id, blocked FROM users WHERE id = :id');
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
                $insert = $db->prepare('INSERT INTO loans (user_id, book_id, due_date) VALUES (:user_id, :book_id, DATE("now", "+' . LOAN_DAYS . ' days"))');
                $insert->execute([':user_id' => $userId, ':book_id' => $bookId]);
                $db->prepare('UPDATE books SET quantity = quantity - 1 WHERE id = :id')->execute([':id' => $bookId]);
                $db->commit();
                set_flash('Empréstimo registrado com sucesso.');
                redirect('loans.php');
            }
        }
    }
}

$loans = $db->query('SELECT l.id, u.name AS user_name, b.title AS book_title, l.loaned_at, l.due_date, l.returned_at FROM loans l JOIN users u ON l.user_id = u.id JOIN books b ON l.book_id = b.id ORDER BY l.loaned_at DESC')->fetchAll();
$users = $db->query('SELECT id, name, blocked FROM users ORDER BY name')->fetchAll();
$books = $db->query('SELECT id, title, quantity FROM books ORDER BY title')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Empréstimos</h1>
</div>

<div class="card">
    <h2>Novo empréstimo</h2>
    <?php if ($error): ?>
        <div class="flash error"><?php echo h($error); ?></div>
    <?php endif; ?>
    <form method="post" action="loans.php">
        <div class="form-group">
            <label for="user_id">Usuário</label>
            <select id="user_id" name="user_id" required>
                <option value="">Selecione um usuário</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo (int)$user['id']; ?>"><?php echo h($user['name']); ?><?php echo $user['blocked'] ? ' (bloqueado)' : ''; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="book_id">Livro</label>
            <select id="book_id" name="book_id" required>
                <option value="">Selecione um livro</option>
                <?php foreach ($books as $book): ?>
                    <option value="<?php echo (int)$book['id']; ?>"><?php echo h($book['title']); ?> (<?php echo (int)$book['quantity']; ?> disponíveis)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="submit" value="Registrar empréstimo">
    </form>
</div>

<div class="card">
    <h2>Lista de empréstimos</h2>
    <?php if (empty($loans)): ?>
        <p>Sem empréstimos registrados.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Livro</th>
                    <th>Emprestado</th>
                    <th>Devolução</th>
                    <th>Status</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($loans as $loan): ?>
                    <tr>
                        <td><?php echo h($loan['user_name']); ?></td>
                        <td><?php echo h($loan['book_title']); ?></td>
                        <td><?php echo h(format_date($loan['loaned_at'])); ?></td>
                        <td><?php echo h(format_date($loan['due_date'])); ?></td>
                        <td>
                            <?php if ($loan['returned_at']): ?>Devolvido em <?php echo h(format_date($loan['returned_at'])); ?>
                            <?php elseif (strtotime($loan['due_date']) < time()): ?>Atrasado
                            <?php else: ?>Ativo
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$loan['returned_at']): ?>
                                <a href="loans.php?action=return&id=<?php echo (int)$loan['id']; ?>" onclick="return confirm('Registrar devolução?');">Devolver</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php';

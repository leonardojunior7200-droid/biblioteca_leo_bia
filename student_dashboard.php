<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role('Aluno');

$db = get_db();

$search = trim($_GET['search'] ?? '');
$searchQuery = '';
$params = [];

if ($search !== '') {
    $searchQuery = 'WHERE title LIKE :search OR author LIKE :search OR category LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$books = $db->prepare('SELECT id, title, author, category, quantity, cover_path FROM books ' . $searchQuery . ' ORDER BY title');
$books->execute($params);
$books = $books->fetchAll();

$user = current_user();
$userId = (int)$user['id'];

$history = $db->prepare('SELECT l.id, b.title, l.loaned_at, l.due_date, l.returned_at FROM loans l JOIN books b ON l.book_id = b.id WHERE l.user_id = :user_id ORDER BY l.loaned_at DESC');
$history->execute([':user_id' => $userId]);
$history = $history->fetchAll();

$overdue = $db->prepare('SELECT l.id, b.title, l.loaned_at, l.due_date FROM loans l JOIN books b ON l.book_id = b.id WHERE l.user_id = :user_id AND l.returned_at IS NULL AND l.due_date < DATE("now") ORDER BY l.due_date ASC');
$overdue->execute([':user_id' => $userId]);
$overdue = $overdue->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Painel do Aluno</h1>
    <p>Bem-vindo, <?php echo h($user['name']); ?>. Aqui você pode pesquisar livros e ver seu histórico e pendências.</p>
</div>

<div class="card">
    <h2>Pesquisar livros</h2>
    <form method="get" action="student_dashboard.php">
        <div class="form-group">
            <label for="search">Título, autor ou categoria</label>
            <input type="text" id="search" name="search" value="<?php echo h($search); ?>" placeholder="Buscar no catálogo...">
        </div>
        <input type="submit" value="Pesquisar">
    </form>
    <?php if ($books === []): ?>
        <p>Nenhum livro encontrado.</p>
    <?php else: ?>
        <table>
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
                                <img src="<?php echo h(base_url($book['cover_path'])); ?>" alt="Foto do livro" style="max-width: 60px; max-height: 60px; object-fit: cover;">
                            <?php else: ?>
                                <span>Sem foto</span>
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
    <?php endif; ?>
</div>

<div class="card">
    <h2>Histórico de empréstimos</h2>
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
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $item): ?>
                    <tr>
                        <td><?php echo h($item['title']); ?></td>
                        <td><?php echo h(format_date($item['loaned_at'])); ?></td>
                        <td><?php echo h(format_date($item['due_date'])); ?></td>
                        <td>
                            <?php if ($item['returned_at']): ?>
                                Devolvido em <?php echo h(format_date($item['returned_at'])); ?>
                            <?php elseif (strtotime($item['due_date']) < time()): ?>
                                Atrasado
                            <?php else: ?>
                                Ativo
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

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

<?php require_once __DIR__ . '/includes/footer.php';

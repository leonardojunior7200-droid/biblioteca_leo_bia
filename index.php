<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$books = [];
$hasData = true;

try {
    $db = get_db();
    $stmt = $db->query('SELECT id, title, author, category, quantity FROM books ORDER BY title LIMIT 10');
    $books = $stmt->fetchAll();
} catch (Exception $e) {
    $hasData = false;
}
?>
<div class="card">
    <h1>Bem-vindo à <?php echo h(SITE_NAME); ?></h1>
    <p>Este sistema de biblioteca escolar gerencia livros, empréstimos, devoluções e reservas.</p>
    <?php if (is_logged_in()): ?>
        <p><a href="<?php echo h(base_url('dashboard.php')); ?>">Ir para o painel</a></p>
    <?php else: ?>
        <p><a href="<?php echo h(base_url('login.php')); ?>">Entrar no sistema</a></p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Catálogo rápido</h2>
    <?php if (!$hasData): ?>
        <p>O banco de dados ainda não foi inicializado. Execute <a href="<?php echo h(base_url('setup.php')); ?>">setup.php</a> para criar o esquema e dados iniciais.</p>
    <?php elseif (empty($books)): ?>
        <p>Nenhum livro encontrado no catálogo.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Autor</th>
                    <th>Categoria</th>
                    <th>Disponível</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $book): ?>
                    <tr>
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

<?php require_once __DIR__ . '/includes/footer.php';

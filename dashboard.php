<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

if (user_has_role('Aluno')) {
    redirect('student_dashboard.php');
}

$stats = [
    'books' => 0,
    'loans' => 0,
    'overdue' => 0,
    'users' => 0,
];

try {
    $db = get_db();
    $stats['books'] = (int)$db->query('SELECT COUNT(*) FROM books')->fetchColumn();
    $stats['loans'] = (int)$db->query('SELECT COUNT(*) FROM loans WHERE returned_at IS NULL')->fetchColumn();
    $stats['overdue'] = (int)$db->query('SELECT COUNT(*) FROM loans WHERE returned_at IS NULL AND due_date < DATE("now")')->fetchColumn();
    $stats['users'] = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();

    $bookUsage = $db->query('SELECT b.id, b.title, COUNT(l.id) AS loan_count FROM books b LEFT JOIN loans l ON l.book_id = b.id GROUP BY b.id ORDER BY loan_count DESC, b.title ASC')->fetchAll();
    $maxCount = !empty($bookUsage) ? max(array_column($bookUsage, 'loan_count')) : 0;
    $maxCount = max(1, (int)$maxCount);

    $mostRead = array_slice($bookUsage, 0, 5);
    $leastRead = array_slice(array_reverse($bookUsage), 0, 5);
} catch (Exception $e) {
    $bookUsage = [];
    $mostRead = [];
    $leastRead = [];
    $maxCount = 1;
    // Database not initialized yet.
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Painel</h1>
    <p>Bem-vindo, <?php echo h(current_user()['name']); ?>.</p>
</div>

<div class="card">
    <h2>Visão geral</h2>
    <table>
        <tr><th>Livros cadastrados</th><td><?php echo h((string)$stats['books']); ?></td></tr>
        <tr><th>Empréstimos ativos</th><td><?php echo h((string)$stats['loans']); ?></td></tr>
        <tr><th>Empréstimos atrasados</th><td><?php echo h((string)$stats['overdue']); ?></td></tr>
        <tr><th>Usuários cadastrados</th><td><?php echo h((string)$stats['users']); ?></td></tr>
    </table>
</div>

<div class="card">
    <h2>Leitura dos livros</h2>
    <p>Veja rapidamente os livros com mais e menos empréstimos.</p>

    <?php if (empty($bookUsage)): ?>
        <p class="muted">Ainda não há dados de empréstimos para exibir.</p>
    <?php else: ?>
        <div class="chart-grid">
            <div class="chart-panel">
                <h3>Mais lidos</h3>
                <?php foreach ($mostRead as $book): ?>
                    <?php $width = $maxCount > 0 ? round(((int)$book['loan_count'] / $maxCount) * 100) : 0; ?>
                    <div class="chart-row">
                        <div class="chart-meta">
                            <span><?php echo h($book['title']); ?></span>
                            <strong><?php echo (int)$book['loan_count']; ?></strong>
                        </div>
                        <div class="chart-bar">
                            <span style="width: <?php echo h((string)$width); ?>%"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="chart-panel">
                <h3>Menos lidos</h3>
                <?php foreach ($leastRead as $book): ?>
                    <?php $width = $maxCount > 0 ? round(((int)$book['loan_count'] / $maxCount) * 100) : 0; ?>
                    <div class="chart-row">
                        <div class="chart-meta">
                            <span><?php echo h($book['title']); ?></span>
                            <strong><?php echo (int)$book['loan_count']; ?></strong>
                        </div>
                        <div class="chart-bar">
                            <span class="low" style="width: <?php echo h((string)$width); ?>%"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php';

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
} catch (Exception $e) {
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

<?php require_once __DIR__ . '/includes/footer.php';

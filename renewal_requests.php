<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

$db = get_db();
$csrfToken = csrf_token();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Sessão expirada ou token inválido.');
    }

    $requestId = require_positive_int($_POST['id'] ?? null);
    $action = $_POST['action'] ?? '';
    $note = trim((string)($_POST['admin_note'] ?? ''));
    $request = null;
    if ($requestId) {
        $stmt = $db->prepare('SELECT rr.*, l.returned_at, l.due_date, l.book_id, u.name AS user_name, b.title AS book_title FROM loan_renewal_requests rr JOIN loans l ON l.id = rr.loan_id JOIN users u ON u.id = rr.user_id JOIN books b ON b.id = l.book_id WHERE rr.id = :id');
        $stmt->execute([':id' => $requestId]);
        $request = $stmt->fetch();
    }

    if (!$request || $request['status'] !== 'pending') {
        $error = 'Solicitação inexistente ou já analisada.';
    } elseif ($request['returned_at']) {
        $error = 'O empréstimo já foi devolvido.';
    } elseif (!in_array($action, ['approve', 'reject'], true)) {
        $error = 'Ação inválida.';
    } elseif ($action === 'reject' && $note === '') {
        $error = 'Informe o motivo da recusa.';
    } else {
        $reviewerId = (int)current_user()['id'];
        if ($action === 'approve') {
            $newDueDate = date('Y-m-d', strtotime($request['due_date'] . ' +' . LOAN_DAYS . ' days'));
            $db->beginTransaction();
            $db->prepare('UPDATE loans SET due_date = :due_date WHERE id = :loan_id AND returned_at IS NULL')->execute([':due_date' => $newDueDate, ':loan_id' => $request['loan_id']]);
            $db->prepare('UPDATE loan_renewal_requests SET status = "approved", reviewed_at = CURRENT_TIMESTAMP, reviewed_by = :reviewer, new_due_date = :new_due_date, admin_note = :note WHERE id = :id AND status = "pending"')->execute([':reviewer' => $reviewerId, ':new_due_date' => $newDueDate, ':note' => $note ?: null, ':id' => $requestId]);
            $db->commit();
            set_flash('Renovação aprovada e nova data de devolução registrada.');
        } else {
            $stmt = $db->prepare('UPDATE loan_renewal_requests SET status = "rejected", reviewed_at = CURRENT_TIMESTAMP, reviewed_by = :reviewer, admin_note = :note WHERE id = :id AND status = "pending"');
            $stmt->execute([':reviewer' => $reviewerId, ':note' => $note, ':id' => $requestId]);
            set_flash('Solicitação de renovação recusada.');
        }
        redirect('renewal_requests.php');
    }
}

$requests = $db->query('SELECT rr.*, u.name AS user_name, b.title AS book_title, l.due_date FROM loan_renewal_requests rr JOIN users u ON u.id = rr.user_id JOIN loans l ON l.id = rr.loan_id JOIN books b ON b.id = l.book_id ORDER BY CASE rr.status WHEN "pending" THEN 0 ELSE 1 END, rr.requested_at DESC')->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell">
    <?php $sidebarActive = 'renewal_requests.php'; $sidebarSubtitle = 'Solicitações de renovação'; require __DIR__ . '/includes/admin_sidebar.php'; ?>
    <div class="dashboard-main-panel">
        <header class="dashboard-topbar"><div><p class="eyebrow">Empréstimos</p><h1>Renovações</h1><p class="topbar-subtitle">Analise as solicitações enviadas pelos alunos.</p></div></header>
        <?php if ($error): ?><div class="flash error"><?php echo h($error); ?></div><?php endif; ?>
        <section class="panel"><div class="table-wrapper"><table class="dashboard-table"><thead><tr><th>Aluno</th><th>Livro</th><th>Vencimento</th><th>Solicitado em</th><th>Status</th><th>Ação</th></tr></thead><tbody>
        <?php foreach ($requests as $request): ?><tr><td><?php echo h($request['user_name']); ?></td><td><?php echo h($request['book_title']); ?></td><td><?php echo h(format_date($request['due_date'])); ?></td><td><?php echo h(format_date($request['requested_at'])); ?></td><td><?php echo h(['pending' => 'Pendente', 'approved' => 'Aprovada', 'rejected' => 'Recusada'][$request['status']] ?? $request['status']); ?><?php if ($request['admin_note']): ?><br><small><?php echo h($request['admin_note']); ?></small><?php endif; ?></td><td><?php if ($request['status'] === 'pending'): ?><form method="post" class="loan-form"><input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>"><input type="hidden" name="id" value="<?php echo (int)$request['id']; ?>"><input type="hidden" name="action" value="approve"><button type="submit">Aprovar</button></form><form method="post" class="loan-form"><input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>"><input type="hidden" name="id" value="<?php echo (int)$request['id']; ?>"><input type="hidden" name="action" value="reject"><input type="text" name="admin_note" required placeholder="Motivo da recusa"><button type="submit">Recusar</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div><?php if (!$requests): ?><p class="empty-state">Nenhuma solicitação de renovação registrada.</p><?php endif; ?></section>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';
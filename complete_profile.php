<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role('Aluno');

$user = current_user();
$db = get_db();
$turmas = [
    '6º Ano A', '6º Ano B', '7º Ano A', '7º Ano B',
    '8º Ano A', '8º Ano B', '9º Ano A', '9º Ano B',
    '1º Ano A', '1º Ano B', '2º Ano A', '2º Ano B',
    '3º Ano A', '3º Ano B',
];
$turnos = ['Manhã', 'Tarde'];
$name = (string)($user['name'] ?? '');
$turma = (string)($user['turma'] ?? '');
$turno = (string)($user['turno'] ?? '');
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $turma = trim((string)($_POST['turma'] ?? ''));
        $turno = trim((string)($_POST['turno'] ?? ''));

        if ($name === '' || $turma === '' || $turno === '') {
            $error = 'Preencha nome, turma e turno.';
        } elseif (!in_array($turma, $turmas, true) || !in_array($turno, $turnos, true)) {
            $error = 'Turma ou turno inválido.';
        } else {
            $stmt = $db->prepare('UPDATE users SET name = :name, turma = :turma, turno = :turno, profile_completed = 1 WHERE id = :id');
            $stmt->execute([
                ':name' => $name,
                ':turma' => $turma,
                ':turno' => $turno,
                ':id' => (int)$user['id'],
            ]);
            login_user((int)$user['id']);
            set_flash('Perfil configurado com sucesso.');
            redirect('student_dashboard.php');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Configure seu perfil</h1>
    <p>Antes de continuar, confirme seus dados. Sua matrícula não pode ser alterada.</p>

    <?php if ($error): ?>
        <div class="flash error"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post" class="loan-form">
        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
        <div class="form-group">
            <label for="matricula">Matrícula</label>
            <input type="text" id="matricula" value="<?php echo h((string)($user['matricula'] ?? '')); ?>" readonly>
        </div>
        <div class="form-group">
            <label for="name">Nome completo *</label>
            <input type="text" id="name" name="name" value="<?php echo h($name); ?>" required>
        </div>
        <div class="form-group">
            <label for="turma">Turma *</label>
            <select id="turma" name="turma" required>
                <option value="">Selecione a turma</option>
                <?php foreach ($turmas as $option): ?>
                    <option value="<?php echo h($option); ?>" <?php echo $turma === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="turno">Turno *</label>
            <select id="turno" name="turno" required>
                <option value="">Selecione o turno</option>
                <?php foreach ($turnos as $option): ?>
                    <option value="<?php echo h($option); ?>" <?php echo $turno === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="primary-btn">Salvar e continuar</button>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php';

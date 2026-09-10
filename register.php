<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

redirect('users.php?new_student=1');

$name = '';
$email = '';
$matricula = '';
$turma = '';
$turno = '';
$parentName = '';
$parentPhone = '';
$parentEmail = '';
$error = null;

$turmas = [
    '6º Ano A',
    '6º Ano B',
    '3º Ano A',
    '3º Ano B',
    '7º Ano A',
    '7º Ano B',
    '8º Ano A',
    '8º Ano B',
    '9º Ano A',
    '9º Ano B',
    '1º Ano A',
    '1º Ano B',
    '2º Ano A',
    '2º Ano B',
];

$turnos = [
    'Manhã',
    'Tarde',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $matricula = trim($_POST['matricula'] ?? '');
    $turma = trim($_POST['turma'] ?? '');
    $turno = trim($_POST['turno'] ?? '');
    $parentName = trim($_POST['parent_name'] ?? '');
    $parentPhone = trim($_POST['parent_phone'] ?? '');
    $parentEmail = trim($_POST['parent_email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($name === '' || $matricula === '' || $turma === '' || $turno === '' || $password === '' || $confirmPassword === '') {
        $error = 'Preencha todos os campos.';
    } elseif (!in_array($turma, $turmas, true)) {
        $error = 'Turma inválida.';
    } elseif (!in_array($turno, $turnos, true)) {
        $error = 'Turno inválido.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($password !== $confirmPassword) {
        $error = 'As senhas não conferem.';
    } else {
        try {
            $db = get_db();

            $matriculaStmt = $db->prepare('SELECT id FROM users WHERE matricula = :matricula LIMIT 1');
            $matriculaStmt->execute([':matricula' => $matricula]);
            if ($matriculaStmt->fetch()) {
                $error = 'Esta matrícula já está cadastrada.';
                throw new Exception('Matrícula duplicada.');
            }

            $email = 'matricula-' . hash('sha256', strtolower($matricula)) . '@local.invalid';

            $stmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $error = 'Este email já está cadastrado.';
            } else {
                $roleStmt = $db->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
                $roleStmt->execute([':name' => 'Aluno']);
                $role = $roleStmt->fetch();

                if (!$role) {
                    $error = 'Não foi possível encontrar o papel de aluno.';
                } else {
                    $insertStmt = $db->prepare('INSERT INTO users (name, email, password, role_id, matricula, turma, turno, parent_name, parent_phone, parent_email, blocked) VALUES (:name, :email, :password, :role_id, :matricula, :turma, :turno, :parent_name, :parent_phone, :parent_email, 0)');
                    $insertStmt->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':password' => password_hash($password, PASSWORD_DEFAULT),
                        ':role_id' => (int)$role['id'],
                        ':matricula' => $matricula,
                        ':turma' => $turma,
                        ':turno' => $turno,
                        ':parent_name' => $parentName !== '' ? $parentName : null,
                        ':parent_phone' => $parentPhone !== '' ? $parentPhone : null,
                        ':parent_email' => $parentEmail !== '' ? $parentEmail : null,
                    ]);

                    set_flash('Aluno cadastrado com sucesso.');
                    redirect('users.php');
                }
            }
        } catch (Exception $e) {
            if ($error === null) {
                $error = 'Não foi possível concluir o cadastro no momento.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell">
    <aside class="dashboard-sidebar">
        <div>
            <div class="sidebar-brand">
                <div class="brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h8.25A2.5 2.5 0 0 1 17.25 6.5v11A2.5 2.5 0 0 1 14.75 20H6.5A2.5 2.5 0 0 1 4 17.5z"></path>
                        <path d="M9 4v16"></path>
                        <path d="M20 7v10"></path>
                    </svg>
                </div>
                <div>
                    <h2>Biblioteca Escolar</h2>
                    <p>Cadastro de usuários</p>
                </div>
            </div>
            <nav class="sidebar-nav" aria-label="Menu principal">
                <a class="nav-item" href="dashboard.php"><span class="nav-icon">◉</span><span>Dashboard</span></a>
                <a class="nav-item" href="index.php"><span class="nav-icon">◌</span><span>Catálogo</span></a>
                <a class="nav-item" href="loans.php"><span class="nav-icon">◌</span><span>Empréstimos</span></a>
                <a class="nav-item" href="reservations.php"><span class="nav-icon">◌</span><span>Reservas</span></a>
                <a class="nav-item" href="reports.php"><span class="nav-icon">◌</span><span>Relatórios</span></a>
                <a class="nav-item" href="books.php"><span class="nav-icon">◌</span><span>Livros</span></a>
                <a class="nav-item active" href="register.php"><span class="nav-icon">◌</span><span>Usuários</span></a>
                <a class="sidebar-logout nav-item" href="logout.php"><span class="nav-icon">↩</span><span>Sair</span></a>
            </nav>
        </div>
    </aside>

    <div class="dashboard-main-panel">
        <header class="dashboard-topbar">
            <div>
                <p class="eyebrow">Acesso</p>
                <h1>Cadastro de aluno</h1>
                <p class="topbar-subtitle">Crie uma conta para acessar o painel do aluno.</p>
            </div>
        </header>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Conta</p>
                    <h2>Dados de cadastro</h2>
                    <p class="panel-subtitle">Preencha os campos abaixo para criar sua conta.</p>
                </div>
            </div>
            <?php if ($error): ?>
                <div class="flash error"><?php echo h($error); ?></div>
            <?php endif; ?>
            <form method="post" action="<?php echo h(base_url('register.php')); ?>" class="loan-form">
                <div class="field-group">
                    <label for="name">Nome</label>
                    <input type="text" id="name" name="name" value="<?php echo h($name); ?>" required>
                </div>
                <div class="field-group">
                    <label for="matricula">Matrícula *</label>
                    <input type="text" id="matricula" name="matricula" value="<?php echo h($matricula); ?>" required>
                </div>
                <div class="field-group">
                    <label for="turma">Turma</label>
                    <select id="turma" name="turma" required>
                        <option value="">Selecione a turma</option>
                        <?php foreach ($turmas as $option): ?>
                            <option value="<?php echo h($option); ?>" <?php echo $turma === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-group">
                    <label for="turno">Turno</label>
                    <select id="turno" name="turno" required>
                        <option value="">Selecione o turno</option>
                        <?php foreach ($turnos as $option): ?>
                            <option value="<?php echo h($option); ?>" <?php echo $turno === $option ? 'selected' : ''; ?>><?php echo h($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-group">
                    <label for="parent_name">Nome do Responsável (Pai / Mãe / Tutor)</label>
                    <input type="text" id="parent_name" name="parent_name" value="<?php echo h($parentName); ?>" placeholder="Ex: Maria dos Santos">
                </div>
                <div class="field-group">
                    <label for="parent_phone">Telefone / WhatsApp do Responsável</label>
                    <input type="tel" id="parent_phone" name="parent_phone" value="<?php echo h($parentPhone); ?>" placeholder="(00) 00000-0000">
                </div>
                <div class="field-group">
                    <label for="parent_email">Email do Responsável</label>
                    <input type="email" id="parent_email" name="parent_email" value="<?php echo h($parentEmail); ?>" placeholder="responsavel@email.com">
                </div>
                <div class="field-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="field-group">
                    <label for="confirm_password">Confirmar senha</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <input type="submit" class="primary-btn" value="Cadastrar">
            </form>
            <p><a href="<?php echo h(base_url('login.php')); ?>">Já tenho conta. Fazer login</a></p>
        </section>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';

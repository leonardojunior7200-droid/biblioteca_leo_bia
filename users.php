<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

$db = get_db();
$csrfToken = csrf_token();
ensure_parent_columns();

$error = null;
$search = trim((string)($_GET['search'] ?? ''));
$filterTurma = trim((string)($_GET['turma'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_student') {
    $postCsrf = trim((string)($_POST['csrf_token'] ?? ''));
    if (!verify_csrf_token($postCsrf)) {
        http_response_code(419);
        set_flash('Sessão expirada ou token inválido.', 'error');
        redirect('users.php');
    }

    $studentName = trim((string)($_POST['student_name'] ?? ''));
    $studentMatricula = trim((string)($_POST['student_matricula'] ?? ''));
    $studentEmail = trim((string)($_POST['student_email'] ?? ''));
    $studentPassword = trim((string)($_POST['student_password'] ?? ''));
    $studentConfirmPassword = trim((string)($_POST['student_confirm_password'] ?? ''));
    $studentTurma = trim((string)($_POST['student_turma'] ?? ''));
    $studentTurno = trim((string)($_POST['student_turno'] ?? ''));
    $studentParentName = trim((string)($_POST['student_parent_name'] ?? ''));
    $studentParentPhone = trim((string)($_POST['student_parent_phone'] ?? ''));
    $studentParentEmail = trim((string)($_POST['student_parent_email'] ?? ''));

    if ($studentName === '' || $studentMatricula === '' || $studentPassword === '' || $studentConfirmPassword === '' || $studentTurma === '' || $studentTurno === '') {
        $error = 'Preencha todos os campos obrigatórios do cadastro do aluno.';
    } elseif (strlen($studentPassword) < 6) {
        $error = 'A senha do aluno deve ter pelo menos 6 caracteres.';
    } elseif ($studentPassword !== $studentConfirmPassword) {
        $error = 'As senhas do aluno não conferem.';
    } else {
        $studentEmail = 'matricula-' . hash('sha256', strtolower($studentMatricula)) . '@local.invalid';
        $existingMatricula = $db->prepare('SELECT id FROM users WHERE matricula = :matricula LIMIT 1');
        $existingMatricula->execute([':matricula' => $studentMatricula]);
        if ($existingMatricula->fetch()) {
            $error = 'Esta matrícula já está cadastrada no sistema.';
        }
        $existing = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existing->execute([':email' => $studentEmail]);
        if (!$error && $existing->fetch()) {
            $error = 'Este e-mail já está cadastrado no sistema.';
        } else {
            $roleStmt = $db->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
            $roleStmt->execute([':name' => 'Aluno']);
            $role = $roleStmt->fetch();
            if (!$role) {
                $error = 'Não foi possível localizar o papel de aluno.';
            } else {
                $stmt = $db->prepare('INSERT INTO users (name, email, password, role_id, matricula, turma, turno, parent_name, parent_phone, parent_email, blocked) VALUES (:name, :email, :password, :role_id, :matricula, :turma, :turno, :parent_name, :parent_phone, :parent_email, 0)');
                $stmt->execute([
                    ':name' => $studentName,
                    ':email' => $studentEmail,
                    ':password' => password_hash($studentPassword, PASSWORD_DEFAULT),
                    ':role_id' => (int)$role['id'],
                    ':matricula' => $studentMatricula,
                    ':turma' => $studentTurma,
                    ':turno' => $studentTurno,
                    ':parent_name' => $studentParentName !== '' ? $studentParentName : null,
                    ':parent_phone' => $studentParentPhone !== '' ? $studentParentPhone : null,
                    ':parent_email' => $studentParentEmail !== '' ? $studentParentEmail : null,
                ]);
                set_flash('Aluno cadastrado com sucesso e já pode fazer login no painel do aluno.');
                redirect('users.php');
            }
        }
    }
}

$turmas = [
    '6º Ano A', '6º Ano B',
    '7º Ano A', '7º Ano B',
    '8º Ano A', '8º Ano B',
    '9º Ano A', '9º Ano B',
    '1º Ano A', '1º Ano B',
    '2º Ano A', '2º Ano B',
    '3º Ano A', '3º Ano B',
];

$turnos = ['Manhã', 'Tarde'];

// Atualização de dados do aluno/responsável pelo Admin/Bibliotecário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    $postCsrf = trim((string)($_POST['csrf_token'] ?? ''));
    if (!verify_csrf_token($postCsrf)) {
        http_response_code(419);
        set_flash('Sessão expirada ou token inválido.', 'error');
        redirect('users.php');
    }

    $targetUserId = require_positive_int($_POST['user_id'] ?? null) ?? 0;
    $name = trim((string)($_POST['name'] ?? ''));
    $matricula = trim((string)($_POST['matricula'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $turma = trim((string)($_POST['turma'] ?? ''));
    $turno = trim((string)($_POST['turno'] ?? ''));
    $parentName = trim((string)($_POST['parent_name'] ?? ''));
    $parentPhone = trim((string)($_POST['parent_phone'] ?? ''));
    $parentEmail = trim((string)($_POST['parent_email'] ?? ''));
    $parentDoc = trim((string)($_POST['parent_document'] ?? ''));
    $blocked = isset($_POST['blocked']) ? 1 : 0;
    $roleStmt = $db->prepare('SELECT r.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
    $roleStmt->execute([':id' => $targetUserId]);
    $isStudent = $roleStmt->fetchColumn() === 'Aluno';

    if ($targetUserId > 0 && $name !== '' && $email !== '' && ($matricula !== '' || !$isStudent)) {
        $duplicate = $db->prepare('SELECT id FROM users WHERE matricula = :matricula AND id <> :id LIMIT 1');
        $duplicate->execute([':matricula' => $matricula, ':id' => $targetUserId]);
        if ($duplicate->fetch()) {
            $error = 'Esta matrícula já está cadastrada no sistema.';
        }
    }

    if ($targetUserId > 0 && $name !== '' && $email !== '' && ($matricula !== '' || !$isStudent) && $error === null) {
        $stmt = $db->prepare('UPDATE users SET name = :name, email = :email, matricula = :matricula, turma = :turma, turno = :turno, parent_name = :parent_name, parent_phone = :parent_phone, parent_email = :parent_email, parent_document = :parent_doc, blocked = :blocked WHERE id = :id');
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':matricula' => $matricula,
            ':turma' => $turma !== '' ? $turma : null,
            ':turno' => $turno !== '' ? $turno : null,
            ':parent_name' => $parentName !== '' ? $parentName : null,
            ':parent_phone' => $parentPhone !== '' ? $parentPhone : null,
            ':parent_email' => $parentEmail !== '' ? $parentEmail : null,
            ':parent_doc' => $parentDoc !== '' ? $parentDoc : null,
            ':blocked' => $blocked,
            ':id' => $targetUserId,
        ]);
        set_flash('Dados do usuário e dos responsáveis atualizados com sucesso.');
        redirect('users.php' . ($search ? '?search=' . urlencode($search) : ''));
    } else {
        $error = $error ?? 'Por favor, preencha os campos obrigatórios.';
    }
}

// Alteração rápida de bloqueio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_block') {
    if (!verify_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(419);
        exit('Sessão expirada ou token inválido.');
    }
    $toggleId = require_positive_int($_POST['user_id'] ?? null);
    if ($toggleId === null) {
        set_flash('Identificador do usuário inválido.', 'error');
        redirect('users.php');
    }

    $stmt = $db->prepare('UPDATE users SET blocked = CASE WHEN blocked = 1 THEN 0 ELSE 1 END WHERE id = :id');
    $stmt->execute([':id' => $toggleId]);
    set_flash('Status de bloqueio do usuário alterado.');
    redirect('users.php');
}

// Montar busca
$where = ['1=1'];
$params = [];

if ($search !== '') {
    $where[] = '(u.name LIKE :s OR u.email LIKE :s OR u.parent_name LIKE :s OR u.parent_phone LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

if ($filterTurma !== '') {
    $where[] = 'u.turma = :turma';
    $params[':turma'] = $filterTurma;
}

$whereSql = implode(' AND ', $where);

$usersList = $db->prepare("SELECT u.*, r.name AS role_name, (SELECT COUNT(*) FROM loans l WHERE l.user_id = u.id AND l.returned_at IS NULL) AS active_loans FROM users u JOIN roles r ON u.role_id = r.id WHERE {$whereSql} ORDER BY u.name ASC");
$usersList->execute($params);
$users = $usersList->fetchAll();

// Usuário selecionado para edição modal/card
$editUserId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editUser = null;
if ($editUserId > 0) {
    $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $editUserId]);
    $editUser = $stmt->fetch();
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="dashboard-shell">
    <?php $sidebarActive = 'users.php'; $sidebarSubtitle = 'Gestão de Usuários e Pais'; require __DIR__ . '/includes/admin_sidebar.php'; ?>

    <div class="dashboard-main-panel">
        <header class="dashboard-topbar">
            <div>
                <p class="eyebrow">Administração</p>
                <h1>Usuários e Dados dos Pais</h1>
                <p class="topbar-subtitle">Gerencie alunos, contatos de pais/responsáveis e autorizações de empréstimos.</p>
            </div>
            <div class="topbar-actions">
                <a href="users.php?new_student=1" class="primary-btn" style="text-decoration: none;">+ Cadastrar aluno</a>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="flash error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['new_student']) || !empty($_SESSION['flash'])): ?>
            <section class="panel" id="student-form">
                <div class="panel-header">
                    <div>
                        <p class="panel-eyebrow">Cadastro do aluno</p>
                        <h2>Cadastrar aluno no sistema</h2>
                    </div>
                </div>
                <form method="post" action="users.php" class="loan-form">
                    <input type="hidden" name="action" value="create_student">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">

                    <div class="field-group">
                        <label for="student_name">Nome do aluno *</label>
                        <input type="text" id="student_name" name="student_name" value="" required>
                    </div>
                    <div class="field-group">
                        <label for="student_matricula">Matrícula do aluno *</label>
                        <input type="text" id="student_matricula" name="student_matricula" value="" required>
                    </div>
                    <div class="field-group">
                        <label for="student_turma">Turma *</label>
                        <select id="student_turma" name="student_turma" required>
                            <option value="">Selecione a turma</option>
                            <?php foreach ($turmas as $t): ?>
                                <option value="<?php echo h($t); ?>"><?php echo h($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="student_turno">Turno *</label>
                        <select id="student_turno" name="student_turno" required>
                            <option value="">Selecione o turno</option>
                            <?php foreach ($turnos as $tu): ?>
                                <option value="<?php echo h($tu); ?>"><?php echo h($tu); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="student_password">Senha do aluno *</label>
                        <input type="password" id="student_password" name="student_password" required>
                    </div>
                    <div class="field-group">
                        <label for="student_confirm_password">Confirmar senha *</label>
                        <input type="password" id="student_confirm_password" name="student_confirm_password" required>
                    </div>
                    <div class="field-group">
                        <label for="student_parent_name">Nome do responsável</label>
                        <input type="text" id="student_parent_name" name="student_parent_name" placeholder="Opcional">
                    </div>
                    <div class="field-group">
                        <label for="student_parent_phone">Telefone do responsável</label>
                        <input type="text" id="student_parent_phone" name="student_parent_phone" placeholder="Opcional">
                    </div>
                    <div class="field-group">
                        <label for="student_parent_email">E-mail do responsável</label>
                        <input type="email" id="student_parent_email" name="student_parent_email" placeholder="Opcional">
                    </div>

                    <div style="grid-column: 1 / -1; display: flex; gap: 1rem; margin-top: 1rem;">
                        <button type="submit" class="primary-btn">Salvar cadastro do aluno</button>
                        <a href="users.php" class="secondary-btn" style="text-decoration: none; display: inline-flex; align-items: center;">Cancelar</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($editUser): ?>
            <section class="panel" style="border: 2px solid #3b82f6; margin-bottom: 2rem;">
                <div class="panel-header">
                    <div>
                        <p class="panel-eyebrow">Edição</p>
                        <h2>Editar Usuário & Dados dos Pais — <?php echo h($editUser['name']); ?></h2>
                    </div>
                    <a href="users.php" class="soft-link">Fechar edição</a>
                </div>
                <form method="post" action="users.php" class="loan-form">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" value="<?php echo (int)$editUser['id']; ?>">
                    
                    <h3 style="grid-column: 1 / -1; margin-top: 0.5rem; color: #1e3a8a;">Dados do Aluno</h3>
                    <div class="field-group">
                        <label for="name">Nome do Aluno *</label>
                        <input type="text" id="name" name="name" value="<?php echo h($editUser['name']); ?>" required>
                    </div>
                    <div class="field-group">
                        <label for="matricula">Matrícula do Aluno *</label>
                        <input type="text" id="matricula" name="matricula" value="<?php echo h($editUser['matricula'] ?? ''); ?>" required>
                    </div>
                    <div class="field-group">
                        <label for="email">E-mail do Aluno *</label>
                        <input type="email" id="email" name="email" value="<?php echo h($editUser['email']); ?>" required>
                    </div>
                    <div class="field-group">
                        <label for="turma">Turma</label>
                        <select id="turma" name="turma">
                            <option value="">Não informada</option>
                            <?php foreach ($turmas as $t): ?>
                                <option value="<?php echo h($t); ?>" <?php echo ($editUser['turma'] ?? '') === $t ? 'selected' : ''; ?>><?php echo h($t); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="turno">Turno</label>
                        <select id="turno" name="turno">
                            <option value="">Não informado</option>
                            <?php foreach ($turnos as $tu): ?>
                                <option value="<?php echo h($tu); ?>" <?php echo ($editUser['turno'] ?? '') === $tu ? 'selected' : ''; ?>><?php echo h($tu); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <h3 style="grid-column: 1 / -1; margin-top: 1rem; color: #1e3a8a;">👨‍👩‍👧 Dados dos Pais / Responsável Legal</h3>
                    <div class="field-group">
                        <label for="parent_name">Nome do Pai/Mãe/Responsável</label>
                        <input type="text" id="parent_name" name="parent_name" value="<?php echo h($editUser['parent_name'] ?? ''); ?>" placeholder="Nome completo do responsável">
                    </div>
                    <div class="field-group">
                        <label for="parent_phone">Telefone / WhatsApp do Responsável</label>
                        <input type="tel" id="parent_phone" name="parent_phone" value="<?php echo h($editUser['parent_phone'] ?? ''); ?>" placeholder="(00) 00000-0000">
                    </div>
                    <div class="field-group">
                        <label for="parent_email">E-mail do Responsável</label>
                        <input type="email" id="parent_email" name="parent_email" value="<?php echo h($editUser['parent_email'] ?? ''); ?>" placeholder="email@responsavel.com">
                    </div>
                    <div class="field-group">
                        <label for="parent_document">CPF ou RG do Responsável</label>
                        <input type="text" id="parent_document" name="parent_document" value="<?php echo h($editUser['parent_document'] ?? ''); ?>" placeholder="000.000.000-00">
                    </div>

                    <div class="field-group" style="grid-column: 1 / -1; display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="blocked" name="blocked" value="1" <?php echo !empty($editUser['blocked']) ? 'checked' : ''; ?> style="width: auto;">
                        <label for="blocked" style="margin: 0; font-weight: normal; cursor: pointer;">Usuário Bloqueado para novos empréstimos</label>
                    </div>

                    <div style="grid-column: 1 / -1; display: flex; gap: 1rem; margin-top: 1rem;">
                        <button type="submit" class="primary-btn">Salvar Alterações</button>
                        <a href="users.php" class="secondary-btn" style="text-decoration: none; display: inline-flex; align-items: center;">Cancelar</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Filtros</p>
                    <h2>Filtrar Usuários</h2>
                </div>
            </div>
            <form method="get" action="users.php" class="loan-form" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
                <div class="field-group" style="flex: 1; min-width: 200px;">
                    <label for="search">Buscar (Aluno, Pai, E-mail ou Telefone)</label>
                    <input type="text" id="search" name="search" value="<?php echo h($search); ?>" placeholder="Ex: Maria, João, 99999...">
                </div>
                <div class="field-group" style="min-width: 150px;">
                    <label for="turma_filter">Turma</label>
                    <select id="turma_filter" name="turma">
                        <option value="">Todas as turmas</option>
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?php echo h($t); ?>" <?php echo $filterTurma === $t ? 'selected' : ''; ?>><?php echo h($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="primary-btn" style="height: 42px;">Filtrar</button>
                <?php if ($search !== '' || $filterTurma !== ''): ?>
                    <a href="users.php" class="soft-link" style="margin-bottom: 0.5rem;">Limpar filtros</a>
                <?php endif; ?>
            </form>
        </section>

        <section class="panel table-panel">
            <div class="panel-header">
                <div>
                    <p class="panel-eyebrow">Cadastros</p>
                    <h2>Lista de Alunos e Responsáveis (<?php echo count($users); ?>)</h2>
                    <p class="panel-subtitle">Acesse as autorizações e dados de contato das famílias.</p>
                </div>
            </div>

            <div class="table-wrapper">
                <?php if (empty($users)): ?>
                    <p class="empty-state">Nenhum usuário encontrado com os critérios fornecidos.</p>
                <?php else: ?>
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Aluno</th>
                                <th>Turma / Turno</th>
                                <th>Pai / Responsável</th>
                                <th>Contato Pais</th>
                                <th>Empréstimos</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo h($u['name']); ?></strong><br>
                                        <small class="muted"><?php echo h($u['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['turma'])): ?>
                                            <span class="status-pill status-active"><?php echo h($u['turma']); ?></span>
                                            <br><small><?php echo h($u['turno'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <span class="muted"><?php echo h($u['role_name']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['parent_name'])): ?>
                                            <strong><?php echo h($u['parent_name']); ?></strong>
                                            <?php if (!empty($u['parent_document'])): ?>
                                                <br><small class="muted">Doc: <?php echo h($u['parent_document']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-pill status-warning" style="font-size: 0.75rem;">Não informado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['parent_phone'])): ?>
                                            <a href="https://wa.me/<?php echo preg_replace('/[^\d]/', '', $u['parent_phone']); ?>" target="_blank" rel="noopener" style="color: #16a34a; font-weight: 500; text-decoration: none;">
                                                📱 <?php echo h($u['parent_phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="muted">-</span>
                                        <?php endif; ?>
                                        <?php if (!empty($u['parent_email'])): ?>
                                            <br><small class="muted"><?php echo h($u['parent_email']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['active_loans'] > 0): ?>
                                            <span class="status-pill status-active"><?php echo (int)$u['active_loans']; ?> livro(s)</span>
                                        <?php else: ?>
                                            <span class="muted">Nenhum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($u['blocked'])): ?>
                                            <span class="status-pill status-overdue">Bloqueado</span>
                                        <?php else: ?>
                                            <span class="status-pill status-active">Ativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <a class="action-link" href="users.php?edit=<?php echo (int)$u['id']; ?>" style="margin-right: 0.5rem;">✏️ Editar</a>
                                        <form method="post" action="users.php" style="display:inline; margin:0;">
                                            <input type="hidden" name="action" value="toggle_block">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                            <button type="submit" class="soft-link" onclick="return confirm('Alterar status deste usuário?');" style="background:none; border:none; padding:0; cursor:pointer;">
                                                <?php echo !empty($u['blocked']) ? 'Desbloquear' : 'Bloquear'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php';

<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(user_has_role('Aluno') ? 'student_dashboard.php' : 'dashboard.php');
}

$name = '';
$email = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $error = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um email válido.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($password !== $confirmPassword) {
        $error = 'As senhas não conferem.';
    } else {
        try {
            $db = get_db();

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
                    $insertStmt = $db->prepare('INSERT INTO users (name, email, password, role_id, blocked) VALUES (:name, :email, :password, :role_id, 0)');
                    $insertStmt->execute([
                        ':name' => $name,
                        ':email' => $email,
                        ':password' => password_hash($password, PASSWORD_DEFAULT),
                        ':role_id' => (int)$role['id'],
                    ]);

                    $userId = (int)$db->lastInsertId();
                    login_user($userId);
                    set_flash('Cadastro realizado com sucesso.');
                    redirect('student_dashboard.php');
                }
            }
        } catch (Exception $e) {
            $error = 'Não foi possível concluir o cadastro no momento.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Cadastro de aluno</h1>
    <p>Crie sua conta para acessar o painel do aluno.</p>
    <?php if ($error): ?>
        <div class="flash error"><?php echo h($error); ?></div>
    <?php endif; ?>
    <form method="post" action="<?php echo h(base_url('register.php')); ?>">
        <div class="form-group">
            <label for="name">Nome</label>
            <input type="text" id="name" name="name" value="<?php echo h($name); ?>" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo h($email); ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirmar senha</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <input type="submit" class="btn-login" value="Cadastrar">
    </form>
    <p><a href="<?php echo h(base_url('login.php')); ?>">Já tenho conta. Fazer login</a></p>
</div>
<?php require_once __DIR__ . '/includes/footer.php';

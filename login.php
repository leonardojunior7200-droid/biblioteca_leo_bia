<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$email = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Informe email e senha.';
    } else {
        $db = get_db();
        $stmt = $db->prepare('SELECT id, password FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            login_user((int)$user['id']);
            set_flash('Login efetuado com sucesso.');
            redirect('dashboard.php');
        }

        $error = 'Email ou senha inválidos.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Login</h1>
    <?php if ($error): ?>
        <div class="flash error"><?php echo h($error); ?></div>
    <?php endif; ?>
    <form method="post" action="<?php echo h(base_url('login.php')); ?>">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo h($email); ?>" required>
        </div>
        <div class="form-group">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required>
        </div>
        <input type="submit" class="btn-login" value="Entrar">
    </form>
    <p><a href="<?php echo h(base_url('register.php')); ?>">Ainda não tenho conta. Cadastrar-se</a></p>
</div>
<?php require_once __DIR__ . '/includes/footer.php';

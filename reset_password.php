<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = null;
$success = null;
$validReset = false;
$resetId = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $stmt = get_db()->prepare('SELECT id FROM password_resets WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at >= CURRENT_TIMESTAMP LIMIT 1');
    $stmt->execute([':token_hash' => hash('sha256', $token)]);
    $reset = $stmt->fetch();
    if ($reset) {
        $validReset = true;
        $resetId = (int)$reset['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirmation = (string)($_POST['confirm_password'] ?? '');
    if (!$validReset) {
        $error = 'Este link é inválido, expirou ou já foi utilizado.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($password !== $confirmation) {
        $error = 'As senhas não conferem.';
    } else {
        $db = get_db();
        $db->beginTransaction();
        try {
            $userStmt = $db->prepare('SELECT user_id FROM password_resets WHERE id = :id LIMIT 1');
            $userStmt->execute([':id' => $resetId]);
            $resetUser = $userStmt->fetch();
            if (!$resetUser) {
                throw new RuntimeException('Token inválido.');
            }
            $update = $db->prepare('UPDATE users SET password = :password WHERE id = :user_id');
            $update->execute([':password' => password_hash($password, PASSWORD_DEFAULT), ':user_id' => (int)$resetUser['user_id']]);
            $mark = $db->prepare('UPDATE password_resets SET used_at = CURRENT_TIMESTAMP WHERE id = :id');
            $mark->execute([':id' => $resetId]);
            $db->commit();
            $validReset = false;
            $success = 'Senha redefinida com sucesso. Você já pode entrar no sistema.';
        } catch (Throwable $exception) {
            $db->rollBack();
            $error = 'Não foi possível redefinir a senha. Tente solicitar um novo link.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="card auth-card">
    <h1>Redefinir senha</h1>
    <?php if ($error): ?><div class="flash error"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="flash success"><?php echo h($success); ?></div><?php endif; ?>
    <?php if ($validReset): ?>
        <form method="post" action="<?php echo h(base_url('reset_password.php')); ?>?token=<?php echo h(urlencode($token)); ?>">
            <input type="hidden" name="token" value="<?php echo h($token); ?>">
            <label for="password">Nova senha</label>
            <input type="password" id="password" name="password" minlength="6" required autocomplete="new-password">
            <label for="confirm_password">Confirme a nova senha</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="6" required autocomplete="new-password">
            <button class="btn" type="submit">Salvar nova senha</button>
        </form>
    <?php endif; ?>
    <p><a href="<?php echo h(base_url('login.php')); ?>">Voltar para o login</a></p>
</div>
<?php require_once __DIR__ . '/includes/footer.php';

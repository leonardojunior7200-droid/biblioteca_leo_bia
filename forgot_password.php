<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('index.php');
}

$message = null;
$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Informe um e-mail válido.';
    } else {
        $db = get_db();
        $stmt = $db->prepare('SELECT id FROM users WHERE LOWER(email) = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);
            $db->prepare('DELETE FROM password_resets WHERE user_id = :user_id OR expires_at < CURRENT_TIMESTAMP')->execute([':user_id' => (int)$user['id']]);
            $reset = $db->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)');
            $reset->execute([':user_id' => (int)$user['id'], ':token_hash' => hash('sha256', $token), ':expires_at' => $expiresAt]);
            $link = base_url('reset_password.php?token=' . urlencode($token));
            $subject = SITE_NAME . ' - Redefinição de senha';
            $body = "Olá!\n\nAcesse o link abaixo para redefinir sua senha (válido por 1 hora):\n$link\n\nSe você não solicitou isso, ignore esta mensagem.";
            $headers = "From: no-reply@biblioteca.local\r\nContent-Type: text/plain; charset=UTF-8\r\n";
            $sent = @mail($email, $subject, $body, $headers);
            if (!$sent && getenv('BIBLIOTECA_DEV_RESET_LINK') === '1') {
                $message = 'Ambiente local: link de redefinição gerado: ' . $link;
            } else {
                $message = 'Se o e-mail estiver cadastrado, as instruções foram enviadas.';
            }
        } else {
            $message = 'Se o e-mail estiver cadastrado, as instruções foram enviadas.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="card auth-card">
    <h1>Esqueci minha senha</h1>
    <p>Informe o e-mail cadastrado para receber as instruções de redefinição.</p>
    <?php if ($error): ?><div class="flash error"><?php echo h($error); ?></div><?php endif; ?>
    <?php if ($message): ?><div class="flash success"><?php echo h($message); ?></div><?php endif; ?>
    <form method="post" action="<?php echo h(base_url('forgot_password.php')); ?>">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?php echo h($email); ?>" required autocomplete="email">
        <button class="btn" type="submit">Enviar instruções</button>
    </form>
    <p><a href="<?php echo h(base_url('login.php')); ?>">Voltar para o login</a></p>
</div>
<?php require_once __DIR__ . '/includes/footer.php';

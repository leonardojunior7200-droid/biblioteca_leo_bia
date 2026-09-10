<?php 
require_once __DIR__ . '/includes/auth.php'; 
require_once __DIR__ . '/includes/functions.php'; 

if (is_logged_in()) {
    $current = current_user();
    $redirectPage = user_has_role('Aluno') ? 'student_dashboard.php' : 'dashboard.php';
    redirect($redirectPage);
}

$email = ''; 
$matricula = ''; 
$error = null; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    $email = trim((string)($_POST['email'] ?? '')); 
    $matricula = trim((string)($_POST['matricula'] ?? '')); 
    $password = trim((string)($_POST['password'] ?? '')); 
    
    if (($email === '' && $matricula === '') || $password === '') { 
        $error = 'Informe a matrícula do aluno ou o e-mail do administrador e a senha.'; 
    } else { 
        $db = get_db(); 
        $user = null;

        if ($email !== '') {
            $emailStmt = $db->prepare('SELECT u.id, u.password, u.blocked, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = :email LIMIT 1');
            $emailStmt->execute([':email' => $email]);
            $emailUser = $emailStmt->fetch();

            if ($emailUser && $emailUser['role_name'] === 'Aluno') {
                $error = 'Alunos devem entrar usando a matrícula, não o e-mail.';
            } elseif ($emailUser && $emailUser['role_name'] === 'Administrador') {
                $user = $emailUser;
            }
        }

        if ($error === null && $user === null && $matricula !== '') {
            $matriculaStmt = $db->prepare("SELECT u.id, u.password, u.blocked, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.matricula = :matricula AND r.name = 'Aluno' LIMIT 1");
            $matriculaStmt->execute([':matricula' => $matricula]);
            $user = $matriculaStmt->fetch();
        }
        
        if ($user && password_verify($password, $user['password'])) {
            if (!empty($user['blocked'])) {
                $error = 'Esta conta está bloqueada pelo administrador.';
            } else {
                login_user((int)$user['id']);
                set_flash('Login efetuado com sucesso.');
                redirect(($user['role_name'] ?? 'Administrador') === 'Aluno' ? 'student_dashboard.php' : 'dashboard.php');
            }
        } else {
            $error = 'Matrícula, e-mail do administrador ou senha inválidos.';
        }
    } 
} 

require_once __DIR__ . '/includes/header.php'; 
?>

<!-- Biblioteca de Ícones Obrigatória -->
<link rel="stylesheet" href="https://cloudflare.com">

<style>
    /* Estilização da área central entre o header e o footer */
    .login-page-container {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding: 32px 20px 48px;
        box-sizing: border-box;
        min-height: calc(100vh - 80px);
    }

    /* CARD DE LOGIN CENTRALIZADO - IDÊNTICO À FOTO */
    .login-card {
        display: flex;
        width: 850px;
        min-height: 520px;
        height: auto;
        background-color: #0c1a30; /* Cor azul marinho exata do painel */
        border: 1px solid #172a45; /* Cor exata da borda azul fina */
        border-radius: 12px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
        overflow: hidden;
    }

    /* Lado Esquerdo - Logotipo e Slogan */
    .brand-section {
        width: 40%;
        background-color: #081224; /* Fundo escuro sutil da logo */
        padding: 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        border-right: 1px solid #172a45;
        box-sizing: border-box;
    }

    .brand-icon {
        color: #cc9333; /* Tom dourado fosco exato */
        font-size: 72px;
        margin-bottom: 12px;
    }

    .brand-text-main {
        color: #ffffff;
        font-size: 28px;
        font-weight: 600;
        margin: 0;
    }

    .brand-text-sub {
        color: #cc9333; /* Tom dourado fosco exato */
        font-size: 24px;
        font-weight: 500;
        margin: 5px 0 15px 0;
    }

    .brand-line {
        width: 45px;
        height: 1px;
        background-color: #cc9333;
        margin-bottom: 25px;
        opacity: 0.6;
    }

    .brand-phrase {
        color: #8fa0b5;
        font-style: italic;
        font-size: 14px;
        line-height: 1.6;
        max-width: 220px;
        margin: 0;
    }

    /* Lado Direito - Formulário */
    .form-section {
        width: 60%;
        padding: 42px 60px;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        box-sizing: border-box;
    }

    .form-section h1 {
        color: #ffffff;
        font-size: 32px;
        font-weight: 600;
        margin: 0 0 5px 0;
    }

    .form-desc {
        color: #466385; /* Azul acinzentado do texto secundário */
        font-size: 14px;
        margin-bottom: 30px;
    }

    .input-block {
        margin-bottom: 22px;
    }

    .input-block label {
        display: block;
        color: #a0b2c6;
        font-size: 14px;
        margin-bottom: 8px;
    }

    .field-container {
        position: relative;
        display: flex;
        align-items: center;
    }

    .field-container i.input-icon {
        position: absolute;
        left: 15px;
        color: #466385;
        font-size: 16px;
    }

    .field-container input {
        width: 100%;
        padding: 14px 15px 14px 45px;
        background-color: #081224; /* Fundo escuro dos inputs */
        border: 1px solid #172a45;
        border-radius: 6px;
        color: #ffffff;
        font-size: 14px;
        box-sizing: border-box;
    }

    .field-container input::placeholder {
        color: #466385;
    }

    .field-container input:focus {
        border-color: #cc9333;
        outline: none;
    }

    .hide-show-password {
        position: absolute;
        right: 15px;
        color: #466385;
        cursor: pointer;
    }

    .extra-options {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        font-size: 13px;
    }

    .remember-box {
        color: #8fa0b5;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .forgot-link {
        color: #3b82f6;
        text-decoration: none;
    }

    /* BOTÃO ENTRAR - Cor exata Dourado/Mostarda da imagem */
    .submit-button {
        width: 100%;
        padding: 14px;
        background-color: #cc9333; 
        border: none;
        border-radius: 6px;
        color: #ffffff;
        font-size: 14px;
        font-weight: bold;
        letter-spacing: 1px;
        cursor: pointer;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        transition: background-color 0.2s;
    }

    .submit-button:hover {
        background-color: #b37e27;
    }

    .or-separator {
        text-align: center;
        color: #466385;
        font-size: 12px;
        margin: 20px 0;
    }

    .alt-action-link {
        display: block;
        text-align: center;
        padding: 13px;
        border: 1px solid #172a45;
        border-radius: 6px;
        color: #3b82f6;
        text-decoration: none;
        font-size: 13px;
        transition: background-color 0.2s;
    }

    .alt-action-link:hover {
        background-color: #081224;
    }

    .flash.error {
        background-color: #4c1d1d;
        color: #fca5a5;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 14px;
        border: 1px solid #7f1d1d;
    }
</style>

<div class="login-page-container">
    <div class="login-card">
        
        <!-- Esquerda: Identidade Visual -->
        <div class="brand-section">
            <div class="brand-icon"><i class="fa-solid fa-book-open"></i></div>
            <h2 class="brand-text-main">Biblioteca</h2>
            <div class="brand-text-sub">Escolar</div>
            <div class="brand-line"></div>
            <p class="brand-phrase">"Livros abrem portas para mundos imagináveis."</p>
        </div>

        <!-- Direita: Formulário de Login -->
        <div class="form-section">
            <h1>Login</h1>
            <div class="form-desc">Acesse sua conta para continuar</div>

            <?php if ($error): ?> 
                <div class="flash error"><?php echo h($error); ?></div> 
            <?php endif; ?> 

            <form method="post" action="<?php echo h(base_url('login.php')); ?>"> 
                <div class="input-block"> 
                    <label for="email">E-mail do administrador</label> 
                    <div class="field-container">
                        <i class="fa-regular fa-user input-icon"></i>
                        <input type="email" id="email" name="email" value="<?php echo h($email); ?>" placeholder="Somente administradores"> 
                    </div>
                </div>

                <div class="input-block"> 
                    <label for="matricula">Matrícula do aluno</label> 
                    <div class="field-container">
                        <i class="fa-solid fa-id-card input-icon"></i>
                        <input type="text" id="matricula" name="matricula" value="<?php echo h($matricula); ?>" placeholder="Digite sua matrícula"> 
                    </div>
                </div> 

                <div class="input-block"> 
                    <label for="password">Senha</label> 
                    <div class="field-container">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="Digite sua senha" required> 
                        <i class="fa-regular fa-eye hide-show-password" id="btnTogglePass"></i>
                    </div>
                </div> 

                <div class="extra-options">
                    <label class="remember-box">
                        <input type="checkbox" name="remember"> Lembrar-me
                    </label>
                    <a href="#" class="forgot-link">Esqueceu sua senha?</a>
                </div>

                <button type="submit" class="submit-button">
                    <i class="fa-solid fa-right-to-bracket"></i> ENTRAR
                </button> 
            </form> 

            <div class="or-separator">ou</div>

            <a href="<?php echo h(base_url('register.php')); ?>" class="alt-action-link">
                <i class="fa-regular fa-user" style="margin-right: 5px;"></i> Ainda não tenho conta. Cadastrar-se
            </a>
        </div> 
        
    </div>
</div>

<script>
    const btnTogglePass = document.querySelector('#btnTogglePass');
    const passwordInput = document.querySelector('#password');

    btnTogglePass.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
</script>

<?php 
require_once __DIR__ . '/includes/footer.php'; 
?>

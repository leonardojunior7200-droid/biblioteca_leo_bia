<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role('Aluno');

$db = get_db();
ensure_user_profile_photo_column();
ensure_book_pdf_column();

$search = trim($_GET['search'] ?? '');
$searchQuery = '';
$params = [];

if ($search !== '') {
    $searchQuery = 'WHERE title LIKE :search OR author LIKE :search OR category LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$books = $db->prepare('SELECT id, title, author, category, quantity, cover_path, pdf_path FROM books ' . $searchQuery . ' ORDER BY title');
$books->execute($params);
$books = $books->fetchAll();

$user = current_user();
$userId = (int)$user['id'];
$turma = $user['turma'] ?? '';
$turno = $user['turno'] ?? '';

$avatarOptions = [
    ['value' => 'img/avatars/avatar-feminino.svg', 'label' => 'Feminino'],
    ['value' => 'img/avatars/avatar-masculino.svg', 'label' => 'Masculino'],
    ['value' => 'img/avatars/avatar-biblioteca.svg', 'label' => 'Biblioteca'],
];

$profilePhoto = $user['profile_photo'] ?? '';
$profileMessage = null;
$profileMessageType = 'success';
$selectedAvatar = in_array($profilePhoto, array_column($avatarOptions, 'value'), true) ? $profilePhoto : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_photo_form'])) {
    try {
        $photoPath = $profilePhoto;
        $uploadedFile = $_FILES['profile_photo'] ?? null;

        if (is_array($uploadedFile) && ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && !empty($uploadedFile['tmp_name'])) {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
            $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            $maxSize = 2 * 1024 * 1024;

            if (!in_array($extension, $allowedExtensions, true) || $uploadedFile['size'] > $maxSize) {
                throw new Exception('Formato ou tamanho de imagem inválido.');
            }

            $targetDirectory = ensure_upload_directory('uploads/avatars');
            $fileName = 'user-' . $userId . '-' . time() . '.' . $extension;
            $targetPath = $targetDirectory . '/' . $fileName;
            if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                throw new Exception('Não foi possível salvar a imagem.');
            }

            $photoPath = 'uploads/avatars/' . $fileName;
        } elseif (isset($_POST['remove_photo'])) {
            $photoPath = null;
        } elseif (isset($_POST['avatar_choice'])) {
            if ($_POST['avatar_choice'] === '' || in_array($_POST['avatar_choice'], array_column($avatarOptions, 'value'), true)) {
                $photoPath = $_POST['avatar_choice'];
            }
        }

        $stmt = $db->prepare('UPDATE users SET profile_photo = :profile_photo WHERE id = :id');
        $stmt->execute([':profile_photo' => $photoPath, ':id' => $userId]);
        $_SESSION['user']['profile_photo'] = $photoPath;
        $user['profile_photo'] = $photoPath;
        $profilePhoto = $photoPath;
        $selectedAvatar = in_array($profilePhoto, array_column($avatarOptions, 'value'), true) ? $profilePhoto : '';
        $profileMessage = 'Perfil atualizado com sucesso.';
    } catch (Exception $e) {
        $profileMessage = $e->getMessage();
        $profileMessageType = 'error';
    }
}

$displayPhoto = profile_photo_url($profilePhoto);

$history = $db->prepare('SELECT l.id, b.title, l.loaned_at, l.due_date, l.returned_at FROM loans l JOIN books b ON l.book_id = b.id WHERE l.user_id = :user_id ORDER BY l.loaned_at DESC');
$history->execute([':user_id' => $userId]);
$history = $history->fetchAll();

$dueSoon = $db->prepare('SELECT l.id, b.title, l.loaned_at, l.due_date FROM loans l JOIN books b ON l.book_id = b.id WHERE l.user_id = :user_id AND l.returned_at IS NULL AND l.due_date >= DATE("now") AND l.due_date <= DATE("now", "+2 days") ORDER BY l.due_date ASC');
$dueSoon->execute([':user_id' => $userId]);
$dueSoon = $dueSoon->fetchAll();

$overdue = $db->prepare('SELECT l.id, b.title, l.loaned_at, l.due_date FROM loans l JOIN books b ON l.book_id = b.id WHERE l.user_id = :user_id AND l.returned_at IS NULL AND l.due_date < DATE("now") ORDER BY l.due_date ASC');
$overdue->execute([':user_id' => $userId]);
$overdue = $overdue->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Painel do Aluno</h1>
    <p>Bem-vindo, <?php echo h($user['name']); ?>. Aqui você pode pesquisar livros e ver seu histórico e pendências.</p>
</div>

<?php if (!empty($dueSoon)): ?>
<div class="card">
    <div class="flash warning">
        <strong>Atenção!</strong> Você possui <?php echo count($dueSoon); ?> empréstimo(s) vencendo em até 2 dias.
    </div>
    <ul class="alert-list">
        <?php foreach ($dueSoon as $item): ?>
            <li><strong><?php echo h($item['title']); ?></strong> — vence em <?php echo h(format_date($item['due_date'])); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card profile-card">
    <div class="profile-summary">
        <img src="<?php echo h($displayPhoto); ?>" alt="Foto de perfil" class="profile-avatar">
        <div>
            <h2>Seu perfil</h2>
            <p>Turma: <?php echo h($turma !== '' ? $turma : 'Não informada'); ?></p>
            <p>Turno: <?php echo h($turno !== '' ? $turno : 'Não informado'); ?></p>
            <p>Escolha um avatar padrão ou envie uma foto personalizada para o seu painel.</p>
        </div>
    </div>
    <?php if ($profileMessage): ?>
        <div class="flash <?php echo h($profileMessageType); ?>"><?php echo h($profileMessage); ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="profile-form">
        <input type="hidden" name="profile_photo_form" value="1">
        <div class="form-group">
            <label>Avatares padrão</label>
            <div class="avatar-options">
                <?php foreach ($avatarOptions as $option): ?>
                    <label class="avatar-option">
                        <input type="radio" name="avatar_choice" value="<?php echo h($option['value']); ?>" <?php echo ($selectedAvatar === $option['value'] ? 'checked' : ''); ?>>
                        <img src="<?php echo h(base_url($option['value'])); ?>" alt="<?php echo h($option['label']); ?>">
                        <span><?php echo h($option['label']); ?></span>
                    </label>
                <?php endforeach; ?>
                <label class="avatar-option">
                    <input type="radio" name="avatar_choice" value="" <?php echo ($selectedAvatar === '' ? 'checked' : ''); ?>>
                    <img src="<?php echo h(base_url('img/avatars/avatar-biblioteca.svg')); ?>" alt="Sem foto">
                    <span>Sem foto</span>
                </label>
            </div>
        </div>
        <div class="form-group">
            <label for="profile_photo">Enviar foto personalizada</label>
            <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
        </div>
        <div class="actions">
            <input type="submit" value="Salvar perfil">
            <button type="submit" name="remove_photo" value="1">Remover foto</button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Pesquisar livros</h2>
    <form method="get" action="student_dashboard.php">
        <div class="form-group">
            <label for="search">Título, autor ou categoria</label>
            <input type="text" id="search" name="search" value="<?php echo h($search); ?>" placeholder="Buscar no catálogo...">
        </div>
        <input type="submit" value="Pesquisar">
    </form>
    <?php if ($books === []): ?>
        <p>Nenhum livro encontrado.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Título</th>
                    <th>Autor</th>
                    <th>Categoria</th>
                    <th>Disponível</th>
                    <th>PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($books as $book): ?>
                    <tr>
                        <td>
                            <?php if (!empty($book['cover_path'])): ?>
                                <img src="<?php echo h(base_url($book['cover_path'])); ?>" alt="Foto do livro" style="max-width: 60px; max-height: 60px; object-fit: cover;">
                            <?php else: ?>
                                <span>Sem foto</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h($book['title']); ?></td>
                        <td><?php echo h($book['author']); ?></td>
                        <td><?php echo h($book['category']); ?></td>
                        <td><?php echo (int)$book['quantity']; ?></td>
                        <td>
                            <?php if (!empty($book['pdf_path'])): ?>
                                <a href="view_book_pdf.php?id=<?php echo (int)$book['id']; ?>" target="_blank" rel="noopener">Ver / Baixar PDF</a>
                            <?php else: ?>
                                <span class="muted">Sem PDF</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
        <h2 style="margin: 0;">Histórico de empréstimos e Autorizações</h2>
        <span class="muted" style="font-size: 0.85rem;">📄 Baixe a autorização em PDF para assinatura dos responsáveis</span>
    </div>
    <?php if (empty($history)): ?>
        <p>Você ainda não possui histórico de empréstimos.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Livro</th>
                    <th>Emprestado</th>
                    <th>Devolução</th>
                    <th>Status</th>
                    <th>Termo dos Pais</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $item): ?>
                    <tr>
                        <td><strong><?php echo h($item['title']); ?></strong></td>
                        <td><?php echo h(format_date($item['loaned_at'])); ?></td>
                        <td><?php echo h(format_date($item['due_date'])); ?></td>
                        <td>
                            <?php if ($item['returned_at']): ?>
                                <span class="status-pill status-returned">Devolvido</span>
                            <?php else: ?>
                                <?php $today = strtotime(date('Y-m-d')); $dueTimestamp = strtotime($item['due_date']); ?>
                                <?php if ($dueTimestamp < $today): ?>
                                    <span class="status-pill status-overdue">Atrasado</span>
                                <?php elseif ($dueTimestamp <= strtotime('+2 days', $today)): ?>
                                    <span class="status-pill status-warning">Vence em até 2 dias</span>
                                <?php else: ?>
                                    <span class="status-pill status-active">Ativo</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="loan_authorization_pdf.php?id=<?php echo (int)$item['id']; ?>" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600;">
                                📄 Baixar Termo PDF
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Pendências</h2>
    <?php if (empty($overdue)): ?>
        <p>Não há pendências de devolução.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Livro</th>
                    <th>Emprestado</th>
                    <th>Vencimento</th>
                    <th>Dias de atraso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($overdue as $item): ?>
                    <tr>
                        <td><?php echo h($item['title']); ?></td>
                        <td><?php echo h(format_date($item['loaned_at'])); ?></td>
                        <td><?php echo h(format_date($item['due_date'])); ?></td>
                        <td><?php echo max(0, (int)floor((time() - strtotime($item['due_date'])) / 86400)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php';

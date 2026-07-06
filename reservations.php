<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$db = get_db();
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$error = null;

if ($action === 'reserve' && isset($_GET['book_id'])) {
    $bookId = (int)$_GET['book_id'];
    $user = current_user();

    if ($bookId <= 0) {
        $error = 'Livro inválido.';
    } else {
        $stmt = $db->prepare('INSERT INTO reservations (user_id, book_id) VALUES (:user_id, :book_id)');
        $stmt->execute([':user_id' => $user['id'], ':book_id' => $bookId]);
        set_flash('Reserva registrada com sucesso.');
        redirect('reservations.php');
    }
}

if ($action === 'fulfill' && $id) {
    require_role(['Administrador', 'Bibliotecário']);
    $reservation = $db->prepare('SELECT r.id, r.book_id, b.quantity FROM reservations r JOIN books b ON r.book_id = b.id WHERE r.id = :id AND r.fulfilled_at IS NULL');
    $reservation->execute([':id' => $id]);
    $reservation = $reservation->fetch();

    if ($reservation && $reservation['quantity'] > 0) {
        $db->beginTransaction();
        $db->prepare('UPDATE reservations SET fulfilled_at = DATE("now") WHERE id = :id')->execute([':id' => $id]);
        $db->prepare('UPDATE books SET quantity = quantity - 1 WHERE id = :book_id')->execute([':book_id' => $reservation['book_id']]);
        $db->commit();
        set_flash('Reserva cumprida e livro reservado para o usuário.');
    } else {
        $error = 'Não foi possível cumprir a reserva. Verifique se o livro está disponível.';
    }
    redirect('reservations.php');
}

$reservationsQuery = 'SELECT r.id, u.name AS user_name, b.title AS book_title, r.reserved_at, r.fulfilled_at FROM reservations r JOIN users u ON r.user_id = u.id JOIN books b ON r.book_id = b.id';
if (!user_has_role(['Administrador', 'Bibliotecário'])) {
    $reservationsQuery .= ' WHERE u.id = ' . (int)current_user()['id'];
}
$reservationsQuery .= ' ORDER BY r.reserved_at DESC';
$reservations = $db->query($reservationsQuery)->fetchAll();
$books = $db->query('SELECT id, title, quantity, cover_path FROM books ORDER BY title')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Reservas</h1>
</div>

<?php if ($error): ?>
    <div class="flash error"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="card">
    <h2>Nova reserva</h2>
    <p style="margin-top: 6px; color: #22384f; font-weight: 600;">Escolha um livro para reservar.</p>
    <table>
        <thead>
            <tr>
                <th>Foto</th>
                <th>Título</th>
                <th>Disponível</th>
                <th>Ação</th>
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
                    <td><?php echo (int)$book['quantity']; ?></td>
                    <td>
                        <a href="reservations.php?action=reserve&book_id=<?php echo (int)$book['id']; ?>">Reservar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Lista de reservas</h2>
    <?php if (empty($reservations)): ?>
        <p style="margin-top: 8px; color: #22384f; font-weight: 600;">Sem reservas registradas.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th>Livro</th>
                    <th>Reservado em</th>
                    <th>Status</th>
                    <?php if (user_has_role(['Administrador', 'Bibliotecário'])): ?><th>Ações</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $reservation): ?>
                    <tr>
                        <td><?php echo h($reservation['user_name']); ?></td>
                        <td><?php echo h($reservation['book_title']); ?></td>
                        <td><?php echo h(format_date($reservation['reserved_at'])); ?></td>
                        <td><?php echo $reservation['fulfilled_at'] ? 'Cumprida em ' . h(format_date($reservation['fulfilled_at'])) : 'Pendente'; ?></td>
                        <?php if (user_has_role(['Administrador', 'Bibliotecário'])): ?>
                            <td>
                                <?php if (!$reservation['fulfilled_at']): ?>
                                    <a href="reservations.php?action=fulfill&id=<?php echo (int)$reservation['id']; ?>">Cumprir</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php';

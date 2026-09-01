<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/barcode.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

$db = get_db();
$csrfToken = csrf_token();
ensure_book_pdf_column();
ensure_book_barcode_column();
ensure_book_shelf_column();
try {
    $db->exec('ALTER TABLE books ADD COLUMN cover_path TEXT');
} catch (Exception $e) {
    // Ignore when the column already exists.
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$id = require_positive_int($_GET['id'] ?? null);
$error = null;
$search = trim((string)($_GET['search'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postCsrf = trim((string)($_POST['csrf_token'] ?? ''));
    if (!verify_csrf_token($postCsrf)) {
        http_response_code(419);
        set_flash('Sessão expirada ou token inválido.', 'error');
        redirect('books.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim((string)$_POST['action']) : $action;
    $id = require_positive_int($_POST['id'] ?? $_GET['id'] ?? null) ?? $id;

    $title = trim((string)($_POST['title'] ?? ''));
    $author = trim((string)($_POST['author'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $isbn = trim((string)($_POST['isbn'] ?? ''));
    $barcode = normalize_barcode(trim((string)($_POST['barcode'] ?? '')));
    $publisher = trim((string)($_POST['publisher'] ?? ''));
    $year = trim((string)($_POST['year'] ?? ''));
    $quantity = (int)($_POST['quantity'] ?? 0);
    $shelf = trim((string)($_POST['shelf'] ?? ''));
    $internal_code = trim((string)($_POST['internal_code'] ?? ''));
    $coverPath = null;
    $pdfPath = null;

    if ($title === '' || $author === '' || $category === '') {
        $error = 'Título, autor e categoria são obrigatórios.';
    } else {
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Falha ao enviar a foto do livro.';
            } else {
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($fileInfo, $_FILES['cover']['tmp_name']);
                finfo_close($fileInfo);

                if (!in_array($mimeType, $allowedMimeTypes, true)) {
                    $error = 'A foto deve ser um arquivo de imagem válido.';
                } elseif ($_FILES['cover']['size'] > 2 * 1024 * 1024) {
                    $error = 'A foto deve ter no máximo 2 MB.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/books';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $extension = match ($mimeType) {
                        'image/jpeg' => '.jpg',
                        'image/png' => '.png',
                        'image/webp' => '.webp',
                        'image/gif' => '.gif',
                        default => '.jpg',
                    };

                    $fileName = 'book_' . time() . '_' . bin2hex(random_bytes(4)) . $extension;
                    $destination = $uploadDir . '/' . $fileName;

                    if (!move_uploaded_file($_FILES['cover']['tmp_name'], $destination)) {
                        $error = 'Não foi possível salvar a foto do livro.';
                    } else {
                        $coverPath = 'uploads/books/' . $fileName;
                    }
                }
            }
        }

        if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Falha ao enviar o PDF do livro.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES['pdf']['tmp_name']);
                finfo_close($finfo);

                if ($mimeType !== 'application/pdf') {
                    $error = 'O arquivo deve ser um PDF válido.';
                } elseif ($_FILES['pdf']['size'] > 5 * 1024 * 1024) {
                    $error = 'O PDF deve ter no máximo 5 MB.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/books';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileName = 'book_pdf_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                    $destination = $uploadDir . '/' . $fileName;

                    if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $destination)) {
                        $error = 'Não foi possível salvar o PDF do livro.';
                    } else {
                        $pdfPath = 'uploads/books/' . $fileName;
                    }
                }
            }
        }

        if ($error === null) {
            if (!empty($id)) {
                $currentBook = $db->prepare('SELECT cover_path FROM books WHERE id = :id');
                $currentBook->execute([':id' => $id]);
                $currentCover = $currentBook->fetchColumn();

                if ($coverPath === null && $currentCover) {
                    $coverPath = $currentCover;
                } elseif ($coverPath !== null && $currentCover && $currentCover !== $coverPath) {
                    $oldFile = __DIR__ . '/' . $currentCover;
                    if (is_file($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $currentBook = $db->prepare('SELECT pdf_path FROM books WHERE id = :id');
                $currentBook->execute([':id' => $id]);
                $currentPdf = $currentBook->fetchColumn();

                if ($pdfPath === null && $currentPdf) {
                    $pdfPath = $currentPdf;
                } elseif ($pdfPath !== null && $currentPdf && $currentPdf !== $pdfPath) {
                    $oldFile = __DIR__ . '/' . $currentPdf;
                    if (is_file($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $stmt = $db->prepare('UPDATE books SET title = :title, author = :author, category = :category, isbn = :isbn, barcode = :barcode, publisher = :publisher, year = :year, quantity = :quantity, shelf = :shelf, internal_code = :internal_code, cover_path = :cover_path, pdf_path = :pdf_path WHERE id = :id');
                $stmt->execute([
                    ':title' => $title,
                    ':author' => $author,
                    ':category' => $category,
                    ':isbn' => $isbn,
                    ':barcode' => $barcode ?: null,
                    ':publisher' => $publisher,
                    ':year' => $year ?: null,
                    ':quantity' => $quantity,
                    ':shelf' => $shelf,
                    ':internal_code' => $internal_code,
                    ':cover_path' => $coverPath,
                    ':pdf_path' => $pdfPath,
                    ':id' => $id,
                ]);
                set_flash('Livro atualizado com sucesso.');
            } else {
                $stmt = $db->prepare('INSERT INTO books (title, author, category, isbn, barcode, publisher, year, quantity, shelf, internal_code, cover_path, pdf_path) VALUES (:title, :author, :category, :isbn, :barcode, :publisher, :year, :quantity, :shelf, :internal_code, :cover_path, :pdf_path)');
                $stmt->execute([
                    ':title' => $title,
                    ':author' => $author,
                    ':category' => $category,
                    ':isbn' => $isbn,
                    ':barcode' => $barcode ?: null,
                    ':publisher' => $publisher,
                    ':year' => $year ?: null,
                    ':quantity' => $quantity,
                    ':shelf' => $shelf,
                    ':internal_code' => $internal_code,
                    ':cover_path' => $coverPath,
                    ':pdf_path' => $pdfPath,
                ]);
                set_flash('Livro cadastrado com sucesso.');
            }

            redirect('books.php');
        }
    }
}

if ($action === 'delete' && $id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        set_flash('A exclusão de livros deve ser confirmada através do formulário do sistema.', 'error');
        redirect('books.php');
    }

    $stmt = $db->prepare('SELECT cover_path, pdf_path FROM books WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $currentBookFiles = $stmt->fetch();

    try {
        $loanCountStmt = $db->prepare('SELECT COUNT(*) FROM loans WHERE book_id = :id');
        $loanCountStmt->execute([':id' => $id]);
        $loanCount = (int)$loanCountStmt->fetchColumn();

        $reservationCountStmt = $db->prepare('SELECT COUNT(*) FROM reservations WHERE book_id = :id');
        $reservationCountStmt->execute([':id' => $id]);
        $reservationCount = (int)$reservationCountStmt->fetchColumn();

        if ($loanCount > 0) {
            $deleteLoans = $db->prepare('DELETE FROM loans WHERE book_id = :id');
            $deleteLoans->execute([':id' => $id]);
        }

        if ($reservationCount > 0) {
            $deleteReservations = $db->prepare('DELETE FROM reservations WHERE book_id = :id');
            $deleteReservations->execute([':id' => $id]);
        }

        if (!empty($currentBookFiles['cover_path'])) {
            $oldFile = __DIR__ . '/' . $currentBookFiles['cover_path'];
            if (is_file($oldFile)) {
                unlink($oldFile);
            }
        }

        if (!empty($currentBookFiles['pdf_path'])) {
            $oldPdfFile = __DIR__ . '/' . $currentBookFiles['pdf_path'];
            if (is_file($oldPdfFile)) {
                unlink($oldPdfFile);
            }
        }

        $stmt = $db->prepare('DELETE FROM books WHERE id = :id');
        $stmt->execute([':id' => $id]);
        set_flash('Livro excluído com sucesso. Registros de empréstimos e reservas vinculados também foram removidos.', 'success');
    } catch (Exception $e) {
        set_flash('Não foi possível excluir o livro no momento.', 'error');
    }

    redirect('books.php');
}

$book = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare('SELECT * FROM books WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $book = $stmt->fetch();
}

$search = trim($_GET['search'] ?? '');
$books = [];
$searchQuery = '';
$params = [];

if ($search !== '') {
    $searchQuery = 'WHERE title LIKE :search OR author LIKE :search OR category LIKE :search OR barcode LIKE :search OR internal_code LIKE :search OR isbn LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$books = $db->prepare('SELECT * FROM books ' . $searchQuery . ' ORDER BY title');
$books->execute($params);
$books = $books->fetchAll();
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
                    <p>Gestão de acervo</p>
                </div>
            </div>
            <nav class="sidebar-nav" aria-label="Menu principal">
                <a class="nav-item" href="dashboard.php"><span class="nav-icon">📊</span><span>Dashboard</span></a>
                <a class="nav-item" href="index.php"><span class="nav-icon">📚</span><span>Catálogo</span></a>
                <a class="nav-item" href="loans.php"><span class="nav-icon">📖</span><span>Empréstimos</span></a>
                <a class="nav-item" href="reservations.php"><span class="nav-icon">📌</span><span>Reservas</span></a>
                <a class="nav-item" href="reports.php"><span class="nav-icon">📊</span><span>Relatórios</span></a>
                <a class="nav-item active" href="books.php"><span class="nav-icon">📚</span><span>Livros</span></a>
                <a class="nav-item" href="books.php?action=add"><span class="nav-icon">➕</span><span>Cadastrar livro</span></a>
                <a class="nav-item" href="users.php"><span class="nav-icon">👥</span><span>Usuários &amp; Pais</span></a>
                <a class="sidebar-logout nav-item" href="logout.php"><span class="nav-icon">🚪</span><span>Sair</span></a>
            </nav>
        </div>
    </aside>

    <div class="dashboard-main-panel">
        <header class="dashboard-topbar">
            <div>
                <p class="eyebrow">Gerenciamento</p>
                <h1>Livros</h1>
                <p class="topbar-subtitle">Cadastre, edite e acompanhe o acervo da biblioteca.</p>
            </div>
            <div class="topbar-actions">
                <a class="primary-btn" href="books.php?action=add">Novo livro</a>
            </div>
        </header>

        <div class="page-content-stack">
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <p class="panel-eyebrow">Cadastro</p>
                            <h2><?php echo $action === 'edit' ? 'Editar livro' : 'Cadastrar livro'; ?></h2>
                            <p class="panel-subtitle">Preencha as informações do livro com os detalhes necessários.</p>
                        </div>
                    </div>
                    <?php if ($error): ?>
                        <div class="flash error"><?php echo h($error); ?></div>
                    <?php endif; ?>
                    <form method="post" action="books.php<?php echo $action === 'edit' ? '?action=edit&id=' . (int)$id : ''; ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                        <div class="form-group">
                            <label for="title">Título</label>
                            <input type="text" id="title" name="title" value="<?php echo h($book['title'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="author">Autor</label>
                            <input type="text" id="author" name="author" value="<?php echo h($book['author'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Categoria</label>
                            <input type="text" id="category" name="category" value="<?php echo h($book['category'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="isbn">ISBN</label>
                            <input type="text" id="isbn" name="isbn" value="<?php echo h($book['isbn'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="barcode">Código de barras</label>
                            <input type="text" id="barcode" name="barcode" value="<?php echo h($book['barcode'] ?? ''); ?>" inputmode="numeric" autocomplete="off" placeholder="Aponte o leitor e aguarde o bip">
                            <small class="muted">Use um leitor USB/Bluetooth como os de supermercado. Ele funciona como teclado e normalmente envia Enter ao terminar.</small>
                        </div>
                        <div class="form-group">
                            <label for="publisher">Editora</label>
                            <input type="text" id="publisher" name="publisher" value="<?php echo h($book['publisher'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="year">Ano</label>
                            <input type="number" id="year" name="year" value="<?php echo h($book['year'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantidade</label>
                            <input type="number" id="quantity" name="quantity" value="<?php echo h($book['quantity'] ?? 0); ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label for="shelf">Estante</label>
                            <input type="text" id="shelf" name="shelf" value="<?php echo h($book['shelf'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="internal_code">Código interno</label>
                            <input type="text" id="internal_code" name="internal_code" value="<?php echo h($book['internal_code'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="pdf">PDF do livro (opcional)</label>
                            <input type="file" id="pdf" name="pdf" accept="application/pdf">
                            <?php if (!empty($book['pdf_path'])): ?>
                                <p class="muted">PDF já enviado.</p>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="cover">Foto do livro (opcional)</label>
                            <input type="file" id="cover" name="cover" accept="image/*">
                            <?php if (!empty($book['cover_path'])): ?>
                                <div style="margin-top: 8px;">
                                    <img src="<?php echo h(base_url($book['cover_path'])); ?>" alt="Foto atual" style="max-width: 140px; max-height: 140px; object-fit: cover; border: 1px solid rgba(255,255,255,0.14); border-radius: 12px;">
                                </div>
                            <?php endif; ?>
                        </div>
                        <input type="submit" value="Salvar" class="primary-btn">
                    </form>
                </section>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <p class="panel-eyebrow">Acervo</p>
                        <h2>Lista de livros</h2>
                        <p class="panel-subtitle">Visualize todos os livros cadastrados e suas ações rápidas.</p>
                    </div>
                    <div class="view-toggle" aria-label="Alternar visualização">
                        <button type="button" class="toggle-btn active" data-view="shelf">🗃️ Estante</button>
                        <button type="button" class="toggle-btn" data-view="list">📋 Lista</button>
                    </div>
                    <a class="primary-btn" href="books.php?action=add">Cadastrar livro</a>
                </div>
                <?php if (empty($books)): ?>
                    <?php if ($search !== ''): ?>
                        <p class="empty-state">Nenhum livro com o título/autor "<?php echo h($search); ?>" cadastrado. Tente um termo diferente ou visualize o <a href="books.php">catálogo completo</a>.</p>
                    <?php else: ?>
                        <p class="empty-state">Nenhum livro cadastrado no sistema.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div id="shelf-view" class="virtual-bookshelf">
                        <?php
                        $genreShelfMap = [
                            'conto' => 'Estante 1 - Conto',
                            'terror' => 'Estante 2 - Terror',
                            'romance' => 'Estante 3 - Romance',
                            'ficcao' => 'Estante 4 - Ficção',
                            'ficção' => 'Estante 4 - Ficção',
                            'infantil' => 'Estante 5 - Infantil',
                            'aventura' => 'Estante 6 - Aventura',
                            'poesia' => 'Estante 7 - Poesia',
                            'historia' => 'Estante 8 - História',
                            'história' => 'Estante 8 - História',
                            'biografia' => 'Estante 9 - Biografia',
                            'suspense' => 'Estante 10 - Suspense',
                            'drama' => 'Estante 11 - Drama',
                            'educativo' => 'Estante 12 - Educativo',
                            'didatico' => 'Estante 12 - Educativo',
                            'didático' => 'Estante 12 - Educativo',
                        ];

                        $booksByShelf = [];
                        foreach ($books as $bookItem) {
                            $categoryValue = strtolower(trim((string)($bookItem['category'] ?? '')));
                            $shelfName = $genreShelfMap[$categoryValue] ?? 'Estante 13 - Geral';
                            $booksByShelf[$shelfName][] = $bookItem;
                        }

                        ksort($booksByShelf, SORT_NATURAL | SORT_FLAG_CASE);
                        foreach ($booksByShelf as $shelfName => $shelfBooks):
                        ?>
                            <div class="shelf">
                                <div class="shelf-label"><?php echo h($shelfName); ?> <span class="shelf-count">(<?php echo count($shelfBooks); ?>)</span></div>
                                <?php foreach ($shelfBooks as $item): ?>
                                    <?php
                                    $quantity = (int)($item['quantity'] ?? 0);
                                    $badgeClass = $quantity > 1 ? 'available' : ($quantity > 0 ? 'low' : 'empty');
                                    $coverPath = !empty($item['cover_path']) ? base_url($item['cover_path']) : '';
                                    ?>
                                    <div class="book-item" title="<?php echo h($item['title']); ?> - <?php echo h($item['author']); ?>">
                                        <div class="book-badge <?php echo $badgeClass; ?>"><?php echo $quantity; ?></div>
                                        <?php if ($coverPath !== ''): ?>
                                            <img class="book-cover-img" src="<?php echo h($coverPath); ?>" alt="Capa do livro <?php echo h($item['title']); ?>">
                                        <?php else: ?>
                                            <div class="book-spine">
                                                <div class="book-spine-title"><?php echo h($item['title']); ?></div>
                                                <div class="book-spine-author"><?php echo h($item['author']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="book-tooltip">
                                            <strong><?php echo h($item['title']); ?></strong>
                                            <div><?php echo h($item['author']); ?></div>
                                            <span class="tooltip-category"><?php echo h($item['category']); ?></span>
                                            <div class="tooltip-isbn"><?php echo h($item['isbn'] ?: 'SEM ISBN'); ?></div>
                                            <div class="tooltip-actions">
                                                <a class="tooltip-btn edit" href="books.php?action=edit&id=<?php echo (int)$item['id']; ?>">Editar</a>
                                                <form method="post" action="books.php?action=delete&id=<?php echo (int)$item['id']; ?>" style="flex:1; margin:0;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                    <button type="submit" class="tooltip-btn delete" onclick="return confirm('Tem certeza que deseja excluir este livro?');">Excluir</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="list-view" style="display:none;">
                        <div class="table-wrapper">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Título</th>
                                        <th>Autor</th>
                                        <th>Estante</th>
                                        <th>Categoria</th>
                                        <th>Quantidade</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($books as $item): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($item['cover_path'])): ?>
                                                    <img src="<?php echo h(base_url($item['cover_path'])); ?>" alt="Foto do livro" style="max-width: 60px; max-height: 60px; object-fit: cover; border-radius: 12px;">
                                                <?php else: ?>
                                                    <span class="muted">Sem foto</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo h($item['title']); ?></td>
                                            <td><?php echo h($item['author']); ?></td>
                                            <td><?php echo h($item['shelf'] ?? ''); ?></td>
                                            <td><?php echo h($item['category']); ?></td>
                                            <td><?php echo (int)$item['quantity']; ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <a class="icon-btn" href="books.php?action=edit&id=<?php echo (int)$item['id']; ?>" title="Editar">✏️</a>
                                                    <form method="post" action="books.php?action=delete&id=<?php echo (int)$item['id']; ?>" style="display:inline; margin:0;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                                        <button type="submit" class="icon-btn" title="Excluir" onclick="return confirm('Tem certeza que deseja excluir este livro?');" style="border:none; background:none; cursor:pointer;">🗑️</button>
                                                    </form>
                                                    <?php if (!empty($item['pdf_path'])): ?>
                                                        <a class="icon-btn" href="view_book_pdf.php?id=<?php echo (int)$item['id']; ?>" target="_blank" rel="noopener" title="Ver PDF">📄</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const buttons = document.querySelectorAll('.toggle-btn');
        const shelfView = document.getElementById('shelf-view');
        const listView = document.getElementById('list-view');

        if (buttons.length && shelfView && listView) {
            buttons.forEach((button) => {
                button.addEventListener('click', function () {
                    const view = button.dataset.view;
                    const isShelf = view === 'shelf';

                    buttons.forEach((item) => item.classList.toggle('active', item === button));
                    shelfView.style.display = isShelf ? 'block' : 'none';
                    listView.style.display = isShelf ? 'none' : 'block';
                });
            });
        }

        const bookItems = document.querySelectorAll('.book-item');

        bookItems.forEach((bookItem) => {
            const tooltip = bookItem.querySelector('.book-tooltip');
            if (!tooltip) {
                return;
            }

            const showTooltip = () => {
                clearTimeout(bookItem.__tooltipHideTimer);
                bookItem.classList.add('is-hovered');
            };

            const scheduleHideTooltip = () => {
                clearTimeout(bookItem.__tooltipHideTimer);
                bookItem.__tooltipHideTimer = setTimeout(() => {
                    bookItem.classList.remove('is-hovered');
                }, 1100);
            };

            bookItem.addEventListener('mouseenter', showTooltip);
            bookItem.addEventListener('mouseleave', scheduleHideTooltip);
            tooltip.addEventListener('mouseenter', showTooltip);
            tooltip.addEventListener('mouseleave', scheduleHideTooltip);
        });
    });
</script>

<?php require_once __DIR__ . '/includes/footer.php';

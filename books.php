<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

$db = get_db();
ensure_book_pdf_column();
try {
    $db->exec('ALTER TABLE books ADD COLUMN cover_path TEXT');
} catch (Exception $e) {
    // Ignore when the column already exists.
}

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $publisher = trim($_POST['publisher'] ?? '');
    $year = trim($_POST['year'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $shelf = trim($_POST['shelf'] ?? '');
    $internal_code = trim($_POST['internal_code'] ?? '');
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

                $stmt = $db->prepare('UPDATE books SET title = :title, author = :author, category = :category, isbn = :isbn, publisher = :publisher, year = :year, quantity = :quantity, shelf = :shelf, internal_code = :internal_code, cover_path = :cover_path, pdf_path = :pdf_path WHERE id = :id');
                $stmt->execute([
                    ':title' => $title,
                    ':author' => $author,
                    ':category' => $category,
                    ':isbn' => $isbn,
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
                $stmt = $db->prepare('INSERT INTO books (title, author, category, isbn, publisher, year, quantity, shelf, internal_code, cover_path, pdf_path) VALUES (:title, :author, :category, :isbn, :publisher, :year, :quantity, :shelf, :internal_code, :cover_path, :pdf_path)');
                $stmt->execute([
                    ':title' => $title,
                    ':author' => $author,
                    ':category' => $category,
                    ':isbn' => $isbn,
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

$books = $db->query('SELECT * FROM books ORDER BY title')->fetchAll();
require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Livros</h1>
    <div class="actions">
        <a href="books.php?action=add">Novo livro</a>
    </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card">
        <h2><?php echo $action === 'edit' ? 'Editar livro' : 'Cadastrar livro'; ?></h2>
        <?php if ($error): ?>
            <div class="flash error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <form method="post" action="books.php<?php echo $action === 'edit' ? '?action=edit&id=' . (int)$id : ''; ?>" enctype="multipart/form-data">
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
                        <img src="<?php echo h(base_url($book['cover_path'])); ?>" alt="Foto atual" style="max-width: 140px; max-height: 140px; object-fit: cover; border: 1px solid #ddd;">
                    </div>
                <?php endif; ?>
            </div>
            <input type="submit" value="Salvar">
        </form>
    </div>
<?php endif; ?>

<div class="card">
    <h2>Lista de livros</h2>
    <?php if (empty($books)): ?>
        <p>Nenhum livro cadastrado.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Título</th>
                    <th>Autor</th>
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
                                <img src="<?php echo h(base_url($item['cover_path'])); ?>" alt="Foto do livro" style="max-width: 60px; max-height: 60px; object-fit: cover;">
                            <?php else: ?>
                                <span>Sem foto</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo h($item['title']); ?></td>
                        <td><?php echo h($item['author']); ?></td>
                        <td><?php echo h($item['category']); ?></td>
                        <td><?php echo (int)$item['quantity']; ?></td>
                        <td>
                            <a href="books.php?action=edit&id=<?php echo (int)$item['id']; ?>">Editar</a>
                            <a href="books.php?action=delete&id=<?php echo (int)$item['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir este livro?');">Excluir</a>
                            <?php if (!empty($item['pdf_path'])): ?>
                                <a href="view_book_pdf.php?id=<?php echo (int)$item['id']; ?>" target="_blank" rel="noopener">Ver PDF</a>
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

<?php require_once __DIR__ . '/includes/footer.php';

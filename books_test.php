<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/barcode.php';
require_login();
require_role(['Administrador', 'Bibliotecário']);

$db = get_db();
ensure_book_pdf_column();
ensure_book_barcode_column();
try {
    $db->exec('ALTER TABLE books ADD COLUMN cover_path TEXT');
} catch (Exception $e) {
    // Ignore when the column already exists.
}

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$error = null;
$search = trim($_GET['search'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $barcode = normalize_barcode(trim($_POST['barcode'] ?? ''));
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
    // Validar se a requisição é POST e se a senha está correta
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_password']) && $_POST['confirm_password'] === 'admin123') {
        
        // 1. Buscar caminhos dos arquivos antes de deletar o registro do banco
        $stmt = $db->prepare('SELECT cover_path, pdf_path FROM books WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $bookFiles = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($bookFiles) {
            // Apagar a foto da capa do servidor, se ela existir
            if (!empty($bookFiles['cover_path'])) {
                $coverFile = __DIR__ . '/' . $bookFiles['cover_path'];
                if (is_file($coverFile)) {
                    unlink($coverFile);
                }
            }
            // Apagar o arquivo PDF do servidor, se ele existir
            if (!empty($bookFiles['pdf_path'])) {
                $pdfFile = __DIR__ . '/' . $bookFiles['pdf_path'];
                if (is_file($pdfFile)) {
                    unlink($pdfFile);
                }
            }
        }

        // 2. Excluir o livro do banco de dados
        $stmt = $db->prepare('DELETE FROM books WHERE id = :id');
        $stmt->execute([':id' => $id]);

        set_flash('Livro excluído com sucesso.');
        redirect('books.php');
    } else {
        // Se a senha estiver incorreta ou não enviada via POST, gera um erro informando
        $error = 'Senha de confirmação inválida para a exclusão.';
    }
}

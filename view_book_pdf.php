<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = get_db();
$stmt = $db->prepare('SELECT pdf_path FROM books WHERE id = :id');
$stmt->execute([':id' => $id]);
$pdfPath = $stmt->fetchColumn();

if (empty($pdfPath)) {
    http_response_code(404);
    echo '<h1>PDF não encontrado</h1>';
    exit;
}

$fullPath = __DIR__ . '/' . $pdfPath;
if (!is_file($fullPath)) {
    http_response_code(404);
    echo '<h1>Arquivo PDF não encontrado</h1>';
    exit;
}

$filename = basename($pdfPath);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename=' . $filename);
readfile($fullPath);
exit;

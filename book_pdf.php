<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

function pdf_escape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function build_simple_pdf(array $book): string
{
    $lines = [
        'Biblioteca Escolar',
        '',
        'Título: ' . $book['title'],
        'Autor: ' . $book['author'],
        'Categoria: ' . $book['category'],
        'ISBN: ' . ($book['isbn'] ?? '-'),
        'Editora: ' . ($book['publisher'] ?? '-'),
        'Ano: ' . ($book['year'] ?? '-'),
        'Quantidade: ' . ($book['quantity'] ?? 0),
        'Estante: ' . ($book['shelf'] ?? '-'),
        'Código interno: ' . ($book['internal_code'] ?? '-'),
    ];

    $contentLines = [];
    $y = 760;
    foreach ($lines as $line) {
        $contentLines[] = "BT /F1 12 Tf 50 {$y} Td (" . pdf_escape($line) . ") Tj ET";
        $y -= 14;
    }

    $contentStream = implode("\n", $contentLines);

    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
    $objects[] = '<< /Length 0 >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $index => $object) {
        $offsets[$index + 1] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }

    $contentObjectOffset = strlen($pdf);
    $pdf .= "4 0 obj\n<< /Length 0 >>\nstream\n" . $contentStream . "\nendstream\nendobj\n";

    $objects[3] = '<< /Length ' . strlen($contentStream) . ' >>';
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[$index + 1] = strlen($pdf);
        if ($index + 1 === 4) {
            $pdf .= ($index + 1) . " 0 obj\n<< /Length " . strlen($contentStream) . " >>\nendobj\n";
        } else {
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
    }

    $pdf .= "4 0 obj\n<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream\nendobj\n";

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n";
    $pdf .= "%%EOF";

    return $pdf;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db = get_db();
$stmt = $db->prepare('SELECT * FROM books WHERE id = :id');
$stmt->execute([':id' => $id]);
$book = $stmt->fetch();

if (!$book) {
    http_response_code(404);
    echo '<h1>Livro não encontrado</h1>';
    exit;
}

$filename = 'livro-' . $id . '.pdf';
$pdfContent = build_simple_pdf($book);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename=' . $filename);

echo $pdfContent;
exit;

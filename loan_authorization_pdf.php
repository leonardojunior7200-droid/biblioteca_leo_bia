<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$db = get_db();
ensure_parent_columns();

$loanId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($loanId <= 0) {
    http_response_code(400);
    echo '<h1>Identificador de empréstimo inválido.</h1>';
    exit;
}

// Buscar dados completos do empréstimo, usuário e livro
$query = 'SELECT 
            l.id AS loan_id,
            l.loaned_at,
            l.due_date,
            l.returned_at,
            l.parent_name AS loan_parent_name,
            l.parent_signature_status,
            u.id AS user_id,
            u.name AS user_name,
            u.email AS user_email,
            u.turma AS user_turma,
            u.turno AS user_turno,
            u.parent_name AS user_parent_name,
            u.parent_phone AS user_parent_phone,
            u.parent_email AS user_parent_email,
            u.parent_document AS user_parent_document,
            b.id AS book_id,
            b.title AS book_title,
            b.author AS book_author,
            b.category AS book_category,
            b.isbn AS book_isbn,
            b.shelf AS book_shelf,
            b.internal_code AS book_code,
            b.barcode AS book_barcode
          FROM loans l
          JOIN users u ON l.user_id = u.id
          JOIN books b ON l.book_id = b.id
          WHERE l.id = :id';

$stmt = $db->prepare($query);
$stmt->execute([':id' => $loanId]);
$loan = $stmt->fetch();

if (!$loan) {
    http_response_code(404);
    echo '<h1>Empréstimo não encontrado.</h1>';
    exit;
}

// Permissão de acesso: Aluno só pode ver seu próprio empréstimo. Admin/Bibliotecário podem ver todos.
$currentUser = current_user();
if (user_has_role('Aluno') && (int)$currentUser['id'] !== (int)$loan['user_id']) {
    http_response_code(403);
    echo '<h1>Acesso não autorizado.</h1>';
    exit;
}

function pdf_escape(string $text): string
{
    // Remove quebras de linha brutas e escapa parênteses para sintaxe PDF
    $text = str_replace(["\r", "\n"], ' ', $text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function generate_parent_authorization_pdf(array $data): string
{
    $parentName = !empty($data['loan_parent_name']) 
        ? $data['loan_parent_name'] 
        : (!empty($data['user_parent_name']) ? $data['user_parent_name'] : '__________________________________________________');
    
    $parentPhone = !empty($data['user_parent_phone']) ? $data['user_parent_phone'] : 'Não informado';
    $parentEmail = !empty($data['user_parent_email']) ? $data['user_parent_email'] : 'Não informado';
    $parentDoc = !empty($data['user_parent_document']) ? $data['user_parent_document'] : '____________________';

    $loanDate = format_date($data['loaned_at']);
    $dueDate = format_date($data['due_date']);
    $emissao = date('d/m/Y H:i');

    $turma = !empty($data['user_turma']) ? $data['user_turma'] : 'Geral';
    $turno = !empty($data['user_turno']) ? $data['user_turno'] : 'Regular';
    $barcode = !empty($data['book_barcode']) ? $data['book_barcode'] : (!empty($data['book_code']) ? $data['book_code'] : 'N/A');

    // Construção visual e textual do PDF (Padrão A4: 595 x 842 pt)
    $contentLines = [];

    // Linha de cabeçalho / Caixa superior
    $contentLines[] = "0.2 0.4 0.8 rg 40 785 515 25 re f"; // Barra superior azul
    $contentLines[] = "BT /F1 14 Tf 1 1 1 rg 50 792 Td (BIBLIOTECA ESCOLAR - TERMO DE AUTORIZACAO E CIENCIA DOS PAIS) Tj ET";
    $contentLines[] = "0 0 0 rg"; // Reset cor para preto

    $contentLines[] = "BT /F1 9 Tf 40 765 Td (Documento Oficial de Emprestimo e Responsabilidade - Registro No #{$data['loan_id']} | Emissao: {$emissao}) Tj ET";
    $contentLines[] = "0.7 0.7 0.7 RG 1 w 40 755 m 555 755 l S"; // Linha divisória

    // Bloco 1: Identificação do Aluno
    $contentLines[] = "BT /F1 11 Tf 0.1 0.3 0.6 rg 40 735 Td (1. IDENTIFICACAO DO ALUNO) Tj ET";
    $contentLines[] = "0 0 0 rg";
    $contentLines[] = "BT /F1 10 Tf 50 715 Td (" . pdf_escape("Nome do Aluno: " . $data['user_name']) . ") Tj ET";
    $contentLines[] = "BT /F1 10 Tf 50 698 Td (" . pdf_escape("Turma: {$turma}   |   Turno: {$turno}   |   E-mail: {$data['user_email']}") . ") Tj ET";

    // Bloco 2: Identificação dos Pais / Responsáveis
    $contentLines[] = "BT /F1 11 Tf 0.1 0.3 0.6 rg 40 670 Td (2. DADOS DO RESPONSAVEL LEGAL) Tj ET";
    $contentLines[] = "0 0 0 rg";
    $contentLines[] = "BT /F1 10 Tf 50 650 Td (" . pdf_escape("Nome do Responsavel: {$parentName}") . ") Tj ET";
    $contentLines[] = "BT /F1 10 Tf 50 633 Td (" . pdf_escape("Telefone/WhatsApp: {$parentPhone}   |   E-mail: {$parentEmail}") . ") Tj ET";
    $contentLines[] = "BT /F1 10 Tf 50 616 Td (" . pdf_escape("Documento (RG/CPF): {$parentDoc}") . ") Tj ET";

    // Bloco 3: Dados do Livro Retirado
    $contentLines[] = "BT /F1 11 Tf 0.1 0.3 0.6 rg 40 588 Td (3. DADOS DA OBRA EMPRESTADA) Tj ET";
    $contentLines[] = "0 0 0 rg";
    $contentLines[] = "BT /F1 10 Tf 50 568 Td (" . pdf_escape("Titulo do Livro: " . $data['book_title']) . ") Tj ET";
    $contentLines[] = "BT /F1 10 Tf 50 551 Td (" . pdf_escape("Autor: " . $data['book_author'] . "   |   Categoria: " . $data['book_category']) . ") Tj ET";
    $contentLines[] = "BT /F1 10 Tf 50 534 Td (" . pdf_escape("Codigo / Tombo / Barras: {$barcode}   |   Estante: " . ($data['book_shelf'] ?: '-')) . ") Tj ET";
    $contentLines[] = "BT /F1 10 Tf 50 517 Td (" . pdf_escape("Data do Emprestimo: {$loanDate}   |   DATA LIMITE DE DEVOLUCAO: {$dueDate}") . ") Tj ET";

    // Bloco 4: Termo de Responsabilidade e Ciência
    $contentLines[] = "BT /F1 11 Tf 0.1 0.3 0.6 rg 40 487 Td (4. TERMO DE CIENCIA E COMPROMISSO) Tj ET";
    $contentLines[] = "0 0 0 rg";
    
    $termLines = [
        "Eu, responsavel legal pelo(a) aluno(a) acima identificado(a), declaro estar ciente de que o referido",
        "livro foi retirado da Biblioteca Escolar sob sua guarda e responsabilidade temporaria.",
        "",
        "Comprometo-me a orientar e zelar pela integridade fisica do material, garantindo sua devolucao",
        "impreterivelmente ate a data estipulada ({$dueDate}), no mesmo estado em que foi entregue.",
        "",
        "Estou ciente de que o atraso, perda, danificacao ou rasura da obra implicara no bloqueio temporario",
        "de novos emprestimos do aluno, alem do ressarcimento ou reposicao de exemplar equivalente."
    ];

    $ty = 467;
    foreach ($termLines as $tline) {
        if ($tline !== '') {
            $contentLines[] = "BT /F1 9 Tf 50 {$ty} Td (" . pdf_escape($tline) . ") Tj ET";
        }
        $ty -= 14;
    }

    // Bloco 5: Assinaturas
    $contentLines[] = "0.7 0.7 0.7 RG 1 w 40 320 m 555 320 l S";
    $contentLines[] = "BT /F1 10 Tf 50 295 Td (Local e Data: _________________________________, _____/_____/202___) Tj ET";

    // Linha assinatura Responsável
    $contentLines[] = "0 0 0 RG 1 w 50 230 m 270 230 l S";
    $contentLines[] = "BT /F1 9 Tf 65 215 Td (Assinatura do Pai / Mae / Responsavel) Tj ET";

    // Linha visto Biblioteca
    $contentLines[] = "0 0 0 RG 1 w 330 230 m 540 230 l S";
    $contentLines[] = "BT /F1 9 Tf 360 215 Td (Visto do(a) Bibliotecario(a) / Escola) Tj ET";

    // Rodapé
    $contentLines[] = "0.9 0.9 0.9 rg 40 70 515 35 re f";
    $contentLines[] = "BT /F1 8 Tf 0.3 0.3 0.3 rg 50 90 Td (Instrucoes: Imprima este termo, colha a assinatura do responsavel e apresente na Biblioteca.) Tj ET";
    $contentLines[] = "BT /F1 8 Tf 0.3 0.3 0.3 rg 50 77 Td (Sistema de Gestao de Biblioteca Escolar - Documento de validade interna.) Tj ET";

    $contentStream = implode("\n", $contentLines);

    // Montagem do PDF 1.4
    $objects = [];
    $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>';
    $objects[] = '<< /Length ' . strlen($contentStream) . ' >>';
    $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $index => $object) {
        $offsets[$index + 1] = strlen($pdf);
        if ($index + 1 === 4) {
            $pdf .= ($index + 1) . " 0 obj\n<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "\nendstream\nendobj\n";
        } else {
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
    }

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

$pdfOutput = generate_parent_authorization_pdf($loan);
$download = isset($_GET['download']) && $_GET['download'] === '1';
$disposition = $download ? 'attachment' : 'inline';
$filename = 'autorizacao-pais-emprestimo-' . $loanId . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfOutput));

echo $pdfOutput;
exit;

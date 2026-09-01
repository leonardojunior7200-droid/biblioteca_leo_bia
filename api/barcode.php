<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/barcode.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role(['Administrador', 'Bibliotecário']);

$response = ['success' => false, 'data' => null, 'error' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        throw new Exception('Invalid JSON input');
    }

    $action = $input['action'] ?? '';
    $barcode = $input['barcode'] ?? '';

    if ($barcode === '') {
        throw new Exception('Barcode is required');
    }

    if (!validate_barcode($barcode)) {
        throw new Exception('Invalid barcode format');
    }

    $normalizedBarcode = normalize_barcode($barcode);

    switch ($action) {
        case 'validate':
            $isValid = true;
            $isISBN = validate_isbn($normalizedBarcode);
            $book = get_book_by_barcode($normalizedBarcode);
            $response['data'] = [
                'valid' => $isValid,
                'is_isbn' => $isISBN,
                'book' => $book,
                'normalized' => $normalizedBarcode
            ];
            $response['success'] = true;
            break;

        case 'lookup':
            $books = search_books_by_barcode($normalizedBarcode);
            $response['data'] = [
                'books' => $books,
                'count' => count($books),
                'normalized' => $normalizedBarcode
            ];
            $response['success'] = true;
            break;

        case 'add_book':
            // This would be used to associate barcode with a book
            // For now, just return success - actual book creation happens via books.php
            $response['data'] = [
                'barcode' => $normalizedBarcode,
                'message' => 'Barcode ready for association'
            ];
            $response['success'] = true;
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['success'] = false;
}

echo json_encode($response);
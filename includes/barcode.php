<?php
require_once __DIR__ . '/db.php';

function validate_barcode(string $barcode): bool
{
    $barcode = trim($barcode);

    if ($barcode === '' || strlen($barcode) > 30) {
        return false;
    }

    if (preg_match('/^[0-9]+$/', $barcode)) {
        return true;
    }

    if (preg_match('/^[A-Za-z0-9\-_]+$/', $barcode)) {
        return true;
    }

    return false;
}

function normalize_barcode(string $barcode): string
{
    return trim(preg_replace('/[\s\-\_]/', '', $barcode));
}

function validate_isbn(string $isbn): bool
{
    $isbn = preg_replace('/[\s\-]/', '', $isbn);

    if (strlen($isbn) === 10) {
        return validate_isbn10($isbn);
    }

    if (strlen($isbn) === 13) {
        return validate_isbn13($isbn);
    }

    return false;
}

function validate_isbn10(string $isbn): bool
{
    $isbn = preg_replace('/[\s\-]/', '', $isbn);
    if (strlen($isbn) !== 10) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        if (!ctype_digit($isbn[$i])) {
            return false;
        }
        $sum += (int)$isbn[$i] * (10 - $i);
    }

    $check = $isbn[9];
    if ($check === 'X' || $check === 'x') {
        $sum += 10;
    } elseif (ctype_digit($check)) {
        $sum += (int)$check;
    } else {
        return false;
    }

    return $sum % 11 === 0;
}

function validate_isbn13(string $isbn): bool
{
    $isbn = preg_replace('/[\s\-]/', '', $isbn);
    if (strlen($isbn) !== 13 || !ctype_digit($isbn)) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$isbn[$i] * ($i % 2 === 0 ? 1 : 3);
    }

    $check = (10 - ($sum % 10)) % 10;
    return $check === (int)$isbn[12];
}

function convert_isbn10_to_13(string $isbn10): string
{
    $isbn10 = preg_replace('/[\s\-]/', '', $isbn10);
    if (strlen($isbn10) !== 10) {
        return '';
    }

    $prefix = '978' . substr($isbn10, 0, 9);
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$prefix[$i] * ($i % 2 === 0 ? 1 : 3);
    }

    $check = (10 - ($sum % 10)) % 10;
    return $prefix . $check;
}

function get_book_by_barcode(string $barcode): ?array
{
    $db = get_db();
    $normalizedBarcode = normalize_barcode($barcode);

    try {
        $stmt = $db->prepare('SELECT id, barcode, internal_code, isbn FROM books WHERE barcode = :barcode OR internal_code = :internal_code LIMIT 1');
        $stmt->execute([
            ':barcode' => $normalizedBarcode,
            ':internal_code' => $normalizedBarcode,
        ]);
        $book = $stmt->fetch();
        return $book ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function generate_internal_barcode(): string
{
    $prefix = 'LIB';
    $random = strtoupper(bin2hex(random_bytes(4)));
    return $prefix . '-' . $random;
}

function search_books_by_barcode(string $barcode): array
{
    $db = get_db();
    $normalizedBarcode = normalize_barcode($barcode);

    try {
        $stmt = $db->prepare('SELECT id, title, author, category, barcode, internal_code, isbn, quantity FROM books WHERE barcode = :barcode OR internal_code = :internal_code OR isbn = :isbn LIMIT 10');
        $stmt->execute([
            ':barcode' => $normalizedBarcode,
            ':internal_code' => $normalizedBarcode,
            ':isbn' => $normalizedBarcode,
        ]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

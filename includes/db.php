<?php
require_once __DIR__ . '/../config.php';

function get_db(): PDO
{
    static $db = null;

    if ($db === null) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        $db = new PDO(DB_DSN, DB_USER, DB_PASS, $options);

        if (stripos(DB_DSN, 'sqlite:') === 0) {
            $db->exec('PRAGMA foreign_keys = ON');
        }
    }

    return $db;
}

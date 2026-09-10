<?php
// Configuration for the biblioteca project.

// Database settings. By default, use SQLite in data/library.db.
// To use MySQL, update DB_DSN, DB_USER and DB_PASS accordingly.

// Dados privados devem ficar fora do document root do servidor web.
define('PRIVATE_STORAGE_PATH', getenv('BIBLIOTECA_PRIVATE_STORAGE') ?: dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'biblioteca-private');
define('DB_DSN', 'sqlite:' . PRIVATE_STORAGE_PATH . DIRECTORY_SEPARATOR . 'library.db');
define('DB_USER', null);
define('DB_PASS', null);

define('SITE_NAME', 'Biblioteca Escolar');
define('MAX_LOANS_PER_USER', 2);
define('LOAN_DAYS', 30);
define('LOAN_DAY_OPTIONS', [7, 15, 30, 60]);

// Base URL for relative links. Adjust if the app is deployed under a subdirectory.
define('BASE_URL', '/biblioteca/');

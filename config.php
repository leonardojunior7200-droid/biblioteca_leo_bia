<?php
// Configuration for the biblioteca project.

// Database settings. By default, use SQLite in data/library.db.
// To use MySQL, update DB_DSN, DB_USER and DB_PASS accordingly.

define('DB_DSN', 'sqlite:' . __DIR__ . '/data/library.db');
define('DB_USER', null);
define('DB_PASS', null);

define('SITE_NAME', 'Biblioteca Escolar');
define('MAX_LOANS_PER_USER', 2);
define('LOAN_DAYS', 30);

// Base URL for relative links. Adjust if the app is deployed under a subdirectory.
define('BASE_URL', '/biblioteca/');

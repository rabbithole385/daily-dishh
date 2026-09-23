<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__));
define('DB_PATH', BASE_PATH . '/data/dailydish.sqlite');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$pdo = get_db();

<?php
/*
 * Point d'entree commun a toutes les pages PHP.
 */
declare(strict_types=1);

session_start();
date_default_timezone_set('Africa/Abidjan');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

try {
    db();
} catch (PDOException $ex) {
    http_response_code(500);
    require __DIR__ . '/db_error.php';
    exit;
}

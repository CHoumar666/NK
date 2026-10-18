<?php
/*
 * Parametres de connexion a la base MySQL.
 * Valeurs par defaut de WampServer : utilisateur root, mot de passe vide.
 */
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'entreprise_depannage');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Retourne l'unique connexion PDO de l'application.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}

<?php
// ================================================================
//  ERP System – Database Connection (PDO Singleton)
//  /erp/config/db.php
// ================================================================

if (!defined('ERP_ROOT')) {
    require_once __DIR__ . '/config.php';
}

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:Arial;color:#660000;padding:20px;">
                 <strong>Database connection failed:</strong> ' .
                 htmlspecialchars($e->getMessage()) . '</div>');
        }
    }
    return $pdo;
}

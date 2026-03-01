<?php
// ================================================================
//  ERP System – Application Configuration
//  /erp/config/config.php
// ================================================================

// ---- Root path (absolute, no trailing slash) --------------------
define('ERP_ROOT',   dirname(__DIR__));                 // /erp/
define('ERP_INC',    ERP_ROOT . '/includes');
define('ERP_MOD',    ERP_ROOT . '/modules');

// ---- Base URL (adjust for your server) --------------------------
define('BASE_URL',   'http://localhost/erp');

// ---- Application identity ---------------------------------------
define('APP_NAME',   'ERP System');
define('APP_TAGLINE','Enterprise Resource Planning');
define('APP_VERSION','1.0.0');
define('ORG_NAME',   'Your Organisation');

// ---- Database credentials ---------------------------------------
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'erp_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ---- Session lifetime (seconds) ---------------------------------
define('SESSION_LIFETIME', 3600);

// ---- Timezone ---------------------------------------------------
date_default_timezone_set('Asia/Kolkata');

// ---- Error display (set to 0 in production) ---------------------
ini_set('display_errors', 1);
error_reporting(E_ALL);

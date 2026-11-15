<?php
// ============================================================
// config/app.php — Configuration générale de l'application
// ============================================================

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Alma Hotel');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// ── Détection automatique intelligente de BASE_URL ──────────
if (!defined('BASE_URL')) {
    // 1. Détection Protocole (HTTPS, SSL, Cloudflare & Reverse Proxy)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? 80) == 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    $protocol = $is_https ? 'https://' : 'http://';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // 2. Détection du sous-dossier par rapport au script exécuté
    $script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $script_dir  = str_replace('\\', '/', dirname($script_path));
    
    // Si on est dans un sous-répertoire (modules, config, includes, etc.), on extrait la racine
    $sub_dir = $script_dir;
    foreach (['/modules', '/config', '/includes', '/assets', '/sql'] as $sub) {
        $pos = strpos($sub_dir, $sub);
        if ($pos !== false) {
            $sub_dir = substr($sub_dir, 0, $pos);
            break;
        }
    }

    $sub_dir = ($sub_dir === '/' || $sub_dir === '.') ? '' : rtrim($sub_dir, '/');
    $base_url = rtrim($protocol . $host . $sub_dir, '/');
    define('BASE_URL', $base_url);
}

define('BASE_PATH',     dirname(__DIR__));       // Chemin racine sur le serveur
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('MODULES_PATH',  BASE_PATH . '/modules');
define('CURRENCY_SYM',  'F CFA');

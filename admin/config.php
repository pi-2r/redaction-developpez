<?php
// Configuration centrale (compatible PHP ancienne)

// Base paths (compatibles anciennes versions PHP)
$BASE_DIR = dirname(__DIR__);
if ($BASE_DIR === '' || $BASE_DIR === '/' || !is_dir($BASE_DIR)) {
    // Fallback: deux niveaux au-dessus du fichier courant
    $BASE_DIR = dirname(dirname(__FILE__));
}
$BASE_DIR = rtrim(str_replace('\\', '/', $BASE_DIR), '/');

$ARTICLES_DIR = $BASE_DIR . '/articles';
$TUTORIELS_DIR = $BASE_DIR . '/tutoriels';
$PASSWORD_FILE = $BASE_DIR . '/passwords.txt';
$SESSION_NAME = 'cmsxml_sess';
$APP_NAME = 'CMS XML sans BDD';
$TIMEZONE = 'Europe/Paris';

// Mode debug optionnel: export CMSXML_DEBUG=1 dans l'environnement web (apache/nginx/php-fpm)
$CMSXML_DEBUG = getenv('CMSXML_DEBUG') ? true : false;

// Publication: dépendances externes optionnelles
// - wkhtmltopdf pour PDF
// - Calibre `ebook-convert` pour EPUB/MOBI/AZW3
$BIN_WKHTMLTOPDF = trim((string)@shell_exec('command -v wkhtmltopdf 2>/dev/null')) ?: null;
$BIN_EBOOK_CONVERT = trim((string)@shell_exec('command -v ebook-convert 2>/dev/null')) ?: null;

// Auteur par défaut
$DEFAULT_AUTHOR = 'Pierre Therrode';
$DEFAULT_AUTHOR_HOMEPAGE = 'https://www.developpez.com/user/profil/103867/pi-2r/';

// Sécurité
$CSRF_SECRET = md5(__FILE__ . php_uname());

// Utilitaire simple de log
function log_msg($msg) {
    error_log('[CMSXML] ' . $msg);
}

// Création des dossiers si manquants
foreach (array($ARTICLES_DIR, $TUTORIELS_DIR) as $dir) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true)) {
            error_log('[CMSXML] Echec création dossier base: ' . $dir . ' - perms parent=' . (is_writable(dirname($dir))?'w':'!w'));
        }
    }
}

// Fuseau
date_default_timezone_set($TIMEZONE);

// Forcer l’encodage UTF-8 par défaut
ini_set('default_charset','UTF-8');
if (function_exists('mb_internal_encoding')) { @mb_internal_encoding('UTF-8'); }

// Configuration par défaut pour les articles
$DEFAULT_LICENSE = '2';
$DEFAULT_RUBRIC = 40;
$WEBSITE_ROOT_URL = 'https://thpierre.developpez.com/';

$FORCE_DIR_MODE = 0775; // mode souhaité pour dossiers
$FORCE_FILE_MODE = 0664; // mode souhaité pour fichiers

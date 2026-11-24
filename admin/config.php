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

// Dossier de données privées de l'admin (brouillon d'accueil, anti-bruteforce…).
// Protégé par un .htaccess « deny all » créé automatiquement.
$DATA_DIR = __DIR__ . '/data';

// Fichier des comptes :
//  - CMSXML_PASSWORD_FILE s'il est défini (Docker : hors de la racine web) ;
//  - sinon admin/data/passwords.txt, protégé par le .htaccess « deny all » de data/ ;
//  - ancien emplacement (racine du site) accepté pour ne pas bloquer la connexion,
//    mais il est TÉLÉCHARGEABLE depuis le web : le tableau de bord affiche alors une alerte.
$LEGACY_PASSWORD_FILE = $BASE_DIR . '/passwords.txt';
if (getenv('CMSXML_PASSWORD_FILE')) {
    $PASSWORD_FILE = getenv('CMSXML_PASSWORD_FILE');
} elseif (!is_file($DATA_DIR . '/passwords.txt') && is_file($LEGACY_PASSWORD_FILE)) {
    $PASSWORD_FILE = $LEGACY_PASSWORD_FILE;
} else {
    $PASSWORD_FILE = $DATA_DIR . '/passwords.txt';
}
// Vrai si un fichier de comptes traîne à la racine web (lisible par tous)
$PASSWORD_FILE_EXPOSED = is_file($LEGACY_PASSWORD_FILE) && !getenv('CMSXML_PASSWORD_FILE');

$SESSION_NAME = 'cmsxml_sess';
$APP_NAME = 'CMS XML sans BDD';
$TIMEZONE = 'Europe/Paris';

// URL de l'admin, déduite du dossier courant : le dossier peut être renommé
// (change_admin.sh) ou le projet servi depuis un sous-dossier sans rien casser.
$ADMIN_DIR_NAME = basename(__DIR__);
if (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_NAME'])) {
    $ADMIN_URL = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
} else {
    $ADMIN_URL = '/' . $ADMIN_DIR_NAME;
}
// URL racine du site (celle qui contient /articles et /tutoriels), '' si à la racine.
$SITE_URL = rtrim(str_replace('\\', '/', dirname($ADMIN_URL)), '/');

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
$LOGIN_MAX_ATTEMPTS = 5;      // tentatives échouées autorisées…
$LOGIN_WINDOW = 900;          // …par fenêtre de 15 minutes et par adresse IP

// Utilitaire simple de log
function log_msg($msg) {
    error_log('[CMSXML] ' . $msg);
}

// Écrit un .htaccess de protection s'il est absent ou différent.
function ensure_htaccess($dir, $content) {
    if (!is_dir($dir)) return false;
    $file = rtrim($dir, '/') . '/.htaccess';
    if (is_file($file) && file_get_contents($file) === $content) return true;
    return @file_put_contents($file, $content) !== false;
}
// Bloc « tout refuser » compatible Apache 2.2 et 2.4.
function htaccess_deny_block() {
    return "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
}
// Règles des dossiers de contenu : les sources (.md/.xml), sauvegardes,
// meta.json (qui contient le jeton des articles privés) et .date ne doivent
// pas être téléchargeables. Seuls index.php, les images et les exports restent publics.
function content_htaccess_rules() {
    return "# Généré par le CMS (admin/config.php) - ne pas modifier à la main\n"
        . "<FilesMatch \"(^meta\\.json$|^\\.date$|\\.(md|xml|json|old)$|\\.bak|\\.tmp-|^_build\\.html$)\">\n"
        . htaccess_deny_block()
        . "</FilesMatch>\n";
}

// Création des dossiers si manquants
foreach (array($ARTICLES_DIR, $TUTORIELS_DIR) as $dir) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true)) {
            error_log('[CMSXML] Echec création dossier base: ' . $dir . ' - perms parent=' . (is_writable(dirname($dir))?'w':'!w'));
        }
    }
    ensure_htaccess($dir, content_htaccess_rules());
}
if (!is_dir($DATA_DIR)) { @mkdir($DATA_DIR, 0775, true); }
ensure_htaccess($DATA_DIR, "# Données privées de l'admin\n" . htaccess_deny_block());

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

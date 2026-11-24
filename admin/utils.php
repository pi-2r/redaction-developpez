<?php
// Helpers legacy pour compatibilité PHP ancienne
if (!function_exists('starts_with')) { function starts_with($h,$n){ return $n===''||strpos($h,$n)===0; } }
if (!function_exists('ends_with')) { function ends_with($h,$n){ if($n==='') return true; $l=strlen($n); return substr($h,-$l)===$n; } }
if (!function_exists('legacy_random_bytes')) { function legacy_random_bytes($len){ $b = @openssl_random_pseudo_bytes($len,$strong); if($b===false){ $b=''; for($i=0;$i<$len;$i++){ $b.=chr(mt_rand(0,255)); } } return $b; } }
if (!function_exists('hash_equals')) { function hash_equals($a,$b){ if(strlen($a)!==strlen($b)) return false; $res=0; for($i=0;$i<strlen($a);$i++){ $res |= ord($a[$i]) ^ ord($b[$i]); } return $res===0; } }
// Polyfills pour versions PHP anciennes
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) { return $needle === '' || strpos($haystack, $needle) === 0; }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) { if ($needle==='') return true; $len = strlen($needle); return substr($haystack, -$len) === $needle; }
}
if (!function_exists('random_bytes')) {
    function random_bytes($length) {
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if ($bytes === false) { $bytes=''; for($i=0;$i<$length;$i++){ $bytes .= chr(mt_rand(0,255)); } }
        return $bytes;
    }
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ParsedownDvp.php';
require_once __DIR__ . '/dvp_render.php';

// -------- URLs --------
// URL d'une page de l'admin, quel que soit le nom du dossier (cf. config.php).
function admin_url($path = '') {
    return $GLOBALS['ADMIN_URL'] . '/' . ltrim($path, '/');
}
// URL publique d'un projet.
function project_url($type, $slug, $file = '') {
    return $GLOBALS['SITE_URL'] . '/' . $type . '/' . $slug . '/' . ltrim($file, '/');
}

// -------- Page d'accueil du site --------
function home_draft_path() { return $GLOBALS['DATA_DIR'] . '/home_draft.txt'; }
function home_live_path() { return $GLOBALS['BASE_DIR'] . '/index.php'; }

// -------- Session & Auth --------
function is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
}
function ensure_session() {
    global $SESSION_NAME;
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name($SESSION_NAME);
        // Cookie de session : inaccessible au JavaScript, non envoyé par les
        // requêtes POST d'un site tiers, et Secure en HTTPS.
        @ini_set('session.use_strict_mode', '1');
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params(array(
                'lifetime' => 0,
                'path' => '/',
                'secure' => is_https(),
                'httponly' => true,
                'samesite' => 'Lax',
            ));
        } else {
            session_set_cookie_params(0, '/; samesite=Lax', '', is_https(), true);
        }
        session_start();
    }
}
function is_logged_in() {
    ensure_session();
    return isset($_SESSION['user']);
}
function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . admin_url('login.php'));
        exit;
    }
}
function csrf_token() {
    ensure_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(legacy_random_bytes(16));
    }
    $secret = isset($GLOBALS['CSRF_SECRET']) ? $GLOBALS['CSRF_SECRET'] : 'x';
    return hash_hmac('sha256', $_SESSION['csrf'], (string)$secret);
}
function csrf_check($token) {
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('CSRF invalide');
    }
}
function load_passwords() {
    global $PASSWORD_FILE;
    if (!is_file($PASSWORD_FILE)) return array();
    $lines = file($PASSWORD_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); if (!$lines) $lines = array();
    $out = array();
    foreach ($lines as $line) {
        if (starts_with(trim($line), '#')) continue;
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $out[trim($parts[0])] = trim($parts[1]);
        }
    }
    return $out;
}
function verify_login($user, $pass) {
    $pwds = load_passwords();
    if (!isset($pwds[$user])) return false;
    $hash = $pwds[$user];
    // Seuls les hash bcrypt/argon2 sont acceptés : un mot de passe en clair
    // dans le fichier est refusé (générez un hash avec change_admin.sh ou
    // php -r 'echo password_hash("motdepasse", PASSWORD_BCRYPT), "\n";').
    if ((starts_with($hash, '$2y$') || starts_with($hash, '$2b$') || starts_with($hash, '$argon2')) && function_exists('password_verify')) {
        return password_verify($pass, $hash);
    }
    log_msg('Compte "' . $user . '" ignoré : le mot de passe doit être un hash bcrypt/argon2, pas du texte en clair.');
    return false;
}
// -------- Anti-bruteforce (par adresse IP, stocké dans admin/data) --------
function login_throttle_file() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli';
    return $GLOBALS['DATA_DIR'] . '/login-' . md5($ip) . '.json';
}
function login_throttle_state() {
    $f = login_throttle_file();
    $s = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    if (!is_array($s) || !isset($s['count'], $s['first']) || (time() - $s['first']) > $GLOBALS['LOGIN_WINDOW']) {
        $s = array('count' => 0, 'first' => time());
    }
    return $s;
}
// Nombre de secondes à attendre avant de pouvoir réessayer (0 = autorisé).
function login_blocked_for() {
    $s = login_throttle_state();
    if ($s['count'] < $GLOBALS['LOGIN_MAX_ATTEMPTS']) return 0;
    return max(1, $GLOBALS['LOGIN_WINDOW'] - (time() - $s['first']));
}
function login_register_failure() {
    $s = login_throttle_state();
    $s['count']++;
    @file_put_contents(login_throttle_file(), json_encode($s));
}
function login_reset_failures() {
    @unlink(login_throttle_file());
}
// -------- Slug & Paths --------
function slugify($text) {
    // Remplacement manuel des accents pour éviter les problèmes de locale/iconv
    $replacements = array(
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'AE', 'Ç'=>'C',
        'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I',
        'Ð'=>'D', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O',
        'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'TH', 'ß'=>'ss',
        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'ae', 'ç'=>'c',
        'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i',
        'ð'=>'d', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o',
        'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'th', 'ÿ'=>'y'
    );
    $text = strtr($text, $replacements);

    $text = trim(strtolower($text));
    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    if ($trans !== false) $text = $trans;
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    if ($text === null) $text = '';
    $text = trim($text, '-');
    $text2 = preg_replace('~-+~', '-', $text);
    if ($text2 !== null) $text = $text2;
    return $text ? $text : 'projet';
}
function type_dir($type) {
    global $ARTICLES_DIR, $TUTORIELS_DIR;
    return $type === 'articles' ? $ARTICLES_DIR : $TUTORIELS_DIR;
}
function project_paths($type, $slug) {
    $base = rtrim(type_dir($type), '/') . '/' . $slug;
    $xml = $base . '/' . $slug . '.xml';
    $md = $base . '/' . $slug . '.md';
    if (!is_file($xml) && !is_file($md) && is_dir($base)) {
        $scan = scandir($base); if ($scan) {
            foreach ($scan as $f) {
                if ($f === '.' || $f === '..') continue;
                if (ends_with(strtolower($f), '.xml')) { $xml = $base . '/' . $f; }
                if (ends_with(strtolower($f), '.md')) { $md = $base . '/' . $f; }
            }
        }
    }
    return array(
        'dir' => $base,
        'xml' => $xml,
        'md' => $md,
        'index' => $base . '/index.php',
        'date' => $base . '/.date',
    );
}
function list_projects($type) {
    $dir = type_dir($type);
    if (!is_dir($dir)) return array();
    $out = array();
    $scan = scandir($dir); if (!$scan) return $out;
    foreach ($scan as $name) {
        if ($name === '.' || $name === '..') continue;
        $p = $dir . '/' . $name;
        if (is_dir($p)) $out[] = $name;
    }
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}
// -------- File helpers --------
function write_file_atomic($path, $content) {
    $tmp = $path . '.tmp-' . bin2hex(legacy_random_bytes(6));
    if (file_put_contents($tmp, $content) === false) return false;
    $ok = @rename($tmp, $path);
    if (!$ok) { @unlink($tmp); return false; }
    if (isset($GLOBALS['FORCE_FILE_MODE'])) { @chmod($path, $GLOBALS['FORCE_FILE_MODE']); }
    return true;
}
// -------- XML & XSLT --------
function xml_skeleton($title, $author='') {
    $date = date('Y-m-d');
    $titlePage = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $defaultAuthor = isset($GLOBALS['DEFAULT_AUTHOR']) ? $GLOBALS['DEFAULT_AUTHOR'] : '';
    $author = htmlspecialchars($author ? $author : ($defaultAuthor ? $defaultAuthor : (isset($_SESSION['user'])?$_SESSION['user']:'')), ENT_QUOTES, 'UTF-8');
    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<document>\n  <entete>\n    <titre>\n      <page>{$titlePage}</page>\n      <article>{$titlePage}</article>\n    </titre>\n    <date>{$date}</date>\n    <licauteur>{$author}</licauteur>\n  </entete>\n  <summary>\n    <section id=\"1\">\n      <title>Introduction</title>\n      <paragraph>Commencez ici…</paragraph>\n    </section>\n  </summary>\n</document>\n";
}
function xslt_transform_to_html($xmlPath) {
    $dom = new DOMDocument();
    $dom->load($xmlPath);
    $xsl = new DOMDocument();
    $xsl->load(__DIR__ . '/xsl/default.xsl');
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    $html = $proc->transformToXML($dom);
    return $html ? $html : '<!doctype html><meta charset="utf-8"><p>Erreur de transformation XSL.</p>';
}
function markdown_to_html($mdPath, $slug = '', $fallbackTitle = '') {
    if (!is_file($mdPath)) return '<!doctype html><meta charset="utf-8"><p>Fichier Markdown introuvable</p>';
    // Même rendu que la page publiée, avec un CSS autonome imitant le kit (PDF/ePub)
    return dvp_standalone_page(dvp_render_markdown(file_get_contents($mdPath), $slug, $fallbackTitle));
}
// Date en français (« 23 novembre 2026 ») sans strftime(), obsolète depuis PHP 8.1.
function dvp_date_fr($ts = null) {
    $mois = array(1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre');
    if ($ts === null) $ts = time();
    return date('d', $ts) . ' ' . $mois[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}
// Accès aux exports des projets privés : un .htaccess dans le dossier du projet
// bloque PDF/ZIP/ePub tant que l'article est privé (index.php reste protégé par le jeton).
function project_is_private($type, $slug) {
    $paths = project_paths($type, $slug);
    $metaFile = $paths['dir'] . '/meta.json';
    if (!is_file($metaFile)) return false;
    $meta = json_decode((string)file_get_contents($metaFile), true);
    return is_array($meta) && !empty($meta['is_private']);
}
function apply_project_access_rules($type, $slug) {
    $paths = project_paths($type, $slug);
    $file = $paths['dir'] . '/.htaccess';
    $marker = '# CMS: article prive';
    if (project_is_private($type, $slug)) {
        $rules = $marker . " - exports bloques\n<FilesMatch \"\\.(pdf|zip|epub|mobi|azw|azw3)$\">\n" . htaccess_deny_block() . "</FilesMatch>\n";
        return ensure_htaccess($paths['dir'], $rules);
    }
    if (is_file($file) && strpos((string)file_get_contents($file), $marker) === 0) {
        return @unlink($file);
    }
    return true;
}
// Code PHP de protection inséré en tête de l'index.php d'un projet privé.
function private_guard_php($type, $slug) {
    $paths = project_paths($type, $slug);
    $metaFile = $paths['dir'] . '/meta.json';
    $meta = is_file($metaFile) ? json_decode((string)file_get_contents($metaFile), true) : array();
    if (!is_array($meta) || empty($meta['is_private']) || empty($meta['private_token'])) return '';
    $token = var_export((string)$meta['private_token'], true);
    return "if (!isset(\$_GET['token']) || !is_string(\$_GET['token']) || !hash_equals($token, \$_GET['token'])) {\n"
        . "    include __DIR__ . '/../../index.php';\n"
        . "    exit;\n"
        . "}\n"
        . "header('X-Robots-Tag: noindex, nofollow');\n\n";
}
function generate_index_php($type, $slug) {
    global $BIN_WKHTMLTOPDF, $BIN_EBOOK_CONVERT;
    $paths = project_paths($type, $slug);
    if (is_file($paths['md'])) {
        // Conversion au format du kit developpez (voir dvp_render.php)
        $r = dvp_render_markdown(file_get_contents($paths['md']), $slug, project_title_from_xml($paths['xml']));
        $title = $r['title'];
        $toc = dvp_toc_html($r['toc']);
        $processedHtml = $r['html'];

        $author = isset($GLOBALS['DEFAULT_AUTHOR']) ? $GLOBALS['DEFAULT_AUTHOR'] : (isset($_SESSION['user']) ? $_SESSION['user'] : 'Anonyme');
        $year = date('Y');
        $desc = $r['desc'];

        $rootUrl = isset($GLOBALS['WEBSITE_ROOT_URL']) ? rtrim($GLOBALS['WEBSITE_ROOT_URL'], '/') : 'https://www.developpez.com';
        $url = $rootUrl . "/$type/$slug/";

        $defaultRubric = isset($GLOBALS['DEFAULT_RUBRIC']) ? $GLOBALS['DEFAULT_RUBRIC'] : 40;
        $defaultLicense = isset($GLOBALS['DEFAULT_LICENSE']) ? $GLOBALS['DEFAULT_LICENSE'] : '2';

        // Check metadata for private access
        $isPrivate = project_is_private($type, $slug);

        // Les chaînes injectées dans le code PHP passent par var_export() :
        // un titre contenant $, " ou \ reste du texte (addslashes laissait
        // PHP interpréter « {$…} » dans les guillemets doubles).
        $phpTitle = var_export((string)$title, true);
        $phpDesc = var_export((string)$desc, true);
        $phpAuthor = var_export((string)$author, true);
        $phpUrl = var_export((string)$url, true);

        $php = "<?php\n";
        $php .= private_guard_php($type, $slug);

        $php .= "include \$_SERVER['DOCUMENT_ROOT'].\"/template/fonctions.php\";\n\n";
        $php .= "\$rubrique = " . (int)$defaultRubric . ";\n";
        $php .= "\$meta_description = $phpDesc;\n";
        $php .= "\$titre_page = $phpTitle;\n";
        $php .= "\$Auteur = $phpAuthor;\n";
        $php .= "\$Licence = " . var_export((string)$defaultLicense, true) . ";\n";
        $php .= "\$Annee = \"$year\";\n";
        $php .= <<<'DATEPHP'
// Lecture du fichier .date pour les dates de publication
$_moisFr = array(1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre');
$_aujourdhui = date('d') . ' ' . $_moisFr[(int)date('n')] . ' ' . date('Y');
$_dateFile = __DIR__ . '/.date';
if (is_file($_dateFile)) {
    $_dateLines = file($_dateFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $_dates = array();
    foreach ($_dateLines as $_dl) {
        $_parts = explode('=', $_dl, 2);
        if (count($_parts) === 2) { $_dates[trim($_parts[0])] = trim($_parts[1]); }
    }
    $dateBrute['date'] = !empty($_dates['date']) ? $_dates['date'] : $_aujourdhui;
    $dateBrute['miseajour'] = !empty($_dates['miseajour']) ? $_dates['miseajour'] : ' ';
} else {
    $dateBrute['date'] = $_aujourdhui;
    $dateBrute['miseajour'] = ' ';
}

DATEPHP;
        $php .= "\n\$topicType = 'Whitepaper';\n";
        $php .= "\$GoogleAnalytics = \"\";\n";
        $php .= "\$gabarit_mobile = true;\n";
        $php .= "\$gabarit_encodage = 'UTF-8';\n";
        $php .= "\$gabarit_jquery = '3.4.1';\n";
        $php .= "\$gabarit_doctype = 'html5';\n";
        $php .= "\$gabarit_extrahead = \"\\t<!--[if IE 9]><link rel=\\\"stylesheet\\\" type=\\\"text/css\\\" href=\\\"/template/kit/ie9.css\\\" /><![endif]-->\\n\"\n";
        $php .= "    . \"\\t<!--[if IE 8]><link rel=\\\"stylesheet\\\" type=\\\"text/css\\\" href=\\\"/template/kit/ie8.css\\\" /><![endif]-->\\n\"\n";
        $php .= "    . \"\\t<!--[if lt IE 9]><link rel=\\\"stylesheet\\\" type=\\\"text/css\\\" href=\\\"/template/kit/ie.css\\\" /><![endif]-->\\n\"\n";
        $php .= "    . \"\\t<!--[if IE 7]><link rel=\\\"stylesheet\\\" type=\\\"text/css\\\" href=\\\"/template/kit/ie7.css\\\" /><![endif]-->\\n\"\n";
        $php .= "    . \"\\t<!--[if lte IE 6]><link rel=\\\"stylesheet\\\" type=\\\"text/css\\\" href=\\\"/template/kit/ie6.css\\\" /><![endif]-->\\n\"\n";
        $php .= "    . \"\\t<!--[if lt IE 9]><script type=\\\"text/javascript\\\" src=\\\"/template/kit/html5_ie.js\\\"></script><![endif]-->\\n\";\n\n";

        $php .= <<<'EOD'
$gabarit_extrahead .= '
<script type="module">
import mermaid from "https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs";
mermaid.initialize({ startOnLoad: false });
document.addEventListener("DOMContentLoaded", function() {
    const mermaids = document.querySelectorAll("pre > code.language-mermaid");
    mermaids.forEach(el => {
        const pre = el.parentElement;
        const div = document.createElement("div");
        div.className = "mermaid";
        div.textContent = el.textContent;
        div.style.textAlign = "center";
        pre.replaceWith(div);
    });
    mermaid.run();
});
</script>
';
EOD;
        $php .= "\n\$gabarit_extrahead .= " . var_export('<style>' . dvp_extra_css() . '</style>', true) . ";\n";
        $php .= "\$gabarit_js = array('/template/kit/developpez-kit-generation.js', '/template/kit/lightbox.js', '/template/kit/fonctions-kit.js');\n";
        $php .= "\$gabarit_css = array('/template/kit/developpez-kit-generation.css', '/template/kit/code.css', '/template/kit/lightbox.css');\n";

        $php .= "\$open_graph_meta = array('type'=>'article', 'title'=>$phpTitle, 'image'=>'https://www.developpez.com/template/images/logo.png', 'url'=>$phpUrl, 'locale'=>'fr_FR', 'site_name'=>'Developpez.com');\n";
        $php .= "\$twitter_meta = array('card'=>'summary', 'url'=>$phpUrl, 'description'=>$phpDesc, 'title'=>$phpTitle, 'image'=>'https://www.developpez.com/template/images/logo.png');\n\n";

        $php .= <<<'EOD'
function insertion_tag( $MaDate, $tag ) { if ( empty($MaDate) ) { return; } list($annee, $mois, $jour) = explode('-', $MaDate); if ( empty($annee) or empty($mois) or empty($jour) ) { return; } $time_balise = mktime(0,0,0,$mois,$jour,$annee); $time_jour = time(); $delai = 15 * 24 * 60 * 60; if ( ($time_jour - $time_balise) < $delai ) { if ( $tag == "new" ) { return '<script type="text/javascript">document.write(" <span class=\'sommaireQuestionNouveau\'>[Nouveau]</span>");</script>'; } elseif ( $tag == "maj" ) { return '<script type="text/javascript">document.write(" <span class=\'sommaireQuestionAJour\'>[Mise &agrave; jour]</span>");</script>'; } } return; }
function CutName($vTxt, $Car) { while(strlen($vTxt) > $Car) return substr($vTxt, 0, $Car) . "..."; return $vTxt; }
function unichr($u) { if ( intval($u) >= 127 && intval($u) <= 159 ) { return chr($u - 127 + 32); } return html_entity_decode('&#' . intval($u) . ';', ENT_QUOTES, 'UTF-8'); }
function new_crypt($phrase) { $key = "le_club_des_developeur"; $output = ""; for ($c = 0 ; $c < strlen($phrase) ; $c++) { $output = $output.unichr(ord($phrase[$c]) + ord($key[$c % strlen($key)])); } return $output; }
function VerifierAdresseMail($email) { $Syntaxe='#^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$#'; if ( preg_match($Syntaxe,$email) ) { return true; } else { return false; } }
function RetournerMailOuAutre($message) { if ( VerifierAdresseMail($message) ) { return 'contact'; } else { return $message; } }
function encrypt_email($email, $texte, $titre="") { if (preg_match("/^mailto:/i", $email)) { $email_parts = explode('mailto:', $email); $email = $email_parts[1]; } $email_parts = explode('@', $email); $javascript = "<script type=\"text/javascript\">\n"; $javascript .= "<!--\n"; $javascript .= "Ch=new Array(4);Res=new Array(4);\n"; $javascript .= "Ch[0]='le_club_des_developeur';"; $javascript .= "Ch[1]='" . new_crypt("mailto") . "';\n"; $javascript .= "Ch[2]='" . new_crypt($email_parts[0] . "@") . "';"; $javascript .= "Ch[3]='" . new_crypt($email_parts[1]) . "';\n"; $javascript .= "for(y=1;y<4;y++){Res[y]=\"\";for(x=0;x<Ch[y].length;x++)\n"; $javascript .= "{"; $javascript .= "cryptChar = Ch[y].charCodeAt(x);\n"; $javascript .= "if (cryptChar >= 32 && cryptChar <= 64 )\n"; $javascript .= "cryptChar = cryptChar-32+127;\n"; $javascript .= "Res[y]+=String.fromCharCode(cryptChar-Ch[0].charCodeAt(x%Ch[0].length));}"; $javascript .= "}\n"; $javascript .= "var st = '<a class=\"'"; if ( $titre != '' ) { $javascript .= " + ' tooltip blue-tooltip' "; } $javascript .= " + ' lienArticle mailto\"' "; $javascript .= " + 'href=\"' + Res[1] + ':' + Res[2] + Res[3] + '\"' "; $javascript .= " + '>'"; $javascript .= " + " . "'" . RetournerMailOuAutre( addslashes($texte) ) . "'"; if ( $titre != '' ) { $javascript .= " + '<span>" . RetournerMailOuAutre($titre) . "</span>' "; } $javascript .= " + '<\/a>'"; $javascript .= ";\n"; $javascript .= "document.write(st);\n"; $javascript .= "//-->\n"; $javascript .= "</script>"; return $javascript; }
function web_to_mobile($video) { return preg_replace('/http:\/\/www\.youtube\.com/', 'http://m.youtube.com', $video); }
EOD;
        $php .= "\n\ninclude(\$_SERVER['DOCUMENT_ROOT'] . '/template/entete.php');\n?>\n";
        
        if ($isPrivate) {
            $php .= '<div style="background:#fff3cd;color:#856404;padding:15px;text-align:center;border-bottom:1px solid #ffeeba;font-family:sans-serif;">⚠️ <strong>Article privé</strong> : Cet article est en cours de rédaction.</div>';
        }

        $php .= "<article class=\"ArticleComplet\" itemscope itemtype=\"https://schema.org/Article\">\n";
        $php .= "    <meta itemprop=\"genre\" content=\"informatique\"/>\n";
        $php .= "    <meta itemprop=\"inLanguage\" content=\"fr-FR\"/>\n";
        $php .= "    <h1 class=\"titre-article\" itemprop=\"name\">" . htmlspecialchars($title) . "</h1><section>\n\n";
        $php .= "        " . $toc . "\n\n";
        $php .= "        <div class=\"contenu\">\n\n";
        $php .= "            <section itemprop=\"about\" class=\"SectionSynopsis\">\n";
        $php .= "                <p>" . htmlspecialchars($desc) . "</p>\n";
        $php .= "                <p class=\"TextNbrVues\">Article lu <span id=\"NbrVues\">&nbsp;</span> fois.</p></section><div class=\"InfoAuthArtBook\">\n";
        $authorHomepage = isset($GLOBALS['DEFAULT_AUTHOR_HOMEPAGE']) ? $GLOBALS['DEFAULT_AUTHOR_HOMEPAGE'] : '#';

        $php .= "                <section class=\"SectionAuteurs\">\n";
        $php .= "                    <h2 class=\"TitreHeader\">L'auteur</h2>\n";
        $php .= "                    <p class=\"auteur\" itemprop=\"name\" itemscope itemtype=\"http://schema.org/Person\"><a itemprop=\"url\" class=\"auteur\" href=\" \"><span itemprop=\"author\">" . htmlspecialchars($author) . "</span></a><a class=\"homepage\" href=\"" . htmlspecialchars($authorHomepage) . "\"><img src=\"https://www.developpez.net/forums/images/ranks/pro.png\" title=\"Profil Pro\" alt=\"Profil Pro\" style=\"vertical-align:middle;\"/></a><a class=\"homepage\" href=\"https://thpierre.developpez.com\"><img src=\"/template/kit/kitbm-site-personnel.png\" title=\"Espace de " . htmlspecialchars($author) . "\" alt=\"Site personnel\" style=\"vertical-align:middle;\"/></a></p>\n";
        $php .= "                </section>\n";
        $php .= "                <section class=\"SectionInformationArticle\">\n";
        $php .= "                    <h2 class=\"TitreHeader\">L'article</h2>\n";
        $php .= "                    <p class=\"InfoArticle\">Publi&eacute;&nbsp;le&nbsp;<span itemprop=\"datePublished\"><?php echo htmlspecialchars(\$dateBrute['date']); ?></span>";
        $php .= "<?php if (trim(\$dateBrute['miseajour']) !== '') { echo ' - Mis &agrave; jour le <span itemprop=\"dateModified\">' . htmlspecialchars(\$dateBrute['miseajour']) . '</span>'; } ?>";
        $php .= "</p>\n";

        if ($BIN_WKHTMLTOPDF || class_exists('ZipArchive')) {
            $php .= "<p>";
            if ($BIN_WKHTMLTOPDF) {
                $php .= "<span class=\"lientelechargementpdf\"><a class=\"lientelechargement\" href=\"{$url}{$slug}.pdf\" title=\"Télécharger la version PDF de cet article.\">Version PDF</a></span> ";
            }
            if (class_exists('ZipArchive')) {
                $php .= "<span class=\"lientelechargementzip\"><a class=\"lientelechargement\" href=\"{$url}{$slug}.zip\" title=\"Télécharger la version HTML de cet article.\">Version hors-ligne</a></span>";
            }
            $php .= "</p>\n";
        }
        if ($BIN_EBOOK_CONVERT) {
            $php .= "<p><span class=\"lientelechargementebook\"><a class=\"lientelechargement\" href=\"{$url}{$slug}.epub\" title=\"Télécharger la version ePub de l'article compatible avec la plupart des liseuses non Kindle (Amazon).\">ePub</a></span>, <span class=\"lientelechargementebook\"><a class=\"lientelechargement\" href=\"{$url}{$slug}.azw\" title=\"Télécharger la version Azw spécifique aux liseuses Kindle (Amazon).\">Azw</a></span> et <span class=\"lientelechargementebook\"><a class=\"lientelechargement\" href=\"{$url}{$slug}.mobi\" title=\"Télécharger la version Mobi de l'article compatible avec les liseuses Amazon.\">Mobi</a></span></p>\n";
        }

        $php .= "                </section>\n";
        $php .= "                <section class=\"SectionBookmarks\">\n";
        $php .= "                    <h2 class=\"TitreHeader\">Liens&nbsp;sociaux</h2>\n";
        $php .= "                    <div class=\"InfoBookmarks\"><?php echo DoBookMarks(" . $phpTitle . ", " . $phpUrl . "); ?></div>\n";
        $php .= "                </section></div><section itemprop=\"articleBody\" class=\"articleBody\">\n\n";
        $php .= $processedHtml . "\n\n";
        $php .= "            </section><section class=\"articleBody\"><p>Vous avez aimé ce tutoriel ? Alors partagez-le en cliquant sur les boutons suivants : <?php echo DoBookMarks(" . $phpTitle . ", " . $phpUrl . "); ?></p></section></div>\n\n";
        $php .= "    </section>\n";
        $php .= "</article>\n\n";
        $php .= "<?php\ninclude(\$_SERVER[\"DOCUMENT_ROOT\"].\"/template/pied.php\");\n?>\n";

        return $php;
    }
    $relXml = var_export('/' . basename($paths['xml']), true);
    // Chemin de la feuille XSL relatif au nom réel du dossier admin (renommable)
    $relXsl = var_export('/../../' . $GLOBALS['ADMIN_DIR_NAME'] . '/xsl/default.xsl', true);
    return "<?php\n" . private_guard_php($type, $slug) . "header('Content-Type: text/html; charset=utf-8');\n\$xml = __DIR__ . {$relXml};\n\$xsl = __DIR__ . {$relXsl};\nif (!is_file(\$xml) || !is_file(\$xsl)) { http_response_code(500); echo 'Ressource manquante'; exit; }\n\$dom = new DOMDocument();\n\$dom->load(\$xml);\n\$x = new DOMDocument();\n\$x->load(\$xsl);\n\$p = new XSLTProcessor();\n\$p->importStylesheet(\$x);\necho \$p->transformToXML(\$dom);\n";
}
// -------- Publication --------
function publish_all_formats($type, $slug) {
    // Pas de display_errors ici : un simple avertissement affiché empêchait la
    // redirection de publish.php (« headers already sent »). Les erreurs vont au log.
    global $BIN_WKHTMLTOPDF, $BIN_EBOOK_CONVERT;
    $paths = project_paths($type, $slug);
    $dir = $paths['dir'];

    $sourceFile = '';
    if (is_file($paths['md'])) {
        $html = markdown_to_html($paths['md'], $slug, project_title_from_xml($paths['xml']));
        $sourceFile = $paths['md'];
    } elseif (is_file($paths['xml'])) {
        $html = xslt_transform_to_html($paths['xml']);
        $sourceFile = $paths['xml'];
    } else {
        return array('error' => 'Aucun fichier source (XML ou MD) trouvé');
    }

    $htmlPath = $dir . '/_build.html';
    file_put_contents($htmlPath, $html);
    write_file_atomic($paths['index'], generate_index_php($type, $slug));
    if (isset($GLOBALS['FORCE_FILE_MODE'])) { @chmod($paths['index'], $GLOBALS['FORCE_FILE_MODE']); }
    apply_project_access_rules($type, $slug);

    // Gestion du fichier .date
    $dateFile = $paths['date'];
    $now = dvp_date_fr();
    if (is_file($dateFile)) {
        // Article déjà publié : conserver la date originale, mettre à jour miseajour
        $dateContent = file_get_contents($dateFile);
        $origDate = '';
        if (preg_match('/^date=(.*)$/m', $dateContent, $m)) {
            $origDate = trim($m[1]);
        }
        if ($origDate === '') { $origDate = $now; }
        $newDateContent = "date=$origDate\nmiseajour=$now\n";
    } else {
        // Première publication
        $newDateContent = "date=$now\nmiseajour=\n";
    }
    write_file_atomic($dateFile, $newDateContent);
    if (isset($GLOBALS['FORCE_FILE_MODE'])) { @chmod($dateFile, $GLOBALS['FORCE_FILE_MODE']); }

    $outputs = array();
    if ($BIN_WKHTMLTOPDF) {
        $pdf = $dir . '/' . $slug . '.pdf';
        $cmd = escapeshellcmd($BIN_WKHTMLTOPDF) . ' --encoding utf-8 ' . escapeshellarg($htmlPath) . ' ' . escapeshellarg($pdf) . ' 2>&1';
        $outputs['pdf'] = shell_exec($cmd);
    } else { $outputs['pdf'] = 'wkhtmltopdf non trouvé'; }
    if ($BIN_EBOOK_CONVERT) {
        foreach (array('epub','mobi','azw') as $fmt) {
            $target = $dir . '/' . $slug . '.' . $fmt;
            $cmd = escapeshellcmd($BIN_EBOOK_CONVERT) . ' ' . escapeshellarg($htmlPath) . ' ' . escapeshellarg($target) . ' 2>&1';
            $outputs[$fmt] = shell_exec($cmd);
        }
    } else {
        $outputs['epub'] = 'ebook-convert non trouvé';
        $outputs['mobi'] = 'ebook-convert non trouvé';
        $outputs['azw'] = 'ebook-convert non trouvé';
    }
    if (class_exists('ZipArchive')) {
        $zipFile = $dir . '/' . $slug . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            if (is_file($sourceFile)) $zip->addFile($sourceFile, basename($sourceFile));
            foreach (array('pdf','epub','mobi','azw') as $fmt) {
                $fp = $dir . '/' . $slug . '.' . $fmt;
                if (is_file($fp)) $zip->addFile($fp, basename($fp));
            }
            $zip->close();
        }
    }
    @unlink($htmlPath);
    return $outputs;
}
function clean_path_param($v) { return preg_replace('~[^a-z0-9\-]~', '', strtolower($v)); }
// -------- Flash --------
function set_flash($msg, $type='info') { ensure_session(); $_SESSION['flash'] = array('m'=>$msg,'t'=>$type,'ts'=>time()); }
function get_flash() { ensure_session(); if (!isset($_SESSION['flash'])) return null; $v = $_SESSION['flash']; unset($_SESSION['flash']); return $v; }
function rrmdir($dir) {
    if (!is_dir($dir)) return false;
    $items = scandir($dir); if (!$items) return false;
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $path = $dir . '/' . $it;
        // Un lien symbolique est supprimé, jamais suivi (sinon on viderait sa cible)
        if (is_dir($path) && !is_link($path)) rrmdir($path); else @unlink($path);
    }
    return @rmdir($dir);
}
function fix_project_permissions($type, $slug) {
    if (!isset($GLOBALS['FORCE_DIR_MODE']) || !isset($GLOBALS['FORCE_FILE_MODE'])) return array('ok'=>false,'error'=>'Modes non définis');
    $paths = project_paths($type, $slug);
    $base = $paths['dir'];
    if (!is_dir($base)) return array('ok'=>false,'error'=>'Projet introuvable');
    $dirMode = $GLOBALS['FORCE_DIR_MODE'];
    $fileMode = $GLOBALS['FORCE_FILE_MODE'];
    $changedDirs = 0; $changedFiles = 0; $errors = 0;
    $stack = array($base);
    while ($stack) {
        $d = array_pop($stack);
        @chmod($d, $dirMode); $changedDirs++;
        $items = @scandir($d);
        if (!$items) { $errors++; continue; }
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            $full = $d . '/' . $it;
            if (is_dir($full)) {
                $stack[] = $full;
            } else {
                @chmod($full, $fileMode); $changedFiles++;
            }
        }
    }
    return array('ok'=>true,'dirs'=>$changedDirs,'files'=>$changedFiles,'errors'=>$errors);
}

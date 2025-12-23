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
require_once __DIR__ . '/Parsedown.php';

// -------- Session & Auth --------
function ensure_session() {
    global $SESSION_NAME;
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name($SESSION_NAME);
        session_start();
    }
}
function is_logged_in() {
    ensure_session();
    return isset($_SESSION['user']);
}
function require_login() {
    if (!is_logged_in()) {
        header('Location: /admin/login.php');
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
    if ($token !== csrf_token()) {
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
    if (starts_with($hash, '$2y$') || starts_with($hash, '$argon2')) {
        if (function_exists('password_verify')) return password_verify($pass, $hash); // bcrypt/argon2
    }
    return hash_equals($hash, $pass);
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
    $author = htmlspecialchars($author ? $author : (isset($_SESSION['user'])?$_SESSION['user']:''), ENT_QUOTES, 'UTF-8');
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
function markdown_to_html($mdPath) {
    if (!is_file($mdPath)) return '<!doctype html><meta charset="utf-8"><p>Fichier Markdown introuvable</p>';
    $text = file_get_contents($mdPath);
    $pd = new Parsedown();
    $body = $pd->text($text);
    return "<!doctype html>\n<meta charset=\"utf-8\">\n<style>body{font-family:system-ui,-apple-system,sans-serif;max-width:800px;margin:0 auto;padding:20px;line-height:1.6;color:#333}img{max-width:100%;height:auto}pre{background:#f4f4f5;padding:15px;overflow-x:auto;border-radius:5px}blockquote{border-left:4px solid #e5e7eb;margin:0;padding-left:15px;color:#6b7280}table{border-collapse:collapse;width:100%}th,td{border:1px solid #e5e7eb;padding:8px;text-align:left}th{background:#f9fafb}</style>\n" . $body;
}
function generate_index_php($type, $slug) {
    $paths = project_paths($type, $slug);
    if (is_file($paths['md'])) {
        $relMd = basename($paths['md']);
        return "<?php\nrequire_once __DIR__ . '/../../admin/Parsedown.php';\n\$md = __DIR__ . '/{$relMd}';\nif (!is_file(\$md)) { http_response_code(500); echo 'Ressource manquante'; exit; }\n\$pd = new Parsedown();\necho \"<!doctype html>\\n<meta charset=\\\"utf-8\\\">\\n<style>body{font-family:system-ui,-apple-system,sans-serif;max-width:800px;margin:0 auto;padding:20px;line-height:1.6;color:#333}img{max-width:100%;height:auto}pre{background:#f4f4f5;padding:15px;overflow-x:auto;border-radius:5px}blockquote{border-left:4px solid #e5e7eb;margin:0;padding-left:15px;color:#6b7280}table{border-collapse:collapse;width:100%}th,td{border:1px solid #e5e7eb;padding:8px;text-align:left}th{background:#f9fafb}</style>\\n\";\necho \$pd->text(file_get_contents(\$md));\n";
    }
    $relXml = basename($paths['xml']);
    return "<?php\nheader('Content-Type: text/html; charset=utf-8');\n\$xml = __DIR__ . '/{$relXml}';\n\$xsl = __DIR__ . '/../../admin/xsl/default.xsl';\nif (!is_file(\$xml) || !is_file(\$xsl)) { http_response_code(500); echo 'Ressource manquante'; exit; }\n\$dom = new DOMDocument();\n\$dom->load(\$xml);\n\$x = new DOMDocument();\n\$x->load(\$xsl);\n\$p = new XSLTProcessor();\n\$p->importStylesheet(\$x);\necho \$p->transformToXML(\$dom);\n";
}
// -------- Publication --------
function publish_all_formats($type, $slug) {
    global $BIN_WKHTMLTOPDF, $BIN_EBOOK_CONVERT;
    $paths = project_paths($type, $slug);
    $dir = $paths['dir'];

    $sourceFile = '';
    if (is_file($paths['md'])) {
        $html = markdown_to_html($paths['md']);
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
        if (is_dir($path)) rrmdir($path); else @unlink($path);
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

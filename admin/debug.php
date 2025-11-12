<?php
require_once __DIR__ . '/utils.php';
require_login();

// Autorisation: nécessite CMSXML_DEBUG=1 ou présence d'un fichier admin/enable_debug.flag
$flagFile = __DIR__ . '/enable_debug.flag';
if (empty($GLOBALS['CMSXML_DEBUG']) && !is_file($flagFile)) {
    http_response_code(403);
    exit('Debug désactivé. Créez enable_debug.flag ou export CMSXML_DEBUG=1');
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
if ($action === 'phpinfo') {
    phpinfo();
    exit;
}

function perms_str($path) {
    clearstatcache(false, $path);
    if (!file_exists($path)) return 'absent';
    $p = fileperms($path);
    $type = ($p & 0x4000) ? 'd' : (($p & 0x8000) ? '-' : '?');
    $map = function($u){ return array(0=>'-',1=>'x',2=>'w',3=>'wx',4=>'r',5=>'rx',6=>'rw',7=>'rwx')[$u]; };
    $owner = $map(($p >> 6) & 7); $group = $map(($p >> 3) & 7); $other = $map($p & 7);
    return $type . str_pad($owner,3,' ',STR_PAD_RIGHT) . str_pad($group,3,' ',STR_PAD_RIGHT) . str_pad($other,3,' ',STR_PAD_RIGHT);
}

function test_write($dir) {
    if (!is_dir($dir)) return 'dossier absent';
    if (!is_writable($dir)) return 'non inscriptible (is_writable=false)';
    $tmp = $dir . '/.__write_test_' . bin2hex(legacy_random_bytes(4));
    $ok = @file_put_contents($tmp, 'test ' . date('c')) !== false;
    if ($ok) { @unlink($tmp); return 'OK'; }
    $err = error_get_last();
    return 'échec: ' . (isset($err['message']) ? $err['message'] : 'inconnu');
}

$sections = array(
    'Serveur' => array(
        'PHP Version' => PHP_VERSION,
        'SAPI' => PHP_SAPI,
        'System' => php_uname(),
        'Memory limit' => ini_get('memory_limit'),
        'Timezone' => date_default_timezone_get(),
        'Include path' => ini_get('include_path'),
    ),
    'Chemins' => array(
        'BASE_DIR' => $BASE_DIR,
        'ARTICLES_DIR' => $ARTICLES_DIR,
        'TUTORIELS_DIR' => $TUTORIELS_DIR,
    ),
    'Droits' => array(
        'articles perms' => perms_str($ARTICLES_DIR),
        'tutoriels perms' => perms_str($TUTORIELS_DIR),
        'articles writable' => is_writable($ARTICLES_DIR)?'oui':'non',
        'tutoriels writable' => is_writable($TUTORIELS_DIR)?'oui':'non',
        'articles test écriture' => test_write($ARTICLES_DIR),
        'tutoriels test écriture' => test_write($TUTORIELS_DIR),
    ),
    'Extensions (chargées)' => array('extensions' => implode(', ', get_loaded_extensions())),
);

// Recherche error_log local
$possibleLogs = array(__DIR__ . '/error_log', dirname(__DIR__) . '/error_log');
$logTail = '';
foreach ($possibleLogs as $lf) {
    if (is_file($lf)) { $logTail = $lf; break; }
}
$logContent = '';
if ($logTail) {
    $lines = @file($logTail, FILE_IGNORE_NEW_LINES);
    if ($lines) {
        $last = array_slice($lines, -50);
        $logContent = implode("\n", $last);
    }
}

?><!doctype html>
<meta charset="utf-8">
<title>Debug CMSXML</title>
<style>body{font-family:system-ui; background:#0b1220; color:#e5e7eb; margin:0; padding:20px}h1{margin-top:0}table{border-collapse:collapse;width:100%;margin:0 0 30px}td,th{border:1px solid #1f2937;padding:6px 8px;font-size:14px}th{text-align:left;background:#111827}code{font-family:monospace;background:#1f2937;padding:2px 4px;border-radius:4px}a{color:#3b82f6;text-decoration:none}pre{background:#111827;padding:10px;border-radius:8px;overflow:auto;font-size:12px;}</style>
<h1>Page de debug</h1>
<p><a href="debug.php?action=phpinfo">phpinfo()</a> | <a href="index.php">Retour admin</a></p>
<?php foreach ($sections as $title=>$rows): ?>
  <h2><?=htmlspecialchars($title)?></h2>
  <table>
    <tbody>
    <?php foreach ($rows as $k=>$v): ?>
      <tr><th><?=htmlspecialchars($k)?></th><td><code><?=htmlspecialchars((string)$v)?></code></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endforeach; ?>
<h2>Error log (tail)</h2>
<?php if ($logTail): ?><div>Fichier détecté: <code><?=htmlspecialchars($logTail)?></code></div><?php else: ?><div>Aucun fichier error_log trouvé dans admin/ ou racine.</div><?php endif; ?>
<pre><?=htmlspecialchars($logContent)?></pre>

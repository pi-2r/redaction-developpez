<?php
require_once __DIR__ . '/utils.php';
require_login();

if (isset($_POST['md'])) {
    $pd = new Parsedown();
    $html = $pd->text($_POST['md']);
    echo '<!doctype html><meta charset="utf-8"><style>body{font-family:system-ui;background:#fff;color:#111;padding:20px;max-width:800px;margin:0 auto}img{max-width:100%}</style>' . $html;
    exit;
}

$raw = (string)(isset($_POST['xml']) ? $_POST['xml'] : '');
if ($raw === '') { http_response_code(400); echo '<p>Contenu vide</p>'; exit; }
libxml_use_internal_errors(true);
$dom = new DOMDocument();
if (!$dom->loadXML($raw, LIBXML_NOENT | LIBXML_NOCDATA | LIBXML_NONET)) {
    $errs = libxml_get_errors();
    $msg = 'Erreurs XML:<br><ul>'; foreach ($errs as $e){ $msg .= '<li>'.htmlspecialchars(trim($e->message)).'</li>'; } $msg .= '</ul>';
    libxml_clear_errors();
    echo '<!doctype html><meta charset="utf-8"><style>body{font-family:system-ui;background:#fff;color:#111}</style><h1>Erreur</h1><div>'.$msg.'</div>'; exit;
}
$xsl = new DOMDocument();
$xsl->load(__DIR__ . '/xsl/default.xsl');
$proc = new XSLTProcessor();
$proc->importStylesheet($xsl);
$html = $proc->transformToXML($dom) ?: '<p>Erreur XSLT</p>';
header('Content-Type: text/html; charset=utf-8');
echo $html;

<?php
require_once __DIR__ . '/utils.php';
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok'=>false,'error'=>'Méthode invalide'));
    exit;
}

try {
    csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : '');
} catch (Exception $e) {
    echo json_encode(array('ok'=>false,'error'=>'CSRF'));
    exit;
}

$type = ((isset($_POST['type']) ? $_POST['type'] : 'tutoriels') === 'articles') ? 'articles' : 'tutoriels';
$slug = clean_path_param((string)(isset($_POST['slug']) ? $_POST['slug'] : ''));
$xml = (string)(isset($_POST['xml']) ? $_POST['xml'] : '');

if ($slug === '') {
    echo json_encode(array('ok'=>false,'error'=>'Paramètres manquants'));
    exit;
}

$paths = project_paths($type, $slug);

if (isset($_POST['md'])) {
    $md = (string)$_POST['md'];
    $target = $paths['md'];
    if (!$target) $target = $paths['dir'] . '/' . $slug . '.md';

    $backup = $target . '.bak-' . date('Ymd-His');
    if (is_file($target)) @copy($target, $backup);

    if (!write_file_atomic($target, $md)) {
        echo json_encode(array('ok'=>false,'error'=>'Échec d’écriture MD'));
        exit;
    }
    echo json_encode(array('ok'=>true));
    exit;
}

if ($xml === '') {
    echo json_encode(array('ok'=>false,'error'=>'Contenu vide'));
    exit;
}

if (!is_file($paths['xml'])) {
    echo json_encode(array('ok'=>false,'error'=>'Fichier XML introuvable'));
    exit;
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
if (!$dom->loadXML($xml, LIBXML_NOENT | LIBXML_NOCDATA | LIBXML_NONET)) {
    $errs = libxml_get_errors(); libxml_clear_errors();
    echo json_encode(array('ok'=>false,'error'=>'XML invalide (' . count($errs) . ' erreur(s))'));
    exit;
}

$backup = $paths['xml'] . '.bak-' . date('Ymd-His');
@copy($paths['xml'], $backup);

if (!write_file_atomic($paths['xml'], $xml)) {
    echo json_encode(array('ok'=>false,'error'=>'Échec d’écriture'));
    exit;
}

echo json_encode(array('ok'=>true));

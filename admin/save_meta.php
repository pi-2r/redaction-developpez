<?php
require_once __DIR__ . '/utils.php';
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok'=>false,'error'=>'Method Not Allowed'));
    exit;
}

try {
    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : (isset($_POST['csrf']) ? $_POST['csrf'] : '');
    csrf_check($token);
} catch (Exception $e) {
    echo json_encode(array('ok'=>false,'error'=>'CSRF invalide'));
    exit;
}

$type = (isset($_POST['type']) ? $_POST['type'] : 'tutoriels') === 'articles' ? 'articles' : 'tutoriels';
$slug = clean_path_param((string)(isset($_POST['slug']) ? $_POST['slug'] : ''));

if ($slug === '') {
    echo json_encode(array('ok'=>false,'error'=>'Slug manquant'));
    exit;
}

$paths = project_paths($type, $slug);
if (!is_dir($paths['dir'])) {
    echo json_encode(array('ok'=>false,'error'=>'Projet introuvable'));
    exit;
}

$metaFile = $paths['dir'] . '/meta.json';
$meta = array();
if (is_file($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true);
    if (!is_array($meta)) $meta = array();
}

if (isset($_POST['is_private'])) {
    $meta['is_private'] = $_POST['is_private'] === 'true' || $_POST['is_private'] === '1';
    if ($meta['is_private'] && empty($meta['private_token'])) {
        $meta['private_token'] = bin2hex(random_bytes(8)); // 16 chars
    }
}

if (file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT))) {
    if (isset($GLOBALS['FORCE_FILE_MODE'])) { @chmod($metaFile, $GLOBALS['FORCE_FILE_MODE']); }

    // Regenerate index.php to apply protection immediately
    $indexContent = generate_index_php($type, $slug);
    write_file_atomic($paths['index'], $indexContent);
    if (isset($GLOBALS['FORCE_FILE_MODE'])) { @chmod($paths['index'], $GLOBALS['FORCE_FILE_MODE']); }

    echo json_encode(array('ok'=>true, 'meta'=>$meta));
} else {
    echo json_encode(array('ok'=>false,'error'=>'Erreur écriture meta'));
}

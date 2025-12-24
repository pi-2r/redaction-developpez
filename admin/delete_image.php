<?php
require_once __DIR__ . '/utils.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : (isset($_POST['csrf']) ? $_POST['csrf'] : '');
    csrf_check($token);
} catch (Exception $e) {
    http_response_code(400);
    exit('CSRF invalide');
}

$type = (isset($_POST['type']) ? $_POST['type'] : 'tutoriels') === 'articles' ? 'articles' : 'tutoriels';
$slug = clean_path_param((string)(isset($_POST['slug']) ? $_POST['slug'] : ''));
$filename = isset($_POST['filename']) ? $_POST['filename'] : '';

if ($slug === '' || $filename === '') {
    http_response_code(400);
    exit('Paramètres manquants');
}

// Security check on filename to prevent directory traversal
if (preg_match('~[/\x00]~', $filename) || $filename === '.' || $filename === '..') {
    http_response_code(400);
    exit('Nom de fichier invalide');
}

$paths = project_paths($type, $slug);
$target = $paths['dir'] . '/images/' . $filename;

if (!is_file($target)) {
    http_response_code(404);
    exit('Fichier introuvable');
}

if (@unlink($target)) {
    header('Content-Type: application/json');
    echo json_encode(array('ok' => true));
} else {
    http_response_code(500);
    exit('Erreur lors de la suppression');
}


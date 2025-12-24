<?php
require_once __DIR__ . '/utils.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

try {
    // CSRF check via header or POST
    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : (isset($_POST['csrf']) ? $_POST['csrf'] : '');
    csrf_check($token);
} catch (Exception $e) {
    http_response_code(400);
    exit('CSRF invalide');
}

$type = (isset($_POST['type']) ? $_POST['type'] : 'tutoriels') === 'articles' ? 'articles' : 'tutoriels';
$slug = clean_path_param((string)(isset($_POST['slug']) ? $_POST['slug'] : ''));

if ($slug === '') {
    http_response_code(400);
    exit('Slug manquant');
}

$paths = project_paths($type, $slug);
if (!is_dir($paths['dir'])) {
    http_response_code(404);
    exit('Projet introuvable');
}

$imagesDir = $paths['dir'] . '/images';
if (!is_dir($imagesDir)) {
    if (!@mkdir($imagesDir, 0775, true)) {
        http_response_code(500);
        exit('Impossible de créer le dossier images');
    }
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit('Aucun fichier envoyé ou erreur upload');
}

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg');

if (!in_array($ext, $allowed)) {
    http_response_code(400);
    exit('Type de fichier non autorisé (images seulement)');
}

// Sanitize filename
$filename = preg_replace('~[^a-z0-9\-\.]~', '', strtolower($file['name']));
if (!$filename) $filename = 'image-' . time() . '.' . $ext;

$target = $imagesDir . '/' . $filename;

// Avoid overwriting existing files by appending number
$counter = 1;
$originalFilename = pathinfo($filename, PATHINFO_FILENAME);
while (file_exists($target)) {
    $filename = $originalFilename . '-' . $counter . '.' . $ext;
    $target = $imagesDir . '/' . $filename;
    $counter++;
}

if (move_uploaded_file($file['tmp_name'], $target)) {
    if (isset($GLOBALS['FORCE_FILE_MODE'])) { @chmod($target, $GLOBALS['FORCE_FILE_MODE']); }

    // Return relative path for usage in MD/XML
    $relativePath = './images/' . $filename;

    header('Content-Type: application/json');
    echo json_encode(array(
        'location' => $relativePath, // For some editors
        'url' => $relativePath,
        'filename' => $filename
    ));
} else {
    http_response_code(500);
    exit('Erreur lors de la sauvegarde du fichier');
}


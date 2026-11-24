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
// SVG exclu : un SVG peut contenir du JavaScript qui s'exécuterait sur le
// domaine du site (et donc de l'admin). Pour les schémas, utiliser Mermaid ou un PNG.
$allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');

if (!in_array($ext, $allowed, true)) {
    http_response_code(400);
    exit('Type de fichier non autorisé (jpg, png, gif, webp)');
}
// Vérifie que le contenu est bien une image (et pas un fichier renommé)
// et que son type réel correspond à l'extension.
$info = @getimagesize($file['tmp_name']);
$typeByExt = array('jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF,
    'webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : -1);
if ($info === false || empty($info[0]) || empty($info[1]) || $info[2] !== $typeByExt[$ext]) {
    http_response_code(400);
    exit('Le fichier n\'est pas une image valide');
}

// Sanitize filename
// Nom = base nettoyée + extension contrôlée (pas de « .php.png », « .htaccess »…)
$base = preg_replace('~[^a-z0-9\-]+~', '-', strtolower(pathinfo($file['name'], PATHINFO_FILENAME)));
$base = trim((string)$base, '-');
if ($base === '') $base = 'image-' . time();
$filename = $base . '.' . $ext;

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


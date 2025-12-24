<?php
require_once __DIR__ . '/utils.php';
require_login();

$type = (isset($_GET['type']) ? $_GET['type'] : 'tutoriels') === 'articles' ? 'articles' : 'tutoriels';
$slug = clean_path_param((string)(isset($_GET['slug']) ? $_GET['slug'] : ''));

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
$images = array();

if (is_dir($imagesDir)) {
    $files = scandir($imagesDir);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        if (is_file($imagesDir . '/' . $f)) {
            $images[] = array(
                'name' => $f,
                'url' => './images/' . $f,
                'size' => filesize($imagesDir . '/' . $f)
            );
        }
    }
}

header('Content-Type: application/json');
echo json_encode($images);


<?php
require_once __DIR__ . '/utils.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . admin_url());
    exit;
}

try { csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : ''); } catch (Exception $e) { set_flash('CSRF invalide', 'error'); header('Location: ' . admin_url()); exit; }

$type = ((isset($_POST['type']) ? $_POST['type'] : 'tutoriels') === 'articles') ? 'articles' : 'tutoriels';
$slug = clean_path_param((string)(isset($_POST['slug']) ? $_POST['slug'] : ''));
if ($slug === '') { set_flash('Paramètres manquants', 'error'); header('Location: ' . admin_url()); exit; }
$paths = project_paths($type, $slug);
if (!is_file($paths['xml']) && !is_file($paths['md'])) { set_flash('Fichier source (XML ou Markdown) introuvable', 'error'); header('Location: ' . admin_url()); exit; }

$results = publish_all_formats($type, $slug);
$notes = array();
foreach ($results as $fmt => $out) {
    if ($out === '' || stripos($out, 'Error') === false) { $notes[] = strtoupper($fmt).': OK'; }
    else { $notes[] = strtoupper($fmt).': ' . substr($out, 0, 200); }
}
set_flash('Publication terminée. ' . implode(' | ', $notes), 'success');
header('Location: ' . admin_url('edit.php') . '?type=' . urlencode($type) . '&slug=' . urlencode($slug));

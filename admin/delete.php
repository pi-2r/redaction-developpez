<?php
require_once __DIR__ . '/utils.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /admin/'); exit; }
try { csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : ''); } catch (Exception $e) { set_flash('CSRF invalide', 'error'); header('Location: /admin/'); exit; }
$type = ((isset($_POST['type']) ? $_POST['type'] : 'tutoriels') === 'articles') ? 'articles' : 'tutoriels';
$slug = clean_path_param((string)(isset($_POST['slug']) ? $_POST['slug'] : ''));
if ($slug === '') { set_flash('Paramètres manquants', 'error'); header('Location: /admin/'); exit; }
$paths = project_paths($type, $slug);
if (!is_dir($paths['dir'])) { set_flash('Projet introuvable', 'error'); header('Location: /admin/'); exit; }

if (!rrmdir($paths['dir'])) {
    set_flash('Suppression échouée', 'error');
} else {
    set_flash('Projet supprimé: ' . $type . '/' . $slug, 'success');
}
header('Location: /admin/');

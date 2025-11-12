<?php
require_once __DIR__ . '/utils.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/');
    exit;
}

csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : '');
$title = trim((string)(isset($_POST['title']) ? $_POST['title'] : ''));
$type = (isset($_POST['type']) ? $_POST['type'] : 'tutoriels') === 'articles' ? 'articles' : 'tutoriels';

if ($title === '') {
    set_flash('Le titre est obligatoire', 'error');
    header('Location: /admin/');
    exit;
}

$slug = slugify($title);
$paths = project_paths($type, $slug);
$parent = dirname($paths['dir']);

// S'assurer que le dossier parent existe
if (!is_dir($parent)) {
    @mkdir($parent, 0775, true);
}
if (!is_dir($parent)) {
    set_flash("Le dossier parent n'existe pas: " . $parent, 'error');
    header('Location: /admin/');
    exit;
}

if (is_dir($paths['dir'])) {
    set_flash('Un projet avec ce slug existe déjà: ' . $slug, 'error');
    header('Location: /admin/');
    exit;
}

// Tenter la création même si is_writable() est imprécis sur cet hébergeur
if (!@mkdir($paths['dir'], 0775, true)) {
    $err = error_get_last();
    $msg = 'Impossible de créer le dossier du projet';
    if (!empty($GLOBALS['CMSXML_DEBUG'])) {
        $msg .= ' (dir=' . $paths['dir'] . ', parent=' . $parent . ', is_writable(parent)=' . (is_writable($parent)?'oui':'non') . ')';
        if ($err && isset($err['message'])) { $msg .= ' - ' . $err['message']; }
    }
    error_log('[CMSXML] mkdir échec pour ' . $paths['dir'] . ' parent=' . $parent . ' writable=' . (is_writable($parent)?'oui':'non') . ($err && isset($err['message'])? ' err=' . $err['message'] : ''));
    set_flash($msg, 'error');
    header('Location: /admin/');
    exit;
}
if (isset($GLOBALS['FORCE_DIR_MODE'])) { @chmod($paths['dir'], $GLOBALS['FORCE_DIR_MODE']); }

$xml = xml_skeleton($title);
if (!write_file_atomic($paths['xml'], $xml)) {
    set_flash('Impossible d’écrire le fichier XML', 'error');
    header('Location: /admin/');
    exit;
}
if (isset($GLOBALS['FORCE_FILE_MODE'])) { @chmod($paths['xml'], $GLOBALS['FORCE_FILE_MODE']); }

set_flash('Projet créé: ' . $type . '/' . $slug, 'success');
header('Location: /admin/edit.php?type=' . urlencode($type) . '&slug=' . urlencode($slug));

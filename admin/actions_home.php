<?php
require_once __DIR__ . '/utils.php';
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok'=>false, 'error'=>'Méthode invalide'));
    exit;
}

$token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : (isset($_POST['csrf']) ? $_POST['csrf'] : '');
if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
    echo json_encode(array('ok'=>false, 'error'=>'CSRF invalide'));
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
// Le brouillon est stocké en .txt dans admin/data (protégé par .htaccess) :
// auparavant admin/home_draft.php était exécutable directement depuis le web.
$draftPath = home_draft_path();
$livePath = home_live_path();

try {
    if ($action === 'save') {
        $content = isset($_POST['content']) ? (string)$_POST['content'] : '';
        if (!write_file_atomic($draftPath, $content)) {
            throw new Exception('Impossible d\'écrire le fichier brouillon (permissions ?)');
        }
        echo json_encode(array('ok'=>true));
        exit;

    } elseif ($action === 'publish') {
        if (!is_file($draftPath)) {
            throw new Exception('Aucun brouillon à publier');
        }
        $content = file_get_contents($draftPath);

        // Sauvegarde dans admin/data (pas à la racine web, où un .bak serait téléchargeable)
        if (is_file($livePath)) {
            @copy($livePath, $GLOBALS['DATA_DIR'] . '/index.php.bak-' . date('Ymd-His'));
        }

        if (!write_file_atomic($livePath, $content)) {
            throw new Exception('Impossible d\'écrire le fichier public (permissions ?)');
        }
        @unlink($draftPath);

        echo json_encode(array('ok'=>true));
        exit;
    }

    echo json_encode(array('ok'=>false, 'error'=>'Action inconnue'));

} catch (Exception $e) {
    http_response_code(200); // Return 200 so JS can parse the JSON error
    echo json_encode(array('ok'=>false, 'error' => $e->getMessage()));
    exit;
}

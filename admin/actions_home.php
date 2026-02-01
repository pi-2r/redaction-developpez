<?php
require_once __DIR__ . '/utils.php';
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('ok'=>false, 'error'=>'Méthode invalide'));
    exit;
}

try {
    csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : '');
} catch (Exception $e) {
    echo json_encode(array('ok'=>false, 'error'=>'CSRF invalide'));
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$draftPath = __DIR__ . '/home_draft.php';
$livePath = __DIR__ . '/../index.php';
$metaFile = __DIR__ . '/home_meta.json';

try {
    if ($action === 'save') {
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        
        // Save draft using atomic write from utils (handles permissions)
        if (!write_file_atomic($draftPath, $content)) {
            throw new Exception('Impossible d\'écrire le fichier brouillon (permissions ?)');
        }

        // Ensure token exists
        $meta = array();
        if (file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true);
        }
        if (empty($meta['token'])) {
            // Use legacy_random_bytes if available for max compatibility, or random_bytes
            $bytes = function_exists('legacy_random_bytes') ? legacy_random_bytes(16) : random_bytes(16);
            $meta['token'] = bin2hex($bytes);
            if (!write_file_atomic($metaFile, json_encode($meta))) {
                 // Non-fatal, but good to know
            }
        }

        echo json_encode(array('ok'=>true, 'token'=>$meta['token']));
        exit;

    } elseif ($action === 'publish') {
        
        if (!file_exists($draftPath)) {
            throw new Exception('Aucun brouillon à publier');
        }

        $content = file_get_contents($draftPath);
        
        // Backup
        if (file_exists($livePath)) {
            @copy($livePath, $livePath . '.bak-' . date('Ymd-His'));
        }

        if (!write_file_atomic($livePath, $content)) {
            throw new Exception('Impossible d\'écrire le fichier public (permissions ?)');
        }

        echo json_encode(array('ok'=>true));
        exit;
    }

    echo json_encode(array('ok'=>false, 'error'=>'Action inconnue'));

} catch (Exception $e) {
    http_response_code(200); // Return 200 so JS can parse the JSON error
    echo json_encode(array('ok'=>false, 'error' => $e->getMessage()));
    exit;
}

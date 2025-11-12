<?php
require_once __DIR__ . '/config.php';

$dirs = array($ARTICLES_DIR, $TUTORIELS_DIR);
echo '<!doctype html><meta charset="utf-8"><title>Fix perms</title><body style="font-family:system-ui;background:#0b1220;color:#e5e7eb">';
foreach ($dirs as $d) {
    echo '<p><strong>' . htmlspecialchars($d) . '</strong><br>';
    if (!is_dir($d)) { echo 'Statut: absent (cannot test)<br>'; continue; }
    $parent = dirname($d);
    echo 'Parent: ' . htmlspecialchars($parent) . '<br>';
    echo 'Writable parent: ' . (is_writable($parent)?'oui':'non') . '<br>';
    echo 'Writable dir: ' . (is_writable($d)?'oui':'non') . '<br>';
    $chmodOk = @chmod($d, 0775);
    echo 'chmod 0775: ' . ($chmodOk ? 'OK' : 'échec') . '<br>';
    $test = $d . '/.__perm_test_' . substr(md5(uniqid('', true)),0,8);
    $writeOk = @file_put_contents($test, 'test ' . date('c')) !== false;
    if ($writeOk) { @unlink($test); }
    echo 'Test écriture: ' . ($writeOk ? 'OK' : 'échec') . '<br>';
    $err = error_get_last(); if (!$writeOk && $err && isset($err['message'])) { echo 'Dernière erreur: ' . htmlspecialchars($err['message']) . '<br>'; }
    echo '</p><hr>'; }

// Suggestion fallback si non writable
$allWritable = true; foreach ($dirs as $d) { if (!is_writable($d)) { $allWritable = false; break; } }
if (!$allWritable) {
    echo '<p>Fallback possible: modifier config.php pour utiliser des dossiers sous admin (qui semble potentiellement writable) ex: $ARTICLES_DIR = __DIR__ . "/data/articles"; $TUTORIELS_DIR = __DIR__ . "/data/tutoriels" puis créer ces dossiers avec FTP.</p>';
}

echo '</body>';

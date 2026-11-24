<?php
// Édition de la page d'accueil du site (index.php à la racine).
// « Enregistrer » garde un brouillon dans admin/data, « Publier » remplace
// l'index.php public (une sauvegarde est conservée dans admin/data).
require_once __DIR__ . '/utils.php';
require_login();

$draftPath = home_draft_path();
$livePath = home_live_path();
$hasDraft = is_file($draftPath);
$content = $hasDraft ? file_get_contents($draftPath) : (is_file($livePath) ? file_get_contents($livePath) : '');
if ($content === false) $content = '';
?><!doctype html>
<meta charset="utf-8">
<title>Éditer l'accueil - Admin</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#0b1220;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;display:flex;flex-direction:column;height:100vh}
.header{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid #1f2937}
.header a{color:#e5e7eb;text-decoration:none}
.btn{padding:6px 10px;border-radius:8px;border:1px solid #1f2937;background:#111827;color:#e5e7eb;text-decoration:none;font-size:13px;cursor:pointer}
.btn.primary{background:#3b82f6;border-color:#3b82f6;color:#fff}
.btn.warn{background:#b45309;border-color:#b45309;color:#fff}
.small{color:#9ca3af;font-size:12px}
textarea{flex:1;border:0;background:#0b1220;color:#e5e7eb;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13px;padding:12px;line-height:1.5;resize:none}
</style>
<header class="header">
  <div><a href="<?=htmlspecialchars(admin_url())?>">← Retour</a></div>
  <div class="small">
    Accueil du site (index.php) —
    <span id="status"><?=$hasDraft ? 'brouillon non publié chargé' : 'version en ligne chargée'?></span>
  </div>
  <div style="display:flex;gap:8px">
    <a class="btn" href="<?=htmlspecialchars($GLOBALS['SITE_URL'] . '/')?>" target="_blank">Voir le site</a>
    <button class="btn primary" type="button" onclick="act('save')">Enregistrer le brouillon</button>
    <button class="btn warn" type="button" onclick="publishHome()">Publier</button>
  </div>
</header>
<textarea id="content" spellcheck="false"><?=htmlspecialchars($content)?></textarea>
<script>
const statusEl = document.getElementById('status');
function act(action) {
  const fd = new FormData();
  fd.append('csrf', <?=json_encode(csrf_token())?>);
  fd.append('action', action);
  if (action === 'save') fd.append('content', document.getElementById('content').value);
  statusEl.textContent = action === 'save' ? 'Enregistrement…' : 'Publication…';
  return fetch(<?=json_encode(admin_url('actions_home.php'))?>, {method:'POST', body:fd})
    .then(r => r.json())
    .then(j => {
      statusEl.textContent = j.ok ? (action === 'save' ? 'Brouillon enregistré' : 'Publié') : 'Erreur : ' + (j.error || '');
      return j.ok;
    })
    .catch(() => { statusEl.textContent = 'Erreur réseau'; return false; });
}
function publishHome() {
  if (!confirm('Remplacer la page d\'accueil en ligne par ce contenu ?')) return;
  act('save').then(ok => { if (ok) act('publish'); });
}
</script>

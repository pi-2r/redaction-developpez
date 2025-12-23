<?php
require_once __DIR__ . '/utils.php';
require_login();
$type = (isset($_GET['type']) ? $_GET['type'] : 'tutoriels') === 'articles' ? 'articles' : 'tutoriels';
$slug = clean_path_param((string)(isset($_GET['slug']) ? $_GET['slug'] : ''));
if ($slug === '') { set_flash('Projet introuvable', 'error'); header('Location: /admin/'); exit; }
$paths = project_paths($type, $slug);

$isMd = is_file($paths['md']);
$isXml = is_file($paths['xml']);

// Si aucun fichier, on suppose qu'on veut créer du Markdown car c'est la nouvelle préférence
if (!$isMd && !$isXml) {
    $mode = 'md';
    $content = "# $slug\n\nCommencez à écrire...";
} else {
    // Si MD existe, on l'utilise. Sinon XML.
    $mode = $isMd ? 'md' : 'xml';
    $content = $isMd ? file_get_contents($paths['md']) : file_get_contents($paths['xml']);
}
if ($content === false) $content = '';

$flash = get_flash();
?><!doctype html>
<meta charset="utf-8">
<title>Éditer - <?=$slug?></title>
<style>
:root{--bg:#0b1220;--panel:#0f172a;--muted:#9ca3af;--primary:#3b82f6}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
.header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #1f2937}
.header a{color:#e5e7eb;text-decoration:none}
.container{display:grid;grid-template-columns:1fr 1fr;gap:0;height:calc(100vh - 54px)}
.panel{background:var(--panel);height:100%;display:flex;flex-direction:column}
.toolbar{padding:10px;border-bottom:1px solid #1f2937;display:flex;gap:8px;flex-wrap:wrap}
.btn{padding:6px 10px;border-radius:8px;border:1px solid #1f2937;background:#111827;color:#e5e7eb;text-decoration:none;font-size:13px;cursor:pointer}
.btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}
textarea{flex:1;border:0;background:#0b1220;color:#e5e7eb;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13px;padding:12px;line-height:1.5;resize:none}
iframe{flex:1;border:0;background:#ffffff}
.flash{margin:10px;padding:10px;border-radius:8px;background:#052e1a;color:#86efac}
.small{color:#9ca3af;font-size:12px}
</style>
<header class="header">
  <div><a href="/admin/">← Retour</a></div>
  <div><?=$type?> / <strong><?=$slug?></strong> <span class="small">(<?=$mode?>)</span></div>
  <div>
    <form style="display:inline" method="post" action="/admin/publish.php">
      <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
      <input type="hidden" name="type" value="<?=htmlspecialchars($type)?>">
      <input type="hidden" name="slug" value="<?=htmlspecialchars($slug)?>">
      <button class="btn" type="submit">Publier</button>
    </form>
  </div>
</header>
<div class="container">
  <div class="panel">
    <div class="toolbar">
      <?php if ($mode === 'xml'): ?>
      <button class="btn" type="button" onclick="wrapTag('paragraph')">Paragraph</button>
      <button class="btn" type="button" onclick="insertSection()">+ Section</button>
      <?php else: ?>
      <button class="btn" type="button" onclick="wrapMd('**','**')">Gras</button>
      <button class="btn" type="button" onclick="wrapMd('*','*')">Italique</button>
      <button class="btn" type="button" onclick="wrapMd('[','](url)')">Lien</button>
      <button class="btn" type="button" onclick="wrapMd('\n## ','\n')">H2</button>
      <?php endif; ?>
      <button class="btn primary" type="button" onclick="saveContent()">Enregistrer</button>
      <span class="small" id="status"></span>
    </div>
    <?php if ($flash): ?><div class="flash"><?=htmlspecialchars($flash['m'])?></div><?php endif; ?>
    <textarea id="editor" spellcheck="false"><?=htmlspecialchars($content)?></textarea>
  </div>
  <div class="panel">
    <div class="toolbar">
      <span class="small">Aperçu</span>
      <button class="btn" type="button" onclick="refreshPreview()">Rafraîchir</button>
      <a class="btn" href="/<?=$type?>/<?=$slug?>/index.php" target="_blank">Ouvrir la page</a>
    </div>
    <iframe id="preview"></iframe>
  </div>
</div>
<script>
const ta = document.getElementById('editor');
const pv = document.getElementById('preview');
const statusEl = document.getElementById('status');
const mode = '<?=$mode?>';
let saveTimer;

ta.addEventListener('input', ()=>{
  clearTimeout(saveTimer);
  saveTimer = setTimeout(refreshPreview, 500);
});

function refreshPreview(){
  const body = new FormData();
  body.append(mode, ta.value);
  fetch('/admin/preview.php', {method:'POST', body}).then(r=>r.text()).then(html=>{
    const doc = pv.contentDocument || pv.contentWindow.document;
    doc.open(); doc.write(html); doc.close();
  }).catch(()=>{});
}

function wrapTag(tag){
  const s = ta.selectionStart, e = ta.selectionEnd;
  const before = ta.value.slice(0,s); const sel = ta.value.slice(s,e); const after = ta.value.slice(e);
  const wrapped = `<${tag}>${sel||'Texte...'}</${tag}>`;
  ta.value = before + wrapped + after;
  ta.focus(); ta.selectionStart = s; ta.selectionEnd = s + wrapped.length;
  refreshPreview();
}
function insertSection(){
  const tpl = `\n  <section id="X">\n    <title>Titre</title>\n    <paragraph>Texte...</paragraph>\n  </section>\n`;
  const s = ta.selectionStart; const before = ta.value.slice(0,s); const after = ta.value.slice(s);
  ta.value = before + tpl + after; ta.focus(); ta.selectionStart = s; ta.selectionEnd = s + tpl.length; refreshPreview();
}

function wrapMd(start, end){
  const s = ta.selectionStart, e = ta.selectionEnd;
  const before = ta.value.slice(0,s); const sel = ta.value.slice(s,e); const after = ta.value.slice(e);
  ta.value = before + start + sel + end + after;
  ta.focus(); ta.selectionStart = s + start.length; ta.selectionEnd = s + start.length + sel.length;
  refreshPreview();
}

function saveContent(){
  statusEl.textContent = 'Enregistrement...';
  const body = new FormData();
  body.append('csrf', '<?=htmlspecialchars(csrf_token())?>');
  body.append('type', '<?=htmlspecialchars($type)?>');
  body.append('slug', '<?=htmlspecialchars($slug)?>');
  body.append(mode, ta.value);
  fetch('/admin/save.php', {method:'POST', body}).then(r=>r.json()).then(j=>{
    statusEl.textContent = j.ok ? 'Sauvegardé' : 'Erreur: ' + (j.error||'');
  }).catch(()=>{statusEl.textContent='Erreur réseau';});
}

refreshPreview();
</script>


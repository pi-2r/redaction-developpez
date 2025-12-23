<?php
require_once __DIR__ . '/utils.php';
require_login();
$type = (isset($_GET['type']) ? $_GET['type'] : 'tutoriels') === 'articles' ? 'articles' : 'tutoriels';
$slug = clean_path_param((string)(isset($_GET['slug']) ? $_GET['slug'] : ''));
if ($slug === '') { set_flash('Projet introuvable', 'error'); header('Location: /admin/'); exit; }
$paths = project_paths($type, $slug);

$isMd = is_file($paths['md']);
$isXml = is_file($paths['xml']);

$mode = isset($_GET['mode']) ? $_GET['mode'] : '';
if ($mode !== 'xml' && $mode !== 'md') {
    // Auto-detect
    if ($isMd) $mode = 'md';
    elseif ($isXml) $mode = 'xml';
    else $mode = 'md';
}

$content = '';
if ($mode === 'md') {
    if ($isMd) {
        $content = file_get_contents($paths['md']);
    } else {
        $content = "# $slug\n\nCommencez à écrire...";
    }
} else {
    $content = file_get_contents($paths['xml']);
}
if ($content === false) $content = '';

$flash = get_flash();
?><!doctype html>
<meta charset="utf-8">
<title>Éditer - <?=$slug?></title>
<link rel="stylesheet" href="https://unpkg.com/easymde/dist/easymde.min.css">
<script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
<style>
:root{--bg:#0b1220;--panel:#0f172a;--muted:#9ca3af;--primary:#3b82f6}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
.header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #1f2937}
.header a{color:#e5e7eb;text-decoration:none}
.container{display:flex;height:calc(100vh - 54px);overflow:hidden}
.panel{background:var(--panel);height:100%;display:flex;flex-direction:column;position:relative;min-width:200px}
.resizer{width:6px;background:#0b1220;cursor:col-resize;flex-shrink:0;border-left:1px solid #1f2937;border-right:1px solid #1f2937;transition:background 0.2s}
.resizer:hover,.resizer.active{background:#3b82f6}
.toolbar{padding:10px;border-bottom:1px solid #1f2937;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.btn{padding:6px 10px;border-radius:8px;border:1px solid #1f2937;background:#111827;color:#e5e7eb;text-decoration:none;font-size:13px;cursor:pointer}
.btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}
textarea{flex:1;border:0;background:#0b1220;color:#e5e7eb;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13px;padding:12px;line-height:1.5;resize:none}
iframe{flex:1;border:0;background:#ffffff}
.flash{margin:10px;padding:10px;border-radius:8px;background:#052e1a;color:#86efac}
.small{color:#9ca3af;font-size:12px}
/* EasyMDE overrides */
.EasyMDEContainer{background:#fff;color:#333;flex:1;display:flex;flex-direction:column}
.CodeMirror{flex:1;border:0;border-radius:0}
.editor-toolbar{border-color:#ddd;border-radius:0}
.editor-statusbar{display:none}
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
<div class="container" id="container">
  <div class="panel" id="leftPanel" style="width:50%">
    <div class="toolbar">
      <?php if ($mode === 'xml'): ?>
      <button class="btn" type="button" onclick="wrapTag('paragraph')">Paragraph</button>
      <button class="btn" type="button" onclick="insertSection()">+ Section</button>
      <a class="btn" href="?type=<?=$type?>&slug=<?=$slug?>&mode=md">Passer au Markdown</a>
      <?php else: ?>
      <!-- EasyMDE has its own toolbar -->
      <?php endif; ?>
      <button class="btn primary" type="button" onclick="saveContent()">Enregistrer</button>
      <span class="small" id="status"></span>
    </div>
    <?php if ($flash): ?><div class="flash"><?=htmlspecialchars($flash['m'])?></div><?php endif; ?>
    <textarea id="editor" spellcheck="false"><?=htmlspecialchars($content)?></textarea>
  </div>
  <div class="resizer" id="resizer"></div>
  <div class="panel" style="flex:1">
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
let easyMDE = null;
let saveTimer;

if (mode === 'md') {
    easyMDE = new EasyMDE({
        element: ta,
        spellChecker: false,
        autosave: { enabled: false },
        toolbar: ["bold", "italic", "heading", "|", "quote", "unordered-list", "ordered-list", "|", "link", "image", "|", "guide"],
        status: false
    });
    easyMDE.codemirror.on("change", () => {
        ta.value = easyMDE.value();
        triggerPreview();
    });
} else {
    ta.addEventListener('input', triggerPreview);
}

function triggerPreview(){
    clearTimeout(saveTimer);
    saveTimer = setTimeout(refreshPreview, 500);
}

function refreshPreview(){
  const val = easyMDE ? easyMDE.value() : ta.value;
  const body = new FormData();
  body.append(mode, val);
  fetch('/admin/preview.php', {method:'POST', body}).then(r=>r.text()).then(html=>{
    const doc = pv.contentDocument || pv.contentWindow.document;
    doc.open(); doc.write(html); doc.close();
  }).catch(()=>{});
}

// XML helpers
function wrapTag(tag){
  if(mode==='md') return;
  const s = ta.selectionStart, e = ta.selectionEnd;
  const before = ta.value.slice(0,s); const sel = ta.value.slice(s,e); const after = ta.value.slice(e);
  const wrapped = `<${tag}>${sel||'Texte...'}</${tag}>`;
  ta.value = before + wrapped + after;
  ta.focus(); ta.selectionStart = s; ta.selectionEnd = s + wrapped.length;
  refreshPreview();
}
function insertSection(){
  if(mode==='md') return;
  const tpl = `\n  <section id="X">\n    <title>Titre</title>\n    <paragraph>Texte...</paragraph>\n  </section>\n`;
  const s = ta.selectionStart; const before = ta.value.slice(0,s); const after = ta.value.slice(s);
  ta.value = before + tpl + after; ta.focus(); ta.selectionStart = s; ta.selectionEnd = s + tpl.length; refreshPreview();
}

function saveContent(){
  statusEl.textContent = 'Enregistrement...';
  const val = easyMDE ? easyMDE.value() : ta.value;
  const body = new FormData();
  body.append('csrf', '<?=htmlspecialchars(csrf_token())?>');
  body.append('type', '<?=htmlspecialchars($type)?>');
  body.append('slug', '<?=htmlspecialchars($slug)?>');
  body.append(mode, val);
  fetch('/admin/save.php', {method:'POST', body}).then(r=>r.json()).then(j=>{
    statusEl.textContent = j.ok ? 'Sauvegardé' : 'Erreur: ' + (j.error||'');
  }).catch(()=>{statusEl.textContent='Erreur réseau';});
}

refreshPreview();

// Resizer
const resizer = document.getElementById('resizer');
const leftPanel = document.getElementById('leftPanel');
const container = document.getElementById('container');
let isResizing = false;

resizer.addEventListener('mousedown', (e) => {
  isResizing = true;
  document.body.style.cursor = 'col-resize';
  resizer.classList.add('active');
  e.preventDefault();
});

document.addEventListener('mousemove', (e) => {
  if (!isResizing) return;
  const containerRect = container.getBoundingClientRect();
  const x = e.clientX - containerRect.left;
  const widthPercent = (x / containerRect.width) * 100;
  if (widthPercent > 10 && widthPercent < 90) {
      leftPanel.style.width = widthPercent + '%';
  }
});

document.addEventListener('mouseup', () => {
  if (isResizing) {
    isResizing = false;
    document.body.style.cursor = 'default';
    resizer.classList.remove('active');
    if (easyMDE) easyMDE.codemirror.refresh();
  }
});
</script>


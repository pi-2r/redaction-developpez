<?php
require_once __DIR__ . '/utils.php';
require_login();
$type = (isset($_GET['type']) ? $_GET['type'] : 'tutoriels') === 'articles' ? 'articles' : 'tutoriels';
$slug = clean_path_param((string)(isset($_GET['slug']) ? $_GET['slug'] : ''));
if ($slug === '') { set_flash('Projet introuvable', 'error'); header('Location: /admin/'); exit; }
$paths = project_paths($type, $slug);

$metaFile = $paths['dir'] . '/meta.json';
$meta = array();
if (is_file($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true);
}
$isPrivate = !empty($meta['is_private']);
$privateToken = isset($meta['private_token']) ? $meta['private_token'] : '';

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
.panel{background:var(--panel);height:100%;display:flex;flex-direction:column;position:relative;min-width:200px;overflow:hidden}
.resizer{width:6px;background:#0b1220;cursor:col-resize;flex-shrink:0;border-left:1px solid #1f2937;border-right:1px solid #1f2937;transition:background 0.2s;z-index:10}
.resizer:hover,.resizer.active{background:#3b82f6}
.toolbar{padding:10px;border-bottom:1px solid #1f2937;display:flex;gap:8px;flex-wrap:wrap;align-items:center;flex-shrink:0}
.btn{padding:6px 10px;border-radius:8px;border:1px solid #1f2937;background:#111827;color:#e5e7eb;text-decoration:none;font-size:13px;cursor:pointer}
.btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}
textarea{flex:1;border:0;background:#0b1220;color:#e5e7eb;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13px;padding:12px;line-height:1.5;resize:none;overflow:auto;min-height:0}
iframe{flex:1;border:0;background:#ffffff;min-height:0}
.flash{margin:10px;padding:10px;border-radius:8px;background:#052e1a;color:#86efac;flex-shrink:0}
.small{color:#9ca3af;font-size:12px}
/* EasyMDE overrides */
.EasyMDEContainer{background:#fff;color:#333;flex:1;display:flex;flex-direction:column;min-height:0}
.CodeMirror{flex:1;border:0;border-radius:0;min-height:0;height:100%!important}
.editor-toolbar{border-color:#ddd;border-radius:0;flex-shrink:0}
.editor-statusbar{display:none}
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);display:none;justify-content:center;align-items:center;z-index:100}
.modal{background:#1f2937;padding:20px;border-radius:8px;width:500px;max-width:90%;max-height:80vh;display:flex;flex-direction:column}
.modal-header{display:flex;justify-content:space-between;margin-bottom:15px;font-weight:bold}
.modal-body{overflow-y:auto;flex:1}
.image-item{display:flex;align-items:center;justify-content:space-between;padding:8px;border-bottom:1px solid #374151}
.image-item img{width:40px;height:40px;object-fit:cover;border-radius:4px;margin-right:10px}
.image-info{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.image-actions{display:flex;gap:5px}
.close-modal{cursor:pointer;font-size:20px}
/* Switch */
.switch {position: relative;display: inline-block;width: 40px;height: 20px;}
.switch input {opacity: 0;width: 0;height: 0;}
.slider {position: absolute;cursor: pointer;top: 0;left: 0;right: 0;bottom: 0;background-color: #ccc;-webkit-transition: .4s;transition: .4s;border-radius: 20px;}
.slider:before {position: absolute;content: "";height: 16px;width: 16px;left: 2px;bottom: 2px;background-color: white;-webkit-transition: .4s;transition: .4s;border-radius: 50%;}
input:checked + .slider {background-color: #2196F3;}
input:focus + .slider {box-shadow: 0 0 1px #2196F3;}
input:checked + .slider:before {-webkit-transform: translateX(20px);-ms-transform: translateX(20px);transform: translateX(20px);}
</style>
<header class="header">
  <div><a href="/admin/">← Retour</a></div>
  <div style="display:flex;align-items:center;gap:15px">
      <div><?=$type?> / <strong><?=$slug?></strong> <span class="small">(<?=$mode?>)</span></div>
      <div style="display:flex;align-items:center;gap:8px;background:#1f2937;padding:4px 8px;border-radius:8px">
          <label class="switch">
              <input type="checkbox" id="privateSwitch" <?=$isPrivate?'checked':''?> onchange="togglePrivate()">
              <span class="slider"></span>
          </label>
          <span id="privateLabel" class="small"><?=$isPrivate?'Privé':'Public'?></span>
          <div id="privateUrlContainer" style="display:<?=$isPrivate?'flex':'none'?>;gap:5px">
              <input type="text" id="privateUrl" readonly style="background:#111827;border:1px solid #374151;color:#9ca3af;padding:2px 5px;border-radius:4px;width:150px;font-size:11px">
              <button class="btn" style="padding:2px 6px;font-size:11px" onclick="copyPrivateUrl()">Copier</button>
          </div>
      </div>
  </div>
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
      <input type="file" id="imgUpload" style="display:none" accept="image/*">
      <button class="btn" type="button" onclick="triggerUpload()">📷 Upload Image</button>
      <button class="btn" type="button" onclick="openImgModal()">📂 Gérer Images</button>
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

<div class="modal-overlay" id="imgModal">
  <div class="modal">
    <div class="modal-header">
      <span>Gérer les images</span>
      <span class="close-modal" onclick="closeImgModal()">×</span>
    </div>
    <div class="modal-body" id="imgList">Chargement...</div>
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
        toolbar: [
            "bold", "italic", "strikethrough", "|",
            "heading-1", "heading-2", "heading-3", "|",
            "code", "quote", "unordered-list", "ordered-list", "|",
            "link", "image", "table", "horizontal-rule", "|",
            "preview", "guide"
        ],
        status: ["lines", "words", "cursor"],
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
    const baseUrl = window.location.origin + '/<?=$type?>/<?=$slug?>/';
    doc.open();
    doc.write('<base href="' + baseUrl + '">');
    doc.write(html);
    doc.close();
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

// Image Upload
function triggerUpload() {
    document.getElementById('imgUpload').click();
}

document.getElementById('imgUpload').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        uploadFile(this.files[0]);
    }
    this.value = ''; // reset
});

function uploadFile(file) {
    statusEl.textContent = 'Upload en cours...';
    const fd = new FormData();
    fd.append('csrf', '<?=htmlspecialchars(csrf_token())?>');
    fd.append('type', '<?=htmlspecialchars($type)?>');
    fd.append('slug', '<?=htmlspecialchars($slug)?>');
    fd.append('file', file);

    fetch('/admin/upload.php', {method:'POST', body: fd})
    .then(r => {
        if (!r.ok) return r.text().then(t => { throw new Error(t) });
        return r.json();
    })
    .then(data => {
        statusEl.textContent = 'Image uploadée';
        insertImageCode(data.url, data.filename);
    })
    .catch(e => {
        statusEl.textContent = 'Erreur: ' + e.message;
        console.error(e);
    });
}

function insertImageCode(url, alt) {
    const code = mode === 'md' ? `![${alt}](${url})` : `<image src="${url}" alt="${alt}" />`;

    if (easyMDE) {
        const cm = easyMDE.codemirror;
        const doc = cm.getDoc();
        const cursor = doc.getCursor();
        doc.replaceRange(code, cursor);
    } else {
        const s = ta.selectionStart;
        const before = ta.value.slice(0,s);
        const after = ta.value.slice(ta.selectionEnd);
        ta.value = before + code + after;
        ta.focus();
        ta.selectionStart = s + code.length;
        ta.selectionEnd = s + code.length;
        refreshPreview();
    }
}

function openImgModal() {
    document.getElementById('imgModal').style.display = 'flex';
    loadImages();
}
function closeImgModal() {
    document.getElementById('imgModal').style.display = 'none';
}
function loadImages() {
    const list = document.getElementById('imgList');
    list.innerHTML = 'Chargement...';
    fetch(`/admin/list_images.php?type=<?=htmlspecialchars($type)?>&slug=<?=htmlspecialchars($slug)?>`)
    .then(r => r.json())
    .then(imgs => {
        if (imgs.length === 0) {
            list.innerHTML = '<div style="padding:10px;text-align:center;color:#9ca3af">Aucune image</div>';
            return;
        }
        list.innerHTML = '';
        imgs.forEach(img => {
            const div = document.createElement('div');
            div.className = 'image-item';
            // Construct URL for preview. Note: this assumes standard structure.
            // Ideally list_images.php returns full relative URL or we construct it carefully.
            // list_images.php returns 'url' as './images/filename'.
            // But for the <img> src in the modal, we need a path relative to the admin page OR absolute.
            // The admin page is at /admin/edit.php.
            // The images are at /type/slug/images/filename.
            const previewUrl = `/${'<?=htmlspecialchars($type)?>'}/${'<?=htmlspecialchars($slug)?>'}/images/${img.name}`;

            div.innerHTML = `
                <div style="display:flex;align-items:center;overflow:hidden">
                    <img src="${previewUrl}" alt="">
                    <div class="image-info" title="${img.name}">${img.name}</div>
                </div>
                <div class="image-actions">
                    <button class="btn" onclick="insertImageCode('${img.url}', '${img.name}');closeImgModal()">Insérer</button>
                    <button class="btn" style="background:#ef4444;border-color:#ef4444" onclick="deleteImage('${img.name}')">Suppr.</button>
                </div>
            `;
            list.appendChild(div);
        });
    })
    .catch(e => list.innerHTML = 'Erreur chargement');
}
function deleteImage(filename) {
    if(!confirm('Supprimer ' + filename + ' ?')) return;
    const fd = new FormData();
    fd.append('csrf', '<?=htmlspecialchars(csrf_token())?>');
    fd.append('type', '<?=htmlspecialchars($type)?>');
    fd.append('slug', '<?=htmlspecialchars($slug)?>');
    fd.append('filename', filename);
    fetch('/admin/delete_image.php', {method:'POST', body:fd})
    .then(r => r.json())
    .then(j => {
        if(j.ok) loadImages();
        else alert('Erreur suppression');
    })
    .catch(() => alert('Erreur réseau'));
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

function togglePrivate() {
    const isPrivate = document.getElementById('privateSwitch').checked;
    const fd = new FormData();
    fd.append('csrf', '<?=htmlspecialchars(csrf_token())?>');
    fd.append('type', '<?=htmlspecialchars($type)?>');
    fd.append('slug', '<?=htmlspecialchars($slug)?>');
    fd.append('is_private', isPrivate ? 1 : 0);

    fetch('/admin/save_meta.php', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(j=>{
        if(j.ok) {
            document.getElementById('privateLabel').textContent = isPrivate ? 'Privé' : 'Public';
            document.getElementById('privateUrlContainer').style.display = isPrivate ? 'flex' : 'none';
            if (isPrivate && j.meta.private_token) {
                updatePrivateUrl(j.meta.private_token);
            }
        } else {
            alert('Erreur: ' + j.error);
            document.getElementById('privateSwitch').checked = !isPrivate; // revert
        }
    })
    .catch(e => {
        alert('Erreur réseau');
        document.getElementById('privateSwitch').checked = !isPrivate;
    });
}

function updatePrivateUrl(token) {
    const baseUrl = window.location.origin + '/<?=$type?>/<?=$slug?>/index.php';
    const url = baseUrl + '?token=' + token;
    document.getElementById('privateUrl').value = url;
}

function copyPrivateUrl() {
    const copyText = document.getElementById("privateUrl");
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value);
    alert("URL copiée");
}

<?php if ($isPrivate && $privateToken): ?>
updatePrivateUrl('<?=$privateToken?>');
<?php endif; ?>
</script>


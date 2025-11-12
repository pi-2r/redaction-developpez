<?php
require_once __DIR__ . '/utils.php';
require_login();
$flash = get_flash();
$types = array('tutoriels','articles');
// Statut système de base
$statuses = array(
  array('label'=>'tutoriels','dir'=>$TUTORIELS_DIR,'exists'=>is_dir($TUTORIELS_DIR),'writable'=>is_writable($TUTORIELS_DIR)),
  array('label'=>'articles','dir'=>$ARTICLES_DIR,'exists'=>is_dir($ARTICLES_DIR),'writable'=>is_writable($ARTICLES_DIR)),
);
$showPerms = (!empty($GLOBALS['CMSXML_DEBUG']) || is_file(__DIR__ . '/enable_debug.flag'));
?>
<!doctype html>
<meta charset="utf-8">
<title>Admin - Tableau de bord</title>
<style>
:root{--bg:#0f172a;--card:#111827;--muted:#9ca3af;--primary:#3b82f6;--ok:#10b981;--warn:#f59e0b;--err:#ef4444}
*{box-sizing:border-box}body{margin:0;background:#0b1220;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
.header{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #1f2937;background:#0b1220;position:sticky;top:0}
.header .title{font-weight:600}
.header a{color:#e5e7eb;text-decoration:none}
.container{padding:20px;max-width:1200px;margin:0 auto}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.card{background:#0f172a;border:1px solid #1f2937;border-radius:12px;padding:16px}
.h{margin:0 0 12px;font-size:18px}
ul{list-style:none;padding:0;margin:0}
.row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #111827}
.actions a, .actions form button{margin-left:8px}
.btn{display:inline-block;padding:8px 10px;border-radius:8px;border:1px solid #1f2937;background:#111827;color:#e5e7eb;text-decoration:none;font-size:14px}
.btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}
.btn.danger{background:#1f2937;border-color:#3f1d1d;color:#fecaca}
.flash{margin:10px 0;padding:10px;border-radius:8px}
.flash.success{background:#052e1a;color:#86efac}
.flash.error{background:#3b0a0a;color:#fecaca}
.form-inline{display:flex;gap:10px;flex-wrap:wrap}
input[type=text],select{background:#0b1220;color:#e5e7eb;border:1px solid #1f2937;border-radius:8px;padding:8px 10px}
.status-ok{color:#86efac}
.status-bad{color:#fca5a5}
.small{color:#9ca3af;font-size:12px}
</style>
<header class="header">
  <div class="title">CMS XML sans BDD</div>
  <div><a class="btn" href="/admin/logout.php">Se déconnecter</a></div>
</header>
<div class="container">
  <?php if ($flash): ?>
  <div class="flash <?=htmlspecialchars($flash['t'])?>"><?php echo htmlspecialchars($flash['m']); ?></div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:20px">
    <h2 class="h">Statut système</h2>
    <?php if ($showPerms): ?><p style="margin:0 0 10px"><a class="btn" href="/admin/perms.php">Permissions projets</a></p><?php endif; ?>
    <ul>
      <?php foreach ($statuses as $s): ?>
      <li class="row" style="border:none;padding:4px 0">
        <div>
          <div style="font-weight:600"><?php echo htmlspecialchars($s['label']); ?></div>
          <div class="small">Dir: <?php echo htmlspecialchars($s['dir']); ?></div>
        </div>
        <div>
          <span class="small">existe: </span><span class="<?php echo $s['exists']?'status-ok':'status-bad'; ?>"><?php echo $s['exists']?'oui':'non'; ?></span>
          <span class="small" style="margin-left:10px">écriture: </span><span class="<?php echo $s['writable']?'status-ok':'status-bad'; ?>"><?php echo $s['writable']?'oui':'non'; ?></span>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card" style="margin-bottom:20px">
    <h2 class="h">Créer un nouveau projet</h2>
    <form class="form-inline" method="post" action="/admin/create.php">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <input type="text" name="title" placeholder="Titre du projet" required style="min-width:280px">
      <select name="type">
        <option value="tutoriels">tutoriels</option>
        <option value="articles">articles</option>
      </select>
      <button class="btn primary" type="submit">Créer</button>
    </form>
  </div>

  <div class="grid">
    <?php foreach ($types as $type): $items = list_projects($type); ?>
    <div class="card">
      <h2 class="h"><?php echo htmlspecialchars(ucfirst($type)); ?> (<?php echo count($items); ?>)</h2>
      <?php if (!$items): ?><div style="color:#9ca3af">Aucun projet.</div><?php endif; ?>
      <ul>
        <?php foreach ($items as $slug): ?>
        <li class="row">
          <div>
            <div style="font-weight:600"><?php echo htmlspecialchars($slug); ?></div>
            <div style="color:#9ca3af;font-size:12px">/<?php echo $type; ?>/<?php echo htmlspecialchars($slug); ?></div>
          </div>
          <div class="actions">
            <a class="btn" href="/admin/edit.php?type=<?php echo $type; ?>&slug=<?php echo urlencode($slug); ?>">Éditer</a>
            <form style="display:inline" method="post" action="/admin/publish.php">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
              <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
              <button class="btn" type="submit">Publier</button>
            </form>
            <form style="display:inline" method="post" action="/admin/delete.php" onsubmit="return confirm('Supprimer ce projet ? Cette action est irréversible.');">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
              <input type="hidden" name="slug" value="<?php echo htmlspecialchars($slug); ?>">
              <button class="btn danger" type="submit">Supprimer</button>
            </form>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
  </div>
</div>

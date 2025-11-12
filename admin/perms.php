<?php
require_once __DIR__ . '/utils.php';
require_login();
$flagFile = __DIR__ . '/enable_debug.flag';
if (empty($GLOBALS['CMSXML_DEBUG']) && !is_file($flagFile)) { http_response_code(403); exit('Debug requis'); }

$types = array('tutoriels','articles');
$result = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(isset($_POST['csrf'])?$_POST['csrf']:'');
    $type = (isset($_POST['type']) && $_POST['type']==='articles')?'articles':'tutoriels';
    $slug = clean_path_param(isset($_POST['slug'])?$_POST['slug']:'');
    if ($slug==='') { $error='Slug vide'; }
    else { $result = fix_project_permissions($type,$slug); if (!$result['ok']) $error=$result['error']; }
}
function owner_str($path){
    $uid = @fileowner($path); $gid = @filegroup($path);
    $ou = function_exists('posix_getpwuid')?@posix_getpwuid($uid):null;
    $og = function_exists('posix_getgrgid')?@posix_getgrgid($gid):null;
    $u = $ou && isset($ou['name'])?$ou['name']:$uid; $g = $og && isset($og['name'])?$og['name']:$gid;
    return $u . ':' . $g;
}
?><!doctype html>
<meta charset="utf-8"><title>Permissions projets</title>
<style>body{font-family:system-ui;background:#0b1220;color:#e5e7eb;margin:0;padding:20px}a{color:#3b82f6;text-decoration:none}.box{max-width:1000px;margin:0 auto}.card{background:#111827;padding:16px;border-radius:12px;margin-bottom:18px}table{width:100%;border-collapse:collapse}td,th{padding:6px 8px;border:1px solid #1f2937;font-size:13px}th{background:#1f2937;text-align:left}.msg{padding:10px;border-radius:8px;margin-bottom:15px}.ok{background:#052e1a;color:#86efac}.err{background:#3b0a0a;color:#fecaca}.btn{background:#3b82f6;color:#fff;border:0;padding:6px 10px;border-radius:6px;cursor:pointer;font-size:13px}form{margin:0}</style>
<div class="box">
  <h1>Permissions projets</h1>
  <p><a href="/admin/">← Retour admin</a></p>
  <?php if ($error): ?><div class="msg err"><?=htmlspecialchars($error)?></div><?php endif; ?>
  <?php if ($result && $result['ok']): ?><div class="msg ok">Réglé: dossiers <?=intval($result['dirs'])?> | fichiers <?=intval($result['files'])?> | erreurs <?=intval($result['errors'])?></div><?php endif; ?>
  <?php foreach ($types as $type): $list = list_projects($type); ?>
  <div class="card">
    <h2><?=htmlspecialchars(ucfirst($type))?> (<?=count($list)?>)</h2>
    <?php if (!$list): ?>
      <div style="color:#9ca3af">Aucun projet.</div>
    <?php else: ?>
    <table>
      <tr><th>Slug</th><th>Owner</th><th>Writable</th><th>Action</th></tr>
      <?php foreach ($list as $slug): $p = project_paths($type,$slug); $dir = $p['dir']; ?>
      <tr>
        <td><?=htmlspecialchars($slug)?></td>
        <td><?=htmlspecialchars(owner_str($dir))?></td>
        <td><?=is_writable($dir)?'oui':'non'?></td>
        <td>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
            <input type="hidden" name="type" value="<?=htmlspecialchars($type)?>">
            <input type="hidden" name="slug" value="<?=htmlspecialchars($slug)?>">
            <button class="btn" type="submit">Fix chmod</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <div class="card" style="font-size:12px;color:#9ca3af">
    Modes forcés actuels: dossiers <?=isset($GLOBALS['FORCE_DIR_MODE'])?decoct($GLOBALS['FORCE_DIR_MODE']):'n/a'?> | fichiers <?=isset($GLOBALS['FORCE_FILE_MODE'])?decoct($GLOBALS['FORCE_FILE_MODE']):'n/a'?><br>
    Pour élargir accès si conflit owner: ajuster dans config.php $FORCE_DIR_MODE=0777 et $FORCE_FILE_MODE=0666 (dernier recours).
  </div>
</div>


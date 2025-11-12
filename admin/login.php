<?php
require_once __DIR__ . '/utils.php';
ensure_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : '');
    $u = trim((string)(isset($_POST['user']) ? $_POST['user'] : ''));
    $p = (string)(isset($_POST['pass']) ? $_POST['pass'] : '');
    if ($u !== '' && verify_login($u, $p)) {
        $_SESSION['user'] = $u;
        set_flash('Connexion réussie', 'success');
        header('Location: /admin/');
        exit;
    }
    set_flash('Identifiants invalides', 'error');
}

$flash = get_flash();
?><!doctype html>
<meta charset="utf-8">
<title>Connexion - Admin</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f6f7fb;margin:0;display:flex;align-items:center;justify-content:center;height:100vh}
.box{background:#fff;padding:24px 28px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.08);width:360px}
.h{font-size:20px;margin:0 0 16px}
label{display:block;margin:10px 0 6px}
input[type=text],input[type=password]{width:100%;padding:10px 12px;border:1px solid #d0d4dd;border-radius:8px;font-size:14px}
button{margin-top:14px;width:100%;padding:10px 12px;background:#3b82f6;color:#fff;border:0;border-radius:8px;font-weight:600;cursor:pointer}
.flash{margin-bottom:10px;padding:10px;border-radius:8px;font-size:14px}
.flash.success{background:#ecfdf5;color:#065f46}
.flash.error{background:#fef2f2;color:#991b1b}
.small{font-size:12px;color:#6b7280;margin-top:8px}
</style>
<div class="box">
  <h1 class="h">Espace d'administration</h1>
  <?php if ($flash): ?>
  <div class="flash <?=htmlspecialchars($flash['t'])?>"><?=htmlspecialchars($flash['m'])?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
    <label>Utilisateur</label>
    <input name="user" type="text" autocomplete="username" required>
    <label>Mot de passe</label>
    <input name="pass" type="password" autocomplete="current-password" required>
    <button type="submit">Se connecter</button>
  </form>
  <p class="small">Les mots de passe sont stockés dans passwords.txt (format user:hash).</p>
</div>

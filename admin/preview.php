<?php
require_once __DIR__ . '/utils.php';
require_login();

// L'aperçu renvoie du HTML non filtré : il exige le jeton CSRF pour qu'un site
// tiers ne puisse pas faire exécuter du JavaScript dans l'admin.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
$token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : (isset($_POST['csrf']) ? $_POST['csrf'] : '');
csrf_check($token);

header('Content-Type: text/html; charset=utf-8');

if (isset($_POST['md'])) {
    // Même conversion que la page publiée (dvp_render.php), avec le CSS du kit
    // developpez chargé depuis www.developpez.com : l'aperçu ressemble au rendu final.
    $type = (isset($_POST['type']) && $_POST['type'] === 'articles') ? 'articles' : 'tutoriels';
    $slug = clean_path_param((string)(isset($_POST['slug']) ? $_POST['slug'] : ''));
    $fallback = '';
    if ($slug !== '') { $p = project_paths($type, $slug); $fallback = project_title_from_xml($p['xml']); }
    $r = dvp_render_markdown((string)$_POST['md'], $slug, $fallback);
    $base = $slug !== '' ? '<base href="' . htmlspecialchars(project_url($type, $slug), ENT_QUOTES, 'UTF-8') . '">' : '';
    $kit = 'https://www.developpez.com/template/kit/';
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">' . $base
        . '<link rel="stylesheet" href="' . $kit . 'developpez-kit-generation.css">'
        . '<link rel="stylesheet" href="' . $kit . 'code.css">'
        . '<script src="' . $kit . 'fonctions-kit.js"></script>'
        . '<style>body{font:13px/1.5 Verdana,Geneva,sans-serif;color:#222;background:#fff;max-width:1000px;margin:0 auto;padding:15px;text-align:justify}'
        . 'h1.titre-article{text-align:center;color:#25465b;font-size:22px;margin:10px 0 20px}'
        . '.dvp-preview-desc{border:1px dashed #9bb1bc;padding:6px 10px;color:#555;font-size:12px;margin-bottom:15px}'
        . dvp_extra_css() . '</style></head><body>'
        . '<h1 class="titre-article">' . htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') . '</h1>'
        . '<div class="dvp-preview-desc"><b>Résumé (meta description) :</b> ' . htmlspecialchars($r['desc'], ENT_QUOTES, 'UTF-8') . '</div>'
        . '<section class="articleBody">' . $r['html'] . '</section>'
        . '<script type="module">
    import mermaid from "https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs";
    mermaid.initialize({ startOnLoad: false });
    document.querySelectorAll("pre > code.language-mermaid").forEach(el => {
        const div = document.createElement("div");
        div.className = "mermaid";
        div.textContent = el.textContent;
        el.parentElement.replaceWith(div);
    });
    mermaid.run();
    </script></body></html>';
    exit;
}

$raw = (string)(isset($_POST['xml']) ? $_POST['xml'] : '');
if ($raw === '') { http_response_code(400); echo '<p>Contenu vide</p>'; exit; }
libxml_use_internal_errors(true);
$dom = new DOMDocument();
// Pas de LIBXML_NOENT : cette option active les entités externes (XXE) et
// permettait de lire n'importe quel fichier du serveur via <!ENTITY … SYSTEM>.
if (!$dom->loadXML($raw, LIBXML_NOCDATA | LIBXML_NONET)) {
    $errs = libxml_get_errors();
    $msg = 'Erreurs XML:<br><ul>'; foreach ($errs as $e){ $msg .= '<li>'.htmlspecialchars(trim($e->message)).'</li>'; } $msg .= '</ul>';
    libxml_clear_errors();
    echo '<!doctype html><meta charset="utf-8"><style>body{font-family:system-ui;background:#fff;color:#111}</style><h1>Erreur</h1><div>'.$msg.'</div>'; exit;
}
$xsl = new DOMDocument();
$xsl->load(__DIR__ . '/xsl/default.xsl');
$proc = new XSLTProcessor();
$proc->importStylesheet($xsl);
$html = $proc->transformToXML($dom) ?: '<p>Erreur XSLT</p>';
echo $html;

<?php
/**
 * Conversion Markdown → HTML au format du kit de publication developpez.com.
 *
 * Utilisé par la page publiée (index.php), l'aperçu de l'éditeur et l'export
 * PDF/ePub, pour obtenir le même rendu partout. Le HTML produit reprend les
 * classes du kit (developpez-kit-generation.css / code.css) :
 *   - titres h2…h6 → TitreSection0…4, contenu dans BlocSection0…4 imbriqués ;
 *   - blocs de code → div.code_et_titre + « Sélectionnez » ; code en ligne → codeinline ;
 *   - tableaux → table.tableau ; listes → ul.ListeDVP / ol.liste1 ; liens → lienArticle ;
 *   - citations et alertes GitHub ([!NOTE], [!TIP], [!IMPORTANT], [!WARNING],
 *     [!CAUTION]) → encadrés rich-imgtext-info/idea/warning/error ;
 *   - image seule dans un paragraphe → figure centrée (div-figure).
 */
require_once __DIR__ . '/ParsedownDvp.php';

/**
 * @param string $markdown      Source Markdown
 * @param string $slug          Slug du projet (un premier titre égal au slug est ignoré)
 * @param string $fallbackTitle Titre à utiliser si le document n'a pas de titre H1 exploitable
 * @return array title, desc, html (contenu, sans le titre), toc (liste level/id/text)
 */
function dvp_render_markdown($markdown, $slug = '', $fallbackTitle = '') {
    $pd = new ParsedownDvp();
    $html = $pd->text((string)$markdown);

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="dvp-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);
    $root = $xp->query('//div[@id="dvp-root"]')->item(0);

    // ---- Titre : premier H1 du document ----
    // Un H1 identique au slug (« # mon-slug », contenu par défaut d'un nouveau
    // projet) n'est pas un vrai titre : il est retiré et le titre de repli est utilisé.
    $title = '';
    $firstH1 = $xp->query('//h1')->item(0);
    if ($firstH1) {
        $t = dvp_clean_text($firstH1->textContent);
        if ($slug === '' || $t !== $slug) { $title = $t; }
        $firstH1->parentNode->removeChild($firstH1);
    }
    if ($title === '') { $title = $fallbackTitle !== '' ? $fallbackTitle : $slug; }

    // ---- Niveaux de titres ----
    // Si d'autres H1 restent (parties « # 1. … »), tout descend d'un niveau
    // pour que les parties deviennent des h2 = TitreSection0 comme dans le kit.
    $shift = $xp->query('//h1')->length > 0 ? 1 : 0;
    $toc = array();
    $n = 0;
    foreach (iterator_to_array($xp->query('//h1|//h2|//h3|//h4|//h5|//h6')) as $h) {
        $lvl = min(6, (int)substr($h->nodeName, 1) + $shift);
        if ($lvl < 2) $lvl = 2;
        $h = dvp_rename($dom, $h, 'h' . $lvl);
        $sec = $lvl - 2; // 0 à 4
        $n++;
        $id = 'L' . $n;
        $text = dvp_clean_text($h->textContent);
        $h->setAttribute('id', $id);
        $h->setAttribute('class', 'TitreSection' . $sec);
        $a = $dom->createElement('a', '▲');
        $a->setAttribute('class', 'HautPage');
        $a->setAttribute('href', '#');
        $a->setAttribute('title', 'Haut de page');
        $h->appendChild($a);
        if ($sec <= 1) { $toc[] = array('level' => $sec, 'id' => $id, 'text' => $text); }
    }

    // ---- Séparateurs « --- » : le kit sépare les sections par leurs titres ----
    foreach (iterator_to_array($xp->query('//hr')) as $hr) { $hr->parentNode->removeChild($hr); }

    // ---- Alertes GitHub et citations → encadrés du kit ----
    $kinds = array('NOTE' => 'info', 'TIP' => 'idea', 'IMPORTANT' => 'warning', 'WARNING' => 'warning', 'CAUTION' => 'error');
    foreach (iterator_to_array($xp->query('//blockquote')) as $bq) {
        $kind = 'info';
        $firstP = $xp->query('./p', $bq)->item(0);
        if ($firstP && $firstP->firstChild && $firstP->firstChild->nodeType === XML_TEXT_NODE
            && preg_match('/^\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*/i', $firstP->firstChild->nodeValue, $m)) {
            $kind = $kinds[strtoupper($m[1])];
            $firstP->firstChild->nodeValue = substr($firstP->firstChild->nodeValue, strlen($m[0]));
            if (trim($firstP->textContent) === '' && !$xp->query('.//img', $firstP)->length) {
                $firstP->parentNode->removeChild($firstP);
            }
        }
        $box = $dom->createElement('div');
        $box->setAttribute('class', 'rich-imgtext-' . $kind);
        $table = $dom->createElement('table');
        $table->setAttribute('class', 'dvp-alert');
        $tr = $dom->createElement('tr');
        $tdIcon = $dom->createElement('td');
        $tdIcon->setAttribute('class', 'dvp-alert-icon');
        $img = $dom->createElement('img');
        $img->setAttribute('src', 'https://www.developpez.com/template/kit/kit' . $kind . '.png');
        $img->setAttribute('alt', $kind);
        $img->setAttribute('width', '32');
        $img->setAttribute('height', '32');
        $tdIcon->appendChild($img);
        $tdBody = $dom->createElement('td');
        $tdBody->setAttribute('class', 'dvp-alert-body');
        while ($bq->firstChild) { $tdBody->appendChild($bq->firstChild); }
        $tr->appendChild($tdIcon);
        $tr->appendChild($tdBody);
        $table->appendChild($tr);
        $box->appendChild($table);
        $bq->parentNode->replaceChild($box, $bq);
    }

    // ---- Blocs de code (hors Mermaid, rendu en diagramme côté navigateur) ----
    $c = 0;
    foreach (iterator_to_array($xp->query('//pre[code]')) as $pre) {
        $code = $xp->query('./code', $pre)->item(0);
        $lang = '';
        if (preg_match('/\blanguage-([\w+#.-]+)/', (string)$code->getAttribute('class'), $m)) { $lang = strtolower($m[1]); }
        if ($lang === 'mermaid') continue;
        $c++;
        $cid = 'c' . $c;
        $wrap = $dom->createElement('div');
        $wrap->setAttribute('class', 'code_et_titre');
        $titre = $dom->createElement('div');
        $titre->setAttribute('class', 'titre_des_codes');
        $titre->setAttribute('id', 'IdTitreCode-' . $cid);
        $titre->appendChild($dom->createTextNode(($lang !== '' && $lang !== 'text' && $lang !== 'plaintext') ? dvp_lang_label($lang) : "\xC2\xA0"));
        $sel = $dom->createElement('div');
        $sel->setAttribute('class', 'selectionner_code');
        $span = $dom->createElement('span', 'Sélectionnez');
        $span->setAttribute('class', 'selectionner_code LienSelectionne');
        $span->setAttribute('id', 'code-' . $cid);
        $span->setAttribute('onclick', "selectionCode('contenuCode" . $cid . "', 'IdTitreCode-" . $cid . "');");
        $sel->appendChild($span);
        $titre->appendChild($sel);
        $newPre = $dom->createElement('pre');
        $newPre->setAttribute('class', 'code_uniquement');
        $newPre->setAttribute('style', 'overflow:auto;');
        $newCode = $dom->createElement('code');
        $newCode->setAttribute('id', 'contenuCode' . $cid);
        $newCode->setAttribute('class', 'contenuCode');
        $newCode->appendChild($dom->createTextNode(rtrim($code->textContent, "\n")));
        $newPre->appendChild($newCode);
        $wrap->appendChild($titre);
        $wrap->appendChild($newPre);
        $pre->parentNode->replaceChild($wrap, $pre);
    }

    // ---- Code en ligne, tableaux, listes, liens ----
    foreach (iterator_to_array($xp->query('//code[not(ancestor::pre)]')) as $el) { dvp_add_class($el, 'codeinline'); }
    foreach (iterator_to_array($xp->query('//table[not(@class="dvp-alert")]')) as $t) {
        dvp_add_class($t, 'tableau');
        $scroll = $dom->createElement('div');
        $scroll->setAttribute('class', 'dvp-table-scroll');
        $t->parentNode->replaceChild($scroll, $t);
        $scroll->appendChild($t);
    }
    foreach (iterator_to_array($xp->query('//ul')) as $el) { dvp_add_class($el, 'ListeDVP'); }
    foreach (iterator_to_array($xp->query('//ol')) as $el) { dvp_add_class($el, 'liste1'); }
    foreach (iterator_to_array($xp->query('//a[@href and not(contains(@class,"HautPage"))]')) as $el) { dvp_add_class($el, 'lienArticle'); }

    // ---- Image seule dans un paragraphe → figure centrée du kit ----
    foreach (iterator_to_array($xp->query('//p[img]')) as $p) {
        $imgs = $xp->query('./img', $p);
        if ($imgs->length !== 1 || trim(str_replace("\xC2\xA0", '', $p->textContent)) !== '' || $p->childNodes->length > 3) continue;
        $img = $imgs->item(0);
        dvp_add_class($img, 'figure-img-caption');
        $div = $dom->createElement('div');
        $div->setAttribute('class', 'div-figure');
        $fig = $dom->createElement('figure');
        $fig->setAttribute('class', 'figure-center');
        $fig->appendChild($img);
        $alt = trim((string)$img->getAttribute('title')) !== '' ? $img->getAttribute('title') : $img->getAttribute('alt');
        if (trim((string)$alt) !== '') { $fig->appendChild($dom->createElement('figcaption', htmlspecialchars($alt, ENT_QUOTES, 'UTF-8'))); }
        $div->appendChild($fig);
        $p->parentNode->replaceChild($div, $p);
    }

    // ---- Description : premier paragraphe du document ----
    $desc = '';
    $p = $xp->query('//p')->item(0);
    if ($p) { $desc = dvp_truncate(dvp_clean_text($p->textContent), 300); }
    if ($desc === '') $desc = $title;

    // ---- Découpage en BlocSection imbriqués ----
    dvp_wrap_blocs($dom, $root);

    $out = '';
    foreach ($root->childNodes as $child) { $out .= $dom->saveHTML($child); }
    // Le contenu finit dans un fichier .php : neutraliser toute balise PHP
    $out = str_replace('<?', '&lt;?', $out);

    return array('title' => $title, 'desc' => $desc, 'html' => '<div class="dvp-md">' . $out . '</div>', 'toc' => $toc);
}

// Regroupe le contenu qui suit chaque titre de premier niveau du conteneur dans
// un div.BlocSectionN, en imbriquant selon le niveau (h2 > h3 > h4…).
function dvp_wrap_blocs(DOMDocument $dom, DOMNode $root) {
    $children = array();
    foreach ($root->childNodes as $ch) { $children[] = $ch; }
    $stack = array(); // [niveau, conteneur]
    foreach ($children as $node) {
        $root->removeChild($node);
        if ($node->nodeType === XML_ELEMENT_NODE && preg_match('/^h([2-6])$/', $node->nodeName, $m)) {
            $lvl = (int)$m[1];
            while ($stack && $stack[count($stack) - 1][0] >= $lvl) { array_pop($stack); }
            $parent = $stack ? $stack[count($stack) - 1][1] : $root;
            $parent->appendChild($node);
            $bloc = $dom->createElement('div');
            $bloc->setAttribute('class', 'BlocSection' . ($lvl - 2));
            $parent->appendChild($bloc);
            $stack[] = array($lvl, $bloc);
        } else {
            $parent = $stack ? $stack[count($stack) - 1][1] : $root;
            $parent->appendChild($node);
        }
    }
}

// Table des matières au format du kit (2 niveaux : parties et sections)
function dvp_toc_html(array $toc) {
    $html = '<nav class="nav-sommaire" role="navigation">' . "\n"
        . '<h2 class="TitreHeader ToggleGecko">Table des mati&egrave;res<img class="ToggleImgPlier" title="Plier" alt="Plier" src="/template/kit/fleche-haut-bleue.png"/><img class="ToggleImgDeplier" title="Déplier" alt="Déplier" src="/template/kit/fleche-bas-bleue.png" style="display:none;"/></h2>' . "\n"
        . '<ul id="nav_sommaire_ul_principal" class="nav-sommaire-ul">' . "\n";
    $open = false; $sub = false;
    foreach ($toc as $item) {
        $link = '<a class="summaryIndent' . $item['level'] . '" href="#' . $item['id'] . '">' . htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . '</a>';
        if ($item['level'] === 0 || !$open) {
            if ($sub) { $html .= "</ul>"; $sub = false; }
            if ($open) { $html .= "</li>\n"; }
            $html .= '<li>' . $link;
            $open = true;
        } else {
            if (!$sub) { $html .= '<ul class="nav-sommaire-ul">'; $sub = true; }
            $html .= '<li>' . $link . '</li>';
        }
    }
    if ($sub) $html .= '</ul>';
    if ($open) $html .= "</li>\n";
    return $html . "</ul></nav>";
}

// Compléments au CSS du kit pour les éléments issus du Markdown
function dvp_extra_css() {
    return '.dvp-md .dvp-table-scroll{overflow-x:auto;margin:0 0 10px}'
        . '.dvp-md table.tableau{border:1px solid #999}'
        . '.dvp-md table.tableau th,.dvp-md table.tableau td{border:1px solid #999;padding:4px 6px;text-align:left;vertical-align:top}'
        . '.dvp-md code.codeinline{white-space:pre-wrap;word-break:break-word;font-size:0.95em}'
        . '.dvp-md table.dvp-alert{width:100%;border:0;border-collapse:collapse;margin:0;background:transparent}'
        . '.dvp-md table.dvp-alert td{border:0;padding:4px;vertical-align:top;text-align:justify}'
        . '.dvp-md td.dvp-alert-icon{width:40px;text-align:center}'
        . '.dvp-md td.dvp-alert-body>:first-child{margin-top:0}.dvp-md td.dvp-alert-body>:last-child{margin-bottom:0}'
        . '.dvp-md [class^="rich-imgtext-"]{margin:10px 0}'
        . '.dvp-md .mermaid{text-align:center;margin:10px 0;overflow-x:auto}'
        . '.dvp-md figure figcaption{text-align:center}'
        // Code en ligne dans un titre : pas de fond gris sur la barre colorée
        . '.dvp-md [class^="TitreSection"] code.codeinline{background:transparent;color:inherit;font-size:inherit;padding:0}';
}

// CSS autonome (PDF/ePub, hors gabarit developpez) qui imite le kit
function dvp_standalone_css() {
    return 'body{font-family:Verdana,Arial,sans-serif;font-size:12px;line-height:1.5;color:#222;max-width:900px;margin:0 auto;padding:20px;text-align:justify}'
        . 'h1.titre-article{text-align:center;color:#25465b}'
        . '[class^="TitreSection"]{color:#fff;padding:4px 10px;border-radius:4px 4px 0 0;margin:20px 0 10px}'
        . '.TitreSection0{background:#5D7B8E}.TitreSection1{background:#728D9E}.TitreSection2{background:#89A1B0}.TitreSection3{background:#96ABB7}.TitreSection4{background:#AFC1CB}'
        . '.HautPage{display:none}[class^="BlocSection"]{margin:10px 0 10px 15px}'
        . '.code_et_titre{border:1px solid #ccc;margin:10px 0}.titre_des_codes{background:#EBEFF2;font-weight:600;padding:2px 6px}.selectionner_code{display:none}'
        . 'pre.code_uniquement{margin:0;padding:8px;background:#f8f8f8;white-space:pre-wrap;font:11px "Courier New",monospace}'
        . '.codeinline{font-family:"Courier New",monospace;background:#ECECEC;padding:0 3px}'
        . '.rich-imgtext-info,.rich-imgtext-idea,.rich-imgtext-warning,.rich-imgtext-error{border:1px solid #25465b;border-radius:10px;padding:5px;margin:10px 0}'
        . '.rich-imgtext-info{background:#def}.rich-imgtext-idea{background:#ffd}.rich-imgtext-warning{background:#feb}.rich-imgtext-error{background:#ffbcc5}'
        . 'table.tableau{width:98%;border-collapse:collapse;margin:0 auto 10px}table.tableau th{background:#EBEFF2}'
        . 'img{max-width:100%;height:auto}' . dvp_extra_css();
}

// Page HTML autonome (export PDF/ePub)
function dvp_standalone_page($r) {
    return "<!doctype html>\n<html lang=\"fr\"><head><meta charset=\"utf-8\"><title>" . htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') . "</title>\n<style>" . dvp_standalone_css() . "</style></head><body>\n"
        . '<h1 class="titre-article">' . htmlspecialchars($r['title'], ENT_QUOTES, 'UTF-8') . "</h1>\n" . $r['html'] . "\n</body></html>";
}

// ---- Petites fonctions utilitaires ----
function dvp_rename(DOMDocument $dom, DOMElement $el, $tag) {
    if ($el->nodeName === $tag) return $el;
    $new = $dom->createElement($tag);
    foreach (iterator_to_array($el->attributes) as $attr) { $new->setAttribute($attr->nodeName, $attr->nodeValue); }
    while ($el->firstChild) { $new->appendChild($el->firstChild); }
    $el->parentNode->replaceChild($new, $el);
    return $new;
}
function dvp_add_class(DOMElement $el, $cls) {
    $cur = trim((string)$el->getAttribute('class'));
    if (!preg_match('/(^|\s)' . preg_quote($cls, '/') . '(\s|$)/', $cur)) {
        $el->setAttribute('class', trim($cur . ' ' . $cls));
    }
}
function dvp_clean_text($s) {
    return trim(preg_replace('/\s+/u', ' ', str_replace('▲', '', (string)$s)));
}
function dvp_truncate($s, $max) {
    if (mb_strlen($s, 'UTF-8') <= $max) return $s;
    $cut = mb_substr($s, 0, $max, 'UTF-8');
    $sp = mb_strrpos($cut, ' ', 0, 'UTF-8');
    return ($sp !== false && $sp > $max * 0.6 ? mb_substr($cut, 0, $sp, 'UTF-8') : $cut) . '…';
}
function dvp_lang_label($lang) {
    $labels = array('js' => 'JavaScript', 'javascript' => 'JavaScript', 'ts' => 'TypeScript', 'typescript' => 'TypeScript',
        'py' => 'Python', 'python' => 'Python', 'json' => 'JSON', 'yaml' => 'YAML', 'yml' => 'YAML', 'bash' => 'Bash',
        'sh' => 'Shell', 'shell' => 'Shell', 'php' => 'PHP', 'java' => 'Java', 'kotlin' => 'Kotlin', 'sql' => 'SQL',
        'html' => 'HTML', 'xml' => 'XML', 'css' => 'CSS', 'c' => 'C', 'cpp' => 'C++', 'csharp' => 'C#', 'cs' => 'C#',
        'go' => 'Go', 'rust' => 'Rust', 'ruby' => 'Ruby', 'dockerfile' => 'Dockerfile', 'markdown' => 'Markdown', 'md' => 'Markdown');
    return isset($labels[$lang]) ? $labels[$lang] : ucfirst($lang);
}
// Titre « humain » d'un projet : celui saisi à la création (squelette XML)
function project_title_from_xml($xmlPath) {
    if (!is_file($xmlPath)) return '';
    $dom = new DOMDocument();
    if (!@$dom->load($xmlPath, LIBXML_NONET)) return '';
    $xp = new DOMXPath($dom);
    foreach (array('/document/entete/titre/article', '/document/entete/titre/page') as $q) {
        $n = $xp->query($q)->item(0);
        if ($n && trim($n->textContent) !== '') return trim($n->textContent);
    }
    return '';
}

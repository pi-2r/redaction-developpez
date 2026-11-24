<?php
/**
 * Extension de Parsedown propre au CMS.
 *
 * Parsedown.php reste la version officielle 1.7.4, non modifiée
 * (https://github.com/erusev/parsedown) : pour la mettre à jour, il suffit de
 * remplacer ce fichier. Les personnalisations sont ici :
 *  - les liens externes (http/https) s'ouvrent dans un nouvel onglet ;
 *  - <center>…</center> est converti en <div style="text-align:center"> ET le
 *    Markdown à l'intérieur est interprété (Parsedown ne le fait pas dans un
 *    bloc HTML, ce qui laissait les images centrées en texte brut).
 */
require_once __DIR__ . '/Parsedown.php';

class ParsedownDvp extends Parsedown
{
    public function text($text)
    {
        $text = $this->convertCenterBlocks((string)$text);
        return parent::text($text);
    }

    protected function convertCenterBlocks($text)
    {
        $self = $this;
        $out = preg_replace_callback('~<center>(.*?)</center>~is', function ($m) use ($self) {
            $inner = trim($m[1]);
            $html = $inner === '' ? '' : trim($self->renderFragment($inner));
            return "\n\n<div style=\"text-align:center\">\n" . $html . "\n</div>\n\n";
        }, $text);
        return $out === null ? $text : $out;
    }

    /** Rend un fragment Markdown avec une instance neuve (pas de partage d'état). */
    public function renderFragment($markdown)
    {
        $pd = new self();
        return $pd->text($markdown);
    }

    protected function element(array $Element)
    {
        if (isset($Element['name']) && $Element['name'] === 'a'
            && isset($Element['attributes']['href'])
            && preg_match('~^https?://~i', (string)$Element['attributes']['href'])) {
            $Element['attributes']['target'] = '_blank';
            $Element['attributes']['rel'] = 'noopener noreferrer';
        }
        return parent::element($Element);
    }
}

{extends file='page.tpl'}
{block name='page_title'}Créez votre montre Watchys{/block}
{block name='page_content'}
<section id="watchys-configurator" class="wy-config" aria-label="Configurateur de montre">
  <script id="watchys-data" type="application/json">{$watchys_payload nofilter}</script>
  <div class="wy-heading"><p class="wy-eyebrow">VOTRE MONTRE, VOS CHOIX</p><h2>Composez votre chronographe</h2><p>Choisissez les composants et la formule qui vous correspondent.</p></div>
  <div class="wy-layout"><div class="wy-preview">
    <div class="wy-watch" role="img" aria-label="Aperçu de votre configuration"><div class="wy-strap wy-strap-top"><img alt="" data-layer="strap-top"></div><div class="wy-strap wy-strap-bottom"><img alt="" data-layer="strap-bottom"></div><div class="wy-dial"><img alt="" data-layer="dial"></div><div class="wy-hands"><img alt="" data-layer="hands"></div><img class="wy-case" alt="" data-layer="case"></div>
    <p class="wy-caption">Aperçu de composition à valider sur cette version DEV.</p>
    <div class="wy-detail-images" aria-label="Composants sélectionnés"></div>
  </div><div class="wy-controls">
    <label class="wy-model-label" for="wy-model">Modèle</label><select id="wy-model"></select>
    <div id="wy-groups"></div>
    <fieldset class="wy-offers"><legend>Votre formule</legend><div id="wy-offers"></div></fieldset>
    <label for="wy-quantity">Quantité</label><input id="wy-quantity" type="number" min="1" max="100" step="1" value="1">
    <div class="wy-summary" aria-live="polite" aria-atomic="true"><p id="wy-status">Vérification de votre configuration…</p><p class="wy-price" id="wy-price"></p><p id="wy-stock"></p></div>
    <p class="wy-dev-notice">Version de validation DEV — la commande sera ouverte après validation du configurateur.</p>
  </div></div>
  <noscript>Activez JavaScript pour choisir votre configuration.</noscript>
</section>
{/block}

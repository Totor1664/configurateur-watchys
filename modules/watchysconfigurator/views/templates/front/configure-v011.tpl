{extends file='page.tpl'}
{block name='page_title'}Créez votre montre Watchys{/block}
{block name='page_content'}
<section id="watchys-configurator" class="wy-config" aria-label="Configurateur de montre">
  <script id="watchys-data" type="application/json">{$watchys_payload nofilter}</script>
  <div class="wy-heading"><p class="wy-eyebrow">VOTRE MONTRE, VOS CHOIX</p><h2>Composez votre chronographe</h2><p>Choisissez les composants et la formule qui vous correspondent.</p></div>
  <div class="wy-layout"><div class="wy-preview">
    <div class="wy-watch"><canvas id="wy-canvas" width="1000" height="1400" role="img" aria-label="Aperçu de votre configuration"></canvas></div>
    <p class="wy-caption">Aperçu de votre sélection — les composants détaillés figurent ci-dessous.</p>
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

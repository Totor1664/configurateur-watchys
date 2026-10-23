# Watchys — configurateur de montre

Prototype interactif en français : **40 compositions à 490 CHF chacune**, panier local persistant. Aucun supplément pour le cadran, le bracelet, les aiguilles ou la carrure. Sans PrestaShop, paiement, stock réel, TVA ou livraison. Prix de démonstration ; la tarification du mouvement et de l'emboîtage atelier sera ajoutée dans une évolution ultérieure.

## Lancer et tester

Node.js 20 ou supérieur. Aucune dépendance à installer, aucun build.

```sh
cd /workspace/configurateur-watchys
npm start
```

Sur votre machine, utiliser le chemin de votre clone au lieu de `/workspace/configurateur-watchys`. Ouvrir `http://127.0.0.1:5173` sur la machine du serveur. Pour une machine distante, rediriger le port 5173. Arrêt : Ctrl+C. Port alternatif : `PORT=5174 npm start`.

```sh
npm test
```

Les tests vérifient les 40 configurations, leurs prix, identifiants stables et nomenclatures, les métadonnées PrestaShop facultatives, les opérations et la restauration du panier, et la stabilité de la géométrie des simulations. Les anciens paniers bleu/noir conservent leurs configurations et quantités ; tous leurs prix sont recalculés à partir du catalogue actuel, à 490 CHF par montre.

## Composants

- Cadran : bleu, noir, vert, anthracite, gris clair. Les références vert et anthracite sont celles déjà fournies ; le gris clair correspond à la nouvelle référence à chiffres foncés.
- Bracelet cuir 20 mm : bleu ou noir.
- Aiguilles : chromées ou noires.
- Boîtier titane 41 mm : poli ou sablé.
- Mouvement commun dans la nomenclature : ETA/Valjoux 7750.

Calcul : 5 × 2 × 2 × 2 = 40. Tous les suppléments du catalogue sont à zéro. Le calcul de prix reste dans un module indépendant ; ne pas inventer de tarif atelier ou mouvement avant la définition de ces prestations.

## Visuels et stabilité

Un seul aperçu « Simulation visuelle ». Les sélecteurs montrent uniquement le composant concerné ; le panier montre les montres assemblées.

Les visuels sont des simulations issues du rendu de référence. Les fichiers originaux des photos affichées dans la conversation ne sont pas disponibles dans le dépôt. Bracelets fermés, teintes, matière et détails restent à valider ; ces images ne sont pas contractuelles. Le gris clair simule un fond clair, les chiffres foncés et des index lumineux clairs, mais ne reproduit pas exactement le brossage ou les reliefs de la référence.

`public/watch-blue-blue.png` fournit une seule base géométrique. Les aiguilles noires et la carrure sablée sont des calques limités à leur composant par des masques SVG fixes. Les centres/rayons mesurés sont dans `src/alignment.js`. Toutes les vues partagent un cadran centré en `(320, 320)`, rayon `166`, dans un repère carré de `640`.

Les couleurs sont définies dans `src/dial-colors.js` et appliquées à l'affichage : désaturation pour le noir, teinte verte, gris anthracite, gris clair avec chiffres foncés. Le rendu protège les aiguilles, les fenêtres jour/date et les index lumineux du gris clair. Le bracelet noir est une simulation de couleur sur la même géométrie bleue. Changer une option ne redimensionne pas les autres composants.

`public/components.png` fournit les vignettes des composants ; les nouvelles couleurs du cadran utilisent les mêmes transformations pour conserver leurs proportions. Les anciennes planches et propositions restent archivées pour les liens déjà partagés, sans être utilisées par l'interface. Le registre `src/images.js`, indépendant de l'interface actuelle, permet de préparer une future association de rendus validés.

## Organisation

- `src/catalog.js` : composants, prix, identifiants locaux, champs facultatifs `prestashopProductId` et `prestashopCombinationId`.
- `src/selection.js` : configurations et nomenclature complète.
- `src/pricing.js` : calcul du prix, séparé des visuels et de l'interface.
- `src/dial-colors.js` : simulations des cinq teintes de cadran.
- `src/simulation.js` : calques, cadrages et vignettes de composants.
- `src/cart.js` : ajout, regroupement, quantité, suppression, validation et total.
- `src/app.js` : interactions et sauvegarde `localStorage` (`watchys.cart.v1`).
- `src/style.css` : interface adaptative, bleu #5C7EA2 et gris #D1D2D4.
- `server.mjs` : serveur statique local.

Identifiant stable : `watchys-7750--<cadran>--<bracelet>--<aiguilles>--<boîtier>`. Le panier conserve la sélection et la quantité, pas un ancien prix qui pourrait devenir faux. La sauvegarde reste propre au navigateur et à l'origine du site.

## GitHub Pages

Publication autorisée par l'utilisateur. Branche `gh-pages`, dossier `/ (root)`, source **Deploy from a branch**. Adresse : `https://totor1664.github.io/configurateur-watchys/`. Vérifier le résultat dans **Actions → pages build and deployment** : un envoi Git seul ne prouve pas que le site a fini son déploiement.

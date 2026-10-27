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

Les visuels sont des simulations issues du rendu de référence. Les cinq cadrans sont maintenant fournis en PNG détourés dans le dépôt ; les photographies originales des autres composants ne sont pas disponibles comme fichiers. Bracelets fermés, teintes, matière et détails restent à valider ; ces images ne sont pas contractuelles. Le gris clair utilise désormais la photographie réelle, sans inversion de couleurs : brossage, chiffres, index et compteurs proviennent du fichier fourni. Son placement reste une adaptation de perspective dans une montre simulée.

`public/watch-blue-blue.png` fournit une seule base géométrique. Les aiguilles noires et la carrure sablée sont des calques limités à leur composant par des masques SVG fixes. Les centres/rayons mesurés sont dans `src/alignment.js`. Toutes les vues partagent un cadran centré en `(320, 320)`, rayon `166`, dans un repère carré de `640`.

Les cinq cadrans utilisent exclusivement les photographies d'origine répertoriées dans `src/dial-colors.js`, sans filtre de couleur. Les échelles de placement compensent le décentrage de l'axe de chaque photo, les masques laissent passer les aiguilles et l'affichage jour/date. Le bracelet noir conserve sa simulation de couleur sur la même géométrie bleue. Les autres composants ne changent pas de taille lors du changement de cadran.

Chaque vignette possède le même repère carré de 100 unités, un centre en `(50, 50)` et un diamètre visible de 80 unités. Les dimensions utiles de chaque cadran sont mesurées, indépendamment des marges transparentes du fichier : le gris clair n'est donc plus plus gros que les autres.

`public/components.png` reste utilisé uniquement pour les bracelets, aiguilles et carrures. Les anciennes planches sont archivées pour préserver les liens partagés. Le registre `src/images.js` est indépendant de l'interface actuelle.

### Origine et limites des fichiers

- `dial-grey-original.png` : archive utilisateur `Photoroom_20261023_095112.png.zip`.
- `dial-blue-original.png`, `dial-black-original.png`, `dial-green-original.png`, `dial-anthracite-original.png` : fichiers de l'archive utilisateur `Archive.zip`.
- Les PNG de 1100 × 1422 sont extraits sans modification ni génération. Seul leur placement SVG est adapté.
- Le détourage du noir conserve des parties opaques dans les ouvertures jour/date. Des fenêtres d'affichage explicites les masquent pour laisser apparaître MON/28.
- Le détourage du vert a effacé des portions de certaines graduations. Les originaux sont conservés ; les 13 fragments effacés sont maintenant restaurés en blanc à l’affichage, à la demande de l’utilisateur, selon leurs contours de transparence.
- Les ouvertures centrales transparentes sont présentées sur un fond neutre adapté au cadran, derrière les moyeux. L'assemblage reste une simulation, pas une photo réelle de la montre terminée.

## Organisation

- `src/catalog.js` : composants, prix, identifiants locaux, champs facultatifs `prestashopProductId` et `prestashopCombinationId`.
- `src/selection.js` : configurations et nomenclature complète.
- `src/pricing.js` : calcul du prix, séparé des visuels et de l'interface.
- `src/dial-colors.js` : photographies des cinq cadrans, repères de placement et normalisation des vignettes.
- `src/simulation.js` : calques, cadrages et vignettes de composants.
- `src/cart.js` : ajout, regroupement, quantité, suppression, validation et total.
- `src/app.js` : interactions et sauvegarde `localStorage` (`watchys.cart.v1`).
- `src/style.css` : interface adaptative, bleu #5C7EA2 et gris #D1D2D4.
- `server.mjs` : serveur statique local.

Identifiant stable : `watchys-7750--<cadran>--<bracelet>--<aiguilles>--<boîtier>`. Le panier conserve la sélection et la quantité, pas un ancien prix qui pourrait devenir faux. La sauvegarde reste propre au navigateur et à l'origine du site.

## GitHub Pages

Publication autorisée par l'utilisateur. Branche `gh-pages`, dossier `/ (root)`, source **Deploy from a branch**. Adresse : `https://totor1664.github.io/configurateur-watchys/`. Vérifier le résultat dans **Actions → pages build and deployment** : un envoi Git seul ne prouve pas que le site a fini son déploiement.

## Photographie du cadran gris clair

`public/dial-grey-original.png` est le PNG RGBA de 1100 × 1422 pixels extrait sans modification de l’archive utilisateur `Photoroom_20261023_095112.png.zip`. Aucun filtre de teinte ou inversion n'est appliqué. La vignette utilise la même photo.

Le placement SVG se fait autour de l'axe source `(550, 635)` ; le rayon horizontal source est de `380` pixels, le bord supérieur à `273` et le bord inférieur à `1032`. Deux échelles verticales se rejoignent sur l'axe pour corriger le décentrage de la photographie tout en gardant l'axe des aiguilles à `(320, 320)` et les bords à `154/486`. Les ouvertures jour/date laissent apparaître l'affichage existant. Le fond de l'orifice central est neutralisé derrière le moyeu. Les aiguilles sont protégées par leur masque de pixels neutres et les autres composants conservent leur géométrie. Cette intégration ne constitue pas un nouveau rendu 3D ou une photo de la montre assemblée.

## Corrections ciblées des index et aiguilles

`src/dial-repairs.js` restaure en blanc 13 fragments de graduations du cadran vert effacés par le détourage. Les contours sont tracés sur les trous de transparence de la couronne des index ; les chiffres et la texture ne sont pas régénérés. Ces retouches SVG sont appliquées à l'aperçu et à la vignette, en conservant le PNG original.

Pour le jeu noir, seules les heures/minutes conservent leur intérieur blanc. Un calque limité à la trotteuse centrale et aux trois petites aiguilles neutralise leurs reflets et centres clairs, puis leur applique un noir uniforme. Leurs formes, positions et les autres composants restent fixes. La vignette du jeu noir est corrigée de la même manière pour ses petites aiguilles.

## Première proposition avec les nouveaux composants générés

Sur la branche `watchys/generated-preview`, voir [REVIEW.md](REVIEW.md) et la page locale `/public/review/index.html`. Une seule composition bleu/bleu/chromé/poli est assemblée depuis les PNG indépendants du ZIP `7750.zip`, pour validation avant déclinaison. L'ancien configurateur et le site publié ne sont pas remplacés. Le module de composition et ses styles sont séparés dans `src/generated-pilot.js`, `src/review-page.js` et `src/review.css`.

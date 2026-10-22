# Watchys — configurateur de montre

Prototype interactif en français : 16 compositions, prix fictifs de 490 à 565 CHF, panier persistant local. Sans PrestaShop, paiement, stock réel, calcul de TVA ou livraison.

## Lancer et tester

Node.js 20 ou supérieur. Aucune dépendance à installer et aucun build.

```sh
cd /workspace/configurateur-watchys
npm start
```

Sur votre machine, utilisez le chemin de votre clone à la place de `/workspace/configurateur-watchys`. Ouvrez `http://127.0.0.1:5173` sur la machine exécutant le serveur ; pour une machine distante, redirigez le port 5173. Arrêt avec Ctrl+C. Port alternatif : `PORT=5174 npm start`.

```sh
npm test
```

Les tests vérifient les 16 configurations, chaque prix, identifiants, nomenclature, ajout/regroupement/quantités/suppression/total/restauration du panier, données invalides et association des visuels.

## Interface et images

L'interface propose un seul aperçu **Simulation visuelle**. Chaque sélecteur affiche uniquement le composant concerné : cadran, bracelet, jeu d'aiguilles ou boîtier. Le panier affiche les montres composées.

Les visuels sont des simulations générées à partir du rendu de référence et des références des composants visibles dans la conversation ; les fichiers originaux des photos n'étaient pas disponibles dans le workspace. Les bracelets fermés sont reconstitués. Les détails horlogers, inscriptions et finitions restent à valider. Ces images ne sont pas contractuelles.

- `public/components.png` : illustrations des huit composants ; affichage par fenêtres SVG pour conserver leurs proportions.
- `public/watch-blue-blue.png`, `watch-blue-black.png`, `watch-black-blue.png`, `watch-black-black.png` : quatre planches haute résolution, une par couple cadran/bracelet. Chaque planche contient quatre vues (haut gauche chromées/poli, haut droite chromées/sablé, bas gauche noires/poli, bas droite noires/sablé). Une vue utilise un quart de la surface au lieu d'un seizième dans l'ancienne planche.
- Les anciens fichiers de propositions restent disponibles pour les liens déjà partagés, mais ne sont plus utilisés dans l'interface.

Le mode « Aperçu fidèle » et l'import de rendu ont été retirés à la demande de l'utilisateur. Le registre `src/images.js` reste un module indépendant pour une éventuelle future intégration de rendus validés ; il n'est pas utilisé par l'interface actuelle.

## Organisation

- `src/catalog.js` : composants, prix, mouvement ETA/Valjoux 7750 commun, champs facultatifs `prestashopProductId` et `prestashopCombinationId`.
- `src/selection.js` : identifiants stables et nomenclature complète.
- `src/pricing.js` : calcul des prix.
- `src/simulation.js` : association des vues et vignettes de composants.
- `src/cart.js` : panier et validation de la sauvegarde.
- `src/app.js` : interactions et persistance `localStorage`.
- `src/style.css` : interface adaptative, bleu #5C7EA2 et gris #D1D2D4.
- `server.mjs` : serveur statique local sans dépendances.

Identifiant stable : `watchys-7750--<cadran>--<bracelet>--<aiguilles>--<boîtier>`. La nomenclature contient le mouvement et les quatre composants. Le prix est recalculé depuis le catalogue. Les montres identiques sont regroupées et le panier (`watchys.cart.v1`) reste après actualisation, dans le même navigateur et sur la même origine.

## GitHub Pages

Publication autorisée par l'utilisateur après la première version locale. Branche `gh-pages`, dossier `/ (root)`, source **Deploy from a branch**. Les chemins relatifs supportent l'adresse `https://totor1664.github.io/configurateur-watchys/`. L'envoi Git ne prouve pas la réussite du déploiement : vérifier **Actions → pages build and deployment**.

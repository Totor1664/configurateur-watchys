# Watchys — configurateur local

Prototype en français, sans PrestaShop, paiement, stock réel, TVA, livraison ni déploiement. Node.js 20 ou supérieur. Aucune dépendance externe, installation inutile.

## Lancer

```sh
cd /workspace/configurateur-watchys
npm start
```

Sur votre propre machine, placez-vous dans le dossier du dépôt avec `cd configurateur-watchys`. Ouvrez ensuite `http://127.0.0.1:5173` dans le navigateur de la machine exécutant le serveur. Arrêt : Ctrl+C. Un port différent est possible avec `PORT=5174 npm start`. Le serveur écoute uniquement sur la boucle locale par défaut. Dans un environnement distant, utilisez une redirection locale du port 5173 pour y accéder ; aucun déploiement n'est nécessaire.

```sh
npm test
```

Tests Node intégrés : 16 combinaisons uniques, prix exact de chacune, nomenclature, métadonnées futures PrestaShop, ajout/regroupement/quantité/suppression/total/restauration du panier, données invalides et association stricte des images. Aucun build nécessaire : modules JavaScript natifs.

## Organisation

- `src/catalog.js` : données, options, mouvement commun, prix de base, champs facultatifs `prestashopProductId` et `prestashopCombinationId`.
- `src/selection.js` : sélection, identifiants stables et nomenclature complète.
- `src/pricing.js` : calcul du prix en CHF (490 à 565 CHF).
- `src/images.js` : registre de rendus exacts par identifiant de configuration.
- `src/cart.js` : opérations de panier et validation des données persistées.
- `src/app.js` : interactions et persistance dans le navigateur.
- `src/style.css` : interface adaptative, bleu #5C7EA2 et gris #D1D2D4.
- `server.mjs` : serveur statique local sans dépendances.

Identifiant : `watchys-7750--<cadran>--<bracelet>--<aiguilles>--<boîtier>`. La nomenclature de chaque ligne de panier conserve le mouvement ETA/Valjoux 7750 et les quatre composants. Le prix est recalculé depuis le catalogue, sans faire confiance à un montant sauvegardé. Deux montres identiques sont regroupées. Le panier utilise `localStorage` (`watchys.cart.v1`) et reste après actualisation. Le stockage est propre au navigateur et à l'origine (port compris). Aucun appel réseau externe.

## Images et références à fournir

Le rendu fourni dans la conversation n'est pas accessible comme fichier dans le dépôt. **Aucun visuel n'est actuellement livré avec les sources.** Utilisez « Importer le rendu de référence » dans l'interface pour charger le fichier original PNG/JPEG/WebP (8 Mo maximum). Il sera associé uniquement à **bleu / bleu / chromées / poli** et sauvegardé dans ce navigateur si l'espace disponible le permet. Aucun téléversement réseau. Pour livrer ce rendu avec le prototype, déposer le fichier dans `public/` et ajouter son chemin `/public/nom-du-fichier.png` dans le registre `renders` de `src/images.js` sous son identifiant exact.

Chaque combinaison sans rendu exact affiche **« Aperçu non disponible »** tout en restant sélectionnable et ajoutable au panier. Pas de recoloration, de montre dessinée ou de détails inventés.

Les 15 rendus complets suivants restent nécessaires (ordre : cadran / bracelet / aiguilles / boîtier) :

| Cadran | Bracelet cuir 20 mm | Aiguilles | Titane 41 mm |
|---|---|---|---|
| Bleu | Bleu | Chromées | Sablé |
| Bleu | Bleu | Noires | Poli |
| Bleu | Bleu | Noires | Sablé |
| Bleu | Noir | Chromées | Poli |
| Bleu | Noir | Chromées | Sablé |
| Bleu | Noir | Noires | Poli |
| Bleu | Noir | Noires | Sablé |
| Noir | Bleu | Chromées | Poli |
| Noir | Bleu | Chromées | Sablé |
| Noir | Bleu | Noires | Poli |
| Noir | Bleu | Noires | Sablé |
| Noir | Noir | Chromées | Poli |
| Noir | Noir | Chromées | Sablé |
| Noir | Noir | Noires | Poli |
| Noir | Noir | Noires | Sablé |

Alternative à ces rendus : un jeu de calques validés, transparents et parfaitement alignés des deux variantes de chaque composant, avec masques, ombres et ordre d'assemblage validés. Des photos isolées sous des angles différents ne suffisent pas. Cette version utilise uniquement des rendus complets ; l'assemblage par calques demanderait une validation supplémentaire.

Tous les rendus devront respecter : bracelet fermé, position de la référence, formes et inscriptions réelles, heures/minutes à intérieur blanc vers 10 h 10, trotteuse fine sans boule entre 4 et 5, entièrement chromée pour le jeu chromé. Les pastilles de sélection représentent seulement les couleurs et finitions, pas des photos des vrais composants. Les photographies détaillées des deux cadrans, bracelets, jeux d'aiguilles et boîtiers seront nécessaires pour remplacer ces pastilles par des vignettes fidèles.

## Vérification manuelle

Sélectionner les variantes sur ordinateur et mobile ; ajouter deux fois la même montre puis une autre ; modifier une quantité ; actualiser ; vérifier le maintien des lignes et quantités ; supprimer une ligne ; contrôler le total. Importer le rendu de référence, changer une option et vérifier que l'image disparaît, revenir à la combinaison de référence. Le panier n'engendre aucune commande réelle.

## GitHub Pages

Les chemins sont relatifs pour supporter une publication sous `/configurateur-watchys/`. La branche `gh-pages` contient les fichiers statiques du prototype. Dans GitHub : **Settings → Pages → Build and deployment → Deploy from a branch → gh-pages → / (root) → Save**. L’adresse attendue après déploiement réussi est `https://totor1664.github.io/configurateur-watchys/`. Pour un dépôt privé, GitHub Pages dépend du forfait et des droits du propriétaire. Ne rendez pas le dépôt public sans décision explicite de son propriétaire. Le rendu importé et le panier restent propres au navigateur ; ils ne sont pas publiés.

## Proposition de bracelet fermé

`public/proposition-bracelet-bleu-ferme.png` est une simulation générée, présentée dans une section distincte « Proposition à valider ». Courbure et texture reconstituées ; inscription SWISS MADE manquante. Elle n'est pas enregistrée comme rendu fidèle dans `src/images.js` et ne remplace pas les aperçus indisponibles. Visible sur le site à l'ancre `#proposition`.

## Comparaison visuelle interactive des 16 combinaisons

Le mode par défaut **Simulation visuelle** affiche des propositions générées depuis le rendu de montre au bracelet fermé et les références visibles dans la conversation. Les photos originales des composants n'étaient pas accessibles comme fichiers dans le workspace ; elles ne sont donc pas livrées dans le dépôt. Il ne s'agit pas d'un assemblage photographique exact de ces fichiers.

`src/simulation.js` associe chaque sélection à une cellule distincte de `public/simulations-16.png` (grille 4 × 4). Les lignes représentent cadran/bracelet bleu-bleu, bleu-noir, noir-bleu et noir-noir ; les colonnes représentent aiguilles/boîtier chromées-poli, chromées-sablé, noires-poli et noires-sablé. Des miniatures sont présentées dans les sélecteurs et le panier. Le prix et les données restent indépendants des visuels.

**Limites visibles :** bracelets fermés reconstitués, détails des inscriptions et des petites aiguilles non garantis, géométrie et finitions approximatives, résolution limitée à une cellule de la planche. Ces simulations servent à comparer les couleurs et ne sont pas des photos contractuelles ou des rendus fidèles validés. Le bouton **Aperçu fidèle** utilise uniquement le registre séparé `src/images.js` et le rendu importé, ou indique « Aperçu non disponible ». Les simulations ne sont jamais enregistrées dans ce registre. Les originaux et des rendus corrigés restent nécessaires pour atteindre la fidélité demandée.

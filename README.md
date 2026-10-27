# Watchys — Classic Valjoux 7750 · 41 mm

Configurateur interactif en français : **960 compositions à 490 CHF chacune**, à partir des composants transparents du ZIP utilisateur `7750.zip`. Panier de démonstration persistant, sans paiement, PrestaShop, gestion réelle de stock, TVA ou livraison. La tarification future du mouvement et de l'emboîtage atelier n'est pas encore ajoutée.

## Lancer

Node.js 20 ou supérieur. Aucune dépendance à installer, aucun build.

```sh
cd /workspace/configurateur-watchys
npm start
```

Sur votre Mac, remplacer ce chemin par celui du clone. Ouvrir `http://127.0.0.1:5173`. Arrêt : Ctrl+C. Autre port : `PORT=5174 npm start`. En environnement distant, transférer le port 5173.

```sh
npm test
```

Les 61 tests couvrent les 960 configurations, les prix, les identifiants, la nomenclature, tous les fichiers utilisés, le panier et sa restauration. Les tests de l'ancien moteur photographique restent limités à ses 40 configurations historiques.

Vérification complémentaire dans Chromium (serveur démarré dans un autre terminal) :

```sh
npm install --no-save --package-lock=false playwright
npx playwright install chromium
npm run test:browser
```

Ce contrôle parcourt les 960 sélections dans l'interface, vérifie les calques indépendants, le rendu de référence pixel par pixel, le panier et les petits écrans. `WATCHYS_URL` permet de tester une autre adresse ; `WATCHYS_CHROMIUM_PATH` permet d'utiliser un Chromium déjà installé. Les captures sont enregistrées dans un dossier temporaire affiché à la fin.

## Options du modèle

| Composant | Options |
| --- | --- |
| Cadran (5) | Bleu, noir, vert, anthracite, gris clair argenté |
| Bracelet cuir 20 mm (6) | Bleu, noir, rouge, orange, vert, brun |
| Aiguilles (8) | Chromées Luminova, noires Luminova ; Dauphine chromées, noires, or, or rose, rouges, bleu royal |
| Carrure 41 mm (4) | Polie, sablée, noire brossée, noire PVD polie |
| Mouvement commun | ETA/Valjoux 7750 |

5 × 6 × 8 × 4 = **960**. Les carrures 44 mm appartiendront à d'autres modèles ; elles ne sont pas proposées ici. Les noms « or » et « or rose » désignent les variantes de visuel fournies, sans certification du matériau.

## Visuels

Un seul assemblage de calques transparents indépendants, jamais 960 montres générées séparément. La composition bleu/bleu/Luminova chromées/polie conserve exactement le rendu validé : centre `(615, 625)`, rayon de cadran `318`, canevas carré de `1254`. Changer une option remplace seulement son calque ; les autres conservent leurs sources et transformations.

- `src/model-assets.js` : sources, recadrages et repères propres à chaque image.
- `src/generated-pilot.js` : assemblage sur la géométrie commune, masque de carrure, séparation des aiguilles principales et des compteurs.
- `src/validation-renderer.js` : disponibilité des visuels, aperçu, vignettes.
- `public/generated-7750/sources.json` : correspondance exhaustive avec les 29 PNG du ZIP, conservés sans retouche. Les versions ouvertes des bracelets sont archivées mais ne constituent pas des options supplémentaires.

Les nouveaux cadrans sont recalés localement sur les axes des compteurs et les fenêtres du cadran bleu. Les bracelets fermés utilisent tous les deux emplacements validés ; la silhouette bleue ajustée masque les replis latéraux des autres sources. Le bleu reste le dérivé ajusté déjà validé (`strap-blue-closed-fitted.png`). Le noir et les autres couleurs utilisent maintenant leurs propres images, sans recoloration du bleu. Les vignettes montrent seulement le composant choisi, dans un gabarit identique par famille.

Les Luminova gardent leur intérieur blanc ; les petites aiguilles et la trotteuse du jeu noir sont noires. Les Dauphine suivent les images fournies, avec des facettes métalliques sans ajout d'intérieur blanc. Les heures/minutes restent vers 10 h 10 ; la trotteuse fine est dirigée entre 4 et 5. MON/28 est une inscription de démonstration ajoutée dans les ouvertures du cadran.

Ces assemblages restent des simulations : le recalage et le recadrage adaptent les proportions des fichiers générés ; textures, reflets et petites différences de gravures restent ceux des images fournies. Certaines graduations du cadran vert sont plus sombres dans le nouveau fichier et sont conservées telles quelles. Aucun détail horloger nouveau n'est généré pour cette intégration.

## Données et panier

- `src/catalog.js` : modèle `classic-valjoux-7750-41`, composants, prix et champs PrestaShop facultatifs.
- `src/selection.js` : sélection, identifiants stables et nomenclature (mouvement, cadran, bracelet, aiguilles, carrure).
- `src/pricing.js` : calcul indépendant, suppléments à zéro.
- `src/cart.js` : ajout, regroupement, quantités 1 à 999, suppression, total, restauration.
- `src/app.js` : interface et stockage `watchys.validation.cart.v1`.

Les anciens identifiants `watchys-7750--…` et le panier de validation existant restent compatibles. Le stockage `watchys.cart.v1` du site historique n'est pas modifié. Les futurs modèles devront disposer de leur propre catalogue de composants et d'un préfixe de configuration distinct.

## Page de validation séparée

`https://totor1664.github.io/configurateur-watchys/validation/`

Développement sur `watchys/classic-7750-options`. La branche `watchys/generated-preview` conserve la version précédemment validée. La publication de test met à jour uniquement `validation/` sur `gh-pages` ; l'accueil historique et `main` restent inchangés. Voir [VALIDATION.md](VALIDATION.md).

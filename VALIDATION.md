# Validation — Classic Valjoux 7750, 41 mm

Page séparée : <https://totor1664.github.io/configurateur-watchys/validation/>

Branche : `watchys/classic-7750-options`. La version validée précédemment reste conservée sur `watchys/generated-preview` (`a204f7a`). L'accueil GitHub Pages historique et la branche `main` ne sont pas remplacés.

## Nouvelle collection

- 5 cadrans : bleu, noir, vert, anthracite, gris clair.
- 6 bracelets fermés : bleu, noir, rouge, orange, vert, brun.
- 8 jeux d'aiguilles : 2 Luminova (chromées et noires), 6 Dauphine (chromées, noires, or, or rose, rouges, bleu royal).
- 4 carrures 41 mm : polie, sablée, noire brossée, noire PVD polie.
- 960 compositions, toutes disponibles visuellement et toutes à 490 CHF.

Les images ouvertes et fermées d'une même couleur représentent une seule option de bracelet. Le 44 mm fera l'objet d'autres modèles, avec leurs propres carrures.

## Préservation du rendu approuvé

La composition bleu/bleu/chromées Luminova/polie garde le rendu validé, comparé pixel par pixel dans Chromium. Les nouvelles options sont enregistrées sur la même géométrie. Un changement d'option ne modifie pas les calques des autres composants.

Les 29 originaux du ZIP sont archivés dans `public/generated-7750/` ; `sources.json` documente leurs noms d'origine. Le bracelet bleu reste le dérivé ajusté précédemment accepté. Les autres bracelets sont placés et masqués sur les mêmes deux emplacements. Les cadrans vert, anthracite et gris clair sont recalés sur les fenêtres et compteurs du bleu. Le jour/date MON/28 reste une démonstration.

Les détails, reflets et variations de gravures des sources générées sont conservés ; le recalage des cadrans et les raccords des bracelets restent une simulation. Les Dauphine ont les facettes des images fournies, sans Luminova ajouté. Les graduations sombres visibles sur le vert proviennent du nouveau fichier utilisateur.

## Vérifications

`npm test` : 61 tests, dont les prix, la disponibilité et les identifiants uniques des 960 configurations, les 23 options visuelles, les anciens paniers et toutes les opérations de panier sur les 960 compositions.

Contrôle Chromium : parcours des 960 sélections dans l'interface ; vérification des prix et récapitulatifs ; comparaison des 24 variantes de calques indépendants ; comparaison pixel par pixel de la composition approuvée ; ajout, regroupement, quantité, suppression et persistance du panier ; absence de modification du stockage historique ; unicité des identifiants SVG ; contrôle des ressources et du rendu sur ordinateur et téléphone.

Le panier conserve la clé `watchys.validation.cart.v1`. Les anciens choix et leurs quantités sont restaurés. Les champs facultatifs PrestaShop restent à `null`. Aucun nouveau tarif d'atelier, stock réel, paiement ou connexion externe n'est ajouté.

## Essai manuel

1. Ouvrir la page de validation et choisir plusieurs couleurs ainsi que les deux familles d'aiguilles.
2. Changer séparément cadran, bracelet, aiguilles et carrure : les autres éléments doivent rester fixes.
3. Ajouter des compositions au panier, modifier une quantité, actualiser, supprimer une ligne.
4. Vérifier un prix unitaire de 490 CHF et le total correspondant aux quantités.

Localement : `npm start`, puis <http://127.0.0.1:5173/>. La publication de validation ajoute uniquement les fichiers sous `validation/`, sans remplacer le site historique. Un push Git ne garantit pas la fin immédiate du déploiement Pages.

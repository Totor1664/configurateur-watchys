# Configurateur de validation

Cette version utilise les nouveaux composants générés du ZIP `7750.zip` et rétablit le bracelet de la révision **54d9a6d**, avant la superposition sur les faces intérieures des cornes. Elle est développée sur `watchys/generated-preview`.

## Essayer

La page de test est publiée séparément sous :

`https://totor1664.github.io/configurateur-watchys/validation/`

L'accueil historique et ses fichiers restent inchangés. La publication ajoute seulement le dossier `validation/` à `gh-pages`, sans fusionner cette branche de travail dans `main`. Un envoi Git ne garantit pas que le déploiement Pages est terminé.

Localement : `npm start`, puis ouvrir la racine du serveur, port 5173.

## Ce qui est prêt

- Interface française, ordinateur et mobile, vignettes de composants isolés.
- 40 choix de configuration : cinq cadrans, deux bracelets, deux jeux d'aiguilles et deux carrures.
- Prix unique de 490 CHF, sans supplément.
- 16 aperçus assemblés depuis les cadrans générés bleu/noir et les composants disponibles, sur la même géométrie fixe.
- 24 configurations vert/anthracite/gris clair sélectionnables sans visuel, avec « Aperçu non disponible ».
- Panier de démonstration : regroupement, quantité, suppression, total et conservation après rechargement. Le stockage est séparé (`watchys.validation.cart.v1`) et ne modifie pas le panier du site historique.
- Les aiguilles et carrures sont des calques indépendants. Le bracelet noir reste une simulation par désaturation du cuir bleu corrigé pour conserver exactement sa silhouette ; son nouveau rendu propre pourra le remplacer ensuite.
- Les textes MON/28 sont un affichage de démonstration, pas une mécanique jour/date.

## À valider dans cette version

1. Composer plusieurs montres bleu/noir, changer aiguilles/carrure et vérifier la stabilité des autres composants.
2. Vérifier les proportions, raccords du bracelet et détails des visuels sur ordinateur et mobile.
3. Ajouter plusieurs configurations, modifier les quantités, recharger la page et supprimer des lignes.
4. Vérifier que les choix sans nouveau cadran restent utilisables, mais sans aperçu inventé.

## Fichiers à fournir ensuite

Pour le modèle 7750 : cadrans générés vert, anthracite et gris clair, puis toute variante supplémentaire et éventuellement un bracelet noir fermé au même gabarit que le bleu retenu.

Préparer un ZIP par modèle, avec des noms explicites (`cadran-vert.png`, `bracelet-noir-ferme.png`, `aiguilles-noires.png`, `carrure-sablee.png`). Chaque composant doit avoir un fond transparent, la même vue de face, une définition suffisante et des repères stables : centre/diamètre du cadran, axes des compteurs, points de fixation du bracelet. Éviter de changer perspective, lumière ou échelle entre deux variantes d'un même composant. Inclure un rendu complet de référence par modèle et un inventaire des composants compatibles.

Le modèle courant conserve ses identifiants et sa nomenclature complète. La sélection de nouveaux modèles et leurs règles de compatibilité seront ajoutées après validation de cette base et réception de leurs composants. Aucun tarif de mouvement, d'emboîtage, de stock réel, de paiement ou de connexion PrestaShop n'est ajouté ici.

# Miniatures personnalisées du panier — DEV uniquement

Le module 0.2.2 conserve son cycle de panier et ses gardes de commande. Ce correctif utilise actionPresentCart pour remplacer les URLs de couverture de chaque ligne par une image correspondant à sa configuration enregistrée. La sélection est lue depuis les tables Watchys et sa liaison au panier natif ; les prix, quantités et personnalisations ne sont pas changés. Les articles ordinaires restent inchangés. La photo générique du support 8867 est conservée comme secours.

## Images

960 JPEG de 380 × 494 pixels, fond blanc, sont générés automatiquement depuis le moteur SVG approuvé de gh-pages (faec62714c571ff78ff3b27a389727b7c45921f6). Les calques sont rendus avec ce moteur puis composés dans son ordre exact. Ils couvrent les quatre carrures, cinq cadrans, huit jeux d'aiguilles et six bracelets. L'offre et la quantité ne modifient pas la forme de la montre. La clé SHA256 porte sur le modèle puis case/dial/hands/strap séparés par `|`.

Les images sont des ressources de catalogue publiques ; elles ne contiennent ni identité client, ni numéro de panier, ni prix. Aucun téléversement d'image du navigateur, aucune route privée supplémentaire et aucune capture IA. Les fichiers ne sont pas réécrits lors des changements de quantité ou des ajouts au panier. Les configurations déjà enregistrées bénéficient aussi du rendu, si leur sélection est valide.

## Construire

Installer @napi-rs/canvas dans un environnement Node local, puis récupérer les PNG de la référence validée dans un dossier de travail. Le constructeur vérifie leurs empreintes contre les actifs installés attendus.

```bash
node integration/prestashop/dev-cart-thumbnails/render.cjs CHEMIN_PNG_REFERENCE DOSSIER_MINIATURES CHEMIN_MODULE_CANVAS
python3 integration/prestashop/dev-cart-thumbnails/build.py --assets DOSSIER_MINIATURES --previous-package PAQUET_PANIER_0.2.2.zip --output watchys-miniatures-panier-dev.zip
```

## Contrôler et appliquer

Extraire le paquet en dehors du site. Exécuter d'abord `php installer.php --verifier`. Les tests PHP sont exécutés dans des sous-processus isolés ; aucun bootstrap natif ou écriture site/MySQL dans ce mode. Le script vérifie les 960 JPEG, le catalogue réellement installé, les 29 PNG du gabarit, le code métier, les cinq gardes natives et le support technique. Il signale les templates de mini-panier trouvés pour le contrôle visuel Alysum.

Après lecture du bilan, exécuter séparément `php installer.php`, lire le plan puis taper OUI. Il sauvegarde le code, publie deux fichiers et uniquement les JPEG absents, puis initialise le noyau natif et enregistre la seule accroche actionPresentCart. Il rafraîchit le registre natif et relit les associations en SQL non caché via PDO. Aucun installateur de module, aucune création de produit, aucun remplacement d'image article ni changement de stock ou prix.

Après réussite, recharger le panier, ajouter deux configurations très différentes, contrôler leurs deux images et le mini-panier. Les présentations passant par CartPresenter utilisent ce correctif ; une présentation propre au thème devra être examinée si elle ne réutilise pas ce présentateur. En cas d'image absente/invalide, la photo générique est conservée. La compatibilité réelle du mini-panier Alysum reste à valider sur DEV.

En cas d'arrêt, conserver le journal et relancer --verifier avant toute reprise. Le code antérieur est rétabli si la validation native n'est pas confirmée ; les JPEG nouveaux restent présents. Une accroche native créée avant l'arrêt peut persister : ne pas présumer un retour arrière SQL complet. Le journal permet d'identifier les deux états. Ne pas relancer un ancien paquet de mise à jour du module.

## Tests

`npm run test:all` inclut les clés des 960 configurations, le croisement PHP/JS, des lignes distinctes d'un même produit, des objets ArrayAccess, les articles ordinaires, la configuration inconnue/malformée et le secours générique. Le pont panier et ses protections sont toujours testés avec doubles natifs, pas sur un vrai serveur PrestaShop local. La publication nécessite les contrôles et essais du vrai DEV.

## État au 24 novembre

Les 24 composants existent. Watchys confirme le passage à 10 unités, l'activation manuelle des variantes et leur ajout au panier. La vignette générique est installée (couverture 16190) ; ce nouveau correctif personnalisé est préparé, pas encore confirmé installé. Commande/paiement Watchys restent bloqués ; cycle de stock réel, concurrence entre commandes, annulations/retours et fusion de panier à la connexion restent à développer/tester.

# Vignette générique du panier Watchys — DEV uniquement

Source : vue complète déjà validée, branche gh-pages, commit faec62714c571ff78ff3b27a389727b7c45921f6, validation/public/review/blue-blue-chrome-polished.png. Aucun nouveau dessin ni changement de géométrie. Image générique commune aux configurations, pas leur aperçu personnalisé.

Le script ajoute une couverture au seul produit technique 8867 (référence WCFG-7750-41-CARTDEV). Il refuse toute galerie préexistante, vérifie la boutique DEV et la correspondance du modèle, conserve prix/stocks/fiches et ne charge pas PrestaShop. Aucun module, PNG du configurateur ou blocage de commande n'est modifié.

## Exécution sur le serveur

Déposer le ZIP dans le dossier personnel privé, puis extraire hors du site.

```bash
php installer.php --verifier
```

Le contrôle se fait sans écriture : transaction SQL en lecture seule et conversion des JPEG en mémoire aux dimensions image_type produits du DEV.

Après lecture du bilan, lancer séparément `php installer.php`, lire le plan et taper OUI. Journal privé avant/après ; transaction SQL, fichiers exclusivement nouveaux, refus de remplacement et relecture avant commit. Les fichiers éventuellement créés lors d'un échec sont conservés pour diagnostic. En cas d'arrêt, conserver le journal et contrôler avant reprise ; aucun succès à déduire. Après succès, recharger le panier et vérifier visuellement.

## Validation locale

`php tests.php` : syntaxe PHP 8.2, cinq tailles JPEG, dimensions et fond blanc, rejet des dimensions invalides, refus d'écraser un fichier préexistant. Aucun MySQL utilisé par ces tests. Le test écrit puis supprime un JPEG temporaire dans son propre dossier. La migration SQL nécessite encore la vérification et l'application sur le vrai DEV.

## État du projet au 24 novembre 2026

Watchys a confirmé le passage des composants à 10 unités, activé manuellement les articles et validé leur ajout au panier. Les 24 articles existent ; aucune création n'a été nécessaire. Le module reste en 0.2.2. Le cycle de stock réel à la commande n'est pas développé ; commande et paiement Watchys restent bloqués. Cette vignette est préparée, pas encore confirmée sur DEV.

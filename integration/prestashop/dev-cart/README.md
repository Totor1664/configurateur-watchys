# Panier Watchys DEV 0.2.2 — première étape commerciale

Cette version ajoute le bouton au configurateur et enregistre une configuration dans le vrai panier PrestaShop. Elle conserve le gabarit visuel validé de GitHub et les 29 PNG installés (23 composants et six bracelets fermés pour l'aperçu).

**La commande et le paiement sont bloqués pour les paniers contenant une configuration Watchys.** Le cycle de décrément des composants, annulation, remboursement et retour n'est pas livré dans cette première étape. Aucun stock de composant n'est réservé ni déduit à l'ajout. Les paniers sans configuration Watchys ne sont pas bloqués.

## Architecture

Une fiche technique masquée par modèle, référence `WCFG-7750-41-CARTDEV` pour le premier modèle, est créée à la migration. Elle ne remplace aucune ancienne fiche kit. Son prix natif est zéro ; le prix complet de chaque formule est stocké dans la personnalisation native créée par le serveur. PrestaShop applique ensuite ses règles fiscales, remises et conversion. Les règles fiscales et la catégorie proviennent de l'ancienne fiche 7753, lue seulement. La quantité propre du support technique n'est pas une quantité de montres disponibles : seul le stock réel des composants sert au contrôle.

Six champs personnalisés détaillent le modèle, le cadran, le bracelet, les aiguilles, la carrure et la formule. Chaque nouvel ajout explicite crée une ligne personnalisée ; deux configurations ou formules différentes ne se confondent pas. Une reprise du même ajout après double clic ou coupure utilise une clé de requête stable pour éviter le doublon.

Trois tables du module conservent les correspondances de supports, les nomenclatures et prix instantanés par personnalisation, et les requêtes exécutées. Aucun identifiant de pièce ni prix fourni par le navigateur n'est utilisé pour calculer la vente. Les composants désactivés ou non commandables sont refusés. Les disponibilités sont cumulées sur les configurations déjà au panier et les achats séparés des mêmes pièces. Une quantité native augmentée passe aussi par ce contrôle. Les diminutions et suppressions restent possibles pour nettoyer un panier devenu indisponible.

Les ajouts à un même panier sont sérialisés sur sa ligne native pendant une transaction. Ceci protège la création et la reprise des configurations ; ce n'est pas une réservation de composants entre clients. Les contrôles de commande et la concurrence entre achats restent à développer avant toute ouverture commerciale.

Pour cette première validation, la boutique doit être affichée en CHF. Le serveur convertit le prix CHF vers la devise de base pour la colonne native de personnalisation si nécessaire. Les montants de base sont 190, 510 et 610 CHF HT selon formule ; les remises et taxes du panier peuvent modifier le montant affiché au client. Le mouvement n'entre dans la nomenclature que s'il est inclus ; l'emboîtage n'est pas une pièce physique. Le poids de personnalisation additionne les poids enregistrés des composants, sans inventer de poids absent.

## Déploiement DEV

Prérequis : module actif 0.1.1 après les correctifs faces, rendu et gabarit GitHub. La mise à jour vérifie les empreintes de ce code et des 29 PNG. Elle refuse un état inconnu. Elle ne réinstalle pas le module.

1. Déposer le ZIP dans HOME, hors des répertoires web.
2. Extraire et contrôler :

```bash
unzip -n "$HOME/watchys-panier-dev-0.2.2.zip" -d "$HOME"
php -l "$HOME/watchys-panier-dev-0.2.2/installer.php"
php "$HOME/watchys-panier-dev-0.2.2/installer.php" --verifier
```

3. Après un contrôle réussi :

```bash
php "$HOME/watchys-panier-dev-0.2.2/installer.php"
```

Le script présente le plan, demande OUI et affiche DÉBUT/FIN. Avant OUI : PDO en lecture seule, sans bootstrap PrestaShop ni cache écrit. Après OUI : journal et sauvegarde privée de chaque fichier remplacé, publication de neuf fichiers de module, bootstrap natif complet avec AppKernel, création des tables et préparation transactionnelle du support et des accroches. Les fichiers images et le catalogue installé ne sont jamais remplacés.

Le script vérifie les moteurs transactionnels des tables natives concernées. La création des trois tables précède la transaction native (DDL MySQL) ; des tables de préparation vides peuvent rester en cas d'échec. Le code précédent est restauré si la préparation native échoue normalement. Une interruption brutale nécessite de lire le journal avant reprise. Aucune restauration de base n'est automatique. La PROD n'est pas consultée.

Ne pas utiliser les anciens installateurs 0.1.1 ou correctifs de rendu après cette migration.

## Validation sur DEV

Commencer par une configuration utilisant les articles déjà actifs. Les douze variantes encore désactivées ne sont pas activées par cette mise à jour. Elles restent visibles pour l'aperçu mais non ajoutables au panier tant que leurs statuts ne sont pas validés.

- Sélectionner une formule et vérifier le devis, puis cliquer sur Ajouter ma configuration au panier.
- Vérifier la fiche technique et les six valeurs personnalisées dans le panier réel, le prix et la TVA selon l'adresse et les règles client.
- Ajouter deux configurations/formules différentes, modifier leurs quantités et retirer une ligne.
- Tester un composant commun à plusieurs lignes et à un achat séparé : la somme doit être contrôlée.
- Tester une reprise réseau : pas de doublon pour une même clé ; les ajouts explicites distincts peuvent produire plusieurs lignes identiques.
- Vérifier qu'un composant désactivé ou en rupture est refusé, puis que la quantité disponible n'a pas changé après un ajout/suppression.
- Vérifier que l'accès à la commande renvoie au panier et qu'une création d'Order est également bloquée pour ces configurations.

Les regroupements de panier à la connexion, duplications de paniers et thèmes de paiement alternatifs restent à tester sur le vrai DEV. Une personnalisation dont la correspondance ne peut pas être prouvée est refusée, jamais ajoutée avec un prix nul. Ne pas considérer cette étape comme commercialisable. Une vue composée dans la vignette du panier et la nomenclature atelier dans une commande réelle appartiennent à la suite.

## Contrôles locaux

Tests de 2 880 choix/formules pour le calcul et les besoins de composants ; doubles de service natif pour les trois formules, reprises, stocks communs, achats séparés, personnalisation étrangère, prix altéré et annulation d'écritures ; tests des gardes de commande et de paiement ; test du bouton et de la reprise réseau ; comparaison des 960 arbres SVG au moteur de référence ; suites antérieures ; syntaxe PHP/JS et construction reproductible du ZIP.

Les doubles ne remplacent pas des tests HTTP/MySQL/PrestaShop réels. La migration et l'affichage du panier sur Alysum restent à confirmer après exécution par Watchys.

## Correction 0.2.2

La première tentative 0.2.0 a échoué : getRow() ajoutait LIMIT 1 après FOR UPDATE. Le verrou utilise maintenant executeS sans cache ; les autres lectures métier critiques sont également sans cache. Le contrôle préalable affiche les tables et la référence technique éventuelles et refuse de reprendre automatiquement un état partiel non vide avec un module 0.1.1. Les éventuelles tables vides restent conservées et réutilisées. Un test reproduisant le comportement SQL implicite du cœur couvre désormais cette préparation. Ne pas relancer le paquet 0.2.0.

## Correction du contrôle d'accroches

La tentative 0.2.1 a été arrêtée par une relecture native de displayCustomization non confirmée. La version 0.2.2 rafraîchit le registre et ses alias, conserve l'enregistrement natif, puis vérifie les cinq associations exactes en SQL non caché. Elle ne transforme pas un contrôle négatif en succès : absence réelle, désactivation ou doublon bloquent toujours la migration. Les lectures de contrôle contournent le cache SQL ; les écritures gardent l’invalidation native des caches, sans changer la configuration de cache de la boutique. Les cinq lignes confirmées sont ajoutées au journal. Le contrôle préalable affiche aussi leur état avant la reprise.

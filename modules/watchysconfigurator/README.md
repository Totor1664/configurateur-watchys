# Configurateur Watchys — DEV 0.1.0

Première installation de validation pour PrestaShop 8.2.x, exclusivement sur `dev.watchys.ch`, boutique 1.

## Fonctions livrées

- Page intégrée dans le thème PrestaShop, accessible par lien direct ou par le bouton Configurer du module.
- 5 cadrans, 6 bracelets 20 mm, 8 jeux d'aiguilles et 4 carrures. PNG transparents conservés ; les JPEG des fiches articles ne sont pas modifiés.
- Modèles définis dans un catalogue séparé pour permettre l'ajout futur d'autres modèles.
- Lecture du stock disponible des articles PrestaShop, de leur statut et des références des nouvelles variantes.
- Calcul serveur en CHF HT : 190 sans mouvement, 510 avec mouvement, 610 avec mouvement et atelier.
- Vérification des quantités et des options autorisées. Un prix envoyé par le navigateur est ignoré.
- Articles désactivés visibles pour la revue des images, signalés « À valider », jamais déclarés disponibles.

Cette version ne comporte pas d'ajout au panier, de commande, de réservation ni de décrément de stock. Elle ne remplace pas Product Composer PRO et n'ajoute aucun lien au menu ou à la page d'accueil.

## Préparation et installation

L'archive `watchys-module-dev-0.1.0.zip` contient le module, le catalogue, les contrôles et l'installateur. Les 23 PNG sont copiés depuis les paquets déjà présents dans le dossier personnel SSH : `watchys-7750-variantes` et `watchys-carrures-7750-vues`. Les originaux restent inchangés.

1. Envoyer le ZIP dans le dossier personnel SSH, hors `web` et `sites`.
2. Extraire : `unzip -n "$HOME/watchys-module-dev-0.1.0.zip" -d "$HOME"`.
3. Contrôler l'installateur : `php -l "$HOME/watchys-module-dev-0.1.0/installer.php"`.
4. Vérifier sans installer : `php "$HOME/watchys-module-dev-0.1.0/installer.php" --verifier`.
5. Installer : `php "$HOME/watchys-module-dev-0.1.0/installer.php"`, puis lire le plan et taper OUI.

L'installateur vérifie la boutique, les références des articles, les empreintes des fichiers et la syntaxe PHP avant de demander OUI. Après confirmation, il copie le module sans écraser de dossier existant et utilise l'installation native PrestaShop. Il écrit un journal privé. Aucun article ni stock n'est modifié. En cas d'arrêt après copie, conserver le bilan pour diagnostic ; une installation partielle n'est pas automatiquement supprimée.

Ouvrir après succès :
https://dev.watchys.ch/index.php?fc=module&module=watchysconfigurator&controller=configure

## Validation attendue dans DEV

- Ouverture de la page dans Alysum, chargement des 23 images et du récapitulatif de prix.
- Vérification du cadrage des composants et de l'aperçu de composition sur ordinateur et mobile. Les nouvelles vues n'ont pas encore été calibrées visuellement dans le thème réel ; le montage reste à valider.
- Vérification des trois formules, d'une quantité supérieure à 1 et d'une sélection comprenant un article désactivé.
- Aucun changement des fiches, stocks ni ancien configurateur.

Le prix est une base CHF HT ; l'affichage TTC, les devises, remises et frais de livraison seront raccordés avec le panier. Aucune estimation ne doit être présentée comme une commande possible dans cette version.

## Contrôles automatisés

- `php modules/watchysconfigurator/tests/catalog.php` : 2 880 calculs, formules, mouvement optionnel, rejet des sélections et quantités invalides.
- `php modules/watchysconfigurator/tests/service.php` : doubles de test pour stocks, références, articles désactivés, prix serveur et exclusion de la PROD.
- `npm test` : tests du prototype et du catalogue d'intégration.

Tests locaux PHP 8.2 via WebAssembly ; pas d'installation PrestaShop distante testée. Le contrôle de syntaxe sera répété avec le PHP du serveur DEV avant installation.

Documentation PrestaShop : https://devdocs.prestashop-project.org/8/modules/concepts/controllers/front-controllers/

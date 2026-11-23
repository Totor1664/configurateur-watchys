# Configurateur Watchys — DEV 0.1.1

Module de validation pour PrestaShop 8.2.x, exclusivement sur `dev.watchys.ch`, boutique 1. Aucun panier, commande, réservation ou décrément de stock n'est activé dans cette version.

## Changements de la version 0.1.1

- Aperçu Canvas proportionnel, sans étirement des PNG : parties du bracelet séparées, axes du cadran et des aiguilles, masquage de l'intérieur du boîtier lors de la composition. Les PNG approuvés ne sont pas modifiés.
- Un catalogue source : `integration/prestashop/catalog.json`. La copie `config/catalog.json` est générée uniquement dans le paquet distribué.
- Validation des montants, unités, modèles, formules, sources et géométries.
- JSON invalide → HTTP 400, méthode refusée → HTTP 405 ; erreurs internes masquées avec référence de journal. Prix et quantités restent vérifiés côté serveur.
- Rafraîchissement du statut des composants sélectionnés lors du devis et rejet des réponses asynchrones dépassées.
- Installation neuve, reprise d'une installation partielle connue, ou mise à jour 0.1.0 → 0.1.1. Dossier antérieur conservé avant publication ; modifications inconnues et liens symboliques refusés.

## Fonctions de validation

5 cadrans × 6 bracelets 20 mm × 8 jeux d'aiguilles × 4 carrures. Prix forfaitaires CHF HT : 190 sans mouvement, 510 avec mouvement, 610 avec mouvement et atelier. Les 12 fiches créées désactivées restent visibles en aperçu et signalées « À valider » ; aucune fiche n'est activée automatiquement.

La disponibilité est lue dans les articles PrestaShop, pas dans un stock fictif du kit. Cette lecture n'est pas une réservation et ne sécurise pas deux achats concurrents : le cycle de stock sera développé avec le panier.

## Installation sur DEV

Le ZIP léger `watchys-module-dev-0.1.1.zip` copie les 23 PNG depuis les dossiers privés déjà présents dans le dossier personnel SSH : `watchys-7750-variantes` et `watchys-carrures-7750-vues`. Leurs empreintes sont vérifiées ; les originaux et les JPEG des fiches restent inchangés.

1. Envoyer le ZIP dans le dossier personnel SSH, hors `web` et `sites`.
2. Extraire : `unzip -n "$HOME/watchys-module-dev-0.1.1.zip" -d "$HOME"`.
3. Contrôler : `php -l "$HOME/watchys-module-dev-0.1.1/installer.php"`.
4. Diagnostic sans installation : `php "$HOME/watchys-module-dev-0.1.1/installer.php" --verifier`.
5. Après contrôle du bilan, lancer `php "$HOME/watchys-module-dev-0.1.1/installer.php"` ; lire le plan et taper OUI pour l'appliquer.

Le diagnostic vérifie fichiers, syntaxe PHP, tests métier, sources PNG, boutique DEV, version de base, références et état du module existant. Il n'écrit ni dans le site, ni dans les journaux privés, ni dans MySQL. Les tests peuvent utiliser des fichiers temporaires du système.

L'installation écrit un journal dans `watchys-sauvegardes-privees`, conserve l'état natif du module, prépare un dossier neuf et conserve l'ancien dossier avant remplacement. Si la publication de fichiers échoue, elle tente de remettre l'ancien dossier en place. Si l'installation native échoue après publication, les nouveaux fichiers restent présents et le journal est marqué en échec ; une reprise est possible après diagnostic et nouvelle confirmation OUI. Cela ne constitue pas un rollback universel des effets des hooks natifs PrestaShop.

Les fichiers existants doivent correspondre aux versions reconnues 0.1.0/0.1.1 ou aux PNG vérifiés. `config.xml` généré par PrestaShop est conservé dans la sauvegarde. Un fichier modifié non reconnu entraîne un arrêt ; ne pas le supprimer pour forcer l'installation.

Aucun article, prix, stock, commande, thème ou ancien module n'est modifié par l'installateur. Seul le module Watchys est installé/activé/mis à jour. Ne pas utiliser ce paquet sur PROD.

Ouvrir après succès :
https://dev.watchys.ch/index.php?fc=module&module=watchysconfigurator&controller=configure

## Contrôles de développement

- `npm test` : prototype, correspondances et géométrie des 960 compositions.
- `npm run test:php` : PHP 8.2+ requis ; catalogue, disponibilité avec doubles, erreurs API, schéma et opérations de fichiers de l'installateur.
- `npm run test:build` : génération depuis la source canonique, déduplication entre modèles, empreintes et reproductibilité du ZIP.
- `npm run test:all` : les trois ensembles. `WATCHYS_PHP_BIN` permet d'indiquer un exécutable PHP alternatif.
- `python3 integration/prestashop/build-dev-module.py --output /chemin/watchys-module-dev-0.1.1.zip` : génération sans supprimer un ancien dossier de travail.

Les tests PHP locaux ont été exécutés avec PHP 8.2.35 via WebAssembly. Des rendus Canvas des variantes argentée et noire ont été examinés. L'installation native PrestaShop, l'API HTTP réelle et le rendu dans Alysum n'ont pas encore été validés sur DEV.

La compatibilité mécanique, la disponibilité commerciale des nouvelles fiches, l'affichage TTC, les devises, remises, livraison, panier et cycle complet de stock restent des étapes distinctes.

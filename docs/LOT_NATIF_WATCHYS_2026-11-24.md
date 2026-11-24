# Reprise — lot natif Watchys, 24 novembre 2026

## État confirmé sur DEV avant ce lot

- PrestaShop 8.2.7 ; module Watchys 0.2.2 actif. Rendu SVG approuvé, 29 PNG, 960 miniatures personnalisées, ajout au vrai panier et trois formules fonctionnels.
- Produits simples du modèle 7750 41 mm, bracelets 20 mm ; 24 composants activés manuellement, stock de test à 10.
- Deux tables de suivi réelles présentes et vides. Essais d’allocation et de concurrence isolés MySQL réussis, puis nettoyage confirmé.
- Essai réel ObjectModel/StockAvailable/Db sur copies temporaires réussi ; huit classes de stock publiées sans accroche ni surcharge.
- Commande et paiement Watchys bloqués. Aucun parcours complet de commande configurée confirmé à ce stade.

## Nouveau candidat préparé, non déployé

Source 0.3.0 ; archive privée `watchys-lot-natif-dev.zip`, construite par `integration/prestashop/dev-native-lot/build.py`.

Ce lot regroupe le raccordement aux frontières natives de commande et de statut, les écritures de stocks coordonnées et les retours reçus. Il conserve les activations à 0 et les protections du navigateur.

- Six surcharges du module, installées par l’API PrestaShop. Elles transmettent les appels natifs inchangés quand le suivi est inactif.
- Frontière transactionnelle sur le PDO natif ; verrous des composants triés, besoins agrégés des ventes ordinaires et des configurations, journal de reprise.
- Réconciliation des réservations natives et Watchys ; permis SQL exacts pour éviter l’écriture d’un objet périmé ou d’une connexion non coordonnée.
- Cinq tables de suivi supplémentaires, registre de 24 composants désactivé et deux triggers passifs.
- Adaptation explicite du `new Adapter\\StockManager()` dans la méthode de statut épinglée à PrestaShop 8.2.7. Le reste du corps natif est identique et testé contre la source de référence.
- Décoration Symfony du dépôt des mouvements : les appels directs du cœur sont aussi retenus avant le flush Doctrine.
- Emails, notifications et intentions de mouvements conservés dans une boîte de sortie transactionnelle, état `held`. Aucun transport ou libération automatique dans ce lot.
- Service interne de réception de retours, employé autorisé obligatoire, quantités cumulées bornées par la ligne et l’expédition. Aucune route publique de retour.

## Validation réalisée localement

64 tests JavaScript, dont les 960 compositions SVG. Tests PHP sur doubles et transactions SQLite : trois formules, reprises, frontières, permis exacts, effets retenus, réception partielle/cumulative et six surcharges passives. Construction reproductible du paquet et vérification que le rendu n’est pas publié.

Ces tests ne remplacent pas les essais MySQL et PrestaShop complets du serveur DEV. Le bilan d’application du paquet reste attendu. Ne pas marquer le lot installé ou le paiement disponible sans ce bilan.

## Application prévue

Le contrôle `preparer.php --verifier` est en lecture seule, sans bootstrap natif. L’application après lecture du plan et confirmation `OUI` :

1. Sauvegarde privée et schéma passif.
2. Essai SQL entre deux connexions sur neuf tables appartenant à cette exécution, puis suppression.
3. Publication du candidat, six surcharges, reconstruction des index et du cache Symfony.
4. Essai des vrais parcours natifs sur des clones privés sous transaction extérieure : trois formules, panier mixte, échec injecté après validation puis reprise, objet stock périmé, annulation/réactivation, paiement natif sans banque, expédition, retours et statut financier sans restockage automatique.
5. Annulation de toutes les données de test, relecture des stocks réels, maintien des activations à 0.

Les séquences auto-incrément peuvent avancer. Le journal identifie les fichiers sauvegardés, les objets de test et les surcharges créées. Une erreur déclenche une tentative de restauration ; les tables de suivi vides peuvent rester présentes. Si la restauration n’est pas confirmée, conserver le journal et examiner l’état avant reprise.

## Quatre étapes regroupées

1. **Lot natif actuel** : préparer le candidat et valider les parcours réels sous rollback sur DEV.
2. **Effets et paiements** : transport des notifications et mouvements, reprise après panne, parcours virement/PayPal/PostFinance en environnement de test, interface opérateur pour réactivation et retour reçu ; puis activation DEV contrôlée.
3. **Recette utilisateur** : achat complet, rupture partagée, commande ordinaire, annulation/retour, taxes et livraison, mobile.
4. **Préparation de la mise en production** : sauvegarde, stratégie de migration des seuls objets Watchys, procédure de retour arrière, accord explicite avant publication. Aucune opération PROD autorisée dans le présent lot.

## Contraintes à conserver

Articles simples, stock manuel, boutique unique. Refus des packs et déclinaisons dans les paniers coordonnés. Ne pas recopier le catalogue DEV intégral sur PROD. Ne pas réactiver les anciens installateurs 0.1.x/0.2.x : leurs hypothèses ne couvrent pas ce candidat. Ne pas modifier le rendu approuvé, les miniatures ni les prix 190/510/610 CHF HT.

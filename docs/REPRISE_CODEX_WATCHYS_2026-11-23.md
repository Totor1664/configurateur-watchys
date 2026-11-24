# Point actuel — 24 novembre 2026 : concurrence isolée préparée

L’allocation sur copies MySQL TEMPORARY a été **confirmée réussie sur DEV**. Les stocks réels et les deux tables de suivi sont restés inchangés. Commande et paiement restent bloqués.

Nouvelle brique expérimentale non publiée dans le module : `WatchysNativeStockCoordinator`, frontière de transaction verrouillant avant le chargement du stock natif. Elle vérifie les deltas puis additionne les réservations ordinaires et Watchys, avec lectures courantes verrouillées des lignes natives. Le callback doit avoir fini ses écritures de commande/détails/statuts sur le même PDO, sans commit ni effet externe.

Tests locaux : réservations cumulées, rupture avant callback, rollback après callback échoué ou débit périmé, expédition ordinaire conservant les réservations Watchys. Le fichier natif 8.2.7 épinglé a aussi été exécuté localement avec dépendances simulées et stockage SQLite. Cela n’est pas une preuve de concurrence MySQL.

**Prochaine opération :** paquet privé `integration/prestashop/dev-stock-concurrency/`, `controler.php --verifier`, bilan puis exécution distincte avec OUI. Sept tables de fixtures InnoDB au préfixe aléatoire sont partagées entre deux connexions, puis supprimées. Aucune table préexistante supprimée ni stock réel écrit. Témoin périmé puis course sur dernière unité : vente native gagnante, configuration gagnante, deux configurations distinctes. Résultat de ce test **pas encore reçu**.

Le vrai fichier Core StockManager du DEV est chargé sans bootstrap, avec produit, StockAvailable, services, hooks/cache simulés. Aucun véritable checkout, email, mouvement ou paiement. Les écrivains du site ne sont pas encore raccordés. Avant déploiement : concevoir leur adaptation (y compris BO et SQL direct), caches/chargements antérieurs, réservations, mouvements/alertes, commandes fractionnées et callbacks. Ne pas confondre le verrou testé par fixtures et une intégration complète.

---

# Point actuel — 24 novembre 2026 : allocation isolée préparée

Le socle a été **confirmé installé sur DEV par Watchys** : les deux tables `watchys_order_component` et `watchys_stock_ledger` sont présentes et vides ; test MySQL du journal annulé avec succès. Aucun stock réel ou hook modifié. Module toujours 0.2.2, commande et paiement bloqués.

Nouvelle étape codée : `WatchysStockAllocator` (lot transactionnel avec savepoint et contrôle de stock cumulé), `WatchysOrderNomenclature` (pièces depuis instantanés enregistrés, quantité de la vraie ligne native, pas celle de tout le panier). Ces classes ne sont pas publiées dans le site ni raccordées aux commandes.

Les tests locaux prouvent le rollback SQLite après une écriture partielle injectée, la rupture sur pièces partagées, les reprises et les transitions. Les 2 880 nomenclatures sont contrôlées. Ils ne prouvent pas la concurrence MySQL ni la coordination des ventes natives.

**Prochaine opération :** paquet privé `integration/prestashop/dev-stock-allocation/`, `controler.php --verifier`, bilan puis exécution distincte avec OUI. Il crée seulement trois tables TEMPORARY de connexion, copie les stocks, teste sur ces copies et annule les scénarios. Aucun stock réel écrit, aucune table persistante créée, aucun module publié. Résultat MySQL **confirmé réussi par Watchys** : scénarios sur copies temporaires annulés, stocks et socle réels inchangés.

Avant tout déblocage : coordonner les écrivains natifs (lecture puis mise à jour), réservations recalculées, mouvements/alertes/caches, création de commandes fractionnées et callbacks, puis essais concurrents réels. Ne pas considérer ce test isolé comme un checkout prêt.

---

# Reprise actuelle — Configurateur Watchys — 23 novembre 2026

Ce point complète les documents historiques de préparation. Ne pas déduire l’état actuel des anciennes mentions « module non installé » ou « aperçu provisoire ».

## État confirmé par Watchys

- Module 0.1.1 présent et actif sur DEV ; fichiers, rôles et PNG contrôlés après un arrêt de l’installation native. Ne pas relancer son ancien installateur.
- Les vues de face des quatre carrures ont été remplacées avec les PNG originaux fournis, également sur les fiches produits ; autres vues conservées.
- Les premières corrections Canvas ont dégradé les proportions. Elles ont été remplacées par le moteur SVG exact de la page GitHub `/validation/`, branche gh-pages commit faec62714c571ff78ff3b27a389727b7c45921f6.
- Watchys a validé le résultat visuel comme parfait. Les 29 PNG (23 composants et six bracelets fermés) sont désormais le socle à conserver. Ne pas revenir aux anciennes dimensions du Canvas ni normaliser séparément chaque ouverture de carrure.

## Développement actuel — préparé, pas installé

Version 0.2.0 : bouton Ajouter ma configuration au panier, route POST avec jeton de session et refus des origines externes, prix et nomenclature calculés sur le serveur, personnalisations PrestaShop natives et produit technique masqué par modèle. Trois tables du module conservent supports, instantanés de configuration et requêtes de reprise.

La personnalisation porte le prix complet ; le support a un prix natif nul et n’est jamais ajoutable sans configuration connue. Les trois formules restent 190/510/610 CHF HT avant règles natives de la boutique. Les 960 variantes ne produisent pas des milliers de déclinaisons. Les articles désactivés restent exclus des ajouts.

Contrôle cumulé des composants partagés entre lignes et achats séparés ; protection contre doubles clics et reprises ; retour arrière transactionnel en cas d’échec natif. Les tests de service utilisent des doubles, pas un vrai serveur MySQL ou PrestaShop.

**Commande et paiement bloqués pour les paniers Watchys** : pas encore de décrément de composants, réservation, cycle d’annulation/remboursement/retour ou garantie de concurrence entre commandes. Ne pas enlever ces gardes avant la phase suivante. Les paniers ordinaires sans configuration Watchys restent hors de ce blocage.

## Construire et contrôler

```bash
python3 integration/prestashop/build-cart-dev.py --output ../watchys-panier-dev-0.2.0.zip
npm run test:all
```

PHP 8.2 est requis pour les contrôles PHP (`WATCHYS_PHP_BIN` permet un exécutable alternatif). Les suites passent : 64 tests Node, comparaison des 960 arbres SVG de référence, 2 880 choix/formules, pont panier avec doubles natifs et gardes, erreurs HTTP/JSON, deux contrôles de construction reproductible et syntaxe des 31 fichiers PHP.

Le constructeur historique 0.1.1 est volontairement désactivé : il ne doit pas emballer le code actuel avec un ancien installateur.

Lire `integration/prestashop/dev-cart/README.md` pour le plan, OUI avant écriture, la sauvegarde et les étapes de validation serveur. Le paquet ne remplace ni le catalogue ni les images installées et ne réinstalle pas le module. Le bootstrap natif de la migration initialise AppKernel.

## Prochaine action

Faire exécuter `installer.php --verifier` sur DEV, sans bootstrap PrestaShop ni écriture. Après bilan réussi, exécution séparée avec le plan et OUI. Attendre le résultat : ne pas considérer 0.2.0 installée avant confirmation. Ensuite tester un ajout réel sur une configuration active, les personnalisations et prix dans Alysum, les quantités, suppressions, reprises et gardes de commande.

Après validation de ce premier panier, concevoir et tester le cycle réel de stock avant paiement : concurrence, pièces communes, ventes à l’unité, annulations et retours sans double décrément. Les regroupements de panier à la connexion et thèmes de paiement alternatifs nécessitent aussi des essais sur le vrai DEV.

Ne pas toucher à la PROD, ne pas modifier le rendu validé et ne pas activer automatiquement les nouvelles variantes.

## Reprise 0.2.1 après la tentative panier

Watchys a exécuté la vérification du paquet 0.2.0 avec succès, puis confirmé l'application. La préparation native a échoué sur MySQL 1064 près de LIMIT 1. Le script a indiqué avoir rétabli le code antérieur. L'installation du panier ne doit pas être considérée réussie.

Cause identifiée dans le code officiel PrestaShop 8.2.7 : Db::getRow() ajoute automatiquement LIMIT 1 ; l'appel du verrou sur watchys_model_support formait FOR UPDATE LIMIT 1. Ce verrou est désormais lu avec executeS(...,true,false), sans limite implicite ni cache. Les lectures métier du panier et les vérifications de migration sont également sans cache SQL.

Le paquet 0.2.1 contrôle en lecture seule les trois tables éventuelles, leurs volumes, la référence technique et le drapeau de préparation. Il refuse de reprendre automatiquement un état natif partiel non vide avec un module encore 0.1.1. Les tables vides déjà créées peuvent être réutilisées ; aucune suppression de table ou de fiche n'est prévue.

Un nouveau test reproduit le LIMIT implicite du cœur, couvre la création, la reprise sans doublon et le retour arrière. Il est exécuté avec les autres tests isolés dans le contrôle serveur. 32 fichiers PHP sont désormais vérifiés syntaxiquement.

Prochaine action : utiliser uniquement watchys-panier-dev-0.2.1.zip et son installer.php --verifier, puis attendre le bilan avant OUI. Ne pas relancer le paquet 0.2.0. Aucun vrai panier ni cycle de stock n'est encore confirmé sur DEV.

## Reprise 0.2.2 — vérification du registre d'accroches

Le contrôle 0.2.1 a confirmé les trois tables vides, la fiche technique absente et le module encore 0.1.1. Après OUI, la préparation a été arrêtée à « Accroche non relue : displayCustomization ». Le script a indiqué avoir restauré le code antérieur. Ne pas considérer le panier installé.

La méthode native isRegisteredInHook() utilise la résolution des noms et alias en cache. Le code officiel 8.2.7 construit notamment une clé concaténée pour les identifiants avec alias ; les invalidations de création ciblent d'autres noms de clés. Une relecture périmée est donc une cause possible, à distinguer d'une association réellement absente.

La version 0.2.2 conserve registerHook() natif, rafraîchit les clés du registre avant et après chaque enregistrement, conserve l’invalidation native des caches sur les écritures et contrôle directement en SQL non caché les cinq associations exactes nom/module/boutique et leur activation. Elle journalise les lignes relues et refuse une association réellement manquante ou non unique. La vérification préalable affiche également l'état des cinq accroches et s'arrête si une accroche globale est inactive.

Le test de préparation simule désormais une relecture native périmée et prouve qu'une association réellement absente reste bloquante. Les autres contrôles et les protections de commande restent conservés. Prochaine action : uniquement le paquet 0.2.2 et son --verifier, puis attendre le bilan avant toute nouvelle application.

# Cycle de stock Watchys — préparation du 24 novembre 2026

## Périmètre et état

Le rendu, les prix des trois formules, le panier natif et ses miniatures personnalisées sont validés sur DEV. Les 24 composants existent et Watchys confirme leur stock de 10 et l'activation manuelle des variantes. L'ordre Carrure / Cadran / Aiguilles / Bracelet et le survol gris ont été publiés avec succès ; le titre « Composez votre montre » a ensuite été demandé et accepté.

Le raccordement du cycle réel de stock **n'est pas encore développé ni déployé**. Les gardes de commande et de paiement restent actives. Aucun article, stock, commande, module de paiement ou réglage PROD n'est modifié par cette phase.

## Règles métier proposées

| Événement | Stock disponible des composants | Précision |
| --- | --- | --- |
| Aperçu ou ajout au panier | Aucun retrait | Un panier ne réserve pas durablement les pièces. |
| Création d'une commande hors erreur/annulation | Retrait une seule fois | Inclut une attente de virement ; dernière vérification de disponibilité avant acceptation. |
| Paiement confirmé ou nouveau passage de la notification | Aucun retrait supplémentaire | La même commande ne doit jamais consommer deux fois ses pièces. |
| Expédition | Aucun deuxième retrait du disponible | Tracer la sortie physique et solder les réservations correspondantes. |
| Annulation / erreur avant expédition | Restitution une seule fois si des pièces avaient été retirées | Pas de restitution pour une commande initialement en erreur sans retrait. |
| Réactivation après annulation | Nouvelle vérification puis nouvelle allocation | Refuser si les composants nécessaires ne sont plus disponibles. |
| Remboursement après expédition | Aucune restitution automatique | Un remboursement ne prouve pas un retour physique. |
| Retour effectivement reçu et contrôlé | Restitution explicite des quantités acceptées | Traiter partiel et complet, sans double mouvement. |

Une montre utilise 1 carrure, 1 cadran, 1 jeu d'aiguilles et 1 bracelet. Ajouter 1 mouvement uniquement pour les formules avec mouvement et atelier. L'emboîtage est une prestation sans stock physique. Une quantité de deux montres double les besoins. Le produit technique 8867 est un support commercial, pas le stock de montres terminées.

Les règles seront associées aux IDs/indicateurs réellement trouvés sur DEV, sans supposer que les numéros ou libellés des statuts sont ceux d'une installation standard. Aucun délai d'expiration automatique pour les virements n'est décidé dans cette phase.

## Audit du cœur PrestaShop 8.2.7

Sources primaires inspectées :

- [PaymentModule.php](https://github.com/PrestaShop/PrestaShop/blob/8.2.7/classes/PaymentModule.php)
- [OrderDetail.php](https://github.com/PrestaShop/PrestaShop/blob/8.2.7/classes/order/OrderDetail.php)
- [OrderHistory.php](https://github.com/PrestaShop/PrestaShop/blob/8.2.7/classes/order/OrderHistory.php)
- [Core StockManager.php](https://github.com/PrestaShop/PrestaShop/blob/8.2.7/src/Core/Stock/StockManager.php)
- [Adapter StockManager.php](https://github.com/PrestaShop/PrestaShop/blob/8.2.7/src/Adapter/StockManager.php)

Constats concrets :

1. PaymentModule crée l'Order puis les OrderDetail. OrderDetail retire la quantité, sauf statut initial PS_OS_CANCELED ou PS_OS_ERROR. L'accroche actionValidateOrder est exécutée après ces créations : s'y contenter d'un test de disponibilité est trop tard pour garantir une commande sans incohérence.
2. OrderHistory gère déjà les transitions erreur/annulation des produits des lignes natives. Un composant figurant uniquement dans la nomenclature Watchys n'est pas une ligne native : son retrait et sa restitution doivent avoir un suivi distinct, sans interférer avec les composants vendus à l'unité.
3. Core StockManager lit la quantité dans StockAvailable, applique le delta à l'objet et écrit l'objet ; ce n'est pas, à lui seul, un contrôle conditionnel de disponibilité d'une nomenclature complète. Deux commandes concurrentes et une vente à l'unité doivent partager une stratégie de verrouillage cohérente. Un verrou seulement utilisé par les commandes Watchys ne suffit pas à prouver la protection face aux autres écrivains natifs.
4. Adapter StockManager calcule les réservations et le physique depuis les OrderDetail natifs. Les composants invisibles d'une montre n'y figurent pas. Les futures réservations Watchys devront compléter ce mécanisme sans être effacées par un recalcul natif et sans écraser les réservations des ventes à l'unité.
5. actionOrderStatusPostUpdate précède encore le recalcul physique natif dans OrderHistory. Un recalcul fait dans cette seule accroche pourrait donc être écrasé ensuite : le point de synchronisation doit être établi et testé sur le vrai cycle.
6. Le support technique a actuellement une disponibilité propre distincte des composants. Les réglages PS_ENABLE_BACKORDER_STATUS et l'état StockAvailable du support doivent être examinés pour éviter un faux statut « rupture » déclenché par le support alors que les pièces sont disponibles. Ne pas désactiver globalement ce réglage pour toute la boutique.
7. Plusieurs Order peuvent être créés pour un même panier. Le journal et les quantités doivent être rattachés aux vraies lignes de commande et à id_customization, pas retirer tous les composants du panier à chaque appel d'actionValidateOrder.

Le rapport distingue ces constats des choix d'implémentation encore à arrêter. Il ne promet pas qu'une paire d'accroches suffit à assurer l'atomicité, et ne prévoit pas de mettre aveuglément tout PaymentModule (notifications et e-mails compris) dans une transaction longue.

## Architecture à préciser avant migration

- Nomenclature immuable par ligne commandée, recopiée depuis la configuration enregistrée et validée par le serveur ; prix commerciaux natifs conservés.
- Journal de mouvements unique par opération, ligne de commande et composant, avec quantités cumulées retirées/restituées. Les noms de futures tables du diagnostic sont indicatifs, pas une migration préparée.
- Allocation du besoin cumulé et verrouillage dans un ordre stable, couvrant pièces partagées, ventes séparées et concurrence native. Aucune quantité négative acceptée pour un composant Watchys.
- Retries de passerelles, transitions répétées, échecs et reprises : opérations idempotentes et état avant/après contrôlable. Ne pas lever les gardes avant preuve sur DEV.
- Les réservations et le physique doivent inclure les montres ainsi que les commandes ordinaires, et conserver les mouvements natifs/alertes de stock appropriés.
- Aucune fausse ligne commerciale de composant à prix nul ne sera ajoutée uniquement pour contourner le calcul de stock : la facture reste celle de la montre configurée.
- Retour partiel, annulation après expédition, changement de quantité au back-office et réactivation doivent avoir une décision explicite, pas une restitution indistincte à chaque statut remboursé.

## Étapes suivantes

1. Exécuter le contrôle `integration/prestashop/dev-stock-audit/controler-cycle-stock.php` en lecture seule sur DEV et examiner son bilan : statuts, portées de configuration, paiements candidats, moteurs/colonnes et accroches.
2. À partir de ce bilan, fixer les correspondances de statuts et le mécanisme transactionnel/réservation réellement compatible, puis coder la politique isolée et son journal avec tests.
3. Préparer séparément le plan de migration et les contrôles préalables. Garder DÉBUT/FIN, journal et OUI avant écriture. Déployer d'abord les mécanismes de stock testables avec les gardes de checkout toujours actives.
4. Tester commandes simultanées sur la dernière pièce, composants vendus à l'unité, plusieurs montres d'un panier, quantité >1, notifications répétées, échec en cours de traitement, trois formules, erreur/annulation/réactivation, expédition et retour partiel. Vérifier disponible/réservé/physique et absence de double mouvement.
5. Pour les essais natifs de commande, préparer un mode de paiement de test adapté au DEV et contrôler les e-mails/notifications avant de lancer un scénario. Le diagnostic n'a ni lu de secrets de passerelle ni confirmé leur mode test.
6. Examiner aussi la fusion du panier à la connexion avant de considérer le parcours achetable complet. La PROD reste hors périmètre ; son déploiement nécessite une étape distincte.


## Résultat du contrôle DEV reçu et première brique codée

Le bilan confirme PrestaShop 8.2.7, gestion de stock active, gestion avancée inactive, refus global des ventes hors stock. Les 12 anciens composants héritent de ce refus (out_of_stock=2) et les 12 nouveaux le fixent explicitement à 0. Tous les composants sont actifs et commandables, avec disponible/physique=10 et réservé=0. Les tables nécessaires sont InnoDB et les colonnes de personnalisation des lignes de commande existent. Aucune commande Watchys n'existe encore. Les deux tables de suivi ne sont pas présentes.

Paiements actifs candidats : PayPal 6.4.5, PostFinance Checkout 1.2.51, virement natif 2.1.3. Aucun mode test/réel n'a été validé et aucun secret lu dans les configurations de paiement. PS_ENABLE_BACKORDER_STATUS ne figure pas dans les valeurs renvoyées : ne pas interpréter son absence comme une validation de son comportement effectif. Le support technique reste à zéro avec vente hors stock autorisée ; son risque de faux statut de rupture reste à traiter lors du raccordement.

La politique isolée utilise les 27 statuts exacts. Annulation 6/erreur 8 libèrent le non-expédié. Expédié 4/livré 5 consomment le physique. Remboursements 7/18/21 ne restituent pas automatiquement (le statut 21 est logable mais demeure financier). Les autres statuts connus réservent, dont virement 10 et payé PostFinance 23. La réactivation après annulation exige un booléen serveur explicite. Aucun remboursement, même avant expédition, ne libère sans événement de cancellation explicite dans cette première politique.

Les classes WatchysStockPolicy, WatchysStockSchema et WatchysStockJournal sont codées et testées, mais non reliées au module installé. Le journal nécessite une transaction du demandeur, lie sa nomenclature à une vraie identité future de ligne et retourne les deltas sans jamais modifier stock_available. Il garde référence d'événement, état avant/après, révisions, date et empreinte ; une reprise renvoie les compteurs courants et des deltas nuls, sans réappliquer l'ancien état.

Les tests couvrent 2 880 configurations/formules et des transactions SQLite réelles en mémoire : paiement sans second retrait, annulation/réactivation, expédition, retours partiels/cumulatifs, notification ancienne, conflit d'événement, nomenclature modifiée et rollback. Cela ne prouve pas les verrous MySQL ni la cohérence des écrivains natifs.

Le paquet `integration/prestashop/dev-stock-foundation/` prépare uniquement les deux tables dédiées et vides. Aucun code publié dans le site, hook ajouté, stock réel ni commande modifié. Après OUI, un test du journal sur les deux tables MySQL utilise des références fictives dans une transaction ensuite annulée ; les tables doivent rester vides. Les CREATE TABLE ne sont pas annulables : une création partielle doit être relue et jamais supprimée automatiquement. Ce socle a depuis été **confirmé installé sur DEV** : les deux tables sont vides et le test MySQL annulé a réussi.

La classe Db native 8.2.7 expose getLink(). Le futur adaptateur peut donc vérifier et utiliser le même PDO natif, plutôt qu'une connexion parallèle ; cela reste à intégrer et tester, pas une preuve de sécurité du checkout. Il faudra coordonner journal, débits, écrivains ordinaires et calcul des réservations avant de lever les gardes.


## Allocation isolée — étape suivante préparée

`WatchysOrderNomenclature` vérifie le lien panier/personnalisation/support et la concordance des composants enregistrés avec le catalogue. Une ligne native porte sa propre quantité : une commande fractionnée ne doit pas consommer tout le panier. Un changement historique de mapping échoue explicitement au lieu de réattribuer des pièces.

`WatchysStockAllocator` prend le PDO et la transaction du demandeur. Il verrouille les SKU dans un ordre stable, journalise les transitions, valide le besoin cumulé avant les débits et vérifie les compteurs après écriture. Un savepoint annule le lot sans perdre un travail antérieur dans la transaction appelante. La révision du journal et la clé de requête empêchent un second retrait. Un recalcul natif ayant effacé des réservations Watchys est détecté et refusé, pas réparé silencieusement.

Tests locaux : 2 880 nomenclatures, rupture d’une pièce partagée entre deux configurations, répétition, paiement, annulation/réactivation, expédition/retours, snapshot étranger/altéré, panne SQL au deuxième débit après modification du premier, rollback journal + stock préservant le travail extérieur. SQLite n’est pas une preuve de concurrence MySQL.

Le paquet `dev-stock-allocation` teste le même allocateur sur MySQL TEMPORARY : copies privées à la connexion, aucun débit réel, rollback des scénarios, socle réel relu vide. Il ne publie aucun code dans le module. Sa vérification et son application sont désormais confirmées réussies sur DEV ; stocks réels et socle sont restés inchangés.

Les écrivains natifs ordinaires peuvent lire une ancienne quantité avant un verrou et la réécrire ensuite : les verrous et mises à jour conditionnelles de cet allocateur ne suffisent pas à les coordonner. L’adaptateur commun reste à développer, ainsi que la cohérence des réservations natives, caches, mouvements, alertes et commandes/paiements. Gardes de checkout conservées.


## Coordinateur et preuve concurrente isolée préparés

La course native nécessite un verrou AVANT le chargement de StockAvailable. Le conteneur historique IoC de PrestaShop expose bind/make mais refuse de réassocier un service existant : aucune substitution par réflexion ou modification du cœur n’a été déployée. Les points d’adaptation de tous les écrivains restent à concevoir.

`WatchysNativeStockCoordinator` encadre un callback SQL complet sous la transaction du demandeur. Il verrouille les SKU dans un ordre stable, refuse un disponible négatif avant callback, contrôle le débit annoncé et réconcilie réservé = commandes ordinaires non expédiées + réservations Watchys ; physique = disponible + réservé. Les lectures natives utilisent FOR UPDATE afin de ne pas dépendre d’un instantané REPEATABLE READ périmé. Les IDs annulation/erreur 6/8 proviennent du contrat DEV figé. Une panne ou un débit différent revient au savepoint.

Contraintes : même PDO, aucune fermeture de transaction, écritures des lignes/statuts terminées avant retour et aucun effet externe dans le callback. Le garde-fou détecte un commit interdit mais ne peut pas annuler un commit déjà effectué. Les emails, alertes, caches, mouvements, objets précédemment chargés et écrivains non coopérants ne sont pas intégrés par cette classe. Aucune accroche ne l’installe. Les conflits et deadlocks doivent être traités dans l’adaptateur futur.

Le paquet privé `dev-stock-concurrency` crée sept tables de fixtures partagées entre connexions, au préfixe aléatoire, puis supprime uniquement celles créées par son invocation. Le journal suit les noms en cas d’interruption. Pas de TRUNCATE ni de DROP sur les noms réels. Le vrai Core StockManager 8.2.7 est lu sur DEV après SHA256 ; ses dépendances sont des doubles, aucune classe ObjectModel native ou paiement n’est démarré. Le chargement du cœur conserve sa méthode de calcul et ses branches sans modifier le fichier du site.

Le parent synchronise deux processus par pipes, reproduit le témoin de valeur périmée, puis exige un timeout de verrou de l’autre connexion pendant que le gagnant reste bloqué à la barrière. Après commit du gagnant, une nouvelle tentative du perdant doit être refusée pour rupture. Les deux sens et deux configurations distinctes sont couverts. Cette preuve MySQL est **confirmée réussie par Watchys**, avec fixtures supprimées et stocks/socle réels inchangés. Elle ne remplace pas un test du vrai checkout après raccordement.


## Service de commandes natif préparé ; compatibilité à relever

`WatchysOrderStockService` lit via PDO les vraies tables `orders`, `order_detail`, `order_history`, `customization`, `watchys_configuration` et `watchys_model_support`. Il exige la transaction du demandeur, contrôle le rattachement du snapshot et le cumul des quantités natives fractionnées, puis traite uniquement le dernier historique concordant avec current_state. Une entrée étrangère est refusée ; une entrée ancienne est ignorée. La nomenclature d'une ligne déjà allouée ne peut pas changer silencieusement. Prix, commandes et statuts ne sont pas réécrits. Le savepoint de service et celui d'allocateur annulent leurs écritures sans commettre le travail du demandeur.

L'entrée `fromNative` exige PrestaShop 8.2.7 en DEV et reprend le PDO maître public getLink, sans ouvrir une seconde connexion. Cette entrée n'est encore appelée par aucun hook : sa compatibilité sur le vrai runtime reste à prouver. Le service n'est pas publié dans le module installé. Les tests locaux utilisent SQLite et les schémas/états de commande représentatifs, y compris commandes fractionnées, historique ancien, double notification, annulation/réactivation/expédition, mauvais rattachements et rollback. Les gardes existantes restent inchangées.

Avant de choisir une surcharge ou décoration, le paquet `dev-stock-native-readiness` relève en lecture seule les surcharges de StockAvailable/PaymentModule/OrderDetail/OrderHistory/Db, leurs méthodes et empreintes, les configurations de services candidates et les accroches liées. Présence sur disque et références textuelles ne prouvent pas un chargement effectif. Le relevé n'exécute ni code de surcharge ni index de classes, ne publie aucun fichier du site et ne modifie aucune donnée. Son bilan n'est pas encore reçu.

Le découpage natif reste une contrainte structurante : OrderDetail retire la quantité avant save, OrderHistory termine certains recalculs après des hooks. Le futur adaptateur devra englober correctement les écritures nécessaires, traiter les effets externes séparément et couvrir les autres écrivains ; ce service de liaison ne prétend pas résoudre seul cette frontière.

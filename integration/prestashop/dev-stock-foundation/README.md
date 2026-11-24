# Socle du cycle de stock — préparation DEV

Cette étape ne raccorde pas les stocks aux commandes. Le module installé reste 0.2.2, ses gardes restent actives et aucun fichier du site n'est publié. Trois classes privées sont exercées : politique des statuts, schéma et journal transactionnel. Deux tables dédiées et vides constituent le seul ajout persistant prévu sur DEV.

## Règles testées

- Réserver le disponible à la création d'une commande hors annulation/erreur, y compris attente de virement ; ne rien retirer à l'ajout au panier.
- Les paiements acceptés, dont PostFinance 23, ne retirent pas deux fois les pièces.
- Annulation 6 / erreur 8 : libérer les unités non expédiées, une fois. Toute réactivation exige une décision serveur explicite ; un nouveau statut de paiement après annulation ne suffit pas.
- Expédié 4 / Livré 5 : solder les réservations et tracer la sortie physique, sans deuxième retrait du disponible. L'indicateur livraison du statut 3 ne doit pas être pris pour une expédition.
- Remboursements 7 / 18 / 21 : aucun retour physique automatique. Même avant expédition, il faut un événement d'annulation explicite pour libérer les unités encore réservées.
- Réception contrôlée : total cumulatif accepté, borné aux unités expédiées ; les reprises et anciens totaux ne restituent pas deux fois.

Les quantités retournées ne redeviennent pas une obligation d'expédier sur la même ligne. Un échange/réexpédition nécessitera une opération métier distincte. Les statuts inconnus sont refusés. Les événements sont identifiés côté serveur ; le journal refuse de réutiliser une référence d'événement avec un autre contenu.

## Limites explicites

Le journal écrit uniquement ses deux tables et doit être appelé dans une transaction ouverte par son demandeur. Il retourne des deltas ; il ne les applique jamais à stock_available. Le futur adaptateur doit appliquer les deltas réels et le journal dans une transaction cohérente, gérer les écrivains natifs et identifier les vraies lignes de commande. Utiliser cette classe seule ne réserve aucune pièce et ne rend pas le checkout sûr.

Les règles de concurrence entre commandes/ventes à l'unité, les réservations complémentaires aux calculs natifs, les annulations natives, les notifications de paiement et la fusion de panier restent à implémenter/intégrer. Une connexion/transaction au journal indépendante des écritures natives ne suffit pas. Les essais de paiement réels sont exclus de ce paquet.

## Vérifier puis préparer

Extraire le ZIP dans le dossier personnel privé, hors du site. Lancer `php preparer.php --verifier`. Le script vérifie le paquet, exécute les tests de politique isolés, lit le contrat des 27 statuts/configurations DEV, le module et les gardes, puis contrôle les tables existantes éventuelles. Aucun bootstrap PrestaShop, écriture MySQL/site/fichier privé ou test d'insertion dans ce mode.

Après lecture du bilan, lancer séparément `php preparer.php` et confirmer OUI. Un journal privé est créé. Seules les deux tables absentes sont créées ; aucun ALTER/DROP, aucune ancienne commande ni mouvement existant modifié. Les tables existantes doivent être compatibles et vides.

CREATE TABLE n'est pas transactionnel dans MySQL : une table peut rester présente après un arrêt. Le script ne prétend jamais l'avoir annulée. Conserver le journal et relancer --verifier avant reprise.

Ensuite, le journal est testé sur la vraie connexion MySQL, exclusivement dans ses nouvelles tables avec des références fictives. La transaction vérifie reprise, expédition et retour, puis est annulée par ROLLBACK ; les tables sont relues vides. Les séquences auto-incrément peuvent avancer. Aucun stock ni commande réel n'est utilisé ou modifié par ces essais.

## Tests locaux

`npm run test:all` couvre 2 880 configurations/formules pour la politique, les états incohérents, erreurs/annulations, réactivation et retours. Les tests du journal utilisent une vraie base SQLite en mémoire, avec transactions, reprises, conflits, nomenclature immuable et retour arrière. Ils nécessitent PDO SQLite localement, sans établir de connexion MySQL. Ils ne prouvent pas les verrous MySQL : le test technique MySQL du paquet après OUI ne prouvera pas non plus la concurrence du cycle natif complet.

## Construire

`python3 integration/prestashop/dev-stock-foundation/build.py --output watchys-socle-stock-dev.zip`

Le paquet contient les classes et tests privés, pas un installateur du module. Après succès de ce socle, l'étape suivante sera l'adaptateur d'allocation et sa coordination avec les ventes natives, toujours avant de lever les gardes du checkout.

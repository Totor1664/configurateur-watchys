# Test isolé d’allocation DEV

Paquet privé en HOME, hors des dossiers du site. Ne publie aucune classe dans le module installé. Ne déverrouille ni commande ni paiement.

1. `php -l "$HOME/watchys-allocation-stock-dev/controler.php"`
2. `php "$HOME/watchys-allocation-stock-dev/controler.php" --verifier`
3. Examiner le plan, puis exécuter sans `--verifier` et saisir exactement OUI.

Le contrôle initial lit uniquement DEV et vérifie module, catalogue, gardes, statuts, socle présent et vide. Après OUI : journal privé et trois tables MySQL TEMPORARY avec préfixe aléatoire privé à la connexion. Copie des stocks des composants vers ces tables ; seules les copies sont écrites. Les scénarios sont annulés par ROLLBACK. Aucun bootstrap PrestaShop, aucune écriture du site, aucun stock réel écrit et aucune table persistante créée. La fermeture de la connexion détruit les copies.

Le nouveau moteur prend les pièces ensemble dans une transaction fournie par son appelant. Une rupture ou une erreur SQL annule le lot par savepoint. Les références de commandes et personnalisations sont des fixtures dans les seules tables temporaires. Les quantités des copies sont normalisées pour des scénarios reproductibles, sans modifier les quantités sources.

Ce test ne prouve pas la concurrence entre connexions MySQL, ni la coordination avec les écritures natives de PrestaShop. Les classes ne sont pas encore reliées aux commandes. Restent à développer : coordination des ventes ordinaires, réservations natives, caches, mouvements/alertes, création des commandes fractionnées et callbacks de paiement. Aucun déblocage avant ces validations.

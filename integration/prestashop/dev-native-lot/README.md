# Lot natif Watchys DEV — candidat 0.3.0 passif

Ce paquet poursuit le module installé 0.2.2 et les huit classes déjà préparées. Il ne remplace ni le configurateur validé ni son catalogue.

## Contrôle préalable

Téléverser le ZIP dans le HOME FTP privé, puis :

```sh
unzip -n "$HOME/watchys-lot-natif-dev.zip" -d "$HOME"
php -l "$HOME/watchys-lot-natif-dev/preparer.php"
php "$HOME/watchys-lot-natif-dev/preparer.php" --verifier
```

Ne pas exécuter d’ancien installateur. Lire le bilan avant application. L’application se lance ensuite par le même script sans `--verifier`, et exige `OUI` sur le terminal avant toute écriture.

## Périmètre

- DEV PrestaShop 8.2.7 exclusivement ; identification SQL du domaine avant chaque bootstrap natif.
- Transactions, nomenclatures enregistrées, verrous de composants et reprises sans doublon.
- Six surcharges passives du module : Hook, Mail, PaymentModule, OrderDetail, OrderHistory et StockAvailable. Aucun fichier du cœur remplacé.
- Décoration Symfony du dépôt des mouvements pour retenir aussi les appels directs du cœur utilisant Doctrine.
- Cinq tables supplémentaires, deux triggers sur le stock et registre initial de 24 composants **désactivé**. Le contrôle SQL des quantités exige un permis exact pour la connexion et les valeurs avant/après.
- Trois formules, paniers mixtes, annulation, réactivation explicite, paiement natif par virement sans transaction bancaire, expédition et retours reçus cumulés.
- Notifications, emails et intentions de mouvements retenus dans une boîte de sortie transactionnelle ; aucun transport automatique n’est fourni dans ce lot.

## Essais sur DEV après OUI

Le premier essai crée neuf tables isolées et deux triggers sous un préfixe aléatoire. Deux connexions vérifient les permis et la dernière pièce. Seuls les objets créés par cette exécution sont supprimés.

Le second utilise de vrais objets et parcours PrestaShop sur des clones privés : 24 composants, un support, un transporteur, un client et des commandes. Une transaction extérieure est annulée à la fin. Les articles, stocks et commandes existants doivent rester inchangés. Les séquences auto-incrément peuvent avancer. Les notifications et emails sont retenus ; aucun moyen de paiement externe n’est appelé.

Un journal privé conserve les fichiers sauvegardés, les surcharges effectivement créées et les résultats. Les CREATE TABLE et CREATE TRIGGER sont non transactionnels. En cas d’échec, la reprise tente de retirer les seules surcharges créées, reconstruire les caches, restaurer le code précédent et retirer les triggers de cette exécution. Les tables vides et drapeaux à 0 peuvent rester présents. Ne pas relancer si la reprise n’est pas entièrement confirmée.

## Limites et suite

Les essais locaux sur doubles/SQLite ne prouvent pas ces parcours MySQL natifs. Leur réussite réelle ne sera confirmée qu’après exécution de ce paquet et lecture du bilan DEV.

Le candidat installé reste passif : `WATCHYS_NATIVE_STOCK_READY=0`, `WATCHYS_NATIVE_EFFECTS_READY=0`. Commande et paiement Watchys restent bloqués dans le navigateur. Le rendu, les miniatures, les prix et les stocks disponibles ne sont pas modifiés par le paquet.

Le lot vise les articles simples, une boutique, un stock manuel. Les packs et déclinaisons de paniers coordonnés sont refusés. Le service de réception exige un employé autorisé ; aucune route publique de retour n’est exposée. La réactivation explicite reste réservée à l’essai CLI. L’interface opérateur, la remise des effets, les transports et les parcours des moyens de paiement doivent être validés avant activation réelle.

# Concurrence isolée DEV — aucune activation des ventes

Mettre le ZIP dans HOME hors du site, extraire puis lancer `controler.php --verifier`. Examiner le bilan avant toute exécution sans cet argument. L'application exige exactement OUI dans un terminal.

Ce paquet ne publie aucun code dans le site. Le socle réel doit exister et rester vide. Module 0.2.2, empreinte native 8.2.7, catalogue, statuts et gardes sont vérifiés. Aucun bootstrap PrestaShop et aucun accès PROD.

Après OUI, sept tables InnoDB persistantes sont créées avec un préfixe `watchys_conc_<16 caractères aléatoires>_`. Elles sont partagées entre deux processus PHP et deux connexions MySQL, contrairement aux copies TEMPORARY précédentes. Le stock 7736 est copié puis sa COPIE fixée à une unité. Les ordres et lignes sont fictifs. Toutes les écritures de test, y compris le témoin volontairement incohérent, ne ciblent que ce préfixe.

Scénarios synchronisés par pipes :

1. Témoin : la méthode native lit avant une réservation Watchys et réécrit une valeur périmée. La perte de réservation est attendue sur la copie.
2. Vente ordinaire protégée gagnante : l'autre connexion doit rencontrer le verrou MySQL (délai de session d'une seconde), puis être refusée pour rupture après libération.
3. Configuration gagnante : inverse du scénario précédent, aucun callback natif après rupture.
4. Deux configurations distinctes : une seule allocation de la dernière unité ; aucun journal du perdant.

Le vrai fichier `src/Core/Stock/StockManager.php` du DEV est chargé après contrôle SHA256. Ses dépendances StockAvailable, produit, adaptateurs/services et hooks/cache sont simulées. Les alertes et mouvements sont désactivés. Ce n'est PAS un essai du checkout ni du véritable ObjectModel StockAvailable, d'un paiement, des caches ou de l'installation d'un adaptateur. Les processus reçoivent leurs informations de connexion par stdin ; elles ne sont ni arguments de commande ni journaux.

Les sept tables créées par cette invocation sont supprimées en fin de test et leur absence contrôlée. Aucun DROP sur un nom préexistant : la liste n'est alimentée qu'après CREATE réussi, avec préfixe aléatoire validé. En cas d'arrêt brutal ou de nettoyage échoué, le journal privé indique préfixe et tables à examiner ; ne pas improviser un DROP général.

`WatchysNativeStockCoordinator` est une frontière expérimentale, non installée. Il exige une transaction et que le callback finisse ses écritures natives de commande/détails/statuts sur le même PDO, sans commit ni effet externe. Il verrouille AVANT tout chargement du stock, vérifie les deltas annoncés et réconcilie les réservations ordinaires + Watchys. Les lignes natives sont relues avec verrou pour éviter un instantané périmé en REPEATABLE READ. Les callbacks qui contournent ce contrat, chargements/cache antérieurs, autres écrivains SQL/BO, mouvements/alertes et échecs natifs restent à intégrer. Ne pas entourer simplement les e-mails ou paiements d'une longue transaction ; concevoir l'adaptateur et ses limites avant déploiement.

Commande et paiement Watchys restent bloqués. Ne pas présenter un succès de ces fixtures comme une coordination native déjà déployée.

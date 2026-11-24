# Contrôle du cycle de stock — DEV uniquement

Contrôle PHP CLI autonome : `php controler-cycle-stock.php`, puis OUI. Balises DÉBUT/FIN. Aucun bootstrap PrestaShop, module ou passerelle exécuté ; transaction PDO READ ONLY ; aucun fichier ni donnée modifié. Les fichiers de configuration servent uniquement à se connecter au DEV et confirmer boutique 1 / domaine DEV.

Le bilan expose les statuts et leurs indicateurs, les seules clés techniques de stock et de statuts autorisées, les accroches des modules actifs, les candidats de paiement identifiés par leur classe principale, les moteurs de tables, les colonnes de nomenclature, les quantités des composants et du support technique. Il donne le nombre de commandes contenant un support, sans client ni détail de commande. Aucun identifiant/secret de paiement lu ou affiché, aucun mode test/réel présumé.

Les paramètres sont rapportés avec leur portée : boutique, groupe ou globale. Ils restent à interpréter selon les priorités natives de la boutique. Les modules de paiement sont des candidats, pas une validation de leurs configurations ni une liste exhaustive de leurs classes héritées indirectement.

Lire `docs/CYCLE_STOCK_WATCHYS_2026-11-24.md` pour les règles proposées, les points natifs à traiter et les tests prévus. Ce contrôle ne prépare ni table ni migration et ne débloque pas les commandes.

# Raccordement natif — relevé préalable en lecture seule

`php controler.php --verifier` lit uniquement le DEV : module/catalogue/gardes, socle vide, fichiers natifs, surcharges candidates sur disque, références de services de modules et accroches liées. Aucune classe du site exécutée ; pas de bootstrap, journal, publication, migration, commande, stock, cache ou écriture MySQL. Aucun accès PROD. Aucune confirmation OUI nécessaire puisqu'aucune action d'écriture n'existe dans ce script.

Le relevé affiche noms et empreintes, jamais le contenu des configurations de services, identifiants DB ou secrets. Une surcharge présente dans un dossier module n'est pas nécessairement activée : le rapport distingue cette présence du chargement effectif. Aucun index de classes PHP n'est exécuté. Les références textuelles de services ne prouvent pas qu'une décoration soit effective.

Le paquet contient le service préparé dans son dossier privé, pas dans les fichiers du site. `WatchysOrderStockService::fromNative` prend le PDO maître via `Db::getInstance()->getLink()` et exige la transaction native déjà ouverte. Il n'ouvre pas de connexion parallèle et ne s'installe sur aucune accroche.

Le service lit les commandes, lignes et personnalisations natives, vérifie le lien avec les instantanés enregistrés, le total des quantités de commandes fractionnées et leur nomenclature immuable. Il applique uniquement la dernière entrée d'historique concordant avec l'état courant et ignore les notifications anciennes. Répétitions et réactivation suivent le journal existant. Aucun prix natif n'est écrit. Les commandes ordinaires n'entraînent aucune allocation Watchys.

Les tests SQLite locaux couvrent cette liaison SQL, fractions d'une commande, ventes ordinaires partagées, répétitions, historiques anciens, annulation/réactivation/expédition, mauvaises identités/quantités et rollback appelant. Ce n'est pas une preuve d'intégration native complète.

Avant publication ou activation : examiner le relevé et choisir comment encadrer les écrivains natifs sans écraser une surcharge existante. Le cœur retire le stock avant OrderDetail::save et recalcule ses réservations après des accroches de statut : un branchement isolé ne suffit pas. Restent la frontière transactionnelle native complète (y compris panier mixte/fractionné), autres écrivains BO/SQL, caches/objets antérieurs, mouvements/alertes, paiements/notifications et retours partiels réels. Les gardes de commande/paiement sont maintenues.

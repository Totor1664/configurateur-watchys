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

# Reprise actuelle — Configurateur Watchys — 23 novembre 2026

## Dernier état confirmé — fin de séance du 23 novembre 2026

**Cette section prévaut sur les étapes historiques ci-dessous. Reprise prévue le 24 novembre.**

- Le paquet 0.2.2 a été appliqué sur DEV avec succès ; produit technique 8867, référence WCFG-7750-41-CARTDEV, personnalisations et cinq accroches relus. Ne pas réinstaller les anciens paquets.
- Le vrai ajout au panier fonctionne. Watchys confirme : « ça fonctionne correctement pour chaque version et variante en stock ». La capture montre la formule sans mouvement à 190 CHF HT et les six choix enregistrés. Les formules restent 190 / 510 / 610 CHF HT.
- Le rendu SVG et les proportions sont validés. Conserver les 29 PNG, le moteur de référence, le catalogue et les tarifs approuvés.
- La vignette du produit technique est cassée dans le panier : une image générique reste à préparer et associer, puis éventuellement un aperçu fidèle à chaque configuration.
- Aucun composant n'est décrémenté à l'ajout. Commande et paiement Watchys restent volontairement bloqués. Le cycle de stock réel n'est pas développé ni validé. Les tests locaux avec doubles ne remplacent pas les essais natifs, notamment concurrence, fusion de panier à la connexion, annulations et retours.
- Les tentatives antérieures ont pu laisser des écritures natives malgré une erreur et une restauration du code. Toujours relire l'état réel, sans présumer un retour arrière complet.

### Priorité décidée pour la prochaine séance : articles et stock DEV

L'utilisateur souhaite d'abord disposer de chaque pièce du configurateur et d'un stock de **10 unités par pièce**, avant de reprendre le développement du cycle de stock. Aucune de ces nouvelles opérations n'a été exécutée aujourd'hui.

1. Lire le catalogue réellement installé et les correspondances PrestaShop sur DEV. Vérifier existence, référence, nom, quantité disponible, statut actif/commandable et images de chaque composant.
2. Réutiliser les articles existants ; créer uniquement les composants réellement absents, sans doublons. Les articles 8855 à 8866 ont déjà été créés auparavant avec stock 10, initialement désactivés : vérifier leur état actuel avant toute action.
3. Préparer un plan précis pour fixer la quantité disponible de chaque composant concerné à 10 (pas ajouter 10 à la quantité existante). Le produit technique de panier n'est pas un composant à stocker. Préserver les réservations DEV et la cohérence du stock physique.
4. Afficher les articles concernés, les quantités avant/après et les créations nécessaires. Sauvegarde et journal avant/après, balises DÉBUT/FIN et confirmation exacte OUI avant écriture. Ne pas activer implicitement tous les articles ; présenter séparément les changements de statut nécessaires aux essais.
5. Relire les écritures et vérifier les variantes dans le configurateur. Puis traiter la vignette et reprendre le cycle de stock/commande lors d'une étape distincte.

Périmètre : DEV uniquement, aucun accès PROD, aucun changement de prix ni du rendu validé. Aucune sauvegarde supplémentaire de la base serveur n'a été lancée pendant cette clôture ; seul ce point de reprise est enregistré dans GitHub.

### Correspondances déjà connues à contrôler sur DEV

| Famille | Articles |
| --- | --- |
| Mouvement inclus selon formule | 7736 (MVT26) |
| Cadrans | 8188 noir, 8189 anthracite, 8190 bleu, 8191 vert, 8192 gris |
| Bracelets 20 mm | 8193 noir, 8194 bleu, 8863 rouge, 8864 orange, 8865 vert, 8866 brun |
| Aiguilles courtes Luminova | 8199 noires, 8200 chrome |
| Aiguilles dauphine | 8855 chrome, 8856 noires, 8857 bleues, 8858 rouges, 8859 or rose, 8860 or jaune |
| Carrures 41 mm | 8197 titane poli, 8198 titane sablé, 8861 noir PVD poli, 8862 noir brossé |
| Support technique hors stock composants | 8867 |

---

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

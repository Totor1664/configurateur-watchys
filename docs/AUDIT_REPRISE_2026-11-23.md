# Audit de reprise — Configurateur Watchys — 23 novembre 2026

## Conclusion

Les acquis sont réutilisables : catalogue des articles, sources PNG identifiées par empreinte, trois formules tarifaires et première séparation entre validation PHP et accès PrestaShop. Il n'est pas nécessaire de repartir de zéro.

La version 0.1.0 est une base de validation DEV, pas une version commercialisable. Son installation demeure non confirmée. Aucun panier, commande, réservation ou décrément de composant n'est implémenté. Il est prématuré de la présenter comme un configurateur relié aux commandes.

Audit effectué sans connexion SSH, sans appel à la boutique DEV/PROD et sans modification du code d'exécution. Le seul changement proposé au dépôt est ce rapport documentaire.

## Périmètre et instructions

- Branche distante : `codex/prestashop-integration`.
- Commit audité : `0c71fb33a91abb88124a42ec1f2ccd7a8dbce8c7`.
- Lecture de `docs/REPRISE_CODEX_WATCHYS.md` et des sources du module, de l'installateur, du constructeur de paquet, du catalogue et des tests.
- Aucun AGENTS.md trouvé dans l'arbre distant complet, ni dans les répertoires parents locaux examinés. Les instructions fournies par l'utilisateur restent applicables.
- 60 fichiers locaux comparés aux identifiants des blobs Git distants : aucun écart. Le clone local hérité a néanmoins un ancien historique de checkout et présente `docs/`, `integration/`, `modules/` et un test comme non suivis ; ne pas faire de reset destructeur. Repartir d'un checkout propre de la branche distante pour les futurs correctifs.
- Aucun statut d'installation serveur ne peut être déduit des seules sources Git.

## Bilan de reprise

| Élément | État constaté |
| --- | --- |
| Articles et photos DEV | Préparation et galeries confirmées par les bilans utilisateur du 19 novembre ; non revérifiées sur serveur pendant cet audit |
| Catalogue | 1 modèle, 5 cadrans, 6 bracelets, 8 jeux d'aiguilles, 4 carrures ; 23 options et mouvement 7736 |
| Composition | 960 configurations théoriques, 3 formules ; compatibilité mécanique de chaque combinaison non certifiée par le code |
| Tarifs serveur | 190 / 510 / 610 CHF HT ; prix en centimes entiers, montant navigateur ignoré |
| Images | 23 sources locales retrouvées et conformes à leurs SHA256 ; PNG d'aperçu distincts des JPEG des fiches |
| Module | 0.1.0 préparé ; garde DEV ; devis et lecture des stocks ; installation non confirmée |
| Panier et cycle de stock | À concevoir puis implémenter ; volontairement absents de cette version |
| Démonstration historique | 40 configurations, prix de démonstration 490 CHF et panier local ; distincte du futur parcours PrestaShop |
| Futurs modèles | Catalogue organisé en modèles, mais constructeur de paquet et tests encore liés au premier modèle |

## Constats priorisés

P0 = préalable à toute vente réelle ; P1 = à traiter avant validation du module sur DEV ; P2 = amélioration à planifier dans la consolidation.

### P0 — Panier et stock partagé ne sont pas implémentés

**Sources :** `modules/watchysconfigurator/watchysconfigurator.php` (retours `checkoutEnabled=false`), contrôleur API et gabarit.

Le module retourne une estimation et une liste de composants. Il ne possède pas de route d'ajout au panier, de persistance de configuration de commande ni de cycle de stock. Le contrôle actuel de quantité est une lecture de disponibilité, pas une réservation ou une garantie contre deux commandes concurrentes.

**Conséquence :** aucun achat ne doit être ouvert sur cette version. C'est une limite explicite, pas une régression cachée.

**Action :** définir ensemble produit support, lignes par configuration/formule, nomenclature persistée, intégration fiscale et événements de stock. Tester achats concurrents, composants communs, ventes à l'unité, changements de quantité, annulations, remboursements et retours. Éviter tout double décrément.

### P1 — L'aperçu déforme les bracelets

**Source :** `modules/watchysconfigurator/views/css/configurator.css`, règles `.wy-watch`, `.wy-strap` et `.wy-strap img`.

Le conteneur a un rapport 4:5. Le bracelet occupe 24 % de sa largeur et 36 % de sa hauteur. Son PNG carré est affiché à 530 % de cette largeur et 100 % de cette hauteur, avec `object-fit:fill`.

Pour un aperçu de largeur 1 000, cela donne une image de 1 272 × 450 : étirement horizontal relatif de 2,83 par rapport à la source carrée. Le découpage ne supprime pas cette déformation. Le même positionnement est utilisé pour toutes les variantes, sans métadonnées de centrage propres aux actifs.

**Conséquence :** le montage ne peut pas être considéré comme fidèle aux PNG validés. Les tests visuels de l'ancien prototype ne couvrent pas ces règles du nouveau module.

**Action :** conserver les PNG, ajouter des transformations de cadrage/positionnement avec proportions préservées, puis contrôler centrage cadran, axes des aiguilles, entrecornes et carrures sur desktop/mobile. Ne pas générer de nouvelles formes pour masquer un problème d'alignement.

### P1 — L'installation partielle n'a pas de procédure de reprise

**Source :** `integration/prestashop/dev-installer/installer.php` (détection d'un module existant, publication par renommage, puis installation native et catch).

Le dossier définitif est créé avant l'installation native PrestaShop et le contrôle de présentation. Si une de ces étapes échoue, le dossier est conservé, éventuellement avec des entrées natives déjà créées. Un nouveau lancement refuse alors de poursuivre parce que le dossier ou le module existe déjà.

En cas d'échec après renommage, les chemins journalisés peuvent encore référencer le dossier temporaire : leur réécriture n'a lieu qu'au succès final. Le catch ne finalise pas le journal avec un état d'échec détaillé.

**Conséquence :** diagnostic manuel nécessaire ; relancer aveuglément ne permet pas de terminer ou rétablir proprement l'installation. Aucun échec réel sur DEV n'est affirmé ici : le constat porte sur les branches d'erreur du code.

**Action :** ajouter diagnostic indépendant, états d'installation explicites, chemins définitifs enregistrés au bon moment, reprise contrôlée et sauvegarde des éléments concernés avant mise à jour. Ne pas supprimer automatiquement une installation partielle sans vérifier son contenu et ses entrées natives.

### P1 — Compatibilité native PrestaShop et thème non testée

**Sources :** `package.json`, tests PHP avec doubles, absence de tests HTTP ou d'installation du module dans le dépôt.

`npm test` ne lance pas les tests PHP du module. Les tests de service remplacent Module, Db, Context et StockAvailable par des doubles ; ils ne chargent ni PrestaShop 8.2.7, ni Smarty/Alysum, ni une base réelle.

**Conséquence :** les tests verts ne prouvent pas l'installation native, l'autochargement, le jeton de session dans un navigateur, la réponse JSON réelle ou l'affichage dans Alysum.

**Action :** unifier l'exécution des vérifications et ajouter une validation intégrée sur environnement isolé compatible. Ensuite seulement effectuer un contrôle puis un essai DEV encadré. Vérifier le statut réel du module avant d'essayer une installation.

### P0 avant vente — Compatibilités et statut commercial des nouvelles pièces à confirmer

**Sources :** catalogue et point de reprise ; `componentStatus()` respecte actuellement `active` et `available_for_order`.

Les 12 articles 8855–8866 ont été créés désactivés. Le module les rend visibles pour la revue mais ne les considère pas disponibles. Cette protection est cohérente avec la phase DEV et doit être conservée.

Les carrures PVD en acier sont regroupées avec le modèle 41 mm. Le code ne vérifie pas leur compatibilité mécanique, les dimensions des aiguilles ou les éventuelles caractéristiques héritées lors du clonage des fiches.

**Action :** valider la compatibilité métier et choisir une règle explicite de disponibilité des composants du configurateur. Ne pas activer toutes les fiches comme simple contournement d'un message d'indisponibilité.

### P2 — Le paquet et les tests ne sont pas réellement généralisés à plusieurs modèles

**Sources :** `integration/prestashop/build-dev-module.py`, tests PHP `catalog.php`, tests Node du catalogue.

Le catalogue accepte une liste de modèles, mais le constructeur impose exactement 23 images avec des noms uniques fondés sur le seul ID produit. Les tests PHP imposent 960 compositions par modèle, les mêmes formules/prix et le mouvement 7736. Un second modèle avec ses propres options échouerait ; la réutilisation d'un composant entre modèles peut également faire échouer l'unicité des cibles.

**Action :** inventaire d'actifs dédupliqué, modèle de données explicite et tests séparant règles génériques et attentes spécifiques du 7750. Garder la sélection de modèle existante, sans annoncer une extensibilité déjà terminée.

### P2 — Deux sources de catalogue, validation de schéma limitée

**Sources :** `integration/prestashop/catalog.json`, `modules/watchysconfigurator/config/catalog.json`, `WatchysCatalog.php`.

Les deux fichiers JSON sont identiques aujourd'hui. Le constructeur de paquet vérifie leur égalité mais n'en génère pas un à partir de l'autre. Le service vérifie surtout l'en-tête du catalogue, pas l'ensemble du schéma : unicité des modèles, types des prix intermédiaires, unités positives, composants et cohérence des actifs restent à renforcer.

**Action :** une source canonique, une copie de distribution générée et une validation complète avant construction/installation. Conserver la validation stricte des options et quantités reçues du navigateur.

### P2 — Une requête JSON invalide est annoncée comme indisponibilité serveur

**Source :** `controllers/front/api.php`, `json_decode(..., JSON_THROW_ON_ERROR)` et catch générique Throwable.

Reproduction locale avec un double minimal du contrôleur : POST à contenu JSON vide → HTTP 503 et message de service indisponible. La JsonException n'est pas traitée comme une erreur d'entrée.

**Conséquence :** diagnostic et comportement client trompeurs pour une erreur qui devrait être classée comme requête invalide. Aucun accès à la boutique n'a été utilisé pour reproduire ce cas.

**Action :** traiter explicitement les erreurs de JSON/structure en 400 et réserver 503 aux indisponibilités ; journaliser côté serveur les erreurs internes avec un identifiant exploitable et sans secrets.

### P2 mineur — Les badges de disponibilité peuvent rester périmés

**Source :** `views/js/configurator.js`, `chooseModel()` et `quote()`.

Les boutons gardent le statut fourni au chargement. Le devis relit les composants sélectionnés, mais sa réponse ne réactualise pas les badges des options. Une évolution du stock peut produire un badge « En stock » avec un récapitulatif signalant une indisponibilité.

**Action :** distinguer état indicatif et contrôle actualisé, puis rafraîchir les statuts utiles avec le devis. Ce point ne permet pas aujourd'hui une vente erronée puisque la commande reste désactivée.

## Points positifs à conserver

- Prix en centimes entiers, catalogue de confiance côté serveur, montant envoyé par le navigateur ignoré.
- Sélections et formules inconnues rejetées ; quantité entière bornée ; mouvement ajouté seulement aux formules correspondantes.
- Prise en compte du nombre d'unités requis et regroupement des besoins d'un composant partagé dans un devis.
- Respect des articles désactivés et de la correspondance des nouvelles références.
- Garde DEV, absence de route de commande, conservation de l'ancien module.
- Empreintes PNG, copie exclusive et plan/OUI avant mutation dans l'installateur.
- Texte des choix créé avec textContent, JSON embarqué échappé et validation serveur séparée de l'interface.

Ces éléments sont utiles, mais ne constituent pas une certification de sécurité complète ni une validation de vente.

## Vérifications relancées pendant cet audit

| Vérification | Résultat | Limite |
| --- | --- | --- |
| Comparaison au Git distant | 60 fichiers identiques | Ne confirme pas les fichiers installés sur DEV |
| `npm test` | 59 tests réussis | Prototype et catalogue ; pas installation PHP native |
| Tests catalogue en PHP 8.2.35 via WebAssembly | 2 880 calculs réussis ; entrées invalides rejetées | PHP local isolé |
| Tests service en PHP 8.2.35 via WebAssembly | Réussis | Doubles PrestaShop |
| Analyse syntaxique PHP 8.2 | 19 fichiers sans erreur | Ne charge pas les classes natives PrestaShop |
| Syntaxe JS | Sans erreur | Aucun rendu navigateur du nouveau module validé |
| Images | 23 sources conformes aux SHA256 | Aucun accès aux fichiers actuels du serveur |
| Catalogues | Deux copies identiques | Duplication maintenue dans le code actuel |
| Sonde JSON invalide | 503 reproduit | Double de contrôleur, pas HTTP réel |
| Géométrie CSS bracelet | Déformation 2,83:1 confirmée par les dimensions déclarées | Pas de capture de la boutique |

Aucun test d'achat réel ou de concurrence n'a été exécuté. Aucun scan ou accès à la PROD n'a été réalisé.

## Prochaine étape recommandée

1. Travailler dans un checkout propre de cette branche en conservant le dossier hérité.
2. Consolider une version 0.1.1 de validation : aperçu sans déformation, traitement des erreurs API, source de catalogue unique, procédure d'installation/reprise et exécution explicite des tests PHP.
3. Vérifier en lecture seule si le module existe déjà sur DEV et quels paquets PNG sont présents. Fournir un plan DÉBUT/FIN ; aucune écriture serveur sans OUI.
4. Après correction et contrôles, valider cette version sur DEV dans Alysum. Conserver les fiches, prix, stocks et thème.
5. Concevoir puis développer le vrai panier et le cycle de stock partagé, avec tests de concurrence et d'annulation, avant toute ouverture commerciale.

Les prix 190 / 510 / 610 CHF HT et les correspondances validées ne nécessitent pas d'être redemandés. La compatibilité mécanique et le statut commercial restent des validations distinctes du bon fonctionnement informatique.

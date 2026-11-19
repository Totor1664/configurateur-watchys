# Reprise Codex — Configurateur Watchys

Point de reprise du 19 novembre 2026. Ce document sert de transmission de l'avancement et des décisions, pas de validation de mise en production.

## 1. Situation à la pause

L'utilisateur souhaite arrêter ici puis reprendre le développement dans Codex, en conservant le travail et en améliorant son architecture et sa qualité. Ne pas continuer un déploiement sur la seule base de ce document. À la reprise, auditer le code et présenter les prochaines étapes concrètes.

- Dépôt : https://github.com/Totor1664/configurateur-watchys
- Branche à reprendre : `codex/prestashop-integration`.
- Dernier commit de code avant ce document : `33f64bf6ae7378746f3a1df31e11819fcd5930b8`.
- La branche principale et la démonstration GitHub Pages n'ont pas reçu cette intégration.
- Démonstration historique : https://totor1664.github.io/configurateur-watchys/ — prototype de 40 configurations, panier local simulé, sans connexion au stock ni au panier PrestaShop.
- Les fiches et photos des composants ont été préparées sur DEV et leur mise à jour a été confirmée par les bilans serveur transmis par l'utilisateur.
- Module personnalisé `watchysconfigurator` version 0.1.0 préparé, sauvegardé dans cette branche et livré en ZIP.
- IMPORTANT : aucun retour utilisateur ne confirme encore l'envoi, le contrôle ou l'installation de ce module. Le considérer comme NON INSTALLÉ jusqu'à vérification. Le dernier message proposait `installer.php --verifier`, sans écriture dans le site ou MySQL.
- Le module 0.1.0 est une version de validation : variantes, aperçu provisoire, lecture de stock et calcul serveur CHF HT. Aucun ajout au panier, réservation, commande, décrément ou annulation n'est implémenté.

## 2. Objectif métier et décisions

Créer un module Watchys sur mesure, indépendant de Product Composer PRO. Démarrer avec un modèle puis permettre plusieurs modèles et le passage de l'un à l'autre. Prévoir une structure de catalogue par modèle, avec ses composants compatibles, images et formules.

Le client devra accéder au configurateur depuis le site, choisir une configuration et une formule, l'ajouter au vrai panier PrestaShop et retrouver le détail dans la commande et l'atelier.

Approche retenue : une configuration commerciale complète, reliée à des composants physiques partagés avec les ventes à l'unité. Éviter une explosion de déclinaisons globales. L'architecture précise du panier et du cycle de stock reste à auditer et à implémenter ; ce document ne la prétend pas résolue.

Premier modèle : ETA/Valjoux 7750, carrures argentées titane 41 mm, bracelet 20 mm. Aiguilles courtes Luminova et jeux Dauphine. Les carrures noires des références fournisseur sont en acier 316L revêtu PVD, pas en titane. Confirmer leurs dimensions/compatibilités physiques avant de rendre toutes les combinaisons commandables : le catalogue de préparation les groupe actuellement sous le même modèle 41 mm.

| Formule | Prix CHF HT provisoirement validé | Mouvement | Atelier |
| --- | ---: | --- | --- |
| Kit à monter sans mouvement | 190 | Non | Non |
| Kit à monter avec mouvement | 510 | Oui, article 7736 | Non |
| Montre emboîtée par l'atelier | 610 | Oui, article 7736 | Oui |

Base kit 190 ; supplément mouvement 320 ; supplément atelier 100. Il s'agit de tarifs forfaitaires, pas d'une somme des prix de détail des composants. Les suppléments carrure sont à zéro dans cette préparation ; prévoir qu'ils puissent évoluer. Ne pas confondre les coûts fournisseur avec les prix de vente. TVA, devises et remises doivent être traitées correctement avec PrestaShop lors du raccordement commercial.

## 3. Serveur et règles de travail

- SSH utilisé par l'utilisateur : `uid130974@h2web173`.
- PROD : `$HOME/web`, PrestaShop 1.7.8.11, boutique `www.watchys.ch`, ID 1.
- DEV : `$HOME/sites/dev.watchys.ch`, PrestaShop 8.2.7, PHP CLI observé 8.2.35, thème Alysum 9.4.2 / Promokit.
- Bases PROD et DEV distinctes, contrôlées. Préfixe observé `vjzb_` ; toujours lire la configuration actuelle au lieu de le figer.
- Paramètres DB DEV : `app/config/parameters.php`, tableau `parameters`. Ne jamais publier, demander de coller ou journaliser les mots de passe.
- Langues : 1 français, 4 anglais. Devises : 2 CHF, 1 EUR, 3 USD.
- Dossier de travail/outils privé hors web : `$HOME/watchys-console-privee` et `$HOME/watchys-sauvegardes-privees`.
- Pas de connexion SSH directe établie dans cette session ChatGPT : les opérations serveur confirmées ont été exécutées par l'utilisateur.

Préférences opérationnelles de l'utilisateur : français, étapes concrètes, scripts avec DÉBUT / FIN, plan lisible et saisie exacte OUI avant toute mutation. Les longs scripts collés dans son terminal web ont provoqué des déconnexions ; préférer un fichier/ZIP envoyé par FTP puis une courte commande PHP. Aucun `exit` shell qui ferme sa session.

Conserver la refonte DEV, les catégories et leurs textes/SEO, thème, menus, modules, clients et commandes. Ne jamais écraser DEV par un dump PROD complet. Pas d'écriture PROD dans le cadre de cette phase. Garder Product Composer PRO et ses données durant la transition ; ne pas le désinstaller et ne pas réactiver automatiquement les anciennes fiches kits 7753/7796.

## 4. Synchronisation catalogue déjà effectuée

Bilans transmis par l'utilisateur :

- 60 nouveaux produits PROD (8795–8854) ajoutés sur DEV avec associations et 364 fichiers images.
- 844 lignes actualisées : statuts, catégories principales des produits, quelques prix/textes et quantités disponibles.
- Galeries des produits 1547 et 7873 alignées sur PROD, 7 JPEG copiés.
- 24 priorités tarifaires manquantes ajoutées.
- 2 749 stocks physiques DEV recalculés selon disponible + réservé DEV ; aucune quantité disponible/réservée modifiée lors de ce recalcul.
- Audit final : associations images PROD/DEV identiques ; priorités identiques ; zéro ligne incohérente physique = disponible + réservé.
- 8 caractéristiques équivalentes avec identifiants différents conservées.
- 38 associations fournisseur propres DEV avec id_supplier=0 conservées.
- Image 2917 du produit 1642 volontairement conservée pour examen visuel séparé.
- 29 originaux déjà absents de PROD signalés auparavant ; ne pas prétendre que l'audit a recréé ces originaux.

Sauvegarde complète DEV confirmée sur serveur avant création des variantes :
`$HOME/watchys-sauvegardes-privees/dev-tables-20261119-120729-e9aa24.sql.gz`
418 tables, 4 513 390 lignes, 77.08 Mo.
SHA256 : `a4b299948e3622c71614689650bf4eb6e9cf9b7fb2471d9ada575279cb9a307e`.
Restauration non testée. L'ancienne sauvegarde générée par le back-office était incomplète (252/418 tables) : ne pas l'utiliser comme restauration fiable.

## 5. Correspondances composants

La source structurée est `integration/prestashop/catalog.json` ; sa copie embarquée dans le module est `modules/watchysconfigurator/config/catalog.json`. Elles sont actuellement identiques, mais cette duplication est un point à améliorer.

| Famille | Variante | ID produit DEV | Référence si créée dans cette phase |
| --- | --- | ---: | --- |
| Mouvement | ETA/Valjoux 7750 Base | 7736 | MVT26 |
| Cadran | Noir | 8188 | |
| Cadran | Anthracite | 8189 | |
| Cadran | Bleu | 8190 | |
| Cadran | Vert | 8191 | |
| Cadran | Gris clair | 8192 | |
| Bracelet 20 mm | Noir | 8193 | |
| Bracelet 20 mm | Bleu | 8194 | |
| Bracelet 20 mm | Rouge | 8863 | W7750-STRAP-RED |
| Bracelet 20 mm | Orange | 8864 | W7750-STRAP-ORG |
| Bracelet 20 mm | Vert | 8865 | W7750-STRAP-GRN |
| Bracelet 20 mm | Brun | 8866 | W7750-STRAP-BRN |
| Aiguilles courtes Luminova | Noir | 8199 | |
| Aiguilles courtes Luminova | Chrome | 8200 | |
| Aiguilles Dauphine | Chrome | 8855 | W7750-DPH-CHR |
| Aiguilles Dauphine | Noir | 8856 | W7750-DPH-BLK |
| Aiguilles Dauphine | Bleu | 8857 | W7750-DPH-BLU |
| Aiguilles Dauphine | Rouge | 8858 | W7750-DPH-RED |
| Aiguilles Dauphine | Or rose | 8859 | W7750-DPH-RG |
| Aiguilles Dauphine | Or jaune | 8860 | W7750-DPH-YG |
| Carrure titane | Poli | 8197 | |
| Carrure titane | Sablé | 8198 | |
| Carrure acier PVD noir | Poli | 8861 | W7750-CASE-POL |
| Carrure acier PVD noir | Brossé | 8862 | W7750-CASE-BRU |

23 variantes physiques + mouvement = 24 articles. 5 × 6 × 8 × 4 = 960 compositions théoriques, 2 880 choix avec les trois formules. Ce nombre ne garantit ni compatibilité mécanique ni disponibilité instantanée.

Les 12 nouveaux articles 8855–8866 ont été créés avec stock initial 10 et statut désactivé. Les stocks préexistants ont été conservés. Ne pas utiliser le stock initial comme valeur actuelle. Les jeux longs 8278/8279 ne sont pas utilisés.

Prix de détail des gabarits relevés : aiguilles 20 CHF HT, bracelet 15 CHF HT, carrure 126 CHF HT ; ce ne sont pas les prix du kit.

## 6. Images, paquets et galeries

Sources PNG déjà présentes dans le dossier personnel du serveur, d'après les opérations utilisateur :

1. `$HOME/watchys-7750-variantes/` : 29 PNG originaux, manifeste, script de création des articles. Cadrans, mains, bracelets et anciennes faces des boîtiers.
2. `$HOME/watchys-carrures-7750-vues/` : 16 PNG carrures en quatre vues, manifeste et importateur de galeries.

Les images binaires ne sont pas commitées dans le dépôt. Le catalogue associe chaque option au paquet, au chemin relatif et au SHA256. L'installateur copie les 23 PNG de face/composants vers `modules/watchysconfigurator/views/img/{idProduit}-front.png` sans retoucher les sources.

Le client doit utiliser les PNG transparents dans le configurateur. Les fiches articles utilisent des JPEG sur fond blanc et les dimensions/miniatures paramétrées dans DEV.

- Photos de 11 anciens articles remplacées et relues : 8188–8194, 8197–8200 ; 13 images produit / 78 JPEG. Les bracelets ont deux vues. Prix, stocks et textes inchangés.
- Puis galeries des quatre carrures 8197, 8198, 8861, 8862 remplacées : face, trois quarts, profil, dos ; 16 images produit / 96 JPEG. L'utilisateur a confirmé le succès ET le contrôle visuel des fiches.
- Anciens fichiers physiques conservés ; anciens liens et nouveaux liens journalisés.
- Journal dernière opération carrures : `$HOME/watchys-sauvegardes-privees/vues-carrures-7750-20261119-134002-778f52.json`.

ATTENTION AU SCHÉMA : la table `image` du DEV possède un champ supplémentaire `sc_path varchar(150) NOT NULL DEFAULT ''`. Un premier import a échoué parce que la vérification comparait quatre champs à cinq. L'import corrigé insère et compare `sc_path=''`. `cover=1` pour la première image ; `cover=NULL` pour les autres, pas 0. Tables image/image_shop/image_lang doivent rester cohérentes. Ne pas écraser des JPEG préexistants ; générer sous de nouveaux IDs, journaliser et vérifier les associations avant validation.

Contraintes visuelles validées :
- Quatre finitions distinctes : titane poli, titane sablé, noir PVD poli très sombre, noir PVD brossé très sombre.
- Dos avec gravures tirées des références fournisseur : SAPPHIRE CRYSTAL, TITANIUM pour titane / ALL STAINLESS STEEL pour PVD acier, SWISS MADE, WATER RESISTANT 5ATM.
- Seconde au-dessus des heures/minutes, colinéaire avec son contrepoids ; formes Dauphine légèrement arrondies. Ne pas redessiner arbitrairement les aiguilles approuvées.
- Les vues sont des visuels générés à partir de références, pas des relevés dimensionnels.

Récupération des fichiers si les paquets du serveur ne sont pas accessibles à Codex : demander leur fourniture explicite, ou les extraire via un accès autorisé. Les liens ChatGPT ci-dessous sont utilisables par l'utilisateur connecté, pas nécessairement par un environnement Codex autonome :

- PNG originaux + création articles : https://chatgpt.com/library?file_id=libfile_28fe214a09288191b062684088ad1a00&account_id=personal
- 16 vues carrures + import : https://chatgpt.com/library?file_id=libfile_d88ee8866ab88191a1ab606aa2d231d9&account_id=personal
- Module DEV 0.1.0 : https://chatgpt.com/library?file_id=libfile_f2591f3381908191a28d253d6a1130f1&account_id=personal

## 7. Code préparé et contrôles

- `integration/prestashop/catalog.json` : variantes, articles, références, PNG/SHA256, offres.
- `integration/prestashop/README.md` : décisions et limites commerciales.
- `modules/watchysconfigurator/watchysconfigurator.php` : module et services PrestaShop, garde DEV, lecture stock/statut/référence, présentation et devis.
- `modules/watchysconfigurator/classes/WatchysCatalog.php` : validation modèle/options/formule/quantité, prix en centimes entiers, nomenclature.
- `modules/watchysconfigurator/controllers/front/configure.php` : page dans le thème.
- `modules/watchysconfigurator/controllers/front/api.php` : devis POST avec jeton de session, aucun accès panier.
- `modules/watchysconfigurator/views/` : gabarit Smarty, CSS et JS.
- `modules/watchysconfigurator/tests/` : prix catalogue et service avec doubles de test.
- `integration/prestashop/dev-installer/installer.php` : contrôles, plan, OUI, copie exclusive, installation native du seul nouveau module.
- `integration/prestashop/build-dev-module.py` : construit le ZIP d'installation léger en réutilisant les paquets PNG serveur.

Contrôles réalisés localement :
- 59 tests Node du prototype et du catalogue d'intégration passent.
- PHP 8.2.35 via WebAssembly : 19 fichiers PHP analysés sans erreur de syntaxe.
- 2 880 calculs formule/quantité et rejets des entrées invalides passent.
- Tests de service avec doubles : prix navigateur ignoré, stock insuffisant, mouvement optionnel, article désactivé, référence différente et exclusion PROD passent.
- Empreintes des 23 sources PNG et du paquet vérifiées ; syntaxe JS vérifiée.

Limites : aucun test d'installation dans un vrai PrestaShop, aucun test HTTP réel de l'API dans DEV, aucun test de paiement/commande/stock réel. Aucun contrôle visuel du nouveau module dans Alysum n'a été confirmé. Les 59 tests du prototype ne prouvent pas le bon fonctionnement du nouveau module.

Le premier ZIP module envoyé fait environ 29 Ko et ne contient pas les PNG ; il les copie depuis les paquets existants après vérification des SHA256. Installation non confirmée à la pause.

## 8. Points à améliorer dans Codex

Auditer avant de prolonger cette base. Elle est un point de départ, pas une architecture définitive.

1. Vérifier l'état Git distant, le statut réel de l'installation DEV et les instructions AGENTS applicables. Ne pas supposer que le ZIP 0.1.0 est installé.
2. Préserver les correspondances validées ; valider dimensions et compatibilités physiques, surtout les carrures PVD. Examiner les fiches clonées pour d'éventuels champs/caractéristiques hérités du gabarit titane.
3. Réduire les duplications catalogue et séparer proprement modèle, options, présentation, tarification et accès PrestaShop. Définir comment administrer plusieurs modèles.
4. Améliorer l'aperçu : le CSS du module est provisoire et n'a pas été calibré avec les PNG dans le thème réel. Reprendre les apprentissages du prototype sans prétendre que les nouveaux centrages, échelles et superpositions sont validés. Préserver les actifs approuvés.
5. Valider les contrôleurs, le jeton, les permissions, le cache, les erreurs et l'intégration Alysum/PHP 8.2 avec tests réalistes. Les gardes DEV sont volontaires pour cette version.
6. Revoir l'installateur (reprise après échec, installation partielle, journaux, mises à jour), sans écraser de dossiers existants. Le script initial laisse les fichiers copiés en cas d'échec natif et demande un diagnostic ; il n'offre pas de rollback complet de l'installation native.
7. Concevoir le vrai panier : produit support distinct ou stratégie adaptée, une ligne/configuration/formule, persistance de la nomenclature, prix serveur, quantités, TVA, devises, remises, livraison et informations atelier. Pas de réactivation automatique des anciens kits liés à l'ancien module.
8. Concevoir le cycle de stock partagé avant toute vente : validation ajout et commande, concurrence, pièces communes, achats à l'unité, annulation/remboursement/retour, idempotence et prévention des doubles décréments. L'atelier est une prestation, pas une pièce en stock. Ne pas utiliser un stock fictif du kit comme source de disponibilité.
9. Décider explicitement du statut commercial des 12 nouveaux produits désactivés. Ils peuvent être visibles en aperçu DEV sans devenir commandables.
10. Valider le parcours DEV complet avant ajout d'un accès visible sur le site, puis préparer une mise en production contrôlée et compatible avec les versions différentes PROD/DEV. Ne pas recopier toute la base DEV vers PROD.

## 9. Dernières commandes proposées, non confirmées exécutées

Après envoi du ZIP dans `$HOME` :

```bash
unzip -n "$HOME/watchys-module-dev-0.1.0.zip" -d "$HOME"
php -l "$HOME/watchys-module-dev-0.1.0/installer.php"
php "$HOME/watchys-module-dev-0.1.0/installer.php" --verifier
```

L'installation normale est ensuite `php "$HOME/watchys-module-dev-0.1.0/installer.php"`, avec plan et OUI. Ce document ne demande pas de la lancer automatiquement. Vérifier l'état et améliorer le code d'abord, selon la demande de reprise de l'utilisateur.

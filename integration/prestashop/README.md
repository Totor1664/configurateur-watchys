# Base de configuration du futur module Watchys

État : préparation de l'intégration, **aucun module installé et aucune liaison au panier réel à ce stade**. Cette configuration n'est pas encore chargée par l'interface de démonstration. Le prototype reste à son prix de démonstration tant que son raccordement n'est pas réalisé.

## Décisions validées le 19 novembre 2026

Premier modèle : ETA/Valjoux 7750, carrure de 41 mm, bracelet de 20 mm, aiguilles courtes Luminova ou Dauphine. Les carrures argentées sont en titane ; les carrures noires PVD sont en acier 316L.

| Formule | Prix CHF HT | Mouvement consommé | Emboîtage |
| --- | ---: | --- | --- |
| Kit à monter sans mouvement | 190 | Non | Non |
| Kit à monter avec mouvement | 510 | Oui, article 7736 | Non |
| Montre emboîtée par l'atelier avec mouvement | 610 | Oui, article 7736 | Oui |

Base kit 190 CHF HT ; supplément mouvement 320 CHF HT ; supplément atelier 100 CHF HT. Les tarifs restent modifiables ultérieurement. Les quatre carrures actuelles n'ont pas de supplément. Les prix ne résultent pas de la somme des prix de vente des pièces.

`catalog.json` représente les montants en centimes entiers et sépare les prix des correspondances d'articles. Chaque modèle possède ses options et ses formules pour permettre l'ajout d'autres modèles sans mélanger leurs composants compatibles.

## Articles existants relevés sur DEV

| Composant | ID article |
| --- | ---: |
| Mouvement 7750 Base | 7736 |
| Cadran noir | 8188 |
| Cadran anthracite | 8189 |
| Cadran bleu | 8190 |
| Cadran vert | 8191 |
| Cadran gris clair | 8192 |
| Bracelet noir | 8193 |
| Bracelet bleu | 8194 |
| Carrure polie | 8197 |
| Carrure sablée | 8198 |
| Aiguilles courtes noires | 8199 |
| Aiguilles courtes chromées | 8200 |

Les jeux longs 8278/8279 ne font pas partie de ce modèle. Aucune quantité n'est figée dans le fichier : le futur module devra lire les stocks actuels. Les quantités affichées par l'audit sont un constat à un instant donné.

## Variantes ajoutées sur DEV le 19 novembre 2026

| Famille | Variante | ID article |
| --- | --- | ---: |
| Dauphine | Chrome | 8855 |
| Dauphine | Noir | 8856 |
| Dauphine | Bleu | 8857 |
| Dauphine | Rouge | 8858 |
| Dauphine | Or rose | 8859 |
| Dauphine | Or jaune | 8860 |
| Carrure acier | Noir PVD poli | 8861 |
| Carrure acier | Noir PVD brossé | 8862 |
| Bracelet 20 mm | Rouge | 8863 |
| Bracelet 20 mm | Orange | 8864 |
| Bracelet 20 mm | Vert | 8865 |
| Bracelet 20 mm | Brun | 8866 |

Total : 5 cadrans × 6 bracelets × 8 jeux d'aiguilles × 4 carrures = 960 compositions, soit 2 880 choix avec les trois formules. Ces possibilités théoriques restent soumises au contrôle des compatibilités et des disponibilités par le futur module.

Les 12 nouveaux articles ont été créés désactivés. Leur création et les stocks initiaux ne constituent pas une autorisation de les rendre immédiatement commandables. Le module exclura les composants désactivés tant qu'une autorisation dédiée n'aura pas été définie et validée.

## Images et correspondances

Chaque option possède un `visual` identifiant le paquet, le fichier PNG transparent et son empreinte SHA256. Les paquets sont déjà présents dans le dossier personnel du serveur DEV : `watchys-7750-variantes` et `watchys-carrures-7750-vues`. Ce sont des sources privées : ne jamais transformer leur chemin système en URL publique.

Les carrures utilisent les nouvelles vues de face transparentes, indépendantes des JPEG des fiches articles. Leur champ `gallery` contient les vues face, trois quarts, profil et dos. Les bracelets disposent des vues ouverte et fermée.

Les 16 images des quatre galeries ont été installées et relues sur DEV, avec 96 JPEG, selon le bilan utilisateur. Les PNG n'ont pas été altérés. Les actifs binaires ne sont pas encore copiés dans le module et ne sont pas chargés par la démonstration actuelle.

Les correspondances sont basées sur les créations et contrôles DEV du 19 novembre. Ce fichier ne fournit ni disponibilité actuelle ni preuve de validation mécanique de chaque assemblage. Aucun stock chiffré n'y est figé.

## Exigences pour le raccordement restant à construire

- Module autonome, sans dépendance à Product Composer PRO. Garder l'ancien module et ses données pendant la transition.
- Faire correspondre les composants visuels aux articles ci-dessus, sans créer les 2 880 déclinaisons commerciales globales.
- Prix et compatibilités contrôlés côté serveur à partir de données de confiance. Ne jamais accepter un montant ou un ID de composant arbitraire du navigateur.
- Une ligne commerciale détaillée par configuration et formule ; deux formules différentes doivent rester distinctes au panier.
- Partager les stocks des composants avec leurs ventes séparées, y compris le mouvement seulement lorsqu'il est inclus.
- Vérifier les disponibilités lors de l'ajout et avant commande. Tester deux achats simultanés, les modifications de quantités et les composants communs à plusieurs configurations.
- Définir et tester le cycle de déduction, d'annulation et de retour avec PrestaShop pour éviter tout double mouvement de stock.
- Définir le produit support avant raccordement ; ne pas réactiver ni modifier automatiquement les anciennes fiches 7753/7796 déjà configurées dans l'ancien module.
- L'atelier est une prestation, pas un composant physique à décrémenter.
- TVA, devises, livraison, remises, affichage des commandes et des remboursements restent à intégrer et à tester dans PrestaShop.

## Vérification de cette étape

`npm test` vérifie notamment la couverture des options existantes, le choix des aiguilles courtes et les trois montants approuvés pour les 960 compositions. Ces contrôles ne constituent pas un test d'installation, de paiement ou de stocks PrestaShop.

Prochaine étape : développer puis installer le module sur DEV et valider un parcours réel d'ajout au panier, en conservant la PROD inchangée.


## Module DEV préparé — version 0.1.0

Le code de la première version est dans `modules/watchysconfigurator`. Le construire avec `python3 integration/prestashop/build-dev-module.py`. Le ZIP produit réutilise les PNG privés présents sur le serveur au lieu de les inclure dans le dépôt.

Cette version affiche les variantes, leur disponibilité et les prix CHF HT calculés côté serveur. Elle est strictement réservée à dev.watchys.ch et ne comporte aucune écriture au panier ou au stock. L'installation native PrestaShop et l'affichage dans Alysum restent à vérifier sur le serveur après validation OUI. Le prototype public n'est pas modifié.

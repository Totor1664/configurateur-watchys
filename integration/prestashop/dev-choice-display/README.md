# Ordre des choix et survol — DEV uniquement

Ordre d'affichage : Carrure, Cadran, Aiguilles, Bracelet, également pour les petites vues de détail. Le tri porte sur une copie des groupes de l'interface et ne réordonne ni le catalogue serveur ni les choix d'options à l'intérieur des groupes. Les identifiants et les tarifs sont conservés.

Le survol est gris clair #F2F2F2. Une règle CSS limitée aux boutons du configurateur empêche la couleur rouge globale du thème de s'appliquer. La bordure bleue de sélection et le focus clavier restent présents. Les versions d'adresses CSS/JS changent pour éviter un ancien affichage en cache ; le moteur SVG validé est inchangé.

Construire avec `python3 integration/prestashop/dev-choice-display/build.py --previous-package PAQUET_PANIER_0.2.2.zip --output watchys-interface-dev.zip`.

Déposer le ZIP privé hors du site, extraire et exécuter `php installer.php --verifier`. Ce contrôle lit le DEV sans bootstrap natif ni écriture. Après lecture du bilan, exécuter séparément `php installer.php`, lire le plan et taper OUI. Les trois fichiers sont sauvegardés, remplacés atomiquement et relus. Une erreur provoque une tentative de restauration des seuls fichiers publiés, sans écraser une modification tierce détectée. Le journal précise l'état.

Aucune écriture MySQL ni installation/activation de module. Le script exige le module 0.2.2 et l'accroche des miniatures personnalisées déjà validée, conserve le code métier, le catalogue et les 29 PNG. Il ne republie pas les 960 miniatures ni les images articles. Ne pas relancer les anciens installateurs du module après ce correctif.

Validation locale : test d'interface existant étendu au nouvel ordre, aux vues de détail et à l'absence de mutation du modèle source ; les scénarios d'ajout, de double clic et de reprise réseau restent couverts. Suite complète de régression Node/PHP/construction et analyse syntaxique PHP. L'effet exact des styles du thème doit être confirmé visuellement sur DEV après application.

État confirmé au 24 novembre : Watchys valide les miniatures personnalisées dans le panier et le mini-panier, les composants à 10 unités et l'activation manuelle des variantes. Ce correctif d'ordre/survol est préparé, pas encore confirmé installé. Commande et paiement restent bloqués en attente du cycle réel de stock.

Reprends le projet Configurateur Watchys dans le dépôt https://github.com/Totor1664/configurateur-watchys, sur la branche codex/prestashop-integration.

Lis d'abord les instructions AGENTS applicables, puis docs/REPRISE_CODEX_WATCHYS.md et les fichiers qu'il référence. Ce document contient les décisions métier, les correspondances des articles, les opérations DEV confirmées, les images, les tests et les limites connues.

Conserve les acquis et audite le code existant avant de l'améliorer. Ne repars pas de zéro. Vérifie l'état réel : le module 0.1.0 est préparé, mais son installation sur DEV n'est pas confirmée ; le vrai panier et le décrément des composants ne sont pas implémentés.

L'objectif est un module PrestaShop sur mesure, prévu pour plusieurs modèles, avec les PNG validés, les tarifs approuvés, le vrai panier et un stock partagé avec les articles vendus séparément.

Commence par un bilan de reprise et un audit priorisé, puis propose la prochaine étape concrète avant de modifier ou déployer le site. Toute opération serveur devra conserver un plan, les balises DÉBUT/FIN et une confirmation OUI avant écriture. Ne touche pas à la PROD, aux stocks, aux fiches validées ni à la refonte DEV pendant cet audit.

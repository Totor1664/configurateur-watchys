# Configurateur Watchys — panier DEV 0.2.2

Le rendu SVG validé de GitHub est conservé. Cette version ajoute les configurations au vrai panier via une fiche technique par modèle et les personnalisations natives PrestaShop. Le prix complet provient du serveur ; la commande et le paiement des paniers Watchys restent bloqués pendant les tests du cycle de stock.

Lire `integration/prestashop/dev-cart/README.md` pour la procédure, les limites et la validation serveur.

```bash
python3 integration/prestashop/build-cart-dev.py --output ../watchys-panier-dev-0.2.2.zip
```

Ne pas utiliser `build-dev-module.py` ni l'ancien installateur 0.1.1 : ils correspondent à une étape historique. Le nouveau paquet vérifie le code et les 29 PNG actuellement validés, sauvegarde les fichiers remplacés, demande OUI et initialise le bootstrap Symfony natif. Il ne réinstalle pas le module.

La construction locale n'est pas une preuve d'installation sur DEV. Les tests utilisent notamment des doubles natifs ; les tests HTTP, SQL et visuels sur le vrai PrestaShop sont à réaliser après la migration. Aucun accès PROD autorisé.

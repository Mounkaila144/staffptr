# Durcissement HTTP, TLS et pare-feu

Ce runbook sépare les protections livrées par l'application des actes réservés à l'exploitant du
VPS Ubuntu 24.04 partagé. Il s'applique à `staging.staff.ptrniger.com` puis à
`staff.ptrniger.com`. Chaque commande de vérification et son résultat sont consignés dans le journal
d'exploitation.

## Contrôles applicatifs avant déploiement

Laravel pose HSTS, CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` et
`Permissions-Policy`. HSTS n'est volontairement pas émis en HTTP local. La CSP de production
n'autorise que l'application elle-même, les images `data:` et un nonce distinct par requête ; elle
interdit les objets et l'intégration dans une page tierce. Le mode Vite local possède une politique
séparée qui n'est active qu'en présence du fichier `public/hot`.

Les routes mutantes du groupe `web` conservent la protection CSRF Laravel. Vue et Blade échappent
les contenus affichés, les écritures sont validées par Form Request et passent par Eloquent : ne
jamais remplacer ces protections par du HTML brut, une validation de contrôleur ou une requête SQL
construite par concaténation. La future route de connexion devra utiliser le limiteur nommé
`login`, déjà fixé à cinq tentatives sur quinze minutes ; elle ne doit pas créer une seconde règle.

Avant livraison :

```bash
php artisan test --filter='SecurityHeadersTest|LoginRateLimiterTest|ErrorPagesTest|SensitiveLogRedactionTest|HttpHardeningContractTest'
npm run build
npm run test:e2e
```

Le parcours Playwright doit afficher la démonstration et les pages d'erreur sans erreur de console,
sans page blanche et sans requête vers un domaine externe.

## Certificats Apache et renouvellement

Vérifier d'abord que les vhosts HTTP des deux domaines pointent vers le bon `public/`, puis lancer
certbot séparément pour chaque environnement :

```bash
sudo certbot --apache -d staging.staff.ptrniger.com
sudo certbot --apache -d staff.ptrniger.com
sudo certbot renew --dry-run
systemctl status certbot.timer
systemctl list-timers certbot.timer
journalctl -u certbot.service --since '30 days ago'
```

Le timer doit être actif et le test de renouvellement doit réussir. La surveillance d'exploitation
alerte avant l'expiration du certificat ; ce contrôle ne doit pas dépendre uniquement du timer :

```bash
echo | openssl s_client -servername staff.ptrniger.com -connect staff.ptrniger.com:443 2>/dev/null \
  | openssl x509 -noout -dates
```

Tester cette commande depuis une machine extérieure au VPS et consigner la date d'expiration. Une
alerte est obligatoire à trente jours puis à sept jours de l'échéance.

## Redirection permanente et versions TLS

Le vhost du port 80 ne sert aucun contenu applicatif. Il redirige vers le même hôte et la même URI :

```apache
<VirtualHost *:80>
    ServerName staff.ptrniger.com
    Redirect permanent / https://staff.ptrniger.com/
</VirtualHost>
```

Appliquer l'équivalent au domaine de préproduction. Dans chaque vhost TLS, refuser les protocoles
anciens et conserver les réglages modernes fournis par certbot :

```apache
SSLProtocol -all +TLSv1.2 +TLSv1.3
SSLHonorCipherOrder off
Protocols h2 http/1.1
```

Valider puis recharger sans interrompre les autres projets du VPS :

```bash
sudo apachectl configtest
sudo systemctl reload apache2
curl -I http://staff.ptrniger.com/chemin-de-controle
curl -I https://staff.ptrniger.com/up
openssl s_client -tls1_1 -connect staff.ptrniger.com:443 -servername staff.ptrniger.com
openssl s_client -tls1_2 -connect staff.ptrniger.com:443 -servername staff.ptrniger.com
```

La première réponse doit commencer par `HTTP/1.1 301` et conserver le chemin dans `Location`. La
connexion TLS 1.1 doit échouer ; TLS 1.2 doit réussir. Refaire les contrôles sur la préproduction.

## HSTS et décision `preload`

L'application émet sur HTTPS :

```text
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
```

Cet en-tête n'autorise pas à soumettre immédiatement `ptrniger.com` à la liste HSTS preload des
navigateurs. La soumission est un acte distinct, quasi irréversible à court terme, qui forcerait
HTTPS sur tous les sous-domaines, y compris les autres projets du VPS partagé.

Avant toute soumission, la direction doit confirmer explicitement la décision et signer un
inventaire exhaustif des sous-domaines de `ptrniger.com`, avec une preuve de certificat valide et de
redirection HTTPS pour chacun. Conserver l'inventaire, la confirmation et la date dans le journal
d'exploitation. Sans ces trois éléments, ne pas soumettre le domaine. Un retrait peut prendre des
mois au rythme des mises à jour des navigateurs.

## Pare-feu et services en boucle locale

Avant d'activer UFW, confirmer une session SSH de secours et le vrai port SSH. Si le port est bien
22, appliquer puis vérifier :

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status numbered
sudo ss -ltnp
```

Seuls 22, 80 et 443 sont accessibles depuis le réseau. L'état attendu pour les services de données
est une écoute locale, par exemple `127.0.0.1:3306` pour MariaDB et `127.0.0.1:6379` pour Redis
(une écoute IPv6 `::1` est également locale). Aucune ligne `0.0.0.0:3306`, `[::]:3306`,
`0.0.0.0:6379` ou `[::]:6379` n'est acceptable.

L'isolation a déjà été obtenue en story 1.5 : cette procédure la vérifie, elle ne modifie pas à
l'aveugle les configurations globales partagées. En cas d'écart, arrêter le déploiement, relever
`bind-address` dans MariaDB et `bind`/`protected-mode` dans Redis, puis faire valider la correction
avec les responsables des autres projets du VPS.

Depuis une machine extérieure, confirmer que seuls les trois ports attendus répondent. Ne jamais
publier MySQL ou Redis pour faciliter un dépannage ponctuel ; utiliser un tunnel SSH borné.

## Absence de contenu mixte et contrôle des en-têtes

Après chaque déploiement, ouvrir les pages avec les outils du navigateur : onglet Réseau, console et
vue Sécurité. Parcourir la démonstration et les quatre pages d'erreur à 320 px puis sur un écran
standard. Aucun chargement `http://`, avertissement de contenu mixte, refus CSP, erreur JavaScript,
défilement horizontal ou ressource externe n'est accepté.

Contrôler aussi sans navigateur :

```bash
curl -sS -D - -o /dev/null https://staff.ptrniger.com/
curl -sS -D - -o /dev/null https://staff.ptrniger.com/up
curl -sS https://staff.ptrniger.com/ | grep -Eo '(src|href)="[^"]+"'
```

Les deux premières commandes doivent montrer tous les en-têtes de sécurité. La dernière ne doit
montrer aucune URL externe ni ressource en HTTP. Refaire sur la préproduction et archiver les
sorties datées.

## Configuration de production et journaux

`APP_DEBUG=false` est obligatoire en préproduction et en production, sans exception temporaire.
Après chaque modification du fichier d'environnement :

```bash
php artisan config:clear
php artisan config:cache
php artisan about --only=environment
```

Le canal technique est `daily`, écrit une ligne JSON par événement, masque les secrets et conserve
trente jours. La future commande quotidienne `ptr:check-invariants` de la story 2.3 vérifiera aussi
`APP_DEBUG=false` ; ne pas anticiper sa création dans cette story. Jusqu'à sa livraison, le contrôle
manuel ci-dessus reste bloquant.

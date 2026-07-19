# 25. Déploiement, HTTPS, secrets et CI/CD

## 25.1 Serveur

VPS unique (A-03), 2 vCPU / 4 Go / 80 Go SSD suffisent largement à 100 utilisateurs.
Ubuntu 24.04 LTS (réalité du serveur, constatée en story 1.5), système en **UTC**.

> **Le VPS retenu est partagé avec d'autres projets** (DEC-05, § 24.2). Les composants ci-dessous
> sont donc **mutualisés**, pas dédiés. Quatre mesures d'isolation deviennent obligatoires et non
> négociables : utilisateur système dédié, pool PHP-FPM 8.3 propre, `REDIS_PREFIX` et index `REDIS_DB`
> distincts, surveillance de l'espace disque. Sans elles, un autre projet peut lire le `.env` de PTR
> Staff ou vider son cache.

| Composant | Rôle |
|---|---|
| Apache 2.4 (DEC-13) | TLS, en-têtes de sécurité, Brotli, `X-Sendfile`, ressources statiques |
| PHP-FPM 8.3 | Application, OPcache activé, JIT désactivé (sans intérêt ici) |
| MariaDB 10.11 (DEC-12) | Données, sessions |
| Redis 7 | Cache, files |
| Supervisor | `queue:work` — redémarrage automatique |
| Cron | `schedule:run` à la minute |
| UFW + fail2ban | Ports 22/80/443 seuls ; MySQL et Redis **écoutent sur la boucle locale uniquement** |

## 25.2 HTTPS — NFR11

Let's Encrypt via Certbot, **renouvellement automatique surveillé** (un renouvellement silencieusement
cassé se découvre le jour de l'expiration). Redirection 301 systématique de HTTP vers HTTPS, HSTS
avec `preload` (§ 9.5), TLS 1.2 minimum, chiffrements modernes. **Aucun contenu mixte** — garanti
par NFR3, qui interdit déjà toute ressource externe.

## 25.3 Déploiement

Releases atomiques par lien symbolique. Le basculement est instantané et le retour arrière consiste
à repointer le lien.

```
/var/www/ptrstaff/
├── releases/20260720143000/
├── current -> releases/20260720143000
└── shared/{.env, storage/}
```

Étapes : récupération du code → `composer install --no-dev -o` → `npm ci && npm run build` →
lien de `shared` → `migrate --force` (utilisateur privilégié) → `config:cache route:cache view:cache
event:cache` → bascule du lien → `php-fpm reload` → `queue:restart`.

**`php artisan down` n'est pas utilisé pour un déploiement ordinaire** : le basculement de lien
symbolique rend l'interruption imperceptible. Il est réservé aux migrations lourdes, avec
`--render` pour afficher une page française plutôt qu'une erreur brute.

## 25.4 Deux utilisateurs MySQL

Conséquence directe des barrières d'immuabilité du § 14.1, à ne pas contourner :

| Utilisateur | Privilèges | Usage |
|---|---|---|
| `…_app` | `SELECT, INSERT, UPDATE` — **pas de `DELETE`** sur les tables métier ; **`INSERT` seul** sur `audit_logs` ; `DELETE` accordé sur les tables d'infrastructure | Application (`.env`) |
| `…_migrate` | `ALL` **borné au schéma**, avec `GRANT OPTION` | Migrations, déploiement |

**Une paire de comptes par environnement — conséquence directe de DEC-05.** Préproduction et
production partagent la même instance MySQL. Or `'utilisateur'@'hôte'` y désigne **un compte unique** :
un `ptrstaff_app` accordé sur les deux schémas verrait la production depuis la préproduction, ce qui
annule l'isolation recherchée. Les noms portent donc l'environnement :

| Environnement | Schéma | Compte applicatif | Compte de migration |
|---|---|---|---|
| Production | `ptrstaff_prod` | `ptrstaff_prod_app` | `ptrstaff_prod_migrate` |
| Préproduction | `ptrstaff_staging` | `ptrstaff_staging_app` | `ptrstaff_staging_migrate` |
| CI (éphémère) | `staffptr_test` | `staffptr_app_ci` | `staffptr_migrate_ci` |

⛔ Aucun compte n'est accordé sur un schéma qui n'est pas le sien, et aucun `GRANT` ne porte sur
`*.*`. Le nommage est **symétrique à dessein** : un compte nommé `ptrstaff_app`, sans marqueur
d'environnement, serait lu comme générique et finirait par être réutilisé pour la préproduction —
c'est exactement l'erreur que cette séparation cherche à rendre impossible.

Les identifiants du compte de migration **ne figurent pas dans le `.env` applicatif** : ils sont
injectés par le script de déploiement depuis le magasin de secrets de la CI, le temps de la
migration. Un `.env` compromis ne suffit alors pas à effacer le journal d'audit.

## 25.5 Secrets

- `.env` dans `shared/`, `chmod 600`, propriétaire l'utilisateur applicatif. **Jamais versionné.**
- `APP_KEY` généré à l'installation, sauvegardé **hors ligne** — sans lui, les sessions chiffrées et
  les données chiffrées au repos sont définitivement illisibles.
- Secrets de CI dans GitHub Actions Secrets : clé SSH de déploiement, identifiants du compte de migration de chaque environnement,
  clés du stockage de sauvegarde.
- Clé de déploiement SSH **dédiée, restreinte à l'utilisateur de déploiement**, sans accès `root`.
- Rotation documentée dans `docs/ops/`.
- Option retenue si vous souhaitez versionner la configuration : `php artisan env:encrypt`, la clé
  restant hors dépôt.

## 25.6 CI/CD — GitHub Actions

```
Sur pull request                    Sur fusion dans main
─────────────────                   ────────────────────
pint --test                         (toute la validation)
larastan (niveau 6)                        ↓
phpunit (MySQL en service)          déploiement automatique en préproduction
npm run build                              ↓
budget de poids  ⛔ si dépassé      ptr:check-invariants sur préproduction
playwright (parcours critiques)            ↓
                                    ⏸ APPROBATION MANUELLE
                                           ↓
                                    déploiement en production
                                           ↓
                                    /up + invariants ; retour arrière si échec
```

**La mise en production reste une décision humaine explicite.** Sur une application qui porte la
comptabilité de l'entreprise et dont les données ne se suppriment pas, le déploiement continu
automatique jusqu'en production serait une erreur de jugement, pas une modernité.

---

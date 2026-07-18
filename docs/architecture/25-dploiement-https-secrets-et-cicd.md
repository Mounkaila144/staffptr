# 25. Déploiement, HTTPS, secrets et CI/CD

## 25.1 Serveur

VPS unique (A-03), 2 vCPU / 4 Go / 80 Go SSD suffisent largement à 100 utilisateurs.
Debian 12 stable, système en **UTC**.

| Composant | Rôle |
|---|---|
| Nginx | TLS, en-têtes de sécurité, Brotli, `X-Accel-Redirect`, ressources statiques |
| PHP-FPM 8.3 | Application, OPcache activé, JIT désactivé (sans intérêt ici) |
| MySQL 8 | Données, sessions |
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
| `ptrstaff_app` | `SELECT, INSERT, UPDATE` — **pas de `DELETE`** sur les tables protégées ; **`INSERT` seul** sur `audit_logs` | Application (`.env`) |
| `ptrstaff_migrate` | `ALL` sur le schéma | Migrations, déploiement |

Les identifiants de `ptrstaff_migrate` **ne figurent pas dans le `.env` applicatif** : ils sont
injectés par le script de déploiement depuis le magasin de secrets de la CI, le temps de la
migration. Un `.env` compromis ne suffit alors pas à effacer le journal d'audit.

## 25.5 Secrets

- `.env` dans `shared/`, `chmod 600`, propriétaire l'utilisateur applicatif. **Jamais versionné.**
- `APP_KEY` généré à l'installation, sauvegardé **hors ligne** — sans lui, les sessions chiffrées et
  les données chiffrées au repos sont définitivement illisibles.
- Secrets de CI dans GitHub Actions Secrets : clé SSH de déploiement, identifiants `ptrstaff_migrate`,
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

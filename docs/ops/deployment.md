# Déploiement et retour arrière

> Story 11.1, Task 8 (AC 29, AC 39 à 45, AC 58, AC 59).

## 1. Principe

Le déploiement est **atomique par lien symbolique**. Une release se construit entièrement à côté
de celle qui sert, puis `current` est repointé d'un seul geste. À aucun instant un visiteur ne voit
une application à moitié déployée — c'est ce qui permet de se passer de `php artisan down` pour un
déploiement ordinaire (AC 41).

```
/srv/staffptr/production/
├── releases/
│   ├── 20260817120000/     ← précédente, conservée pour le retour arrière
│   └── 20260817143000/     ← nouvelle
├── shared/
│   ├── .env                ← secrets, jamais dans une release
│   ├── storage/            ← pièces jointes, jamais dans une release
│   └── ops/
│       └── restore-log.md  ← registre persistant (AC 16)
└── current -> releases/20260817143000
```

**Pourquoi `shared/`.** Les releases sont supprimées à la rotation. Tout ce qui doit survivre —
secrets, pièces jointes, registre de restauration — vit hors d'elles.

## 2. Séquence exacte (AC 40)

`deploy/deploy.sh` l'exécute dans cet ordre, qui n'est pas arbitraire :

| # | Étape | Pourquoi à cette place |
|---|---|---|
| 1 | Récupération du code | — |
| 2 | `composer install --no-dev -o` | Avant les caches, qui ont besoin des classes. |
| 3 | `npm ci && npm run build` | Avant la bascule : une release sans assets est cassée. |
| 4 | Liens vers `shared` | **Avant les caches** : un cache de configuration construit sans le `.env` définitif figerait les mauvaises valeurs. |
| 5 | `migrate --force` | Avec l'utilisateur **privilégié**, distinct de l'applicatif. |
| 6 | Caches Laravel | Après `shared`, jamais avant. |
| 7 | Bascule de `current` | `ln -sfn` + `mv -Tf` : atomique. Un `rm` puis `ln` laisserait une fenêtre sans `current`. |
| 8 | Rechargement PHP-FPM | L'OPcache sert encore l'ancien chemin sans lui. |
| 9 | `queue:restart` (AC 29) | Les workers exécutent l'ancien code tant qu'ils n'ont pas redémarré. |
| 10 | Porte post-déploiement | `/up` + invariants. Échec ⇒ **retour arrière automatique** (AC 44). |
| 11 | Rotation | Trois releases conservées. |

## 3. Approbation manuelle en production (AC 43, AC 59)

Toute fusion dans `main` déploie **automatiquement en préproduction** et y exécute
`ptr:check-invariants` (AC 42). La production, elle, exige une approbation humaine explicite.

Cette approbation est portée par le **GitHub Environment `production`** et ses reviewers requis,
pas par une condition dans le workflow. La distinction est importante : une règle écrite dans le
fichier de workflow peut être contournée en modifiant ce fichier ; une protection d'environnement
ne le peut pas.

**Personnes autorisées à approuver** : à renseigner par la direction (Task 1, AC 43).

## 4. Retour arrière

```bash
# Release précédente
/srv/staffptr/production/current/deploy/rollback.sh /srv/staffptr/production

# Release nommée
/srv/staffptr/production/current/deploy/rollback.sh /srv/staffptr/production /srv/staffptr/production/releases/20260817120000
```

Le retour arrière est **le même geste que le déploiement, dans l'autre sens**. C'est ce qui le rend
fiable : il n'emprunte pas un chemin de secours rarement exercé.

### Ce qu'il ne fait pas : annuler les migrations

Une migration appliquée est un fait ; la défaire automatiquement détruirait des données. Le produit
n'écrit que des migrations **additives et compatibles avec la version précédente**, précisément
pour que le retour arrière du code suffise.

Une migration destructrice — suppression de colonne, changement de type incompatible — ne peut donc
pas être déployée seule. Elle se fait en deux temps :

1. Release N : la nouvelle colonne est ajoutée et alimentée, l'ancienne reste lue.
2. Release N+1, après validation : l'ancienne colonne est retirée.

Entre les deux, le retour arrière reste possible.

## 4bis. Piège du transfert manuel — `bootstrap/cache`

`deploy.sh` clone depuis Git et n'est pas concerné. Mais si vous transférez un arbre de travail à
la main — `rsync`, `scp` —, **excluez `bootstrap/cache`** :

```bash
rsync -az --exclude '.git' --exclude 'vendor' --exclude 'node_modules' \
      --exclude 'storage' --exclude '.env' --exclude 'bootstrap/cache' …
```

Le cache de paquets d'un poste de développement référence les dépendances **de développement**.
Transféré tel quel en production, où `composer install --no-dev` les a exclues, il produit une
erreur immédiate et opaque :

```
Class "Laravel\Pail\PailServiceProvider" not found
```

Réparation : supprimer `bootstrap/cache/*.php`, puis `php artisan package:discover` et rebâtir les
caches. Constaté le 2026-08-17.

## 5. Migration lourde (AC 41)

`php artisan down` **n'est pas une étape ordinaire**. Il n'est utilisé que pour une migration dont
la durée rend l'application incohérente, et alors avec une page française :

```bash
php artisan down --render="errors::503" --retry=60
```

## 6. Prérequis de première mise en service

- [ ] **Le retour arrière a réussi en conditions réelles**, pas seulement dans ce document (AC 58).
- [ ] Une restauration complète chronométrée valide le RTO de 4 h (AC 49).
- [ ] `ptr:check-invariants` est vert en préproduction puis en production (AC 50).
- [ ] La décision est datée et consignée dans `docs/ops/go-live-decisions.md` (AC 53).

## 7. Contact d'astreinte

À renseigner par la direction (Task 1, AC 45). Voir `docs/ops/incident.md`.

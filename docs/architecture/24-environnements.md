# 24. Environnements

## 24.1 Les quatre environnements

| | **Local** | **Test / CI** | **Préproduction** | **Production** |
|---|---|---|---|---|
| Emplacement | Poste MAMP | GitHub Actions | VPS, hôte virtuel | VPS, hôte virtuel |
| URL | `localhost:8000` | — | `staging.staff.ptrniger.com` | `staff.ptrniger.com` |
| Base | SQLite ou MySQL local | MySQL (Docker) | MySQL dédiée | MySQL dédiée |
| `APP_DEBUG` | `true` | `true` | `false` | **`false`** |
| Files | `sync` | `sync` | Redis | Redis |
| Courriel | `log` | `array` | `log` | `log` (aucun envoi en MVP) |
| Données | Factories | Factories | **Restauration de production anonymisée** | Réelles |
| Sauvegarde | Non | Non | Non | Quotidienne + test mensuel |

## 24.2 Préproduction

Rôle : **valider la migration et la restauration**, pas seulement les fonctionnalités.

Elle est alimentée par une **restauration de la sauvegarde de production, anonymisée** (noms,
téléphones, pièces jointes remplacés) par la commande `ptr:anonymize`. C'est ce qui rend le test de
restauration du § 21.3 utile en continu plutôt que théorique : la préproduction *est* la
vérification de la sauvegarde.

> **DEC-05 — tranché le 19/07/2026 : préproduction ET production sur le VPS existant, déjà partagé
> avec d'autres projets.** Coût supplémentaire nul. La direction est seule responsable de l'ensemble
> des projets hébergés et assume les conséquences ci-dessous.
>
> **Ce que cette décision coûte, écrit pour ne pas être redécouvert plus tard :**
>
> 1. **Le modèle de menace du § 14.1 est affaibli.** La séparation en deux comptes MySQL existe pour
>    qu'un `.env` compromis ne suffise pas à effacer le journal d'audit. Sur une instance partagée,
>    la faille d'un **autre** projet devient un chemin vers la même instance MySQL, et selon les
>    droits système vers `shared/.env`. La contre-mesure obligatoire est l'isolation système :
>    utilisateur dédié, pool PHP-FPM propre, `.env` en `chmod 600` illisible par les autres projets.
> 2. **`log_bin_trust_function_creators = 1` est un réglage `GLOBAL`.** Exigé par les déclencheurs
>    d'immuabilité (§ 14.1), il relâche un contrôle pour **toutes** les bases de l'instance, pas
>    seulement `ptrstaff`. Décision prise en connaissance de cause.
> 3. **Redis est partagé.** `REDIS_PREFIX` et un index `REDIS_DB` distincts sont **obligatoires** :
>    sans eux, un `cache:clear` de PTR Staff efface les clés des autres projets, et réciproquement.
> 4. **Le disque est partagé** avec la rétention 10 ans de NFR26 et les sauvegardes quotidiennes.
>    La surveillance d'espace disque devient une exigence, pas un confort.
> 5. **La contagion n'est plus limitée à préproduction → production** : n'importe quel projet du
>    serveur peut affecter n'importe quel autre.
>
> **Condition de révision.** Le provisionnement reste paramétré par hôte : un déplacement vers un VPS
> dédié ne doit modifier que des valeurs, jamais du code. Si l'entreprise croît ou si un litige rend
> l'opposabilité du journal d'audit critique, cette décision doit être rouverte en premier.

## 24.3 Commande d'invariants

`php artisan ptr:check-invariants`, quotidienne, alerte si écart :

- `APP_DEBUG=false` et `APP_ENV=production`.
- Exactement **2** comptes détenant `depense.approuver` (PERM-05).
- Aucun `super_admin` porteur d'une permission métier (PERM-03).
- Déclencheurs d'immuabilité présents sur `audit_logs`.
- Utilisateur MySQL applicatif dépourvu de `DELETE` sur `audit_logs`.
- Dernière sauvegarde de moins de 26 h.
- Aucune dépense `payee` sans deux approbations distinctes — **détection de dérive après incident**.

Le dernier point est le plus important : il vérifie l'invariant *dans les données*, pas seulement
dans le code. C'est ce qui détecte une manipulation en base ou une régression passée en production.

---

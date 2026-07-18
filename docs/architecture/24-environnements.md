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

> **DEC-05.** Préproduction sur le même VPS (isolation par utilisateur système, base et hôte virtuel
> distincts) : ~0 € de coût supplémentaire, exploitation simple. L'inconvénient est réel et assumé :
> une saturation de disque ou une erreur d'opération en préproduction peut affecter la production.
> Un second petit VPS lève ce risque pour ~5 €/mois. Votre arbitrage.

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

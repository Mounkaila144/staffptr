# 5. Structure des modules

## 5.1 Principe

Le PRD recommande un découpage en modules internes (§ 8.2). Il est appliqué **par sous-dossiers de
namespace à l'intérieur de l'arborescence Laravel standard**, et non par paquets Composer ou
dossiers racine supplémentaires.

Raison : `php artisan make:*` est imposé par les standards du dépôt et fonctionne nativement avec
`make:model Finance/Expense`. Un découpage en paquets casserait cette ergonomie, imposerait un
autoloader dédié et ajouterait une couche de configuration pour un bénéfice nul à cette taille.
Cette structure **ne crée aucun dossier racine nouveau**, conformément à `source-tree.md`.

## 5.2 Les cinq modules

| Module | Périmètre | Étape | Objets principaux |
|---|---|---|---|
| **Platform** | Paramètres, audit, notifications, pièces jointes, calendrier | 1 | `Setting`, `AuditLog`, `Attachment`, `Holiday` |
| **Identity** | Personnes, comptes, rôles, sessions, organisation | 1 | `Person`, `User`, `Role`, `Absence`, `Department` |
| **Work** | Objectifs, projets, tâches, livrables | 2 | `Objective`, `Project`, `Task`, `Deliverable` |
| **Accountability** | Rapports, blocages, revues, stagiaires, documents | 3 | `DailyReport`, `Blocker`, `WeeklyReview`, `Internship` |
| **Finance** | Comptes, clients, contrats, encaissements, dépenses, parts, réserve, rapprochement, clôture | 1 *(dépenses)* et 4 | `Account`, `Contract`, `Payment`, `Expense`, `Share`, `Reserve` |

> **Dépendance à surveiller.** Le circuit de dépense à double approbation est livré à l'Étape 1
> alors que le module Finance est de l'Étape 4. `Expense` est donc créé dès l'Étape 1 **sans**
> imputation comptable ni compte financier, et enrichi à l'Étape 4 (FR115 vs FR123). Les migrations
> doivent le prévoir : colonnes financières ajoutées par migration ultérieure, jamais par
> modification de la migration d'origine.

## 5.3 Arborescence

```
app/
├── Console/Commands/            ptr:create-first-admin, ptr:test-restore
├── Http/
│   ├── Controllers/{Platform,Identity,Work,Accountability,Finance}/
│   ├── Requests/{…mêmes modules}/     Form Requests — validation exclusive
│   ├── Resources/                     API Resources (§ 10.4)
│   └── Middleware/                    EnsurePasswordChanged, EnsureAccountActive, …
├── Models/{Platform,Identity,Work,Accountability,Finance}/
├── Policies/{…mêmes modules}/         Une policy par modèle protégé
├── Services/{…mêmes modules}/         Logique métier transactionnelle
├── Support/
│   ├── Money.php                      Entiers XOF, formatage
│   ├── PhoneNumber.php                Normalisation +227
│   └── Auditing/                      AuditLogger, trait Auditable
├── Enums/                             États, niveaux d'alerte, types de compte
└── Observers/                         Filet de sécurité d'audit

resources/js/
├── app.js                             Amorçage Inertia
├── Layouts/                           AppLayout, AuthLayout
├── Pages/{Platform,Identity,Work,Accountability,Finance}/
├── Components/                        Les 18 composants du système de design UX
└── Composables/                       useDraft, useMoney, usePermissions
```

**Règle de couplage.** Un service d'un module peut lire les modèles d'un autre module, mais ne
peut pas écrire dedans : toute écriture inter-modules passe par le service propriétaire du modèle
cible. C'est la seule règle de découplage imposée — elle suffit à empêcher l'enchevêtrement sans
introduire d'interfaces ni d'injection de contrats.

---

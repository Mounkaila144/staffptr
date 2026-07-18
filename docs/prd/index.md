<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

# PTR Staff — Epics

Généré depuis `docs/epics-stories.md`. Source produit : `docs/prd.md`.
Source technique : `docs/architecture.md`.

| Epic | Titre | Stories | Fichier |
|---|---|---|---|
| 1 | Fondation technique, base de données, CI et sécurité | 7 | [`epic-1-fondation-technique.md`](epic-1-fondation-technique.md) |
| 2 | Authentification, comptes, rôles et permissions | 10 | [`epic-2-authentification-roles.md`](epic-2-authentification-roles.md) |
| 3 | Organisation, profils, paramètres et documents internes | 8 | [`epic-3-organisation-profils.md`](epic-3-organisation-profils.md) |
| 4 | Calendrier, absences et autorisation des dépenses | 6 | [`epic-4-calendrier-absences-depenses.md`](epic-4-calendrier-absences-depenses.md) |
| 5 | Objectifs, projets, tâches et livrables | 8 | [`epic-5-objectifs-projets.md`](epic-5-objectifs-projets.md) |
| 6 | Rapport quotidien et blocages | 6 | [`epic-6-rapport-quotidien-blocages.md`](epic-6-rapport-quotidien-blocages.md) |
| 7 | Stagiaires et revues hebdomadaires | 6 | [`epic-7-stagiaires-revues.md`](epic-7-stagiaires-revues.md) |
| 8 | Finances : comptes, contrats, encaissements, parts, réserve, clôture | 13 | [`epic-8-finances.md`](epic-8-finances.md) |
| 9 | Alertes, tableaux de bord et notifications | 6 | [`epic-9-alertes-tableaux-de-bord.md`](epic-9-alertes-tableaux-de-bord.md) |
| 10 | Recherche, exports et qualité finale | 5 | [`epic-10-recherche-exports-qualite.md`](epic-10-recherche-exports-qualite.md) |
| 11 | Exploitation, sauvegarde, supervision et mise en service | 7 | [`epic-11-exploitation-livraison.md`](epic-11-exploitation-livraison.md) |

**Total : 82 stories.**

## Documents transverses

- [`socle-transverse.md`](socle-transverse.md) — SOC-01 à SOC-11, applicables à toute story
- [`ecarts-et-decisions.md`](ecarts-et-decisions.md) — écarts d'ordonnancement, décisions en attente
- [`tracabilite.md`](tracabilite.md) — correspondance PRD § 10 → epics
- [`phase-2.md`](phase-2.md) — hors périmètre, à ne pas anticiper

## Jalons

| Epic | Titre | Jalon | Stories | Dépend de |
|---|---|---|---|---|
| **1** | Fondation technique, base de données, CI et sécurité | 1 | 7 | — |
| **2** | Authentification, comptes, rôles et permissions | 1 | 10 | Epic 1 |
| **3** | Organisation, profils, paramètres et documents internes | 1 | 8 | Epic 2 |
| **4** | Calendrier, absences et autorisation des dépenses | 1 | 6 | Epic 3 |
| **5** | Objectifs, projets, tâches et livrables | 2 | 8 | Epic 4 |
| **6** | Rapport quotidien et blocages | 3 | 6 | Epic 5 |
| **7** | Stagiaires et revues hebdomadaires | 3 | 6 | Epic 6 |
| **8** | Finances : comptes, contrats, encaissements, parts, réserve, clôture | 4 | 13 | Epic 4, Epic 5 |
| **9** | Alertes, tableaux de bord et notifications | 4 | 6 | **Epic 7 et Epic 8** |
| **10** | Recherche, exports et qualité finale | 4 | 5 | Epic 9 |
| **11** | Exploitation, sauvegarde, supervision et mise en service | **transverse** | 7 | **par tranche — voir ci-dessous** |

**Epic 9 dépend d'Epic 7 autant que d'Epic 8.** Le tableau de bord consolidé (9.5) agrège rapports
manquants et stagiaires par tuteur, produits par les epics 6 et 7 : ils ne sont pas accessibles
transitivement depuis Epic 8.

**Les dépendances d'Epic 11 se déclarent par tranche**, pas globalement :

| Tranche | Dépend de | Livrée pour |
|---|---|---|
| 11.1 – 11.3 — sauvegarde, restauration, supervision | Epic 1 | avant la première production |
| 11.4 – 11.6 — ordonnanceur, préproduction, déploiement | Epic 4 | porte du Jalon 1 |
| 11.7 — recette de mise en service | le jalon concerné | rejouée à chaque jalon |

```
Jalon 1 — Socle          Epic 1 → Epic 2 → Epic 3 → Epic 4 ─┐
                                            ├→ Epic 11 (11.1-11.6) → MISE EN PRODUCTION 1
Jalon 2 — Objectifs      Epic 5 ────────────────┼→ 11.7 → MISE EN PRODUCTION 2
Jalon 3 — Redevabilité   Epic 6 → Epic 7 ───────────┼→ 11.7 → MISE EN PRODUCTION 3
Jalon 4 — Argent         Epic 8 → Epic 9 → Epic 10 ──────┴→ 11.7 → MISE EN PRODUCTION 4
```

**Epic 11 n'est pas un epic de fin de projet.** Ses stories 11.1 à 11.6 sont un prérequis de la **première**
mise en production, à la fin du Jalon 1 : une application qui porte la comptabilité de l'entreprise
ne va pas en production sans sauvegarde vérifiée ni supervision. La story 11.7 est rejouée à chaque jalon.

---

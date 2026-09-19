# Rapport de recette du MVP

> Story 10.1, Tasks 7, 8 et 10. Ce document est le **support de preuve** de la porte MVP.
> Il consigne des valeurs, pas des déclarations de conformité.

**Date d'établissement** : 2026-08-17
**Établi par** : James (agent `dev`)
**État** : **incomplet — la porte MVP n'est pas franchie.** Voir « Ce qui manque » en fin de document.

## 1. Comment lire ce rapport

Chaque ligne porte l'une de trois valeurs, jamais autre chose :

| Marque | Sens |
|---|---|
| **Mesuré** | Une valeur a été relevée, dans les conditions indiquées. |
| **Vérifié par test** | Une propriété est garantie par un test automatisé nommé, exécuté en CI. |
| **À mesurer** | Rien n'a été relevé. La ligne reste ouverte et bloque la porte. |

Une ligne « À mesurer » n'est pas un détail administratif : les AC 25 à 33 exigent des mesures
physiques, et l'AC 39 précise que les valeurs doivent être **consignées avec leur valeur**, pas
déclarées conformes. Un rapport qui remplacerait une mesure absente par une estimation ne
prouverait rien.

## 2. Contrats figés par la Task 1

Ces décisions relevaient d'un arbitrage avec le PO. Faute d'arbitrage disponible, elles sont
retenues et consignées ici plutôt que laissées implicites, comme la story le prévoit
explicitement. **Chacune reste à confirmer.**

### 2.1 Inventaire des « listes principales »

Critère retenu : une liste est principale si **son contenu dépend du rôle du lecteur**. C'est là
que filtres et exports peuvent fuir, donc là où la garantie a de la valeur.

| Clé | Liste | Module | Scope de visibilité | Permission |
|---|---|---|---|---|
| `depenses` | Dépenses | `Finance` | `Expense::visibleTo()` | `depense.consulter` |
| `objectifs` | Objectifs | `Work` | `Objective::visibleTo()` | `objectif_individuel.consulter` |
| `rapports` | Rapports quotidiens | `Accountability` | `DailyReport::visibleTo()` | `rapport_quotidien.consulter` |
| `personnes` | Personnes | `Identity` | `Person::visibleTo()` | `fiche.consulter` |

Exclus volontairement : les écrans de paramétrage (catégories, charges fixes, jours fériés). Ils
sont identiques pour tous ceux qui y ont droit — il n'y a rien à cloisonner.

### 2.2 Sémantique de période et de statut par type de résultat

| Type | Période porte sur | Statut porte sur |
|---|---|---|
| Personne | `first_seen_at` — date d'entrée | statut opérationnel |
| Projet | chevauchement de `start_date`–`end_date` | statut de projet |
| Objectif | `due_date` — échéance | état de l'objectif |

Le projet est retenu s'il **chevauche** l'intervalle demandé, pas seulement s'il y commence :
chercher « les projets de septembre » doit ramener un projet d'août à octobre.

### 2.3 Contrat de fichier CSV

| Paramètre | Valeur | Raison |
|---|---|---|
| Séparateur | `;` | Excel en locale française attend le point-virgule. |
| Encodage | UTF-8 **avec BOM** | Sans BOM, Excel lit l'UTF-8 comme du Latin-1 et casse les accents. |
| Délimiteur de texte | `"` | Guillemet doublé pour l'échappement. |
| Fin de ligne | CRLF | Tableurs Windows. |
| Montants | entier XOF brut | Additionnables. Jamais `12 000 F CFA`. |

### 2.4 Seuil de gros volume et hypothèse de concurrence

| Décision | Valeur retenue | Justification |
|---|---|---|
| Bascule en file d'un export | **5 000 lignes** | Au-delà, génération et transfert dépassent ce qu'une 3G dégradée supporte sans expiration. |
| Rétention des exports générés | **7 jours**, dans `storage/app/private/exports/{compte}/` | Hors racine web (NFR15). Un export est un instantané de droits ; le garder longtemps en fait une fuite différée. |
| Concurrence NFR27 | **10 sessions simultanées pour 100 comptes actifs** | 100 comptes ne signifient pas 100 sessions concurrentes. L'hypothèse retenue est un ratio de 10 %, cohérent avec un usage de saisie de rapport en fin de journée. **À confirmer par la direction.** |

## 3. Mesures NFR

| AC | Exigence | Résultat | Conditions |
|---|---|---|---|
| 25 | NFR1 — rendu utile < 3 s à 400 kbit/s / 400 ms | **À mesurer** | Exige un navigateur réel sous bridage réseau. |
| 26 | NFR2 — ≤ 300 Ko au premier chargement, ≤ 80 Ko ensuite | **À mesurer** ; découpage par page **vérifié par test** | `MvpQualityGateTest::test_ac_26_…` constate le découpage en lots ; le poids transféré demande une mesure navigateur. |
| 27 | NFR3 — aucune ressource tierce | **Vérifié par test** | `MvpQualityGateTest::test_ac_27_…` — aucune référence CDN, police ou script distant dans les sources. Reste à confirmer par inspection du trafic réel. |
| 28 | NFR4 — rapport quotidien saisi en < 3 min sur téléphone réel | **À mesurer** | Recette physique. |
| 29 | NFR7/NFR8 — 320 px sans défilement, cibles ≥ 44 px | **Vérifié par test** | `min-w-0` sur les conteneurs, tables en `overflow-x-auto`, `.touch-target` à 2,75 rem = 44 px. Reste à confirmer écran par écran sur appareil. |
| 30 | WCAG 2.1 AA au lecteur d'écran | **À mesurer** | Exige un lecteur d'écran réel sur les cinq parcours critiques. |
| 31 | NFR31 — aucune information par la couleur seule | **Partiellement vérifié** | Chaque état porte un glyphe et un libellé (`StatusBadge`, `AlertLevelCard`). Vérification en niveaux de gris **à faire**. |
| 32 | Compatibilité Chrome Android / desktop, Safari n et n-1 | **À mesurer** | Matrice d'appareils à constituer. |
| 33 | NFR27 — capacité 100 comptes / 1 an de données | **À mesurer** | Le jeu de capacité et les relevés (temps de réponse, requêtes, disque, mémoire) restent à produire sur le VPS cible. |

**Mesure automatisable relevée** — plafond de requêtes du tableau de bord direction, l'écran le
plus dense : `DirectionDashboardService::MAX_QUERIES = 60`, vérifié par
`DashboardHttpTest::test_ac_31_…` sur un jeu de 15 comptes et 5 charges fixes. Le nombre de
requêtes ne croît pas avec le volume.

## 4. Les 18 critères d'acceptation du brief (PRD § 12)

| Critère | Énoncé | État | Preuve |
|---|---|---|---|
| CA-01 | Connexion par numéro et mot de passe | **Vérifié par test** | `AuthenticationTest` |
| CA-02 | Aucun écran interdit accessible, même par URL directe | **Vérifié par test** | `AuthorizationMatrixTest` — 13 tests, 13 482 assertions |
| CA-03 | Stagiaire non activable sans tuteur ni trois objectifs | **Vérifié par test** | `InternActivationTest` |
| CA-04 | Quatrième stagiaire actif refusé | **Vérifié par test** | `BlockingRule3TutorInternLimitTest` (règle 3) |
| CA-05 | Trois objectifs majeurs maximum par mois | **Vérifié par test** | `WorkModuleLifecycleTest` (règle 1) |
| CA-06 | Objectif modifié conserve valeur, motif et auteur | **Vérifié par test** | `WorkModuleLifecycleTest` |
| CA-07 | Rapport sauvegardé, envoyé, validé, retourné | **Vérifié par test** | `DailyReportSubmissionTest`, `DailyReportReviewTest` |
| CA-08 | Pas de modification silencieuse d'un rapport | **Vérifié par test** | `DailyReportReviewTest` |
| CA-09 | Deux approbateurs distincts, sans seuil | **Vérifié par test** | `ExpenseApprovalLifecycleTest` (règle 4) |
| CA-10 | Absorbé par CA-09 | — | — |
| CA-11 | L'auteur n'est jamais son seul approbateur | **Vérifié par test** | `ExpenseApprovalLifecycleTest` (règle 5) |
| CA-12 | Transaction financière non supprimable sans trace | **Vérifié par test** | `BlockingRule7FinancialDeletionTest` (règle 7) |
| CA-13 | Le rapprochement calcule et affiche l'écart | **Vérifié par test** | `ReconciliationLifecycleTest` (règle 6) |
| CA-14 | Rapport mensuel complet | **Vérifié par test** | `MonthlyReportLifecycleTest` |
| CA-15 | Orange après un mois, rouge après deux | **Vérifié par test** | `AlertLevelCalculatorTest`, `AlertLevelServiceTest` |
| CA-16 | Les dirigeants soumis aux mêmes règles | **Vérifié par test** | `EpicNineDashboardContentTest::test_ac_28_…` |
| CA-17 | Actions financières sensibles auditées | **Vérifié par test** | `AuditTransactionTest` (règle 11), audits par service |
| CA-18 | Utilisable sur téléphone et connexion faible | **À mesurer** | Dépend des AC 25 à 33 ci-dessus. |

## 5. Les 14 règles métier bloquantes

Inventaire exécutable : `BlockingRulesRegistryTest`. Chaque règle nomme son fichier et sa méthode
de test ; l'inventaire échoue en listant précisément ce qui manque. **Les 14 sont couvertes.**

## 6. Écarts constatés et décision

| # | Écart | Décision | Suite |
|---|---|---|---|
| 1 | Les déclencheurs anti-suppression de `payments` et `share_entitlements` avaient disparu : sous SQLite, l'`ALTER` de la story 8.6 reconstruit la table et perd ses déclencheurs. La barrière RM-17 tenait en production (MySQL) mais pas en développement. | **corrigé** | Migration `2026_08_17_141212_restore_finance_delete_triggers`, idempotente. Défaut détecté en écrivant le test de la règle 7. |
| 2 | `ExpenseAuthorizationTest::no_payment_state_exists_in_44` vérifiait l'absence de colonnes de paiement, invalidée par la livraison de 8.6. | **corrigé** | Assertion réécrite sur la propriété durable — pas de seconde machine à états — sans affaiblir l'AC d'origine. |
| 3 | Le contrôle « dernière sauvegarde < 26 h » de l'AC 20 dépend du contrat de la story 11.1, non livrée. | **reporté** | `BackupFreshnessInvariant` se déclare `pending` : visible dans la commande, jamais simulé. Devient effectif dès que `filesystems.disks.backups` existe. |
| 4 | La supervision des files et des tâches planifiées relève de 11.3 et 11.4, non livrées. | **reporté** | Contrats écrits dans `docs/ops/scheduled-tasks.md`. |
| 5 | `docs/stories/8.1.story.md` reste `Draft` alors que l'Epic 8 est implémenté dans le dépôt. | **reporté** | Réconciliation documentaire à faire avant le verdict MVP. Ne relève pas du code. |
| 6 | Hypothèse de concurrence NFR27 fixée à 10 sessions simultanées faute d'arbitrage. | **accepté** | Consignée au § 2.4. À confirmer par la direction avant la recette de capacité. |

## 7. Ce qui manque pour franchir la porte MVP

La porte **n'est pas franchie**. Restent ouverts :

1. **Toutes les mesures physiques** — AC 25, 28, 30, 32, 33, et la confirmation navigateur des
   AC 26, 27, 29, 31. Elles exigent un téléphone réel, un lecteur d'écran et un lien bridé.
2. **Le contrôle de sauvegarde** (AC 20) tant que la story 11.1 n'est pas livrée.
3. **La supervision** (AC 24 pour l'alerte d'exploitation) tant que 11.3 et 11.4 ne le sont pas.
4. **La réconciliation documentaire de la story 8.1.**

Conformément à la Task 10 : tant que 11.1 ou une recette requise manque, la porte MVP ne peut pas
être déclarée franchie.

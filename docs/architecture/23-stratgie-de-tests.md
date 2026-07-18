# 23. Stratégie de tests

Exigence PRD (§ 8.5) : **Unit + Integration**, un test par changement, **toute règle métier
bloquante testée**, campagne d'accès par URL directe à chaque étape, recette manuelle sur téléphone
réel en réseau dégradé.

## 23.1 Pyramide

| Niveau | Outil | Emplacement | Couvre |
|---|---|---|---|
| **Unitaire** | PHPUnit | `tests/Unit/` | Calculs purs : parts, réserve, alerte, prorata, normalisation de téléphone, formatage XOF |
| **Intégration** | PHPUnit + base | `tests/Feature/` | Services transactionnels, immuabilité, audit, verrous, clôture |
| **API / HTTP** | PHPUnit | `tests/Feature/Http/` | Contrôleurs, validation, codes de statut, **matrice d'autorisation** |
| **E2E** | Playwright | `tests/e2e/` | Parcours critiques réels dans un navigateur |

Analyse statique Larastan niveau 6 en complément, dans la même chaîne CI.

## 23.2 Règles métier bloquantes — un test dédié chacune

Liste opposable, issue du § 8.5 du PRD. Chaque ligne est un test nommé, et **l'absence d'un de ces
tests bloque la porte de qualité de l'étape** :

| # | Règle | Réf. |
|---|---|---|
| 1 | Maximum 3 objectifs majeurs validés par personne et par mois | RM-05, CA-05 |
| 2 | Maximum 5 priorités mensuelles d'entreprise | RM-04 |
| 3 | Maximum 3 stagiaires actifs par tuteur — bloquant | RM-06, CA-04 |
| 4 | Deux approbateurs **distincts**, sans seuil | RM-09, FR117, CA-09 |
| 5 | Le demandeur n'est jamais approbateur, même `direction` | RM-10, FR119, CA-11 |
| 6 | Préparateur ≠ contrôleur sur rapprochement et rapport mensuel | RM-16, FR151 |
| 7 | Suppression financière impossible — modèle, route et base | RM-17, NFR20, CA-12 |
| 8 | Aucune écriture imputable sur un mois clôturé | FR158, FR114 |
| 9 | `super_admin` n'a **aucune** permission métier | PERM-03, C13 |
| 10 | La suspension invalide **toutes** les sessions immédiatement | FR8, PERM-08 |
| 11 | L'échec d'écriture d'audit annule l'opération métier | NFR21 |
| 12 | Unicité du téléphone sur comptes non archivés uniquement | FR3 |
| 13 | Les parts 10 % / 30 % restent payables en alerte rouge | RM-14, FR165 |
| 14 | La somme des parts est exactement égale à la base (reste entier) | FR130, NFR22 |

## 23.3 Campagne d'autorisation — NFR14 / CA-02

Le PRD exige de couvrir **chaque combinaison rôle × ressource protégée**. Une campagne manuelle
n'est pas tenable et ne serait pas rejouée. Elle est donc **générée** :

```php
// tests/Feature/Http/AuthorizationMatrixTest.php
public static function matrix(): array   // rôle × route → statut attendu
{
    // Alimenté depuis config/authorization-matrix.php, transcription directe du § 4.3 du PRD
}

/** @dataProvider matrix */
public function test_acces_direct_par_url(string $role, string $route, int $expected): void
```

Deux propriétés en font un dispositif utile plutôt qu'un test de plus :

1. **Un test complémentaire échoue si une route déclarée n'apparaît pas dans la matrice.** Ajouter
   une route protégée sans déclarer sa politique d'accès casse la chaîne. C'est ce qui empêche la
   couverture de se dégrader étape après étape.
2. `403` et `404` sont distingués de toute redirection — PERM-02 interdit la redirection silencieuse,
   et un test qui accepterait une `302` validerait précisément le défaut qu'on cherche à empêcher.

## 23.4 E2E — Playwright

Limité aux parcours où le chronomètre et l'ergonomie font partie de l'exigence :

| Parcours | Vérifie |
|---|---|
| Rapport quotidien de bout en bout, réseau bridé | NFR4 (< 3 min), NFR5 (brouillon), UX § 4.1 |
| Approbation de dépense depuis la notification | FR121, **3 interactions maximum** |
| Connexion → changement de mot de passe imposé | FR5 |
| Encaissement → calcul des parts → réserve | FR113, FR131, FR143 |

Playwright bride le réseau à 400 kbit/s / 400 ms pour reproduire les conditions de NFR1. **Cela ne
remplace pas la recette sur téléphone réel** (§ 8.5 du PRD, UX § 11.3), qui reste obligatoire et
opposable avant chaque mise en service.

## 23.5 Base de test — DEC-02

> Les standards du dépôt prévoient SQLite en développement. **Recommandation : exécuter la suite sur
> MySQL**, au moins en CI. Les déclencheurs d'immuabilité, la colonne générée de FR3, les contraintes
> `CHECK` et les verrous `lockForUpdate()` **n'existent pas ou se comportent différemment sous
> SQLite**. Or ce sont précisément les garanties les plus critiques du produit : les tester sur un
> moteur qui ne les applique pas reviendrait à ne pas les tester. MySQL tourne en service Docker en
> CI ; en local, SQLite reste utilisable pour les tests unitaires purs et rapides.

---

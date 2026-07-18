# 6. Modèle de données

## 6.1 Règles transverses

| Règle | Application |
|---|---|
| Montants | `BIGINT UNSIGNED`, entiers XOF, cast `integer`. Aucun `DECIMAL`, aucun flottant (NFR22) |
| Horodatages | `TIMESTAMP` UTC (DEC-01) |
| Dates métier | `DATE`, calendrier civil `Africa/Niamey` — jamais converties |
| Clés | `BIGINT UNSIGNED` auto-incrémenté ; `UUID` public pour les objets exposés en URL sensible |
| Suppression | **Aucune table métier ne porte de `deleted_at`.** Voir § 15 |
| États | Colonnes `ENUM` adossées à des enums PHP typés |
| Audit | Toute table de FR21 est couverte par l'observateur d'audit |

## 6.2 Noyau identité — A-06 / CONTRA-02

La séparation **personne / compte applicatif** est structurelle dès l'Étape 1, même sans les
abonnements de phase 2 qui la motivaient (CONTRA-02). Elle sert immédiatement FR4 : le retour d'une
personne dans l'entreprise crée un nouveau compte rattaché à la fiche personne existante.

```mermaid
erDiagram
    people ||--o{ users : "porte 0..n comptes successifs"
    people ||--o{ person_documents : "contrat, convention"
    users ||--o{ model_has_roles : ""
    users ||--o{ sessions : ""
    users ||--o{ login_attempts : ""
    users ||--o{ audit_logs : "auteur"
    users ||--o{ user_history : "changements historisés"

    people {
        bigint id PK
        string full_name
        string operational_status "actif|absent|suspendu|sorti"
        date first_seen_at
    }
    users {
        bigint id PK
        bigint person_id FK
        string phone "E.164 +227…"
        string phone_unique_key "généré: NULL si archivé"
        string password
        enum state "invite|actif|suspendu|termine|archive"
        bool must_change_password
        timestamp locked_until
        smallint failed_attempts
    }
```

**FR3 — unicité du téléphone sur les comptes non archivés.** Résolue au niveau base, pas
applicatif, par colonne générée et index unique. MySQL et MariaDB n'indexent pas les valeurs
`NULL` en doublon, ce qui produit exactement la sémantique demandée :

```sql
ALTER TABLE users
  ADD COLUMN phone_unique_key VARCHAR(20)
    GENERATED ALWAYS AS (IF(state = 'archive', NULL, phone)) STORED,
  ADD UNIQUE INDEX users_phone_active_unique (phone_unique_key);
```

Un compte archivé libère donc son numéro (FR3), et l'historique reste porté par `people`.
Sous réserve de Q17 / CONTRA-09 : si la direction impose l'unicité permanente, l'index devient un
`UNIQUE` simple sur `phone` — changement d'une ligne de migration.

**Historisation (FR18).** Table `user_history` : `user_id`, `field`, `old_value`, `new_value`,
`changed_by`, `changed_at`, `reason`. Alimentée par le même service que l'audit, dans la même
transaction. Elle sert la consultation métier ; `audit_logs` sert le contrôle. Les deux coexistent
volontairement : `audit_logs` est fermé à tous sauf `direction` (FR23), alors que l'historique d'une
fiche doit rester lisible par le responsable.

## 6.3 Noyau financier

```mermaid
erDiagram
    clients ||--o{ contracts : ""
    contracts ||--o{ invoices : ""
    contracts ||--o{ contract_shares : "10/60/30"
    contracts ||--o{ payments : "encaissements imputés"
    invoices ||--o{ payments : ""
    accounts ||--o{ payments : "crédite"
    accounts ||--o{ expenses : "débite"
    accounts ||--o{ reconciliations : ""
    expenses ||--o{ expense_approvals : "exactement 2 distinctes"
    payments ||--o{ share_entitlements : "déclenche le calcul"
    share_entitlements ||--o| expenses : "versée comme dépense ordinaire"
    payments ||--o{ reserve_movements : "20% du bénéfice"
    monthly_reports ||--o{ month_closures : "fige alerte + verrouille"
```

Points de conception notables :

- **`payments` et `expenses` ne sont jamais modifiés après validation.** Correction = nouvelle
  version liée ; annulation = contre-écriture liée. Voir § 15.
- **`share_entitlements`** matérialise le droit à une part (FR131) ; le **versement** est une
  `expense` ordinaire à deux signatures (FR134). Les deux sont liés, jamais confondus : un droit
  calculé n'est pas un paiement effectué.
- **`reserve_movements`** est un livre auxiliaire. Le montant de la réserve n'est jamais une colonne
  de solde : il est la somme du livre (§ 16).
- **`month_closures`** porte le verrou de clôture (FR158) et la trace de réouverture (FR159).
- **`accounts.opening_balance`** est la seule saisie directe de solde ; le solde courant est
  toujours calculé (FR100).

## 6.4 Journal d'audit

```sql
CREATE TABLE audit_logs (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id      BIGINT UNSIGNED NULL,          -- NULL = système (amorçage, tâche planifiée)
  actor_label   VARCHAR(120) NOT NULL,          -- dénormalisé : survit à l'archivage du compte
  occurred_at   TIMESTAMP(3) NOT NULL,
  auditable_type VARCHAR(120) NOT NULL,
  auditable_id  BIGINT UNSIGNED NULL,
  action        VARCHAR(60) NOT NULL,           -- created|updated|approved|cancelled|exported…
  old_values    JSON NULL,
  new_values    JSON NULL,
  reason        TEXT NULL,                      -- motif, obligatoire sur annulation/correction
  ip_address    VARBINARY(16) NULL,
  user_agent    VARCHAR(255) NULL,
  INDEX (auditable_type, auditable_id),
  INDEX (actor_id, occurred_at),
  INDEX (occurred_at)
) ENGINE=InnoDB;
```

`actor_label` est dénormalisé volontairement : un journal d'audit dont les lignes deviennent
illisibles parce que le compte auteur a été archivé ne remplit pas sa fonction.

---

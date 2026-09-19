# 27. Points requérant votre accord

Récapitulatif opposable. **Aucun de ces points ne bloque le démarrage de l'Étape 1** ; chacun est
appliqué selon la recommandation tant que vous n'en décidez pas autrement.

| Réf. | Décision | Recommandation | À trancher avant |
|---|---|---|---|
| **DEC-01** | Fuseau de stockage | UTC en base, Niamey à l'affichage | Étape 1 — première migration |
| **DEC-02** | Base des tests | MySQL en CI, pas SQLite | Étape 1 — mise en place CI |
| **DEC-03** | `spatie/laravel-permission` | Retenue | Jalon 1 — Story 2.2 |
| **DEC-04** | Redis (cache et files) | Retenue ; sessions en base | Étape 1 — provisionnement |
| **DEC-05** | Préproduction | ✅ **Tranché** — VPS existant partagé, risques consignés au § 24.2 | Jalon 1 — provisionnement |
| **DEC-06** | Hébergeur des sauvegardes | **La donnée quitte le Niger — décision non technique** | Étape 1 — mise en service |
| **DEC-07** | Suivi des erreurs | Sentry auto-hébergé, ou fichiers seuls | Étape 1 |
| **DEC-08** | Q11 — pièces jointes | PDF, JPEG, PNG, WebP, HEIC — 8 Mo | Étape 1 — Story pièces jointes |
| **DEC-09** | Q6 — comptes financiers réels | Liste attendue | **Jalon 4 — Story 8.1** |
| **DEC-10** | Q9 — vérification d'identité | ✅ **Tranché 20/07/2026** — code de confirmation WhatsApp, § 7.4 | Jalon 1 — Story 2.8 |
| **DEC-11** | Q12 — conservation 10 ans | Confirme NFR26 et le disque | Étape 4 |
| **DEC-15** | Fournisseur du canal WhatsApp | ✅ **Tranché 20/07/2026** — Evolution API 2.3.7, coûts au § 9.4bis | Jalon 1 — Story 3.7 |
| **DEC-16** | Q18 — portée et consentement WhatsApp | ✅ **Tranché 20/07/2026** — toutes les notifications de FR31, sans refus par l'utilisateur | Jalon 1 — Story 3.7 |

**Contradictions PRD et arbitrages confirmés qui pèsent sur le modèle de données de l'Étape 4 :**

| Réf. | Sujet | Impact architectural si renversé |
|---|---|---|
| ~~CONTRA-01~~ | ✅ **Tranché 17/08/2026** — bénéfice prévisionnel avec régularisation à la clôture | `ShareCalculator` conserve une base explicite |
| **CONTRA-03** | Aucune soupape d'exception à la double approbation | **Fort si renversé** — introduirait un état et un circuit dérogatoires |
| ~~CONTRA-04~~ | ✅ **Tranché 17/08/2026** — oui, un employé apporteur perçoit 10 % | Règle de validation sur le bénéficiaire |
| ~~CONTRA-05~~ | ✅ **Tranché 17/08/2026** — visibilité limitée à sa propre ligne | Scope de visibilité |
| **CONTRA-07** | L'alerte rouge n'a aucun effet sur les parts | **Faible** — exception déjà prévue (§ 13.5) |

---

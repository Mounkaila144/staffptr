# 27. Points requérant votre accord

Récapitulatif opposable. **Aucun de ces points ne bloque le démarrage de l'Étape 1** ; chacun est
appliqué selon la recommandation tant que vous n'en décidez pas autrement.

| Réf. | Décision | Recommandation | À trancher avant |
|---|---|---|---|
| **DEC-01** | Fuseau de stockage | UTC en base, Niamey à l'affichage | Étape 1 — première migration |
| **DEC-02** | Base des tests | MySQL en CI, pas SQLite | Étape 1 — mise en place CI |
| **DEC-03** | `spatie/laravel-permission` | Retenue | Jalon 1 — Story 2.2 |
| **DEC-04** | Redis (cache et files) | Retenue ; sessions en base | Étape 1 — provisionnement |
| **DEC-05** | Préproduction | Même VPS (~0 €) ou VPS séparé (~5 €/mois) | Étape 1 — provisionnement |
| **DEC-06** | Hébergeur des sauvegardes | **La donnée quitte le Niger — décision non technique** | Étape 1 — mise en service |
| **DEC-07** | Suivi des erreurs | Sentry auto-hébergé, ou fichiers seuls | Étape 1 |
| **DEC-08** | Q11 — pièces jointes | PDF, JPEG, PNG, WebP, HEIC — 8 Mo | Étape 1 — Story pièces jointes |
| **DEC-09** | Q6 — comptes financiers réels | Liste attendue | **Jalon 4 — Story 8.1** |
| **DEC-10** | Q9 — vérification d'identité | Procédure humaine à écrire | Jalon 1 — Story 2.8 |
| **DEC-11** | Q12 — conservation 10 ans | Confirme NFR26 et le disque | Étape 4 |

**Contradictions PRD toujours ouvertes, rappelées ici parce qu'elles pèsent sur le modèle de
données de l'Étape 4 :**

| Réf. | Sujet | Impact architectural si renversé |
|---|---|---|
| **CONTRA-01** | Base des parts : prévisionnel + régularisation, ou versement à la clôture | **Modéré** — `ShareCalculator` prend la base en paramètre (§ 16.2), le schéma ne change pas |
| **CONTRA-03** | Aucune soupape d'exception à la double approbation | **Fort si renversé** — introduirait un état et un circuit dérogatoires |
| **CONTRA-04** | Un employé apporteur perçoit-il 10 % ? | **Faible** — règle de validation sur le bénéficiaire |
| **CONTRA-05** | Un non-associé voit sa propre ligne de répartition | **Faible** — scope de visibilité |
| **CONTRA-07** | L'alerte rouge n'a aucun effet sur les parts | **Faible** — exception déjà prévue (§ 13.5) |

---

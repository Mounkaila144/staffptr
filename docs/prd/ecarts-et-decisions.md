<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

# Écarts d'ordonnancement et décisions en attente

Trois points de l'ordre d'analyse fourni entraient en conflit avec le PRD ou l'architecture. Ils
sont signalés ici plutôt que résolus en silence.

| # | Conflit | Résolution appliquée | À confirmer par |
|---|---|---|---|
| **ÉCART-01** | L'ordre demandé place les finances en 6ᵉ position. Or la direction a explicitement exigé, en contrepartie du report de la finance au Jalon 4, que **le journal d'audit et le registre des dépenses à double approbation remontent au Jalon 1** (PRD § 1.2, § 3.1). Les livrer en 6ᵉ position annulerait l'atténuation. | Le circuit **demande → double approbation → registre** est livré en **4** (Jalon 1), sans compte financier ni écriture comptable. Le **paiement**, l'imputation et les écritures restent en Epic 8 (Jalon 4). `Expense` est créé en Epic 4 puis **enrichi par migration** en Epic 8 — jamais par modification de la migration d'origine. | Aucun — application d'une décision existante. |
| **ÉCART-02** | L'ordre demandé place les notifications en 7ᵉ position (avec les tableaux de bord). Or les relances J+1 / J+2 de la double approbation (FR33, PRD 1.13) et le rappel du rapport quotidien (FR66) en dépendent, tous deux antérieurs. | Le **centre de notifications** et son infrastructure sont livrés en **3.7** (Jalon 1). Les **notifications métier complètes** et les rappels planifiés restants sont en **9.6** (Jalon 4). | Aucun — dépendance technique. |
| **ÉCART-03** | L'ordre demandé place les **documents internes** en 3ᵉ position, alors que le PRD les situe à l'Étape 3 (FR94 à FR98, PRD 3.13). | Suivi de l'ordre demandé : livrés en **3.8** (Jalon 1). Le coût est faible — la bibliothèque ne dépend que des pièces jointes, de l'audit et des notifications, tous présents en Epic 3. Le bénéfice est réel : le règlement intérieur et l'engagement de confidentialité sont opposables dès la première mise en service. | **Direction** — confirmer que ce contenu est prêt à être publié dès le Jalon 1. |

**Dépendance avant en Epic 7.** `7.3` (activation d'un stagiaire) porte un critère du PRD — « l'activation
en niveau d'alerte rouge est refusée » (PRD 3.9 AC4, FR164) — dont le niveau d'alerte n'existe qu'en
Epic 9, deux jalons plus tard. Le point de contrôle est donc **posé en 7.3** derrière un service
`AlertLevel` qui retourne `vert` tant que Epic 9 n'est pas livré, et le test bloquant correspondant est
écrit en **9.2**. Sans cette précaution, la règle serait recâblée après coup dans un chemin déjà
en production.

### Registre des arbitrages en attente

**Aucun n'est tranché à ce jour.** Chacun porte une résolution provisoire appliquée dans le plan ;
aucun ne bloque la story 1.1. Ils sont listés avec leur échéance réelle, pas avec une urgence
uniforme.

| Réf. | Sujet | Échéance réelle | Qui décide |
|---|---|---|---|
| **DEC-06** | Hébergeur des sauvegardes hors site — **la donnée quitte le Niger** | **Bloque la mise en production du Jalon 1** (11.1) | Direction — décision non technique |
| **CONTRA-03** | Aucune soupape d'exception à la double approbation | **Avant 4.5** — le renversement après mise en production coûterait cher | Direction |
| **DEC-05** | Préproduction sur le même VPS ou VPS séparé | Avant 1.5 (provisionnement) | Direction |
| **DEC-10** | Q9 — vérification d'identité à la réinitialisation | Avant 2.8 | Direction — procédure humaine |
| **DEC-08** | Q11 — types et taille des pièces jointes | Avant 3.5 — défaut appliqué : PDF/JPEG/PNG/WebP/HEIC, 8 Mo | Direction |
| **DEC-07** | Suivi des erreurs — Sentry auto-hébergé ou fichiers seuls | Avant 11.3 | Direction |
| **CONTRA-01** | Base des parts — prévisionnel + régularisation, ou versement à la clôture | **Avant le modèle financier de l'Epic 8** | Direction |
| **CONTRA-04** | Un employé apporteur perçoit-il 10 % ? | **Avant Epic 8** (8.3) | Direction |
| **DEC-09** | Q6 — comptes financiers réels à initialiser | **Avant Epic 8** (8.1) | Direction |
| **CONTRA-05** | Un non-associé voit sa propre ligne de répartition | Avant 8.8 | Direction |
| **CONTRA-07** | L'alerte rouge n'a aucun effet sur les parts | Avant 9.2 | Direction |
| **DEC-11** | Q12 — conservation 10 ans | Avant 11.1 (dimensionnement disque) | Direction |
| DEC-01 à DEC-04 | Fuseau UTC, tests MySQL, `spatie/laravel-permission`, Redis | Appliqués par défaut, révocables | Architecte |

**Impact si renversé** — seul CONTRA-03 est coûteux : il introduirait un état et un circuit
dérogatoires dans un mécanisme déjà en production. CONTRA-01 est contenu (`ShareCalculator` prend la
base en paramètre, le schéma ne change pas). CONTRA-04, 05 et 07 sont des règles de validation ou de
visibilité.

---

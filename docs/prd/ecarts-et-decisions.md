<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

# Écarts d'ordonnancement et décisions en attente

Trois points de l'ordre d'analyse fourni entraient en conflit avec le PRD ou l'architecture. Ils
sont signalés ici plutôt que résolus en silence.

| # | Conflit | Résolution appliquée | À confirmer par |
|---|---|---|---|
| **ÉCART-01** | L'ordre demandé place les finances en 6ᵉ position. Or la direction a explicitement exigé, en contrepartie du report de la finance au Jalon 4, que **le journal d'audit et le registre des dépenses à double approbation remontent au Jalon 1** (PRD § 1.2, § 3.1). Les livrer en 6ᵉ position annulerait l'atténuation. | Le circuit **demande → double approbation → registre** est livré en **Epic 4** (Jalon 1), sans compte financier ni écriture comptable. Le **paiement**, l'imputation et les écritures restent en Epic 8 (Jalon 4). `Expense` est créé en Epic 4 puis **enrichi par migration** en Epic 8 — jamais par modification de la migration d'origine. | Aucun — application d'une décision existante. |
| **ÉCART-02** | L'ordre demandé place les notifications en 7ᵉ position (avec les tableaux de bord). Or les relances J+1 / J+2 de la double approbation (FR33, PRD 1.13) et le rappel du rapport quotidien (FR66) en dépendent, tous deux antérieurs. | Le **centre de notifications** et son infrastructure sont livrés en **3.7** (Jalon 1). Les **notifications métier complètes** et les rappels planifiés restants sont en **9.6** (Jalon 4). | Aucun — dépendance technique. |
| **ÉCART-03** | L'ordre demandé place les **documents internes** en 3ᵉ position, alors que le PRD les situe à l'Étape 3 (FR94 à FR98, PRD 3.13). | Suivi de l'ordre demandé : livrés en **3.8** (Jalon 1). Le coût est faible — la bibliothèque ne dépend que des pièces jointes, de l'audit et des notifications, tous présents en Epic 3. Le bénéfice est réel : le règlement intérieur et l'engagement de confidentialité sont opposables dès la première mise en service. | **Direction** — confirmer que ce contenu est prêt à être publié dès le Jalon 1. |

**Dépendance avant en Epic 7.** `7.3` (activation d'un stagiaire) porte un critère du PRD — « l'activation
en niveau d'alerte rouge est refusée » (PRD 3.9 AC4, FR164) — dont le niveau d'alerte n'existe qu'en
Epic 9, deux jalons plus tard. Le point de contrôle est donc **posé en 7.3** derrière un service
`AlertLevel` qui retourne `vert` tant que Epic 9 n'est pas livré, et le test bloquant correspondant est
écrit en **9.2**. Sans cette précaution, la règle serait recâblée après coup dans un chemin déjà
en production.

**Décisions en attente qui pèsent sur ce plan.** DEC-06 (hébergeur des sauvegardes — la donnée quitte
le Niger, 11.1), DEC-09 (comptes financiers réels — 8.1), CONTRA-01 et CONTRA-04 (base des parts
et employé apporteur — 8.3 et 8.7). Aucune ne bloque le Jalon 1.

---

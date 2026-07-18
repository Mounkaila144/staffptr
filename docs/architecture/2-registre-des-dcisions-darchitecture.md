# 2. Registre des décisions d'architecture

## 2.1 Décisions validées par la direction le 18/07/2026

| Réf. | Décision | Retenu | Statut |
|---|---|---|---|
| **A-01** | Intégration de Vue 3 | **Inertia.js 2 + Vue 3**, pas de SPA séparée, pas de SSR | ✅ Validé |
| **A-02** | Base de données de production | **MySQL 8.0 / MariaDB 10.11+** | ✅ Validé |
| **A-03** | Hébergement | **VPS unique, déploiement scripté** | ✅ Validé |

## 2.2 Décisions prises par l'Architecte dans le cadre du § 8.4

| Réf. | Décision | Retenu | § |
|---|---|---|---|
| A-04 | Stockage des pièces jointes | Disque privé `storage/app/private`, servi par contrôleur + `X-Accel-Redirect` | [§ 11](#11-pièces-jointes-privées) |
| A-05 | Immuabilité et versionnement | Triple barrière : privilèges SQL, déclencheurs base, trait applicatif | [§ 15](#15-immuabilité-historiques-et-annulations) |
| A-06 | Séparation personne / compte | Tables `people` et `users` distinctes dès l'Étape 1 | [§ 6.2](#62-noyau-identité--a-06--contra-02) |
| A-07 | Notifications | Système de notifications Laravel, canal `database` seul en MVP | [§ 9.4](#94-notifications-a-07) |

## 2.3 Décisions requérant votre accord — non définitives

> Ces onze points sont **proposés, pas figés**. Chacun porte une recommandation applicable en
> l'état ; un désaccord de votre part ne provoque aucune réécriture profonde de ce document.
> Ils sont regroupés et détaillés au [§ 26](#26-points-requérant-votre-accord).

| Réf. | Sujet | Recommandation |
|---|---|---|
| **DEC-01** | Fuseau de stockage des horodatages | UTC en base, `Africa/Niamey` à l'affichage |
| **DEC-02** | Base des tests automatisés | MySQL, et non SQLite, pour parité des déclencheurs |
| **DEC-03** | Dépendance `spatie/laravel-permission` | Retenue |
| **DEC-04** | Dépendance Redis | Retenue (cache et files), sessions en base |
| **DEC-05** | Emplacement de la préproduction | Même VPS, hôte virtuel distinct |
| **DEC-06** | Hébergeur des sauvegardes hors site | À choisir — la donnée quitte le Niger |
| **DEC-07** | Suivi des erreurs (Sentry ou fichiers seuls) | Sentry auto-hébergé, ou fichiers seuls |
| **DEC-08** | Q11 — types et taille des pièces jointes | PDF/JPEG/PNG/WebP/HEIC, 8 Mo |
| **DEC-09** | Q6 — comptes financiers réels à initialiser | Liste à fournir |
| **DEC-10** | Q9 — vérification d'identité à la réinitialisation | Procédure hors application à formaliser |
| **DEC-11** | Q12 — conservation 10 ans | Confirme NFR26 et le dimensionnement disque |

**Rappel PRD :** les contradictions CONTRA-01, 03, 04, 05 et 07 restent ouvertes. Aucune ne bloque
l'Étape 1. CONTRA-01 (régularisation des parts à la clôture de contrat) et CONTRA-04 (employé
apporteur) **doivent être tranchées avant l'écriture du modèle de données financier de l'Étape 4**.

---

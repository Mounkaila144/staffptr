# PTR Staff — Epics et stories

**Version :** 1.0 — 18 juillet 2026
**Auteur :** John, Product Manager (BMAD)
**Sources :** `docs/prd.md` v1.0, `docs/architecture.md` v1.0, `docs/front-end-spec.md` v1.0,
`docs/brief.md` v1.1
**Objet :** plan d'exécution du MVP. Le PRD reste la référence *produit* (§ 5 FR, § 6 NFR) ;
ce document est la référence *d'exécution* (ordre, découpage, dépendances, portes de qualité).
**Statut :** découpé en fichiers epic dans `docs/prd/` — c'est **cette source** qu'il faut modifier,
puis régénérer `docs/prd/`. Ne pas éditer les fichiers epic à la main : ils seraient écrasés.

---

## 1. Comment lire ce document

| Notation | Sens |
|---|---|
| `5.2` | Epic 5, story 2 |
| `[PRD 2.3]` | Story correspondante du § 10 du PRD |
| `⛔` | Règle métier bloquante — test dédié obligatoire (architecture § 23.2) |
| `Jalon n` | Étape de livraison du PRD § 3.1 — chaque jalon est déployable |

### Quelles stories passent obligatoirement au QA

Le marqueur ⛔ signifie « ce critère exige un test dédié ». Il figure sur **70 stories sur 82** : il
guide le dev, il ne sert **pas** à décider d'une revue QA.

Le déclencheur d'un `*review` complet est différent — c'est le fait de porter l'une des **14 règles
métier bloquantes** de l'architecture § 23.2, ou une **recette opposable**. Vingt stories sont
concernées ; sur les autres, la relecture humaine suffit.

| Story | Ce qu'elle porte | Règle § 23.2 |
|---|---|---|
| **1.1** | Gabarit : la forme de ses tests sera recopiée 81 fois | — |
| **1.4** | L'échec d'écriture d'audit annule l'opération métier | 11 |
| **2.1** | Unicité du téléphone sur comptes non archivés | 12 |
| **2.2** | `super_admin` sans aucune permission métier | 9 |
| **2.5** | La suspension invalide toutes les sessions immédiatement | 10 |
| **4.5** | Deux approbateurs distincts sans seuil ; demandeur jamais approbateur | 4, 5 |
| **5.1** | Maximum 5 priorités d'entreprise | 2 |
| **5.2** | Maximum 3 objectifs majeurs par personne et par mois | 1 |
| **6.1** | Recette opposable : saisie du rapport sous 3 minutes (NFR4) | — |
| **7.4** | Maximum 3 stagiaires actifs par tuteur | 3 |
| **8.3** | Somme des parts exactement égale à la base | 14 |
| **8.5** | Suppression financière impossible ; aucune écriture sur mois clôturé | 7, 8 |
| **8.6** | Suppression financière impossible après paiement | 7 |
| **8.7** | Parts au prorata ; somme exactement égale à la base | 14 |
| **8.12** | Préparateur ≠ contrôleur sur rapprochement | 6 |
| **8.13** | Préparateur ≠ contrôleur ; clôture bloquant toute écriture | 6, 8 |
| **9.2** | Les parts 10 % / 30 % restent payables en alerte rouge | 13 |
| **10.5** | Recette opposable : NFR1, NFR2, NFR4, NFR7, NFR8, NFR27, WCAG AA | — |
| **11.2** | Recette opposable : restauration vérifiée, RTO de 4 h | — |
| **11.7** | Recette opposable : porte de mise en service d'un jalon | — |

Sur ces vingt, un gate `FAIL` **interdit la mise en service du jalon**. Sur les soixante-deux autres,
le QA reste possible mais n'est pas une porte.

> **Deux numérotations coexistent, ne pas les confondre.** Ce plan compte **11 epics** ; le § 10 du
> PRD en compte **4**. « Story 1.2 » ne désigne donc pas la même chose selon le document. La
> correspondance complète est au § 5.
>
> `docs/architecture.md` a été réaligné sur **cette** numérotation en v1.1 : son § 28 utilise les
> identifiants du plan, avec l'ancien entre crochets (`Story 1.4  [PRD 1.2]`). Le PRD, lui, garde
> sa numérotation d'origine — il n'est pas régénéré.

Onze epics, **82 stories**, quatre jalons MVP. Le découpage suit l'ordre d'analyse demandé, corrigé
sur trois points où il entrait en conflit avec une dépendance technique ou une décision de la
direction — ces écarts sont énoncés au § 4, pas appliqués en silence.

---

## 2. Socle transverse — s'applique à toute story, jamais répété

Ces onze règles font partie de la définition de terminé de **chaque** story. Une story ne les
redéclare pas ; elle ne mentionne que ce qui lui est propre. Une story qui en enfreint une n'est pas
terminée, quel que soit l'état de ses critères d'acceptation spécifiques.

| Réf. | Règle | Source |
|---|---|---|
| **SOC-01** | Toute vérification d'accès est serveur. Chaque route protégée est déclarée dans `config/authorization-matrix.php` ; une route protégée non déclarée fait échouer la CI. Un rôle non autorisé reçoit `403` — jamais `302`, jamais de contenu partiel. | PERM-01, PERM-02, NFR14, archi § 23.3 |
| **SOC-02** | Toute écriture sensible produit une entrée d'audit **dans la même transaction** que l'opération métier. L'échec de l'audit annule l'opération. | FR21, NFR21 |
| **SOC-03** | Aucune suppression physique. Correction versionnée, annulation motivée ou contre-écriture. | P2, RM-17, NFR20 |
| **SOC-04** | Une migration déployée n'est jamais modifiée : toute évolution est une nouvelle migration. Déclencheurs, contraintes et privilèges sont créés **par migration**, jamais à la main sur le serveur. Seeders idempotents (`updateOrCreate`). | Archi § 20.1, § 20.3 |
| **SOC-05** | Un test Feature par critère d'acceptation ; un test Unit par calcul pur. Suite exécutée **sur MySQL en CI** (DEC-02). `pint --dirty` propre, Larastan niveau 6 sans erreur. | PRD § 8.5, archi § 23 |
| **SOC-06** | **État vide** : ce qui est vide, pourquoi c'est normal, l'action possible. Le vide par filtre ne se confond jamais avec le vide par absence de donnée. Aucune illustration. | UX § 5.6 |
| **SOC-07** | **Chargement** : rien sous 300 ms ; squelette à la forme du contenu de 300 ms à 3 s ; au-delà, squelette + « la connexion semble lente ». Bouton occupé conserve largeur et libellé. Jamais de modale bloquante. | UX § 5.6 |
| **SOC-08** | **Erreur** : ce qui s'est passé, l'action attendue, aucun terme technique, aucun code, sous le champ concerné avec déplacement du focus. Bandeau hors connexion non bloquant, sans promesse de synchronisation. | NFR17, NFR32, UX § 5.6 |
| **SOC-09** | **Mobile** : utilisable à 320 px sans défilement horizontal, cibles tactiles ≥ 44 × 44 px, premier rendu utile < 3 s en 3G dégradée (400 kbit/s / 400 ms), page ≤ 300 Ko au premier chargement et ≤ 80 Ko ensuite, aucune ressource tierce à l'exécution. | NFR1, NFR2, NFR3, NFR7, NFR8 |
| **SOC-10** | Français simple, vocabulaire de contribution et non de surveillance. Aucune information portée par la couleur seule. WCAG 2.1 AA : contraste, libellés associés, navigation clavier. Aucun classement comparatif entre personnes. | NFR29 à NFR31, FR82 |
| **SOC-11** | Validation exclusivement par Form Request, Eloquent plutôt que `DB::`, types de retour explicites, fichiers créés par `php artisan make:*`. Montants en entiers XOF, horodatages stockés en UTC et affichés en `Africa/Niamey`. | `coding-standards.md`, NFR22, NFR23, DEC-01 |

---

## 3. Carte des epics et des jalons

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

## 4. Écarts assumés par rapport à l'ordre demandé

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

# Epic 1 — Fondation technique, base de données, CI et sécurité

**Objectif.** Poser l'ossature avant la première ligne de code métier : application démarrable et
déployable, journal d'audit opérationnel **avant la première écriture sensible**, chaîne
d'intégration continue qui refuse ce qui ne respecte pas les standards, et socle d'interface portant
les états vides, de chargement et d'erreur une seule fois pour toute l'application.

**Dépend de :** rien. **Bloque :** tout.

---

### Story 1.1 — Fondation applicative et point de santé

*En tant qu'équipe de développement, je veux une application Laravel initialisée et déployable, afin
que toute story ultérieure s'appuie sur une base testée.* — [PRD 1.1]

1. Application Laravel 13 / PHP 8.3 en structure « slim » (middleware, exceptions et routes dans `bootstrap/app.php`), démarrable par `php artisan serve` sans erreur.
2. Inertia.js 2 + Vue 3 + Vite 8 + Tailwind 4 en configuration CSS-first sont câblés ; une page de démonstration se rend.
3. Les cinq espaces de noms de modules existent sous `Http/Controllers`, `Models`, `Policies`, `Services` et `resources/js/Pages` : `Platform`, `Identity`, `Work`, `Accountability`, `Finance`. Aucun dossier racine nouveau n'est créé.
4. `/up` retourne en HTTP 200 : version applicative, état de la connexion base, état du cache, espace disque libre, horodatage en `Africa/Niamey`. Le point est accessible **sans authentification** et n'expose aucun secret, chemin sensible ni nom d'hôte interne. La route `health: '/up'` par défaut de `bootstrap/app.php` est **remplacée** — elle ne retourne qu'une page vide.
5. `/up` retourne un statut d'échec explicite lorsque **la base** est injoignable ; testé en coupant la connexion. Chaque composant porte son **état individuel** : un cache indisponible dégrade la réponse sans la faire échouer. En local, Redis peut être absent — `php artisan serve` et `/up` doivent fonctionner sans lui.
6. `php artisan test` passe intégralement ; `vendor/bin/pint --dirty` ne remonte aucune violation.
7. Le dépôt git est initialisé avec une branche `main` et un **premier commit** contenant l'application, `.bmad-core/`, `AGENTS.md` et `docs/`. Sans cela, la protection de branche de la story 1.3 n'a rien à protéger, et Codex Web ne voit ni les agents ni le backlog.
8. `.gitignore` exclut `vendor/`, `node_modules/`, `.env`, `storage/app/private/`, `database/database.sqlite` et `.ai/`. ⛔ Un test de la chaîne échoue si un fichier `.env` est versionné.
9. Un `README.md` décrit l'installation locale de bout en bout, vérifiée sur une machine vierge : `export PATH` vers PHP 8.3 de MAMP, alias Composer, `composer install`, `npm ci`, copie de `.env.example`, `php artisan key:generate`, `php artisan migrate`, `npm run dev`, `php artisan serve`.
10. `.env.example` est complet et à jour : base, Redis, fuseau `Africa/Niamey`, locale `fr`, aucun secret réel.

**Migrations : aucune table métier, et les migrations Laravel par défaut sont traitées ici.**
Laravel livre `0001_01_01_000000_create_users_table.php`, qui crée en réalité **`users`,
`password_reset_tokens` et `sessions`**. La conserver violerait deux règles : `audit_logs` doit
précéder toute table métier (architecture § 20.2), et notre table `users` porte `people_id`, un état
de cycle de vie et une **colonne générée d'unicité conditionnelle** (2.1) — la conserver imposerait
de **modifier une migration déjà déployée**, ce que SOC-04 interdit.

| Migration par défaut | Décision en 1.1 | Motif |
|---|---|---|
| `create_users_table` (users, password_reset_tokens, sessions) | **Supprimée** | Recréée en 2.1 avec le modèle personne / compte |
| `create_cache_table` | **Supprimée** | Cache sur Redis (DEC-04) |
| `create_jobs_table` (jobs, job_batches, failed_jobs) | **Conservée** | Infrastructure, sans donnée métier ; `failed_jobs` est requise par la supervision (11.4) |

⛔ Un test vérifie qu'après `migrate:fresh`, **aucune table `users` n'existe** — c'est ce qui
garantit que l'ordre `audit_logs` d'abord n'a pas été contourné par un artefact d'installation.

**`SESSION_DRIVER=file` en 1.1**, aucune authentification n'existant encore. Il bascule sur
`database` en **2.4**, quand la table `sessions` existe et que l'invalidation immédiate de toutes les
sessions (FR8, PERM-08) le rend nécessaire. `CACHE_STORE` et `QUEUE_CONNECTION` suivent la même
logique : `file` et `sync` en local, Redis en préproduction et production (1.5).

**Audit :** sans objet — `audit_logs` est créée en 1.4.

---

### Story 1.2 — Socle monnaie, temps et téléphone

*En tant qu'équipe de développement, je veux des types partagés pour l'argent, le temps et les
numéros, afin qu'aucune story ne réinvente une règle qui doit être identique partout.* — [PRD 1.1]

1. `Support\Money` stocke des **entiers XOF** et formate sans décimale (`1 250 000 FCFA`). ⛔ Un test échoue si une valeur à virgule flottante franchit le contrat partagé.
1 bis. ⛔ `Money::format()` est réservé à l'**affichage**. Tout export, calcul, comparaison ou écriture en base manipule l'**entier brut** ; un test prouve que la valeur persistable est un `int` PHP, jamais une chaîne formatée. Sans cette règle, l'export CSV de 10.3 produirait des montants qu'aucun tableur français ne parse.
2. **Convention de nommage opposable** : toute colonne monétaire se nomme `*_amount` et **doit** être `BIGINT UNSIGNED`. ⛔ Un test scanne les **fichiers** de `database/migrations/` — pas le schéma instancié, vide à ce stade — et échoue si une colonne `*_amount` y est déclarée en `decimal`, `float`, `double` ou `unsignedDecimal`. Une fixture de migration invalide prouve que le garde-fou **détecte réellement** une violation ; sans elle, on ignorerait s'il fonctionne jusqu'à l'epic 8.
3. `Support\PhoneNumber` normalise au format international avec `+227` par défaut. `90123456`, `+22790123456` et `00227 90 12 34 56` produisent la **même** valeur normalisée ; les trois cas sont testés. ⛔ La validation porte **uniquement** sur 8 chiffres, caractères numériques seuls, forme canonique `+227XXXXXXXX` — **aucune validation de préfixe opérateur ni de plage de numérotation**. Les fixes de Niamey commencent par `20` : restreindre aux préfixes mobiles rendrait impossible la création de tout compte rattaché à un fixe. Un test vérifie qu'un numéro commençant par `20` est accepté.
4. Un numéro non normalisable est refusé avec le message « Ce numéro n'est pas valide. Saisissez 8 chiffres, ou le numéro complet avec son indicatif. »
5. Horodatages stockés en **UTC**, affichés en `Africa/Niamey` (DEC-01) ; un test vérifie l'affichage d'une date connue à cheval sur minuit.
6. Locale applicative `fr`, devise XOF, fuseau `Africa/Niamey` ; aucune chaîne d'interface en anglais.

---

### Story 1.3 — Chaîne d'intégration continue

*En tant qu'équipe de développement, je veux que la chaîne refuse automatiquement ce qui ne respecte
pas les standards, afin que la qualité ne dépende pas de la discipline d'un jour donné.*

1. Le workflow GitHub Actions s'exécute sur chaque pull request : `pint --test`, Larastan niveau 6, `phpunit`, `npm run build`.
2. **La suite tourne sur MySQL 8 en service Docker**, pas sur SQLite (DEC-02) : les déclencheurs d'immuabilité, les colonnes générées, les contraintes `CHECK` et `lockForUpdate()` n'existent pas ou diffèrent sous SQLite. Les tester sur un moteur qui ne les applique pas reviendrait à ne pas les tester.
3. Un **budget de poids** est vérifié : le bundle d'une page du parcours quotidien dépassant 300 Ko compressé fait échouer la chaîne (NFR2).
4. Une pull request dont un seul contrôle échoue ne peut pas être fusionnée ; la protection de branche est active sur `main`.
5. Playwright est installé et exécute au moins un parcours de démonstration ; la structure `tests/e2e/` existe.
6. La durée totale de la chaîne sur une pull request reste **sous 10 minutes**, mesurée et consignée.

---

### Story 1.4 — Journal d'audit : écriture transactionnelle et immuabilité

*En tant que direction, je veux que toute action sensible soit enregistrée de façon inaltérable, afin
de disposer d'une trace opposable, y compris en cas de litige entre associés.* — [PRD 1.2]

**Cette story précède toute autre table métier** (architecture § 20.2) : le journal doit être
opérationnel avant la première écriture sensible, faute de quoi les premières opérations de
l'application seront les seules à ne pas être traçables.

1. Une entrée porte : auteur (nullable pour l'amorçage), libellé d'auteur, horodatage, type d'objet, identifiant d'objet, action, ancienne valeur, nouvelle valeur, identifiant de requête.
2. `Support\Auditing\AuditLogger` et le trait `Auditable` écrivent **dans la transaction de l'opération métier**. ⛔ Un test provoque l'échec de l'écriture d'audit et vérifie que **l'opération métier est annulée** (NFR21).
3. **Triple barrière d'immuabilité** (A-05), les trois posées **par migration** : privilèges SQL (`INSERT` seul sur `audit_logs` pour l'utilisateur applicatif), déclencheurs base refusant `UPDATE` et `DELETE`, garde applicative dans le modèle.
4. ⛔ Aucune route, aucun formulaire, aucune commande ne modifie ni ne supprime une entrée ; testé pour `PUT`, `PATCH` et `DELETE`.
5. Une tentative d'`UPDATE` ou de `DELETE` directe en SQL sous l'utilisateur applicatif échoue ; testé sur MySQL.
6. Un observateur de filet de sécurité journalise toute écriture sur un modèle audité qui aurait contourné le service.
7. La table est indexée sur (type d'objet, identifiant d'objet), sur l'auteur et sur l'horodatage — la consultation filtrée de 2.10 en dépend.

**Migrations :** `audit_logs` **en premier**, avec ses déclencheurs et ses privilèges.

---

### Story 1.5 — Préproduction, secrets et deux utilisateurs de base

*En tant qu'exploitant, je veux un environnement de préproduction et une séparation stricte des
privilèges base, afin qu'un `.env` compromis ne suffise pas à effacer le journal d'audit.*

1. Quatre environnements existent et sont documentés : local, CI, préproduction (`staging.staff.ptrniger.com`), production (`staff.ptrniger.com`).
2. La préproduction dispose de sa **base dédiée** et de son hôte virtuel ; `APP_DEBUG=false`, files Redis, aucun envoi de courriel.
3. **Deux utilisateurs MySQL** existent : `ptrstaff_app` (`SELECT, INSERT, UPDATE`, **sans `DELETE`** sur les tables protégées, `INSERT` seul sur `audit_logs`) et `ptrstaff_migrate` (`ALL`).
4. Les identifiants de `ptrstaff_migrate` **ne figurent pas dans le `.env` applicatif** : ils sont injectés par le script de déploiement depuis le magasin de secrets, le temps de la migration.
5. `.env` en `chmod 600` dans `shared/`, jamais versionné. `APP_KEY` généré et **sauvegardé hors ligne** — sans lui, les données chiffrées au repos sont définitivement illisibles.
6. Les secrets de CI sont en place : clé SSH de déploiement dédiée sans accès `root`, identifiants de migration. Les **emplacements de secrets du stockage de sauvegarde sont préparés et documentés, sans valeur** : celles-ci ne peuvent être fournies qu'en 11.1, une fois DEC-06 arbitré.
7. La procédure de rotation des secrets est écrite dans `docs/ops/`.

> **DEC-05 en attente.** Préproduction sur le même VPS (~0 €) ou VPS séparé (~5 €/mois). La première
> option est appliquée par défaut ; son risque — une saturation disque en préproduction peut affecter
> la production — est assumé jusqu'à arbitrage.

---

### Story 1.6 — Durcissement HTTP et traitement des erreurs

*En tant qu'utilisateur, je veux que l'application soit protégée et qu'elle me parle français quand
elle échoue, afin de ne jamais voir une trace technique ni une page blanche.*

1. HTTPS sur tout le domaine, redirection 301 systématique, HSTS avec `preload`, TLS 1.2 minimum, **aucun contenu mixte** (NFR11).
2. En-têtes de sécurité posés : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, politique de sécurité de contenu cohérente avec NFR3 (aucune source externe autorisée).
3. Protections actives et testées : CSRF, XSS, injection, limitation de débit sur la connexion, `UFW` limité aux ports 22/80/443, MySQL et Redis en écoute **boucle locale uniquement**.
4. Pages d'erreur Inertia dédiées `403`, `404`, `419`, `500`, en français, sans terme technique ni code (NFR32).
5. `419` (session expirée) est traité comme un cas **fréquent** et non limite : sur 3G avec un onglet resté ouvert, le message invite à se reconnecter **sans perdre le brouillon local**.
6. Un processeur Monolog masque `password`, `password_confirmation`, `token`, `secret`, l'en-tête `Authorization` et les cookies. ⛔ Un test provoque une exception contenant un mot de passe et vérifie qu'il **n'apparaît pas** dans le journal, trace de pile comprise (NFR12).
7. Aucune donnée personnelle ni requête complète en journal technique : identifiants d'objet, pas contenus d'objet (NFR17).
8. `APP_DEBUG=false` en production, sans exception.

---

### Story 1.7 — Socle d'interface : mise en page, états transverses et accessibilité

*En tant qu'utilisateur sur un téléphone en 3G, je veux une interface qui se comporte de la même
façon partout quand elle attend, quand elle est vide et quand elle échoue, afin de ne jamais rester
devant un écran que je ne sais pas lire.*

Cette story existe pour que les états vides, de chargement et d'erreur soient **construits une fois**
et non réinventés story par story. Elle rend SOC-06 à SOC-10 exécutables.

1. `AppLayout` et `AuthLayout` existent ; la navigation par rôle est en place, avec une barre inférieure sur téléphone et latérale sur grand écran (UX § 3).
2. Les composants transverses du système de design UX § 6 sont livrés : pastille d'état, bouton (avec état occupé conservant largeur et libellé), champ de formulaire avec erreur sous le champ, carte d'action, file de traitement, confirmation d'opération sensible.
3. **Composant d'état vide** : ce qui est vide, pourquoi c'est normal, l'action possible. Trois tons — vide positif, vide neutre, vide par filtre avec bouton « Réinitialiser les filtres ». Aucune illustration.
4. **Squelettes de chargement** reprenant la forme réelle du contenu. Rien sous 300 ms ; texte « la connexion semble lente » au-delà de 3 s. Aucune modale bloquante.
5. **Bandeau hors connexion** non bloquant : la saisie continue, le bandeau disparaît automatiquement au retour et cède la place 3 secondes à « ✓ Connexion rétablie ». Aucune promesse de synchronisation automatique — le mode hors ligne est en phase 2 et l'interface ne doit jamais laisser croire le contraire.
6. **Aucune ressource tierce chargée à l'exécution** : polices, styles et scripts servis par l'application (NFR3). Un test de la chaîne échoue si une requête sortante vers un autre hôte apparaît.
7. Rendu vérifié à 320 px sans défilement horizontal, cibles tactiles ≥ 44 × 44 px, contraste WCAG 2.1 AA, navigation clavier complète sur les formulaires, aucune information portée par la couleur seule.
8. `useDraft` (brouillon local), `useMoney` et `usePermissions` existent et sont testés.

---

## ✅ Critères de fin de l'epic 1

1. `/up` répond en production et en préproduction, avec base, Redis et disque. **L'âge de la dernière sauvegarde n'est pas encore exposé** — il est ajouté en 11.1, seule story qui crée une sauvegarde.
2. Le dépôt a une branche `main` et un premier commit ; la chaîne CI est verte, tourne sur MySQL, et bloque la fusion en cas d'échec.
3. **Le journal d'audit est opérationnel et prouvé inaltérable** : les trois barrières sont posées par migration et le test d'annulation transactionnelle passe.
4. Le socle d'interface rend les quatre états transverses ; une page de démonstration les expose tous.
5. Aucun secret n'est versionné ; `APP_KEY` est sauvegardé hors ligne.
6. Larastan niveau 6 sans erreur, Pint propre, budget de poids respecté.

---

# Epic 2 — Authentification, comptes, rôles et permissions

**Objectif.** Rendre l'accès contrôlé et prouvable. À l'issue de cet epic, personne n'atteint une
ressource interdite à son rôle, y compris par saisie directe d'URL, et l'application dispose de son
premier administrateur sans qu'aucun mot de passe n'ait transité par Git ni par une route ouverte.

**Dépend de :** Epic 1 (audit, socle d'interface, types partagés).
**Ordre interne imposé par l'architecture § 28 :** `people`/`users` avant les rôles, les rôles avant
la connexion, la campagne d'autorisation dès la première ressource protégée.

---

### Story 2.1 — Fiche personne et compte applicatif

*En tant que direction, je veux que l'identité d'une personne survive à la fermeture de son compte,
afin qu'un départ ne fasse disparaître ni son historique ni ses droits financiers.* — [PRD 1.4]

1. `people` et `users` sont **deux tables distinctes** (A-06, CONTRA-02). La fiche personne porte l'identité durable, le compte porte l'accès.
2. Un compte est rattaché à exactement une fiche personne ; une fiche personne peut porter **plusieurs comptes successifs**.
3. La désactivation ou l'archivage d'un compte laisse la fiche personne intacte et consultable.
4. Le retour d'une personne crée un **nouveau compte** rattaché à la fiche existante ; l'historique des deux comptes reste consultable depuis la fiche.
5. ⛔ Aucune opération applicative ne supprime physiquement une fiche personne ni un compte ; testé pour `DELETE` sur les deux ressources.
6. Une colonne générée porte l'unicité conditionnelle du téléphone : unique **parmi les comptes dont l'état n'est pas `archive`** (FR3, CONTRA-09). ⛔ Un test crée un compte avec le numéro d'un compte archivé et **réussit** ; un test avec le numéro d'un compte actif **échoue**.
7. Création, modification et changement d'état produisent une entrée d'audit.

**Migrations :** `people` → `users` (+ colonne générée d'unicité conditionnelle).

---

### Story 2.2 — Rôles, permissions et contrôle d'accès serveur

*En tant que direction, je veux attribuer des rôles et des permissions fines contrôlés côté serveur,
afin qu'aucun utilisateur n'atteigne un écran interdit, même par URL directe.* — [PRD 1.3]

1. `spatie/laravel-permission` est en place (DEC-03) ; un utilisateur peut porter **plusieurs rôles** et ses permissions effectives sont l'union de ses rôles et de ses permissions unitaires.
2. Les six rôles existent : `super_admin`, `direction`, `finance`, `tuteur`, `employe`, `stagiaire`, avec le jeu de permissions de la matrice PRD § 4.3.
3. ⛔ `super_admin` ne détient **aucune** permission métier par défaut : il ne peut ni approuver une dépense, ni valider un objectif, ni valider un rapport financier, ni lire le journal d'audit métier. Les quatre cas sont testés (PERM-03, C13).
4. ⛔ La permission `depense.approuver` est détenue par les **deux comptes `direction`** et par eux seuls (PERM-05).
5. Une policy existe par modèle protégé ; les quatre niveaux de contrôle de l'architecture § 8.2 sont appliqués (middleware, policy, portée de requête, validation).
6. Toute attribution, modification ou retrait de rôle ou de permission produit une entrée d'audit avec **ancienne et nouvelle valeur** (PERM-04).
7. Le modèle permet la création d'un **rôle strictement lecture seule sans modification de schéma** (PERM-07, C7) ; un test crée un tel rôle et vérifie qu'il ne peut effectuer aucune écriture.

---

### Story 2.3 — Amorçage : premier administrateur et rôles de référence

*En tant qu'exploitant, je veux créer le tout premier compte sans route ouverte ni mot de passe
versionné, afin que l'installation ne soit pas le maillon faible du contrôle d'accès.* —
[architecture § 26]

1. `RolePermissionSeeder` est **idempotent** et crée les six rôles et leurs permissions ; il est rejouable en production sans effet de bord.
2. `php artisan ptr:create-first-admin` s'exécute **en SSH uniquement**, jamais par HTTP.
3. ⛔ La commande **refuse de s'exécuter si un utilisateur existe déjà** : elle est utilisable une seule fois dans la vie de l'installation ; testé.
4. Elle demande nom et téléphone **interactivement** et n'accepte **aucun argument de mot de passe** — qui finirait dans l'historique du shell.
5. Elle génère un mot de passe temporaire aléatoire de 32 caractères, **affiché une seule fois**, et positionne `must_change_password`.
6. Elle crée la fiche `people` puis le compte `users` à l'état `actif`, avec le rôle **`super_admin` seul**.
7. Elle écrit une entrée d'audit avec `actor_id = NULL` et `actor_label = 'Amorçage système'`.
8. Elle rappelle à l'écran que `super_admin` ne détient aucune permission métier et que sa première tâche est de créer les **deux** comptes `direction`.
9. Tant que les deux comptes `direction` n'existent pas, l'application **affiche explicitement** qu'aucune dépense n'est approuvable, plutôt que de laisser croire à un dysfonctionnement.
10. `DemoSeeder` lève une exception si `app()->environment('production')` ; testé.
11. **Première version de `php artisan ptr:check-invariants`**, portant les seuls invariants vérifiables à ce stade : `APP_DEBUG=false` et `APP_ENV` cohérents, aucun `super_admin` porteur d'une permission métier, déclencheurs d'immuabilité présents sur `audit_logs`, utilisateur applicatif dépourvu de `DELETE` sur `audit_logs`.
12. La commande **échoue avec un code de sortie non nul** en cas d'écart, afin d'être utilisable en porte de déploiement.

> **Commande à croissance progressive.** `ptr:check-invariants` est enrichie à mesure que les
> invariants deviennent vérifiables : **4.5** ajoute « aucune dépense `payee` sans deux approbations
> distinctes » et « exactement 2 comptes porteurs de `depense.approuver` », **11.1** ajoute l'âge de
> la dernière sauvegarde, **10.4** finalise la campagne complète. Elle est exigée dès la porte du
> Jalon 1 : elle ne peut donc pas naître en 10.4.

---

### Story 2.4 — Connexion par téléphone et changement de mot de passe imposé

*En tant qu'utilisateur de PTR Niger, je veux me connecter avec mon numéro et mon mot de passe, afin
d'accéder à mon espace sans dépendre d'une adresse électronique.* — [PRD 1.5]

1. ⛔ **Aucune route publique ne permet de créer un compte** ; testé sur l'ensemble des routes déclarées (FR1).
2. Le numéro saisi est normalisé avant enregistrement **et avant comparaison** ; les trois formes de 1.2 désignent le même compte.
3. Les mots de passe sont hachés par un algorithme moderne à coût paramétrable ; aucun n'est journalisé ni stocké en clair (NFR12).
4. La première connexion **redirige vers le changement de mot de passe et bloque tout autre accès** tant qu'il n'est pas effectué ; un middleware le garantit et un test tente d'atteindre trois autres routes.
5. Identifiants faux → « Numéro ou mot de passe incorrect. » — **jamais** « ce numéro n'existe pas » (énumération de comptes).
6. Sessions stockées en base, indispensables à l'invalidation de 2.5.
7. L'écran de connexion se rend en moins de 3 s en 3G dégradée et reste utilisable à 320 px.

---

### Story 2.5 — Cycle de vie du compte et invalidation immédiate des sessions

*En tant que direction, je veux qu'une suspension prenne effet à la seconde, afin qu'un retrait
d'accès ne soit pas théorique.* — [PRD 1.5]

1. Les états `invite`, `actif`, `suspendu`, `termine`, `archive` existent ; **seul `actif` autorise la connexion**, vérifié pour les quatre autres.
2. ⛔ Le passage à `suspendu` **et** tout changement de mot de passe invalident **toutes** les sessions du compte sur tous les appareils. Un test ouvre deux sessions, suspend le compte et vérifie que **les deux** sont rejetées à la requête suivante (FR8, PERM-08).
3. Un compte suspendu qui tente de se connecter voit « Votre compte n'est pas actif. Contactez la direction. »
4. Le statut opérationnel de la personne (`actif`, `absent`, `suspendu`, `sorti`) est **distinct** de l'état du compte ; un test vérifie qu'ils évoluent indépendamment (FR16).
5. Tout changement d'état produit une entrée d'audit avec ancienne et nouvelle valeur.

---

### Story 2.6 — Blocage après tentatives échouées et historique de connexion

*En tant que direction, je veux voir qui se connecte et bloquer le bourrage d'identifiants, afin
qu'une tentative d'intrusion laisse une trace et s'arrête seule.* — [PRD 1.5]

1. Après **N** tentatives échouées consécutives (N **paramétrable**), le compte est bloqué pour une durée **paramétrable** ; le blocage et son expiration sont journalisés (FR10).
2. Le message est « Trop de tentatives. Réessayez dans 15 minutes, ou contactez la direction. » — la durée affichée est celle réellement paramétrée.
3. Le blocage porte sur le compte **et** sur l'adresse d'origine ; un test vérifie qu'un attaquant ne contourne pas la limite en changeant de compte cible.
4. Les connexions réussies, les tentatives échouées et les sessions ouvertes sont consultables **par `direction`** ; l'accès par tout autre rôle est refusé (FR9).
5. L'écran liste appareil, adresse, horodatage et résultat, filtrable par personne et par période. État vide : « Aucune tentative échouée sur les 30 derniers jours. »
6. Aucun mot de passe, même erroné, n'apparaît dans l'historique ni dans les journaux techniques.

---

### Story 2.7 — Création et administration des comptes

*En tant que direction, je veux créer et administrer les comptes moi-même, afin de ne dépendre de
personne pour donner ou retirer un accès.* — [PRD 1.3, FR1]

1. La création d'un compte est réservée à `direction` et `super_admin` ; tout autre rôle est refusé, y compris par URL directe.
2. La création génère un **mot de passe temporaire** affiché une seule fois, transmis hors application.
3. L'écran liste les comptes avec leur état, leurs rôles et leur fiche personne ; il est filtrable par état et par rôle.
4. L'attribution et le retrait de rôle se font depuis cet écran et produisent une entrée d'audit avec ancienne et nouvelle valeur.
5. Aucun bouton de suppression n'existe : archivage uniquement, motivé.
6. État vide au premier lancement : « Seul votre compte existe. Créez les deux comptes de direction pour rendre les dépenses approuvables. » — avec l'action correspondante.
7. L'écran reste utilisable à 320 px, les comptes s'empilant en cartes plutôt qu'en tableau.

---

### Story 2.8 — Réinitialisation d'un mot de passe

*En tant que direction, je veux réinitialiser le mot de passe d'un membre, afin qu'un oubli
n'immobilise personne une journée.* — [PRD 1.5, FR6]

1. La réinitialisation est effectuée par `direction` ou `super_admin` uniquement ; aucune réinitialisation en libre-service n'existe en MVP.
2. Elle génère un mot de passe temporaire affiché une seule fois et positionne `must_change_password`.
3. Elle **invalide toutes les sessions** du compte cible (2.5).
4. Elle produit une entrée d'audit nommant **l'auteur et la cible**.
5. La procédure de vérification d'identité hors application est référencée à l'écran et documentée dans `docs/ops/`.

> **DEC-10 / Q9 en attente.** Quelle vérification d'identité exactement avant réinitialisation. La
> procédure est humaine ; l'application n'en trace que le résultat.

---

### Story 2.9 — Campagne d'autorisation par URL directe

*En tant que direction, je veux la preuve automatisée qu'aucun rôle n'atteint une ressource
interdite, afin que cette garantie ne se dégrade pas étape après étape.* — [NFR14, CA-02]

1. `config/authorization-matrix.php` est la **transcription directe de la matrice PRD § 4.3** : rôle × route → statut attendu.
2. `AuthorizationMatrixTest` parcourt la matrice et vérifie chaque combinaison par accès URL direct.
3. ⛔ **Un test complémentaire échoue si une route protégée déclarée dans l'application n'apparaît pas dans la matrice.** C'est ce qui empêche la couverture de se dégrader : ajouter une route sans déclarer sa politique casse la chaîne.
4. `403` et `404` sont **distingués de toute redirection** : un test qui accepterait une `302` validerait précisément le défaut que PERM-02 interdit.
5. Un refus ne renvoie **jamais** de contenu partiel ; le corps de la réponse est comparé à la page « Vous n'avez pas accès à cette page. »
6. La campagne est exécutée dans la chaîne CI à chaque pull request, et rejouée en porte de qualité de chaque jalon.

---

### Story 2.10 — Consultation et export du journal d'audit

*En tant que direction, je veux lire et exporter le journal d'audit, afin de vérifier une opération
contestée sans demander à quiconque.* — [PRD 1.2]

1. L'écran est filtrable par **auteur, période, type d'objet et action**, et paginé.
2. ⛔ Il est accessible **au rôle `direction` uniquement**. Un utilisateur `finance`, `tuteur`, `employe`, `stagiaire` **ou `super_admin`** accédant par URL directe reçoit un refus ; les cinq cas sont testés (FR23, D-04).
3. Chaque entrée affiche auteur, horodatage en heure de Niamey, objet, action, et le **différentiel ancienne / nouvelle valeur** lisible en français.
4. L'export CSV est réservé à `direction` et **génère lui-même une entrée d'audit** avec auteur, nature des données et nombre de lignes (FR24, PERM-06).
5. État vide par filtre : « Aucune entrée pour ces filtres. » distinct de l'état vide réel, avec réinitialisation des filtres.
6. La consultation d'un mois complet reste sous 3 s ; les index de 1.4 sont vérifiés par un test de volumétrie sur 100 000 entrées.

---

## ✅ Critères de fin de l'epic 2

1. **La campagne d'autorisation passe intégralement**, et une route protégée non déclarée fait échouer la chaîne.
2. Le premier administrateur a été créé par la commande, en SSH, et la commande refuse une seconde exécution.
3. Les deux comptes `direction` existent et sont les **seuls** porteurs de `depense.approuver`.
4. ⛔ Les cinq tests bloquants de l'epic passent : `super_admin` sans permission métier, invalidation totale des sessions, unicité conditionnelle du téléphone, aucune inscription publique, aucune suppression de personne ou de compte.
5. Le journal d'audit est consultable par `direction` et fermé aux cinq autres rôles, `super_admin` compris.
6. Recette manuelle : connexion, changement de mot de passe imposé et suspension vérifiés **sur téléphone réel en 3G**.

---

# Epic 3 — Organisation, profils, paramètres et documents internes

**Objectif.** Donner à l'application la connaissance de l'entreprise — qui dépend de qui, quels
services, quelles règles chiffrées — et livrer les deux mécanismes transverses dont tout le reste
dépend : les pièces jointes privées et le centre de notifications.

**Dépend de :** Epic 2. **Bloque :** Epic 4, Epic 5, Epic 6.

---

### Story 3.1 — Fiche entreprise, services et fonctions

*En tant que direction, je veux décrire l'entreprise, ses services et ses fonctions, afin que chaque
personne ait une place identifiée.* — [PRD 1.6]

1. Une **fiche entreprise unique** existe (nom, coordonnées, logo optionnel) ; ⛔ aucune interface ne permet d'en créer une seconde, et aucune colonne de locataire n'existe dans le schéma (NFR28).
2. Services et fonctions sont créables, renommables et désactivables par `direction` ; ils ne sont jamais supprimés.
3. Un service portant encore des membres ne peut pas être désactivé sans réaffectation ; le message nomme le nombre de membres concernés.
4. `CompanySeeder` initialise la fiche PTR Niger, de façon idempotente.
5. Modification de la fiche, création et désactivation d'un service ou d'une fonction sont auditées.

---

### Story 3.2 — Fiche utilisateur, hiérarchie et statut opérationnel

*En tant que direction, je veux tenir à jour chaque fiche, afin que chacun ait un responsable, une
fonction et un statut identifiés.* — [PRD 1.6]

1. La fiche porte nom, téléphone, photo optionnelle, rôles, service, fonction, **responsable direct**, type de relation (`dirigeant`, `employe`, `contractuel`, `stagiaire`), dates de début et de fin de contrat ou de stage.
2. L'application affiche pour toute personne la liste de ses **responsables et subordonnés directs à la date courante** (FR19).
3. Un cycle hiérarchique est refusé : une personne ne peut pas être son propre responsable, directement ou indirectement ; testé sur une chaîne de trois.
4. Un service métier **expose** la liste des fins de contrat ou de stage proches, avec un délai paramétrable ; il est testé directement, sans passer par une notification. **L'émission effective de la notification est en 9.6** — le centre de notifications n'existe qu'en 3.7, et les rappels planifiés en 9.6.
5. Chacun consulte sa fiche ; le responsable celles de son équipe ; `direction` toutes. Tout autre accès est refusé, y compris par URL directe.
6. La fiche est lisible et modifiable à 320 px, les champs empilés, sans défilement horizontal.

---

### Story 3.3 — Historique des changements de fiche

*En tant que direction, je veux savoir qui a changé quoi et quand sur une fiche, afin qu'un
changement de responsable ou de rôle ne se discute pas de mémoire.* — [PRD 1.6, FR18]

1. Tout changement de **rôle, service, responsable direct ou statut** est historisé avec date, auteur, ancienne et nouvelle valeur.
2. L'historique est affiché sur la fiche, du plus récent au plus ancien, en français lisible (« Responsable : Aïcha → Moussa »).
3. Chaque changement produit **également** une entrée au journal d'audit ; les deux registres sont distincts et ne se remplacent pas (architecture § 22.1).
4. Aucune entrée d'historique n'est modifiable ni supprimable.
5. L'historique est visible par la personne concernée, son responsable et `direction` ; les autres accès sont refusés.

---

### Story 3.4 — Paramètres généraux

*En tant que direction, je veux administrer moi-même les règles chiffrées, afin de changer une limite
sans demander de développement.* — [PRD 1.7]

Cette story livre le **mécanisme** de paramétrage et les paramètres **scalaires immédiatement
exploitables**. Les familles de paramètres qui portent une liste d'objets arrivent avec la story qui
les consomme — sinon on livrerait un écran qui configure quelque chose d'inexistant.

1. Sont paramétrables depuis l'interface, et exploitables dès cette story : jours travaillés de la semaine, heure limite du rapport, délai de rappel, limite de stagiaires par tuteur, pourcentage de réserve, objectif de réserve en mois, types et taille maximale des pièces jointes, nombre de tentatives de connexion et durée de blocage.
2. `SettingSeeder` pose les valeurs initiales : stagiaires **3**, réserve **20 %**, objectif **3 mois**, heure limite **17 h 45**, rappel **60 minutes** (FR27 à FR29).
3. ⛔ **Aucune de ces valeurs n'apparaît en dur dans le code.** Un test modifie chaque paramètre livré ici et vérifie le changement de comportement associé, **sans redéploiement**.
4. Toute modification est auditée avec ancienne et nouvelle valeur et porte une **date d'effet** (FR26).
5. La modification est réservée à `direction` ; tout autre rôle est refusé, y compris par URL directe.
6. Un paramètre dont la modification a un effet chiffré affiche cet effet **avant confirmation** ; le mécanisme d'aperçu est livré ici, ses premiers usages chiffrés arrivent en 8.2.
7. Le cache de configuration est invalidé à l'écriture ; un test vérifie que la valeur nouvelle est lue à la requête suivante.

**Familles de paramètres livrées ailleurs**, chacune avec son consommateur :

| Famille | Story | Pourquoi pas ici |
|---|---|---|
| Jours fériés et fermetures | **4.1** | Le calendrier qui les interprète n'existe pas encore |
| Catégories de dépense et marqueur « essentielle » | **4.3** | Consommées par la demande de dépense |
| Créneaux de suivi par tuteur | **7.6** | Le regroupement des demandes n'existe qu'en Epic 7 |
| Charges fixes et montants mensuels | **8.2** | L'assiette d'alerte et l'objectif de réserve sont en Epic 8 |

---

### Story 3.5 — Pièces jointes privées

*En tant qu'utilisateur, je veux joindre une preuve sans qu'elle devienne accessible à qui possède
son adresse, afin qu'un justificatif ne circule pas hors de l'application.* — [NFR15, NFR16, A-04]

1. Les fichiers sont stockés dans `storage/app/private`, **hors de la racine web** ; ⛔ un test vérifie qu'aucune URL publique ne les atteint.
2. La lecture passe par un contrôleur qui **contrôle l'autorisation avant de servir**, avec `X-Accel-Redirect` pour ne pas faire transiter le fichier par PHP.
3. Types et taille maximale sont **paramétrables** (3.4) ; un téléversement non conforme est refusé **côté serveur**, même si le contrôle client est contourné ; testé en forgeant la requête.
4. Le message de refus est explicite : « Ce fichier fait 8 Mo, la limite est de 5 Mo. Choisissez un fichier plus léger. » — la limite affichée est celle réellement paramétrée.
5. Le type réel du fichier est vérifié, pas seulement son extension ; un exécutable renommé en `.pdf` est refusé.
6. Les images sont redimensionnées côté serveur en vignette pour l'affichage en liste (poids en 3G, UX § 11.2).
7. Toute pièce jointe est rattachée à un objet et hérite de ses règles de visibilité ; l'accès à la pièce d'un objet non autorisé est refusé.
8. Le téléversement affiche une progression et reste utilisable depuis l'appareil photo d'un téléphone.

> **DEC-08 appliqué par défaut :** PDF, JPEG, PNG, WebP, HEIC — 8 Mo. Modifiable au paramétrage.

---

### Story 3.6 — Documents du dossier personnel

*En tant que membre, je veux que mon contrat et mes engagements soient rangés dans mon dossier, afin
qu'ils ne circulent pas par messagerie.* — [PRD 1.6, FR17, FR98]

1. Un document (contrat, convention, fiche de poste, engagement signé) est rattachable au dossier d'une personne.
2. ⛔ Il n'est visible que par **cette personne, son responsable direct et `direction`** ; l'accès par URL directe depuis tout autre compte est refusé, y compris depuis `super_admin`.
3. Le document n'est jamais supprimé ; il est archivé, motivé.
4. Dépôt, consultation et archivage produisent une entrée d'audit.
5. État vide : « Aucun document dans ce dossier. » avec l'action de dépôt si l'utilisateur en a le droit.

---

### Story 3.7 — Centre de notifications interne

*En tant qu'utilisateur, je veux être averti dans l'application de ce qui m'attend, afin de ne pas
découvrir un retard après coup.* — [PRD 1.8]

Livrée ici et non au Jalon 4 : les relances de double approbation (4.6) et les rappels de rapport
(6.2) en dépendent — voir ÉCART-02.

1. Un centre de notifications avec **compteur de non-lues** est accessible depuis toute page authentifiée.
2. Le système de notifications Laravel est utilisé avec le **canal `database` seul** (A-07) ; l'architecture permet d'ajouter SMS ou WhatsApp en phase 2 sans refonte.
3. Chaque notification porte un **lien direct vers l'objet concerné**.
4. ⛔ Depuis la notification, l'objet lié est atteignable en **au plus 3 interactions**, prouvé ici sur une notification générique et son lien autorisé (FR32). **La mesure sur les deux parcours réels est faite là où ils naissent** : approbation de dépense en **4.6**, validation de rapport en **6.3**.
5. Une notification est marquée lue **explicitement** par l'utilisateur ou **implicitement** à l'ouverture de l'objet ; les deux comportements sont testés.
6. ⛔ Aucun envoi SMS, WhatsApp ou courriel n'est déclenché ; un test vérifie qu'aucun canal externe n'est appelé (FR34).
7. État vide : « Vous êtes à jour. » — ton positif, le vide étant ici une bonne nouvelle.
8. Le compteur ne provoque pas de requête à chaque navigation : il est porté par la réponse Inertia partagée.

---

### Story 3.8 — Bibliothèque de documents internes et accusés d'acceptation

*En tant que direction, je veux publier les règles internes et savoir qui les a acceptées, afin qu'un
engagement soit opposable.* — [PRD 3.13] — **avancée au Jalon 1, voir ÉCART-03**

1. Un document interne porte titre, contenu ou fichier, **version** et **date d'application**.
2. Un document peut exiger un **accusé de lecture et d'acceptation**, enregistré par utilisateur avec horodatage.
3. La publication d'une nouvelle version notifie les utilisateurs concernés et **réinitialise l'exigence d'acceptation** ; testé.
4. ⛔ L'historique complet des versions reste consultable ; aucune version n'est supprimable.
5. Un tableau montre à `direction` qui a accepté et qui n'a pas encore accepté chaque document, avec l'ancienneté de la demande.
6. Publication, nouvelle version et accusé d'acceptation produisent chacun une entrée d'audit.
7. La lecture d'un document long est confortable sur téléphone : texte fluide, pas de zoom horizontal, acceptation en pied de document.

---

## ✅ Critères de fin de l'epic 3

1. Chaque membre a une fiche complète avec responsable direct, et la chaîne hiérarchique est sans cycle.
2. Les paramètres livrés à ce jalon — jours travaillés, heure limite, délai de rappel, limite de stagiaires, pourcentage et objectif de réserve, types et taille des pièces jointes, tentatives et durée de blocage — sont modifiables à l'écran, et un test prouve pour chacun le changement de comportement **sans redéploiement**. Les quatre familles restantes de FR25 arrivent en 4.1, 4.3, 7.6 et 8.2.
3. ⛔ Aucune pièce jointe n'est atteignable par URL publique ; le refus de type et de taille est prouvé côté serveur.
4. Le centre de notifications fonctionne et **aucun canal externe n'est appelé**.
5. Le règlement intérieur est publié et l'état des acceptations est visible par `direction`.
6. La campagne d'autorisation couvre les nouvelles ressources, dossiers personnels compris.

---

# Epic 4 — Calendrier, absences et autorisation des dépenses

**Objectif.** Fermer, dès la première mise en service, la défaillance qui a déjà coûté l'entreprise :
aucune dépense engagée sans double autorisation enregistrée. Et poser le calendrier sans lequel tous
les indicateurs de ponctualité du Jalon 3 seraient faux.

**Dépend de :** Epic 3 (paramètres, notifications, pièces jointes) et Epic 2 (les deux comptes `direction`).
**Portée volontairement limitée :** ni compte financier, ni paiement, ni écriture comptable — voir
ÉCART-01.

---

### Story 4.1 — Calendrier des jours travaillés et jours fériés

*En tant que direction, je veux déclarer les jours travaillés et les jours fériés, afin que
l'application n'attende pas de rapport un jour où l'entreprise est fermée.* — [PRD 1.9]

1. Les jours travaillés de la semaine sont **paramétrables**, initialisés du lundi au vendredi.
2. Des jours fériés ou de fermeture ponctuels sont saisissables avec libellé et date ; `HolidaySeeder` initialise les jours fériés nigériens de l'année en cours.
3. Une fonction applicative répond « jour travaillé : oui / non » pour toute date ; testée sur un jour ouvré, un samedi, un dimanche et un jour férié saisi.
4. Aucun rapport quotidien n'est attendu ni compté comme manquant sur un jour non travaillé (contrat consommé par Epic 6).
5. Toute modification du calendrier est auditée et porte une date d'effet.
6. La vue calendrier reste lisible à 320 px ; les jours non travaillés sont distingués **par un libellé** autant que par la couleur.

---

### Story 4.2 — Déclaration et approbation d'absence

*En tant qu'employé ou stagiaire, je veux déclarer mon absence et la faire approuver, afin de ne pas
être compté en retard alors que j'étais en congé ou malade.* — [PRD 1.10]

1. Une absence porte type (congé, maladie, autre), date de début, date de fin, motif court, justificatif optionnel.
2. Les états `demandee`, `approuvee`, `refusee`, `annulee` existent ; **le refus exige un motif**.
3. ⛔ L'approbation appartient au **responsable direct** ; un utilisateur ne peut pas approuver sa propre absence ; testé y compris pour `direction`.
4. ⛔ Aucun rapport n'est attendu sur un jour couvert par une absence **`approuvee`** ; un test crée une absence approuvée et vérifie que le jour n'apparaît pas dans les rapports manquants.
5. Une absence `demandee` ou `refusee` **ne suspend pas** l'attente de rapport ; testé.
6. Les indicateurs de ponctualité excluent du dénominateur les jours non travaillés et les jours d'absence approuvée ; un test calcule le taux sur un mois comportant les deux.
7. Chacun consulte ses absences, le responsable celles de son équipe, `direction` toutes ; les autres accès sont refusés.
8. La déclaration est réalisable en moins d'une minute sur téléphone ; le sélecteur de dates est utilisable au pouce.

---

### Story 4.3 — Catégories de dépense et marqueur « essentielle »

*En tant que direction, je veux administrer les catégories de dépense, afin qu'une nouvelle nature de
dépense n'exige jamais une modification de code.* — [PRD 4.2, partiel — avancé au Jalon 1]

1. Les catégories sont créables, renommables et désactivables depuis le paramétrage ; jamais supprimées.
2. Une catégorie **« gratification de stagiaire »**, distincte des salaires, existe (FR126).
3. Chaque catégorie porte un marqueur booléen **« dépense essentielle »** (FR127), consommé plus tard par l'alerte rouge (9.2).
4. `ExpenseCategorySeeder` est idempotent et pose le jeu initial avec ses marqueurs.
5. ⛔ Aucune catégorie n'est codée en dur ; un test ajoute une catégorie et vérifie qu'elle est immédiatement sélectionnable, sans redéploiement.
6. Création, renommage et désactivation sont audités.

---

### Story 4.4 — Demande de dépense et registre

*En tant que membre de l'équipe, je veux enregistrer toute demande de dépense avant de payer, afin
qu'aucun franc ne sorte de l'entreprise sans trace.* — [PRD 1.11]

1. Une demande porte demandeur, motif, **montant en XOF entier**, bénéficiaire, projet ou contrat optionnel, résultat attendu, catégorie, justificatif prévisionnel optionnel.
2. ⛔ Les états `demandee`, `approuvee`, `refusee`, `payee`, `annulee` existent et sont **distincts** : l'état d'approbation et l'état de paiement ne sont **jamais** confondus (FR116).
3. **Tout rôle applicatif peut créer une demande**, `stagiaire` compris ; testé pour les six rôles.
4. Une demande créée est immédiatement visible dans le registre de `direction` et `finance`, et notifie les deux approbateurs.
5. ⛔ Aucune interface ne permet de supprimer une demande ; l'annulation exige un motif et conserve l'enregistrement (FR122).
6. Création, modification et annulation produisent chacune une entrée d'audit.
7. La saisie est réalisable sur téléphone en moins de deux minutes, la photo du justificatif prise depuis l'appareil.
8. Le registre est filtrable par état, catégorie, demandeur et période. État vide : « Aucune demande de dépense pour ce mois. »

**Migrations :** `expenses`, `expense_approvals` — **sans** colonne financière. Les colonnes de
paiement et d'imputation sont ajoutées **par migration ultérieure** en 8.6, jamais par modification
de celle-ci (architecture § 5.2).

---

### Story 4.5 — Double approbation obligatoire des dépenses

*En tant qu'associé propriétaire, je veux que toute dépense exige nos deux consentements, afin
qu'aucune sortie d'argent ne dépende d'une seule personne.* — [PRD 1.12]

C'est la story qui porte la valeur centrale du Jalon 1. Chacun de ses critères est un test bloquant.

1. ⛔ Une dépense passe à `approuvee` **uniquement** après enregistrement de l'approbation de **deux comptes `direction` distincts**, quel que soit le montant, qu'elle soit ou non prévue au budget (RM-09, CA-09).
2. ⛔ **Aucun seuil de montant n'existe** dans le code ni au paramétrage ; un test soumet une dépense de 1 000 FCFA et vérifie que deux approbations restent exigées.
3. ⛔ Le demandeur ne compte **jamais** comme approbateur de sa propre demande, y compris s'il porte le rôle `direction` (RM-10, CA-11). Message : « Vous êtes le demandeur de cette dépense. Elle doit être approuvée par les deux autres comptes de direction. »
4. ⛔ Tant qu'une seule approbation est enregistrée, la dépense reste `demandee` et **n'est pas payable** ; toute tentative de paiement est refusée côté serveur.
5. ⛔ **Aucune route, aucune permission, aucun paramètre ne permet une approbation dérogatoire ou déléguée** ; un test tente d'approuver avec un seul compte `direction` et échoue (CONTRA-03, C14).
6. Un refus exige un motif ; le refus d'**un seul** approbateur suffit à placer la dépense à `refusee`.
7. Chaque approbation et chaque refus produit une entrée d'audit nommant l'auteur et l'horodatage.
8. L'approbation est protégée contre la concurrence : deux approbations simultanées du même compte ne comptent pas double ; testé sous verrou.
9. Lorsque les deux comptes `direction` n'existent pas encore, l'écran explique pourquoi aucune approbation n'est possible plutôt que d'échouer silencieusement (2.3 AC9).
10. ⛔ `ptr:check-invariants` (créée en 2.3) est **étendue** de deux invariants vérifiés **dans les données** : exactement **2** comptes porteurs de `depense.approuver` (PERM-05), et **aucune dépense `payee` sans deux approbations distinctes**. Le second est le plus important du dispositif : il détecte une manipulation en base ou une régression déjà passée en production.

---

### Story 4.6 — « En attente de mon approbation » et relances

*En tant qu'associé propriétaire, je veux voir immédiatement ce qui attend ma signature et être
relancé, afin que le gel des dépenses reste court et que personne ne paie de sa poche.* — [PRD 1.13]

1. Le tableau de bord d'un compte `direction` affiche **en première position** le bloc « En attente de mon approbation », avec le nombre de dépenses et l'**ancienneté de la plus ancienne** (FR167).
2. Le bloc ne liste que les dépenses **que ce compte n'a pas encore traitées** et **dont il n'est pas le demandeur**.
3. ⛔ Depuis la notification, la décision d'approbation est atteignable en **au plus 3 interactions** ; mesuré en recette sur téléphone réel et couvert par un parcours Playwright (FR121, UX § 4.2).
4. ⛔ Une dépense sans décision de l'un des deux approbateurs déclenche un rappel à **J+1** et à **J+2** vers **l'approbateur manquant uniquement** (FR33).
5. ⛔ Les rappels cessent dès la décision prise ; un test vérifie qu'aucun rappel n'est émis après approbation ou refus.
6. Le bloc vide affiche « Aucune dépense n'attend votre approbation. Les demandes apparaîtront ici dès qu'un membre en créera. » avec un lien vers les dépenses traitées — **et aucun message d'erreur**.
7. La décision est prenable à une main, en 3G, sur un écran de 320 px : le justificatif se consulte sans quitter l'écran de décision.

---

## ✅ Critères de fin de l'epic 4 — porte du Jalon 1

1. ⛔ **Les cinq tests bloquants de la double approbation passent** : deux approbateurs distincts sans seuil, demandeur jamais approbateur, aucune dérogation, non payable à une seule approbation, refus unique suffisant.
2. Une dépense de 1 000 FCFA et une de 5 000 000 FCFA suivent exactement le même circuit.
3. Le calendrier et les absences approuvées suspendent correctement l'attente de rapport — contrat vérifié par test, consommé au Jalon 3.
4. **Recette sur téléphone réel en 3G dégradée** : approbation d'une dépense depuis la notification en **3 interactions maximum**, chronométrée et consignée.
5. Campagne d'autorisation intégralement verte sur l'ensemble des ressources du Jalon 1.
6. `ptr:check-invariants` passe : exactement 2 comptes porteurs de `depense.approuver`, aucun `super_admin` avec permission métier, déclencheurs d'audit présents.
7. **11.1 à 11.6 sont terminées** — sauvegarde, restauration testée, supervision, déploiement — avant la mise en production.

---

# Epic 5 — Objectifs, projets, tâches et livrables

**Objectif.** Rendre le travail explicite avant qu'il ne soit exécuté. À l'issue de cet epic, la
question « qui n'a pas d'objectif ce mois-ci » a une réponse à l'écran, et les dirigeants figurent
dans cette réponse au même titre que les autres.

**Dépend de :** Epic 4 (Jalon 1 en production), Epic 3 (pièces jointes, notifications).
**Jalon 2.**

---

### Story 5.1 — Priorités d'entreprise du mois

*En tant que direction, je veux fixer au plus cinq priorités par mois, afin que l'entreprise
poursuive un nombre d'objectifs qu'elle peut réellement tenir.* — [PRD 2.2]

1. Une priorité porte titre, description courte, responsable, indicateur, cible, échéance, priorité.
2. ⛔ La création d'une **sixième** priorité validée pour un même mois est refusée **côté serveur**, avec un message nommant la limite et le mois (RM-04).
3. Une priorité **annulée ne compte plus** dans la limite ; un test annule puis crée une nouvelle priorité avec succès.
4. Les priorités sont en **lecture pour tous les rôles** et en **gestion pour `direction` seule** ; testé pour les six rôles.
5. Toute modification d'une priorité validée exige un **motif** et produit une entrée d'audit avec ancienne et nouvelle valeur.
6. État vide : « Aucune priorité définie pour juillet. » avec l'action de création si l'utilisateur en a le droit.

---

### Story 5.2 — Objectif individuel et limite de trois par mois

*En tant qu'utilisateur, je veux au plus trois objectifs majeurs par mois, afin de concentrer mon
effort sur ce qui compte réellement.* — [PRD 2.3]

1. Un objectif porte titre, description courte, responsable, indicateur, valeur cible, **preuve attendue**, date limite, moyens nécessaires, priorité. La **preuve attendue est un champ de premier plan**, pas une option repliée.
2. Un objectif peut être rattaché à une priorité d'entreprise et / ou à un projet.
3. ⛔ La validation d'un **quatrième** objectif pour une même personne et un même mois est refusée côté serveur (RM-05, CA-05). Message : « Vous avez déjà 3 objectifs majeurs validés pour juillet. Terminez-en un ou reportez-le avant d'en valider un quatrième. »
4. ⛔ La limite compte les états `valide`, `en_cours`, `atteint`, `partiellement_atteint`, `non_atteint` et `bloque` ; elle **ignore** `brouillon` et `annule`. Chaque cas est testé.
5. ⛔ La règle s'applique **identiquement aux comptes `direction`** ; un test valide trois objectifs pour un associé et vérifie le refus du quatrième (P5, RM-03).
6. Un utilisateur peut **proposer** un objectif : il reste `brouillon` et ne devient officiel qu'après validation de son responsable (FR49).
7. Création, validation et modification sont auditées.

---

### Story 5.3 — États, progression et preuve obligatoire

*En tant que responsable, je veux qu'un objectif ne puisse être déclaré atteint sans preuve, afin
qu'aucun résultat ne repose sur une simple déclaration.* — [PRD 2.4]

1. Les huit états `brouillon`, `valide`, `en_cours`, `atteint`, `partiellement_atteint`, `non_atteint`, `bloque`, `annule` existent ; **les transitions autorisées sont testées**, les interdites refusées.
2. ⛔ Le passage à `atteint` est **refusé si aucune preuve n'est attachée** ; le message rappelle la preuve attendue déclarée à la création (P1, FR47).
3. L'utilisateur met à jour la progression et attache une preuve à tout moment ; le responsable commente, valide ou demande une correction.
4. Le code couleur vert / orange / rouge / gris est **systématiquement doublé d'un libellé textuel** (FR45, NFR31).
5. ⛔ Toute modification après validation exige un **motif**, conserve valeur précédente et auteur, et produit une entrée d'audit ; un test **lit l'ancienne valeur dans le journal** (FR46, CA-06).
6. La pièce jointe de preuve suit les règles de 3.5 ; une photo prise au téléphone est acceptée.

---

### Story 5.4 — Vues et synthèse mensuelle des objectifs

*En tant que responsable ou membre, je veux consulter les objectifs en liste, en calendrier et en
synthèse, afin de repérer les retards avant l'échéance.* — [PRD 2.5]

1. Trois vues existent : liste, calendrier, synthèse mensuelle.
2. ⛔ Chaque vue applique strictement la matrice § 4.3 : `direction` tous, `tuteur` son équipe, les autres les leurs. Un accès par URL directe aux objectifs d'une personne hors périmètre est refusé.
3. La synthèse affiche le nombre d'objectifs par état et la **liste des membres sans objectif validé** pour le mois — comptes `direction` inclus (P5).
4. La copie d'un objectif récurrent vers le mois suivant crée un objectif à l'état **`brouillon`** exigeant une nouvelle validation (FR51).
5. ⛔ Aucun classement comparatif entre personnes n'apparaît (FR82).
6. Vide par filtre distinct du vide réel, avec réinitialisation des filtres.
7. La vue calendrier reste utilisable à 320 px : bascule automatique en liste chronologique plutôt que grille compressée.

---

### Story 5.5 — Projets et membres

*En tant que responsable de projet, je veux créer un projet et y rattacher des membres, afin que le
travail collectif ait un cadre identifié.* — [PRD 2.6]

1. Un projet porte nom, client optionnel, responsable, dates, statut, membres.
2. Les statuts `prevu`, `actif`, `bloque`, `en_validation`, `livre`, `cloture`, `annule` existent ; transitions testées.
3. ⛔ La partie budgétaire n'est visible que par `direction` et `finance` ; l'accès par URL directe depuis tout autre rôle est refusé (FR59).
4. Tout changement de statut est historisé avec auteur et date.
5. Un membre retiré d'un projet **conserve la trace de sa participation passée** ; testé.
6. État vide : « Aucun projet actif. »

---

### Story 5.6 — Tâches, sous-tâches et commentaires

*En tant que membre d'un projet, je veux gérer mes tâches et y joindre mes éléments, afin que mon
travail du jour soit identifiable et prouvable.* — [PRD 2.7]

1. Une tâche porte titre, responsable, échéance, priorité, lien optionnel vers un objectif, statut.
2. ⛔ Les sous-tâches sont limitées à **un seul niveau de profondeur** ; la création d'une sous-sous-tâche est refusée côté serveur.
3. Tâches et projets acceptent pièces jointes, liens et commentaires, selon les règles de 3.5.
4. La liste des tâches est filtrable par responsable, échéance, statut et projet.
5. Les tâches assignées **pour la journée** sont exposées par un service dédié — contrat consommé par le pré-remplissage du rapport quotidien (6.1).
6. La liste du jour se consulte en une vue à 320 px, sans filtre à configurer.

---

### Story 5.7 — Livrables

*En tant que responsable de projet, je veux suivre les livrables et leur validation, afin de savoir ce
qui a réellement été remis au client.* — [PRD 2.8]

1. Un livrable porte responsable, date prévue, date réelle, statut de validation.
2. ⛔ Un livrable n'est marqué validé que par le responsable du projet ou `direction` ; testé pour les autres rôles.
3. L'écart entre date prévue et date réelle est calculé et affiché, en jours, avec libellé (« 4 jours de retard »).
4. Tout changement de statut est historisé et audité.

---

### Story 5.8 — Tableau de bord personnel

*En tant qu'utilisateur, je veux voir en dix secondes ce que j'ai à faire aujourd'hui, afin de ne pas
découvrir mes priorités en réunion.* — [PRD 2.1]

**Le tableau de bord grandit par incréments.** Il est livré ici avec les blocs dont les objets
existent, puis chaque epic ultérieur y ajoute le sien. L'alternative — le déplacer après l'epic 7 —
laisserait le Jalon 2 sans écran d'accueil, ce qui est pire qu'un écran partiel.

| Bloc | Ajouté par |
|---|---|
| Objectifs du mois, tâches du jour, prochaines échéances, notifications, demandes en attente | **5.8** (ici) |
| Rapport du jour à envoyer | **6.1** |
| Blocages ouverts | **6.6** |
| Dernière évaluation | **7.5** |

1. Le tableau de bord affiche les blocs disponibles à ce jalon : objectifs du mois avec progression, tâches du jour, prochaines échéances, notifications, demandes en attente. FR166 n'est **intégralement satisfait qu'à l'issue de 7.5**.
2. ⛔ Chaque bloc n'est rendu que si l'utilisateur détient la permission correspondante ; un bloc non autorisé est **absent**, sans bloc vide ni message d'erreur technique (FR172).
3. Le bloc le plus urgent figure en tête : « En attente de mon approbation » pour `direction`. Pour les autres rôles, « Mon rapport du jour » **prend cette place dès 6.1** ; à ce jalon, ce sont les objectifs du mois.
4. Les blocs sont **empilés verticalement** et lisibles sans défilement horizontal à 320 px.
5. Le premier rendu utile intervient en **moins de 3 secondes** en 3G dégradée simulée ; mesuré par Playwright avec bridage réseau **et** en recette sur téléphone réel.
6. Les blocs vides portent un message de vide propre à chacun ; aucun ne reste blanc.
7. Le nombre de requêtes base par rendu est plafonné et testé — le tableau de bord est la page la plus chargée de l'application.

---

## ✅ Critères de fin de l'epic 5 — porte du Jalon 2

1. ⛔ Les deux limites bloquantes passent : **5 priorités d'entreprise**, **3 objectifs majeurs par personne et par mois**, la seconde **y compris pour les comptes `direction`**.
2. ⛔ Aucun objectif ne peut passer à `atteint` sans preuve attachée.
3. ⛔ Une modification d'objectif validé conserve l'ancienne valeur, le motif et l'auteur, **relus depuis le journal d'audit**.
4. La synthèse mensuelle liste les membres sans objectif, **associés compris**.
5. Aucun écran ne présente de classement entre personnes.
6. Recette sur téléphone réel : tableau de bord personnel en **moins de 3 secondes** en 3G dégradée.
7. Campagne d'autorisation étendue aux objectifs, projets, tâches et budget de projet — verte.
8. 11.7 exécutée : recette de mise en service du Jalon 2.

---

# Epic 6 — Rapport quotidien et blocages

**Objectif.** Installer la boucle courte du produit. Cet epic porte **le point de vérité** :
la saisie de fin de journée sur téléphone en 3G, en moins de trois minutes. Si cette mesure échoue en
recette, c'est le produit qui échoue, pas la story.

**Dépend de :** Epic 5 (tâches du jour pour le pré-remplissage), Epic 4 (calendrier et absences), Epic 3
(notifications, pièces jointes). **Jalon 3.**

---

### Story 6.1 — Saisie du rapport quotidien et brouillon local

*En tant qu'utilisateur, je veux rendre compte de ma journée en moins de trois minutes depuis mon
téléphone, afin que rendre compte reste un geste tenable tous les jours.* — [PRD 3.1]

1. ⛔ Il existe **au plus un rapport par personne et par jour travaillé** ; une seconde création pour le même jour ouvre le rapport existant, elle n'en crée pas un second. Contrainte posée en base autant qu'en applicatif.
2. Les six champs obligatoires sont présents : tâche prévue, résultat obtenu, **preuve ou lien**, blocage, prochaine action, aide demandée.
3. Le champ « tâche prévue » est **pré-rempli** depuis les tâches assignées pour la journée (5.6) et reste modifiable (FR62).
4. Le rapport accepte image, document ou lien, dans les limites paramétrées.
5. ⛔ Le brouillon est sauvegardé automatiquement **au plus tard 10 secondes** après la dernière frappe (NFR5).
6. ⛔ Une saisie interrompue par une coupure réseau est **restaurée intégralement** à la réouverture sur le même appareil ; testé en simulant une coupure au milieu de la saisie.
7. ⛔ **La saisie complète est réalisable en moins de 3 minutes** sur téléphone réel avec pré-remplissage actif. La mesure est consignée en recette et **conditionne la mise en service du jalon** (NFR4, CONTRA-08).
8. Le formulaire est utilisable à 320 px, cibles ≥ 44 × 44 px, sans défilement horizontal, saisissable à une main.
9. ⛔ Une action interrompue par une perte de connexion ne produit **jamais d'enregistrement partiel** : tout ou rien (NFR6).
10. Hors connexion, le bandeau explique « L'envoi n'a pas abouti — pas de connexion. Votre rapport est conservé sur cet appareil. [Réessayer] » sans promettre d'envoi automatique.
11. Le bloc **« Mon rapport du jour »** est ajouté au tableau de bord personnel (5.8) et y prend la **première position** pour tout rôle autre que `direction` (UX § 1.3). Il n'apparaît pas les jours non travaillés ni sur une absence approuvée.

> **Si la mesure des 3 minutes échoue en recette réelle**, l'arbitrage remonte au produit : réduire
> les champs obligatoires ou accepter la friction (CONTRA-08). Ce n'est pas une décision de
> développement.

---

### Story 6.2 — Envoi, heure limite, rappel et retard

*En tant que responsable, je veux que l'heure limite et les rappels soient gérés par l'application,
afin de ne plus courir après les rapports.* — [PRD 3.2]

1. Les états `brouillon`, `envoye`, `valide`, `retourne`, `en_retard` existent ; transitions testées.
2. Un rappel est émis au **délai paramétré avant l'heure limite** (60 minutes par défaut) à toute personne dont le rapport n'est pas `envoye` (FR66, C12).
3. Une notification de retard est émise après l'heure limite si le rapport n'est pas `envoye`.
4. ⛔ **Aucun rappel ni retard** n'est émis pour un jour non travaillé ou couvert par une absence approuvée ; les deux cas sont testés.
5. Un rapport envoyé après l'heure limite affiche le retard constaté et propose une **explication courte facultative** — sans ton accusatoire (FR71, NFR29).
6. ⛔ La modification de l'heure limite au paramétrage change le comportement **sans redéploiement** ; testé.
7. Les rappels sont émis par tâche planifiée ; un test vérifie qu'un rappel n'est pas envoyé deux fois pour le même jour.
8. La tâche planifiée de rappel est **ajoutée au registre d'ordonnancement** de `docs/ops/` et à la supervision de 11.4 — une tâche qui ne s'exécute pas à l'heure attendue doit alerter.

---

### Story 6.3 — Validation, retour et historique des versions

*En tant qu'utilisateur, je veux que mon responsable ne puisse pas réécrire mon rapport, afin que ce
qui est enregistré à mon nom soit exactement ce que j'ai écrit.* — [PRD 3.3]

1. Le responsable peut commenter, valider ou retourner un rapport.
2. ⛔ Le responsable **ne peut modifier aucun champ saisi par l'auteur** ; une requête de modification par un compte autre que l'auteur est refusée côté serveur, **y compris pour `direction`** (FR67, CA-08).
3. ⛔ Une correction par l'auteur après envoi crée une **nouvelle version** ; la version précédente reste consultable avec son horodatage (FR68).
4. Un rapport retourné revient à l'auteur avec le **motif du retour** et une notification.
5. ⛔ Le périmètre de validation respecte la matrice : `tuteur` son équipe, `direction` tous ; hors périmètre par URL directe → refus.
6. Chaque validation, retour et nouvelle version produit une entrée d'audit.
7. ⛔ Depuis la notification, la validation est atteignable en **au plus 3 interactions** ; c'est ici que la mesure annoncée en 3.7 AC4 est réellement faite, en recette sur téléphone réel (UX § 4.3).

---

### Story 6.4 — Vues des rapports et rapports manquants

*En tant que direction, je veux voir qui a rendu son rapport et qui ne l'a pas rendu, afin de traiter
le manquement le jour même plutôt qu'en fin de mois.* — [PRD 3.4]

1. Trois vues existent : quotidienne, hebdomadaire, mensuelle.
2. Une liste « rapports manquants du jour » affiche les personnes attendues sans rapport `envoye`.
3. ⛔ Cette liste **exclut** les personnes en absence approuvée et les jours non travaillés ; testé sur un mois comportant les deux.
4. ⛔ Le taux de ponctualité est le rapport des rapports envoyés avant l'heure limite sur les rapports **attendus**, dénominateur corrigé des absences (FR39).
5. Chaque vue applique strictement la matrice § 4.3.
6. ⛔ Aucun classement comparatif entre personnes ; la liste est alphabétique, jamais ordonnée par performance.
7. Vide positif : « Tous les rapports du jour ont été envoyés. »

---

### Story 6.5 — Demande de nouvelle tâche

*En tant qu'utilisateur sans tâche disponible, je veux demander une tâche depuis mon rapport, afin de
ne pas rester sans travail en attendant une réunion.* — [PRD 3.5]

1. Une demande de nouvelle tâche est créable **depuis le formulaire de rapport quotidien**, sans le quitter.
2. Elle est notifiée immédiatement au responsable direct.
3. Elle porte un état ouvert / traité et **conserve le lien vers le rapport d'origine**.
4. Les demandes non urgentes d'un `stagiaire` sont regroupées selon les créneaux de suivi (7.6) ; le contrat est posé ici, la règle appliquée là-bas.
5. Création et traitement sont audités.

---

### Story 6.6 — Blocages et demandes d'aide

*En tant qu'utilisateur bloqué, je veux signaler mon blocage et obtenir une réponse rapide, afin qu'un
obstacle ne consomme pas une journée entière.* — [PRD 3.6]

1. Un blocage est créable depuis une **tâche, un objectif ou un rapport**, et conserve le lien vers son origine ; **les trois chemins sont testés**.
2. Il porte problème, niveau d'urgence, personne sollicitée, date, effet sur l'échéance, action déjà essayée.
3. Les états `ouvert`, `pris_en_charge`, `resolu`, `ferme_sans_solution` existent.
4. ⛔ La création notifie **immédiatement** la personne sollicitée, **sans regroupement**, lorsque l'urgence est marquée (FR92).
5. Les délais création → `pris_en_charge` et création → `resolu` sont calculés et affichés.
6. La fermeture sans solution exige un **motif**.
7. La création d'un blocage depuis le rapport quotidien ne fait pas perdre la saisie en cours.
8. Vide positif : « Aucun blocage ouvert. »
9. Le bloc **« Blocages ouverts »** est ajouté au tableau de bord personnel (5.8).

---

## ✅ Critères de fin de l'epic 6

1. ⛔ **Recette bloquante : saisie complète du rapport quotidien en moins de 3 minutes sur téléphone réel en 3G dégradée**, avec pré-remplissage actif, mesurée et consignée. Sans cette mesure, le jalon ne part pas en production.
2. ⛔ Le brouillon est restauré intégralement après coupure réseau au milieu de la saisie.
3. ⛔ Un responsable, `direction` comprise, ne peut modifier aucun champ d'un rapport dont il n'est pas l'auteur.
4. ⛔ La liste des rapports manquants et le taux de ponctualité excluent correctement absences approuvées et jours non travaillés.
5. Un seul rapport par personne et par jour, garanti en base autant qu'en applicatif.
6. Parcours Playwright « rapport quotidien de bout en bout, réseau bridé à 400 kbit/s » vert.
7. Campagne d'autorisation étendue aux rapports et aux blocages — verte.

---

# Epic 7 — Stagiaires et revues hebdomadaires

**Objectif.** Fermer la boucle hebdomadaire et plafonner la charge d'encadrement par une limite
bloquante paramétrable. Aucun stagiaire n'est accueilli sans mission écrite, tuteur désigné et trois
objectifs.

**Dépend de :** Epic 6 (rapports et blocages alimentent la revue), Epic 5 (objectifs).
**Dépendance avant :** 7.3 AC5 est complétée par 9.2 — voir § 4. **Jalon 3.**

---

### Story 7.1 — Revue hebdomadaire

*En tant que responsable, je veux mener la revue du vendredi sur une base factuelle, afin que
l'échange porte sur des résultats et non sur des impressions.* — [PRD 3.7]

1. Une revue est ouvrable par le responsable pour chaque membre de son équipe, **périodicité hebdomadaire par défaut le vendredi** (RM-08).
2. Elle présente **automatiquement** les objectifs, tâches, rapports et blocages de la semaine concernée, **sans ressaisie**.
3. Pour chaque objectif : résultat, preuve, statut, cause de l'écart, prochaine action sont enregistrables.
4. ⛔ Elle enregistre le commentaire de la personne évaluée **et** celui du responsable ; la validation électronique des **deux parties** est horodatée et nominative (FR80).
5. ⛔ Les comptes `direction` suivent **la même procédure** ; un test crée une revue pour un associé (P5, RM-03, CA-16).
6. ⛔ Aucun classement comparatif entre personnes n'apparaît sur aucun écran de revue (FR82).
7. ⛔ L'historique est consultable et **aucune revue validée n'est modifiable**.
8. La revue est consultable sur téléphone ; la validation de la personne évaluée y est faisable sans ordinateur.

---

### Story 7.2 — Plan d'amélioration

*En tant que responsable, je veux formaliser un plan d'amélioration court quand c'est nécessaire, afin
que l'aide apportée soit tracée aussi bien que l'écart constaté.* — [PRD 3.8]

1. Un plan est créable depuis une revue, d'une durée comprise entre **7 et 14 jours** ; ⛔ une durée hors bornes est refusée côté serveur.
2. Il porte actions, **aide fournie**, dates, résultat constaté.
3. ⛔ Il est visible par la personne concernée, son responsable et `direction`, **et par personne d'autre** ; testé par URL directe depuis un pair.
4. ⛔ **Aucune conséquence disciplinaire n'est déclenchée automatiquement** par la clôture d'un plan ; un test vérifie qu'aucun changement d'état de compte n'en découle (RM-18, P3).
5. Le vocabulaire est celui du soutien, non de la sanction (NFR29).

---

### Story 7.3 — Fiche d'entrée et activation d'un stagiaire

*En tant que direction, je veux qu'aucun stagiaire ne soit activé sans cadre défini, afin de ne plus
accueillir de stagiaires sans mission ni objectifs.* — [PRD 3.9]

1. Une fiche d'entrée porte besoin réel, mission, responsable / tuteur, durée, outils et **trois résultats obligatoires** ; ⛔ la soumission avec moins de trois résultats est refusée.
2. L'approbation se fait en **une seule étape** par `direction` ; aucun circuit multi-états n'est implémenté en MVP (C5).
3. ⛔ Un compte `stagiaire` ne peut passer à `actif` sans **fiche d'entrée approuvée**, **tuteur désigné** **et** **trois objectifs enregistrés** ; les trois conditions sont testées **séparément** (FR84, CA-03).
4. Une **checklist d'intégration** est générée à l'activation : contrat ou convention, matériel, accès, règlement intérieur, première tâche, présentation du tuteur.
5. Le point de contrôle du **niveau d'alerte rouge** est posé ici, derrière un service `AlertLevel` retournant `vert` tant que Epic 9 n'est pas livré. Le test bloquant correspondant est écrit en **9.2** — voir § 4.
6. L'activation produit une entrée d'audit.

---

### Story 7.4 — Limite bloquante de stagiaires par tuteur

*En tant que direction, je veux que l'application refuse d'affecter un stagiaire de trop, afin que la
charge d'encadrement ne reprenne pas le temps des exécutants.* — [PRD 3.10]

1. ⛔ L'affectation à un tuteur ayant atteint la limite paramétrée est **refusée côté serveur** ; le message **nomme le tuteur et sa charge actuelle** : « Moussa encadre déjà 3 stagiaires actifs, soit la limite en vigueur. Choisissez un autre tuteur. » (RM-06, CA-04, FR85).
2. ⛔ Avec la valeur initiale de 3, l'affectation d'un **quatrième** stagiaire actif est refusée ; celle du troisième réussit.
3. ⛔ La limite est **lue depuis le paramétrage à chaque contrôle** ; un test la porte à 2 puis vérifie le refus du troisième, **sans redéploiement**.
4. ⛔ Seuls les stagiaires **actifs** comptent ; un stagiaire terminé ou archivé **libère une place**, ce qui est testé.
5. ⛔ Un employé porteur du rôle `tuteur` est soumis **exactement** à la même limite que les associés.
6. L'écran de gestion affiche pour chaque tuteur le nombre de stagiaires encadrés et **signale visuellement et par un libellé** celui qui a atteint la limite (FR93).
7. Le contrôle est protégé contre la concurrence : deux affectations simultanées ne peuvent pas dépasser la limite ; testé sous verrou.

---

### Story 7.5 — Plan de stage, évaluations et sortie

*En tant que tuteur, je veux suivre mon stagiaire sur un cadre écrit du début à la fin, afin que le
stage produise des compétences et une trace, pas seulement une présence.* — [PRD 3.11]

1. Un plan de stage porte compétences à apprendre, objectifs, tâches hebdomadaires, preuves attendues.
2. Une **évaluation hebdomadaire** est enregistrable par le tuteur et **consultable par le stagiaire**.
3. Une **évaluation finale** est enregistrable ; l'application indique si les conditions d'attestation sont remplies, **sans générer de document** en MVP (FR89).
4. Une **checklist de sortie** est générée : livrables remis, matériel rendu, accès fermés, documents sauvegardés, évaluation finale enregistrée.
5. ⛔ Le stagiaire consulte son dossier, le tuteur ceux de ses stagiaires, `direction` tous ; **tout autre accès est refusé**, y compris par URL directe.
6. Aucune évaluation validée n'est modifiable ni supprimable.
7. Le bloc **« Dernière évaluation »** est ajouté au tableau de bord personnel (5.8). **FR166 est dès lors intégralement satisfait.**

---

### Story 7.6 — Créneaux de suivi et regroupement des demandes

*En tant que tuteur, je veux que les demandes non urgentes de mes stagiaires me parviennent groupées,
afin de ne pas être interrompu toute la journée.* — [PRD 3.12]

1. Des **créneaux de suivi** sont paramétrables **par tuteur** (jours et heures).
2. Les demandes non urgentes d'un stagiaire sont accumulées et présentées **au créneau suivant, en une seule notification** (FR91).
3. ⛔ Un blocage marqué **urgent échappe au regroupement** et notifie immédiatement le tuteur ; testé **en comparant les deux chemins**.
4. Le stagiaire **voit à quel moment sa demande sera examinée**, afin de ne pas relancer.
5. ⛔ **Aucune demande n'est perdue ni fusionnée** : chaque demande reste un objet distinct dans la notification groupée ; testé sur cinq demandes.
6. Un tuteur sans créneau configuré reçoit les demandes à l'unité — le regroupement n'est jamais une cause de perte.

---

## ✅ Critères de fin de l'epic 7 — porte du Jalon 3

1. ⛔ **La limite de stagiaires par tuteur est bloquante**, lue du paramétrage, libérée par un départ, identique pour les associés et les employés tuteurs.
2. ⛔ Aucun stagiaire activable sans fiche d'entrée approuvée, tuteur et trois objectifs — les trois conditions testées séparément.
3. ⛔ La revue hebdomadaire enregistre les deux validations horodatées, et s'applique aux comptes `direction`.
4. ⛔ Un plan d'amélioration ne déclenche aucune conséquence automatique sur un compte.
5. Un blocage urgent échappe au regroupement, un blocage ordinaire y entre — les deux chemins prouvés.
6. Aucun écran de revue ou de suivi ne présente de classement entre personnes.
7. Recette Jalon 3 sur téléphone réel ; 11.7 exécutée.

---

# Epic 8 — Finances : comptes, contrats, encaissements, parts, réserve, clôture

**Objectif.** Tracer chaque franc de bout en bout et calculer les parts et la réserve **à
l'encaissement réel**. Le circuit d'approbation existe depuis Epic 4 ; il est ici prolongé jusqu'au
paiement et à l'écriture comptable.

**Dépend de :** Epic 4 (dépenses et approbations), Epic 5 (projets pour l'imputation), Epic 3 (paramètres).
**Jalon 4.** C'est le plus gros epic du plan : un **point de contrôle intermédiaire** est posé après
8.8.

> **À trancher avant d'écrire le modèle de données de cet epic :** CONTRA-01 (base des parts —
> prévisionnel avec régularisation, ou versement à la clôture du contrat), CONTRA-04 (un employé
> apporteur perçoit-il 10 % ?), et DEC-09 / Q6 (quels comptes financiers réels initialiser).

---

### Story 8.1 — Comptes financiers et soldes calculés

*En tant que responsable financier, je veux tenir les comptes caisse, banque et Mobile Money, afin de
connaître à tout moment l'argent réellement disponible.* — [PRD 4.1]

1. Un compte porte type (`caisse`, `banque`, `mobile_money`), libellé, **solde initial en XOF entier**, date du solde initial.
2. ⛔ Le solde affiché est **calculé** depuis le solde initial et les mouvements validés ; **aucune interface ne permet de saisir un solde courant** (FR100).
3. ⛔ Le solde est calculé par agrégation des mouvements validés ; testé sur des mouvements créés par factory, les encaissements réels n'existant qu'en 8.5. **Le test de bout en bout « encaissement de 50 000 moins dépense payée de 20 000 = +30 000 » est en 8.6**, une fois les deux écritures réelles disponibles.
4. ⛔ L'accès est limité à `direction` et `finance` ; l'accès par URL directe depuis tout autre rôle est refusé. ⛔ Un `stagiaire` n'atteint **aucune** donnée financière globale (NFR19).
5. ⛔ Aucune intégration bancaire ou Mobile Money ; un test vérifie qu'**aucun appel externe** n'est émis (FR101).
6. Aucun compte n'est supprimable ; désactivation motivée uniquement.

> **DEC-09 / Q6 en attente.** Aucun seeder n'invente de compte : la liste réelle (caisse, quelle
> banque, Airtel Money, Moov Money) est requise avant de figer les écrans de rapprochement.

---

### Story 8.2 — Charges fixes paramétrables

*En tant que direction, je veux administrer les charges fixes, afin d'ajouter un poste sans demander
une modification de code.* — [PRD 4.2, reste]

1. `FixedChargeSeeder` initialise **exactement quatre postes** : loyer, électricité, Internet, salaires. Aucun autre.
2. Chaque charge porte un montant mensuel et un état `active` / `inactive` ; ⛔ **seules les actives** entrent dans l'assiette d'alerte et dans l'objectif de réserve (FR139).
3. ⛔ L'ajout d'une charge affiche **avant confirmation** l'impact chiffré sur l'objectif de réserve (FR147).
4. ⛔ Aucun poste n'est codé en dur ; un test ajoute une charge et vérifie que **la somme des charges actives change sans redéploiement**. Cette somme *est* l'assiette d'alerte (FR161), mais le **niveau** d'alerte qu'elle produit n'existe qu'en 9.1 : le test prouvant qu'une nouvelle charge modifie le niveau recalculé y est écrit.
5. ⛔ Les **coûts directs de projet n'entrent pas** dans l'assiette des charges fixes ; testé (FR141).
6. Toute création, modification de montant ou changement d'état est auditée.

---

### Story 8.3 — Fiche client et contrat avec répartition

*En tant que responsable financier, je veux enregistrer le client et le contrat avec sa répartition,
afin que le calcul des parts repose sur un cadre écrit et non sur un accord oral.* — [PRD 4.3]

1. Une fiche client porte nom, téléphone, contact optionnel, notes.
2. Un contrat porte client, projet optionnel, **montant total attendu**, **bénéfice prévisionnel**, apporteur (**pouvant être vide**), exécutants, indicateur **« avec exécution »**.
3. ⛔ La répartition prévue est **déduite** de ces champs, jamais saisie : apporteur vide → **100 % PTR Niger** ; apporteur rempli sans exécution → **10 / 90** ; apporteur rempli avec exécution → **10 / 60 / 30**. **Les trois cas sont testés** (RM-12, FR128, FR129).
4. ⛔ Avec plusieurs exécutants, les 30 % sont répartis en parts **strictement égales** ; testé avec deux et trois exécutants. ⛔ La somme des parts est **exactement égale à la base** — le reste entier est attribué de façon déterministe et testée (FR130, NFR22).
5. La répartition affichée **nomme chaque bénéficiaire, son taux et le montant prévisionnel**.
6. ⛔ La part **exécutant (30 %) est réservée aux associés** (RM-15). La part **apporteur (10 %) est ouverte aux employés** (CONTRA-04, résolution provisoire).
7. Aucun prospect, devis ni opportunité n'existe en MVP (FR108).
8. Création et modification produisent une entrée d'audit.

---

### Story 8.4 — Facture minimale et créances

*En tant que responsable financier, je veux enregistrer les factures et voir ce qui reste dû, afin que
les créances cessent d'être suivies de mémoire.* — [PRD 4.4]

1. Une facture porte **numéro unique**, client, contrat, montant, date d'émission, date d'échéance.
2. ⛔ Les quatre statuts `impayee` / `partiellement_payee` / `payee` / `annulee` existent, l'état initial est **`impayee`**, et ⛔ **aucune interface ne permet de les saisir** — ils sont déduits (FR106). **Les transitions vers `partiellement_payee` et `payee` sont testées en 8.5**, où les encaissements qui les déclenchent apparaissent.
3. ⛔ Une créance est déduite **automatiquement** de toute facture non intégralement payée dont l'échéance est atteinte (FR107) ; testé sur une facture `impayee` échue.
4. La liste des créances affiche le montant restant dû et l'**ancienneté en jours**, triable par ancienneté.
5. ⛔ L'annulation exige un motif et **ne supprime jamais** l'enregistrement.
6. Aucune facture PDF, aucune relance automatisée en MVP.
7. Vide positif : « Aucune créance échue. »

---

### Story 8.5 — Encaissements et reçus

*En tant que responsable financier, je veux enregistrer chaque encaissement avec son reçu, afin que
tout argent reçu soit rattaché à un compte et à un client.* — [PRD 4.5]

1. Un encaissement porte client, contrat ou projet, facture optionnelle, montant, date, **compte crédité**, mode de paiement, référence, justificatif.
2. ⛔ Chaque encaissement reçoit un **numéro de reçu unique attribué par le système, non réutilisable même après annulation** ; testé en annulant puis en créant un nouvel encaissement (FR110).
3. ⛔ **Aucune interface ne permet de supprimer un encaissement validé.** Seules la **correction** (nouvelle version motivée) et l'**annulation** (contre-écriture motivée) existent ; les deux sont auditées (FR111, CA-12).
4. ⛔ Le statut de la facture rattachée bascule à `partiellement_payee` puis `payee` selon les encaissements imputés ; les deux transitions sont testées (complète 8.4 AC2).
5. L'application **signale les encaissements créés plus de 24 h** après leur date de réception déclarée (FR112).
6. Cette story livre la **migration minimale `month_closures` et le service `MonthGuard`**, sans aucune interface de clôture : la garde doit exister au moment où naît la première écriture imputable. ⛔ Toute tentative d'imputation à un **mois clôturé** est refusée avec un message nommant le mois : « Le mois de juin 2026 est clôturé. Aucune écriture ne peut y être imputée. » (FR114). Le rapport mensuel, la validation, la clôture et la réouverture sont en **8.13**.
7. L'écriture est atomique : un encaissement interrompu ne laisse ni reçu orphelin ni imputation partielle ; testé.

**Le calcul des parts n'est pas dans cette story.** Il est livré en **8.7**, qui l'intègre au service
d'encaissement dans la même transaction et porte le test de concurrence correspondant. Poser ici
l'exigence reviendrait à dépendre d'un calculateur qui n'existe pas encore.

---

### Story 8.6 — Paiement des dépenses et imputation

*En tant que responsable financier, je veux payer une dépense approuvée et l'imputer, afin que
l'approbation, le paiement et l'écriture comptable restent trois faits distincts.* — [PRD 4.6]

1. ⛔ **Seule une dépense `approuvee` est payable** ; le paiement d'une dépense `demandee` ou `refusee` est refusé côté serveur.
2. Le paiement enregistre compte débité, date, mode de paiement, référence, puis fait passer la dépense à `payee`.
2 bis. ⛔ **Test de bout en bout du solde** : après un encaissement de 50 000 et une dépense payée de 20 000, le solde du compte progresse **exactement** de 30 000 (reporté de 8.1, les deux écritures existant enfin).
3. Un **justificatif de paiement** est attaché après le paiement ; ⛔ une dépense payée sans justificatif apparaît dans une **liste dédiée jusqu'à régularisation** (FR124).
4. Une dépense peut être imputée à un contrat ou à un projet ; cette imputation alimente les **coûts directs** (8.9).
5. Une **demande de remboursement** d'une avance personnelle suit le même circuit à deux signatures et porte le justificatif d'origine (FR125) — c'est la soupape prévue en lieu et place de toute dérogation (CONTRA-03).
6. ⛔ Aucune dépense payée n'est supprimable ; l'annulation après paiement crée une **contre-écriture motivée**.
7. ⛔ Aucune imputation sur un mois clôturé ; testé pour la dépense comme pour l'encaissement.

**Migrations :** colonnes de paiement et d'imputation **ajoutées** à `expenses` par nouvelle
migration — la migration de 4.4 n'est pas modifiée (architecture § 5.2, SOC-04).

---

### Story 8.7 — Calcul des parts au prorata des encaissements

*En tant qu'associé propriétaire, je veux que les parts se calculent seules au rythme des paiements du
client, afin que l'entreprise ne distribue jamais un argent qu'elle n'a pas reçu.* — [PRD 4.7]

1. ⛔ Chaque encaissement imputé calcule les parts **au prorata du montant encaissé rapporté au montant total attendu**, appliqué au bénéfice retenu (RM-13, FR131).
2. ⛔ **Cas de référence testé** : bénéfice 1 000 000 payé en deux fois à 50 % → apporteur 50 000 puis 50 000 ; exécutants 150 000 puis 150 000 ; PTR Niger 300 000 puis 300 000.
3. ⛔ Un contrat **facturé et non encaissé génère zéro part** ; testé explicitement (RM-13, FR132).
4. ⛔ Un contrat encaissé à moitié puis abandonné n'a généré que **la moitié** des parts ; testé.
5. L'écran du contrat affiche en permanence : montant total attendu, total encaissé, bénéfice retenu, parts déjà versées par bénéficiaire, **parts restant à verser** (FR133).
6. ⛔ Le calcul est affiché avec sa **méthode** : bénéfice retenu, période, encaissement d'origine, taux appliqué, montant. **Un calcul opaque est un défaut** (FR135).
7. ⛔ Les parts **restent dues et calculées en niveau d'alerte rouge** ; testé en 9.2 (RM-14, FR165).
8. `ShareCalculator` prend la **base de calcul en paramètre**, afin qu'un renversement de CONTRA-01 ne modifie pas le schéma.
9. Le calcul est **intégré au service d'encaissement de 8.5**, exécuté **dans la même transaction** que l'enregistrement de l'encaissement (FR113) : un calcul de parts qui survivrait à un encaissement annulé serait un défaut.
10. ⛔ L'intégration est protégée par verrou : deux encaissements simultanés sur le même contrat **ne produisent pas de parts en double** ; testé sous concurrence (reporté de 8.5, où le calculateur n'existait pas encore).

---

### Story 8.8 — Versement d'une part par le circuit de dépense

*En tant qu'associé propriétaire, je veux que ma propre part passe par le circuit d'approbation
ordinaire, afin qu'aucune porte dérobée n'existe pour les associés.* — [PRD 4.8]

1. Un versement de part est enregistré comme une **dépense ordinaire**, avec bénéficiaire, contrat d'origine, base de calcul, taux appliqué et justificatif (FR134).
2. ⛔ Il exige les **deux approbations `direction` distinctes**, y compris lorsque le bénéficiaire est un associé.
3. ⛔ Un associé bénéficiaire **ne peut pas être approbateur de sa propre part** ; testé.
4. La dépense de versement apparaît au journal d'audit et dans le rapport financier mensuel.
5. ⛔ Un bénéficiaire **non-associé** consulte **sa propre part uniquement** — montant, base, taux, contrat d'origine. Toute autre ligne de répartition lui est refusée, **y compris par URL directe** ; testé (FR136, CONTRA-05).
6. Un retrait d'argent par un associé sur la part de 60 % est une **dépense ordinaire**, sans mécanisme particulier (RM-20).

> **▸ Point de contrôle intermédiaire de l'epic 8.** À l'issue de 8.8, la chaîne
> client → contrat → facture → encaissement → parts → versement est complète et démontrable de bout
> en bout. Les stories 8.9 à 8.13 ajoutent le pilotage. Si la vélocité l'impose, l'epic peut être
> scindé ici sans réordonnancement.

---

### Story 8.9 — Coûts directs, bénéfice réalisé et régularisation

*En tant que direction, je veux comparer le bénéfice prévu et le bénéfice réellement réalisé, afin que
la base des parts cesse d'être une estimation permanente.* — [PRD 4.9, CONTRA-01]

1. La somme des dépenses imputées à un contrat constitue ses **coûts directs**.
2. ⛔ Le **bénéfice réalisé** = Σ encaissements imputés − Σ dépenses imputées, affiché **à côté** du bénéfice prévisionnel (D-01).
3. L'écart prévu / réalisé est calculé et affiché en **montant et en pourcentage**.
4. À la clôture d'un contrat, l'application **propose** une régularisation chiffrée lorsque l'écart est non nul — dépense complémentaire ou titre de reversement — soumise au circuit à deux signatures.
5. ⛔ **Aucune régularisation n'est appliquée automatiquement** : le montant est proposé, la décision reste humaine (P3).
6. ⛔ Les coûts directs de projet n'entrent pas dans l'assiette des charges fixes ; testé (FR141).

---

### Story 8.10 — Budgets mensuels et comparaison au réalisé

*En tant que direction, je veux fixer un budget par catégorie et voir l'écart, afin de constater un
dérapage pendant le mois et non après.* — [PRD 4.10]

1. Un budget mensuel est saisissable par catégorie de dépense.
2. La comparaison budget / réalisé est affichée par catégorie et par mois, en montant **et** en pourcentage.
3. Le dépassement est signalé visuellement **et par un libellé**, jamais par la couleur seule.
4. ⛔ L'absence de budget sur une catégorie **ne bloque aucune dépense** — la double approbation reste le seul contrôle bloquant (RM-09).
5. Vide : « Aucun budget défini pour juillet. Les dépenses restent possibles. »

---

### Story 8.11 — Réserve : objectif, alimentation et utilisation

*En tant que direction, je veux savoir combien de mois de charges la réserve couvre, afin de disposer
du temps de réagir avant la rupture de trésorerie.* — [PRD 4.11]

1. ⛔ Objectif de réserve = **nombre de mois paramétré × somme des charges fixes actives** ; recalculé à chaque modification du paramétrage (FR142).
2. ⛔ Tant que l'objectif n'est pas atteint, chaque encaissement imputé affecte **20 % du bénéfice correspondant** à la réserve, **prélevés sur la part de 60 % de PTR Niger** (RM-11, FR143).
3. ⛔ **Test de référence** : sur un bénéfice de 1 000 000, la réserve reçoit 200 000, l'apporteur 100 000, les exécutants 300 000, et il reste **400 000** de fonctionnement.
4. ⛔ Les parts de **10 % et 30 % ne sont jamais entamées** par le prélèvement de réserve ; testé (RM-14).
5. ⛔ Le prélèvement **s'interrompt automatiquement** à l'atteinte de l'objectif et **reprend automatiquement** si la réserve repasse en dessous ; **les deux bascules sont testées** (FR144).
6. Le montant de la réserve et le **nombre de mois couverts** sont affichés en permanence, avec la **méthode de calcul et la date des données source** (FR145).
7. ⛔ Toute **utilisation** de la réserve exige **motif + double approbation `direction` + plan de reconstitution enregistré** ; l'absence de l'un des trois **bloque** l'opération ; les trois cas sont testés séparément (FR146).
8. L'ajout d'une charge fixe augmente l'objectif et peut relancer le prélèvement ; **l'impact est affiché avant confirmation**.

---

### Story 8.12 — Rapprochement hebdomadaire

*En tant que responsable financier, je veux comparer chaque semaine l'argent physique et les
écritures, afin qu'un écart soit expliqué pendant qu'on s'en souvient encore.* — [PRD 4.12]

1. Un rapprochement compare, pour chaque compte, le **solde physique constaté saisi** et le **solde issu des écritures**.
2. ⛔ L'écart est calculé et affiché **systématiquement, y compris lorsqu'il vaut zéro** (FR149, CA-13).
3. ⛔ Un écart non nul exige **explication, responsable et action corrective** avant validation ; la validation sans explication est refusée.
4. ⛔ Le **préparateur et le contrôleur sont deux comptes distincts** ; la validation par un compte unique jouant les deux rôles est refusée côté serveur, **y compris s'il détient les deux permissions** (RM-16, FR151).
5. ⛔ Un rapprochement validé **n'est pas modifiable** ; une correction crée un **nouveau rapprochement rattaché au précédent**, avec motif (FR152).
6. Chaque validation produit une entrée d'audit **nommant préparateur et contrôleur**.
7. La saisie du solde physique est faisable au téléphone, depuis la caisse, sans ordinateur.

---

### Story 8.13 — Rapport financier mensuel, validation et clôture

*En tant que direction, je veux un rapport mensuel validé avant le 5, afin de décider sur des chiffres
arrêtés plutôt que sur une impression.* — [PRD 4.13]

1. ⛔ Le rapport présente les **douze lignes** de FR153, **dans cet ordre** : CA facturé, encaissements reçus, créances clients, coûts directs des projets, salaires et rémunérations, charges fixes, taxes et charges sociales, dettes, trésorerie totale, résultat estimé, réserve disponible, mois de charges couverts.
2. Chaque ligne affiche la **période source et la méthode d'obtention** du montant (FR154).
3. ⛔ Une ligne sans donnée applicable affiche `0` avec la mention **« poste non applicable à ce jour »** et **n'est jamais masquée** ; testé sur « taxes et charges sociales » (FR155, CONTRA-06).
4. ⛔ Le **préparateur et le contrôleur sont deux comptes distincts** ; la **validation finale appartient à `direction`** (FR156, RM-16).
5. L'application **notifie à l'approche du 5 du mois suivant** et signale un dépassement (FR157).
6. ⛔ Après validation, le mois est **clôturé** : toute écriture imputée à ce mois est refusée côté serveur ; testé **pour un encaissement et pour une dépense** (FR158).
7. ⛔ La **réouverture** exige une autorisation `direction` **avec motif**, produit une entrée d'audit, et **marque comme telle** toute écriture postérieure (FR159).
8. ⛔ Cette story crée le **calculateur pur du niveau d'alerte** — assiette = somme des charges actives, comparaison aux encaissements du mois, séquence de deux mois — et la validation **fige le niveau du mois clôturé** (FR160). Un mois clôturé ne peut pas voir son niveau changer rétroactivement. **9.1 réutilise ce calculateur** et y ajoute le recalcul planifié, l'affichage courant et les trois niveaux.
9. Le rapport reste consultable sur téléphone : les douze lignes s'empilent en cartes plutôt qu'en tableau à défilement horizontal.

---

## ✅ Critères de fin de l'epic 8

1. ⛔ **Les huit tests bloquants financiers passent** : suppression financière impossible (modèle, route **et base**), aucune écriture sur mois clôturé, préparateur ≠ contrôleur sur rapprochement **et** rapport mensuel, somme des parts exactement égale à la base, parts nulles sans encaissement, réserve prélevée sur les 60 % seulement, deux approbations sur un versement de part, réserve utilisable seulement avec motif + double approbation + plan.
2. ⛔ Le cas de référence des parts (1 000 000 en deux versements) et celui de la réserve (200 000 / 100 000 / 300 000 / 400 000) passent au franc près.
3. Un solde de compte n'est jamais saisi, toujours calculé — prouvé par le test des 50 000 / 20 000.
4. Le parcours Playwright « encaissement → calcul des parts → réserve » est vert.
5. `ptr:check-invariants` détecte **dans les données** toute dépense `payee` sans deux approbations distinctes.
6. Campagne d'autorisation étendue à toutes les ressources financières ; ⛔ un `stagiaire` n'atteint aucune donnée financière globale.
7. CONTRA-01 et CONTRA-04 ont été tranchées par la direction avant l'écriture du modèle.

---

# Epic 9 — Alertes, tableaux de bord et notifications

**Objectif.** Faire en sorte que l'alerte de trésorerie se déclenche **seule**, sans dépendre de la
vigilance de quiconque, et réunir le travail et l'argent sur un seul écran.

**Dépend de :** Epic 8 (toutes les données financières). **Jalon 4.**

---

### Story 9.1 — Niveau d'alerte vert, orange et rouge

*En tant que direction, je veux être avertie automatiquement avant la séquence qui a déjà fermé
l'entreprise, afin que le mécanisme ne dépende de la vigilance de personne.* — [PRD 4.14]

Cette story **réutilise le calculateur pur créé en 8.13** — elle ne le réécrit pas — et lui ajoute le
recalcul planifié, l'affichage courant et les effets visibles.

1. ⛔ L'assiette d'alerte est la **somme des charges fixes actives du paramétrage** ; **aucune liste codée en dur** (FR161).
2. ⛔ **Vert** : encaissements du mois ≥ assiette. **Orange** : un mois sous l'assiette. **Rouge** : **deux mois consécutifs** sous l'assiette. Les trois cas sont testés sur des jeux de données dédiés (FR162 à FR164, CA-15).
3. ⛔ L'ajout d'une charge fixe modifie l'assiette et **change effectivement le niveau au recalcul suivant** ; testé de bout en bout (complète 8.2 AC4, FR147).
4. Le niveau est affiché en permanence sur le tableau de bord direction, **avec libellé textuel en plus de la couleur** (NFR31).
5. Le recalcul est une tâche planifiée **idempotente** ; le niveau figé à la clôture mensuelle (8.13) fait foi pour le mois clos et n'est jamais réécrit.
6. Le calcul affiche sa **méthode et la date des données source**.
7. La tâche planifiée de recalcul est **ajoutée au registre d'ordonnancement** de `docs/ops/` et à la supervision de 11.4.

---

### Story 9.2 — Effets du niveau d'alerte

*En tant que direction, je veux que l'alerte produise des effets précis et bornés, afin qu'elle
protège la trésorerie sans paralyser l'entreprise ni punir personne.* — [PRD 4.14, C9]

1. ⛔ En **rouge**, l'activation de **tout nouveau compte employé ou stagiaire est refusée** côté serveur, avec un message **nommant le niveau d'alerte** (FR164). **Ferme la dépendance avant de 7.3 AC5.**
2. ⛔ En **rouge**, l'approbation d'une dépense de catégorie **non marquée « essentielle »** affiche un **avertissement explicite mais n'est pas bloquée** (C9, FR164).
3. ⛔ En **rouge**, le **calcul et le versement des parts de 10 % et 30 % restent possibles** ; un test place l'entreprise en rouge et vérifie que calcul **et** paiement demeurent (RM-14, FR165, CONTRA-07).
4. ⛔ En **orange**, l'application demande l'enregistrement d'un **plan correctif sous 48 heures** et **notifie `direction` jusqu'à ce qu'il existe** (FR163).
5. ⛔ **Aucune sanction, rupture ni blocage de personne n'est déclenché** par un niveau d'alerte. Le système peut bloquer une écriture, **jamais une personne** ; testé (RM-18, P3).
6. Chaque effet déclenché produit une entrée d'audit nommant le niveau et l'objet concerné.

---

### Story 9.3 — Plan correctif en niveau orange

*En tant que direction, je veux enregistrer le plan correctif que l'alerte réclame, afin que l'alerte
ait une suite et pas seulement un signal.* — [PRD 4.14 AC3]

1. Un plan correctif porte constat, actions, responsables, échéances, résultat attendu.
2. Il est rattaché au mois qui a déclenché l'orange et reste consultable ensuite.
3. La relance de `direction` cesse dès l'enregistrement du plan ; testé.
4. Le plan n'est ni supprimable ni modifiable après validation ; une révision crée une nouvelle version.
5. Création et validation produisent une entrée d'audit.

---

### Story 9.4 — Tableau de bord financier

*En tant que responsable financier, je veux un écran unique sur l'état de l'argent, afin de préparer
rapprochements et rapports sans reconstituer les chiffres.* — [PRD 4.15]

1. L'écran affiche : soldes par compte, dépenses en attente, encaissements du mois, créances échues, écarts de rapprochement, budget contre réalisé, réserve disponible.
2. Il affiche le **total des engagements de parts restant à verser** sur les contrats en cours (FR171).
3. Chaque bloc est cliquable vers la liste détaillée correspondante.
4. ⛔ L'écran est accessible à `finance` et `direction` **uniquement** ; l'accès par URL directe depuis tout autre rôle est refusé.
5. ⛔ **Aucun bloc contenant une donnée non autorisée n'est rendu, même vide** (FR172).
6. Consultable sur téléphone, blocs empilés, sans défilement horizontal.

---

### Story 9.5 — Tableau de bord direction consolidé

*En tant que direction, je veux le travail et l'argent sur le même écran, afin de décider vite, avec
trace, sans réunion supplémentaire.* — [PRD 4.16]

1. L'écran affiche : membres sans objectif, rapports du jour envoyés / manquants, objectifs verts / orange / rouges / bloqués, projets en retard, stagiaires par tuteur, encaissements du mois, charges du mois, solde disponible, créances, réserve et mois couverts, **niveau d'alerte**.
2. ⛔ Le bloc **« En attente de mon approbation » reste en première position** (FR167).
3. ⛔ Tout tuteur ayant atteint la limite de stagiaires actifs est signalé **visuellement et par un libellé** (FR170).
4. ⛔ « Membres sans objectif » **inclut les comptes `direction` eux-mêmes** (P5).
5. ⛔ « Rapports manquants » **exclut** absences approuvées et jours non travaillés.
6. ⛔ Consultable à **320 px sans défilement horizontal**, blocs empilés ; premier rendu utile **sous 3 secondes** en 3G dégradée.
7. Le nombre de requêtes base par rendu est plafonné et testé — c'est l'écran le plus dense de l'application.

---

### Story 9.6 — Notifications métier complètes et rappels planifiés

*En tant qu'utilisateur, je veux que chaque événement qui m'attend produise une notification utile,
afin de ne rien découvrir en retard.* — [FR31, reste]

1. Les onze événements de FR31 notifient : rapport bientôt en retard, rapport en retard, objectif proche de l'échéance, commentaire ou correction demandée, blocage affecté, dépense à approuver, rapprochement ou rapport financier à préparer, document interne à accepter, fin de contrat ou de stage proche.
1 bis. La notification de **fin de contrat ou de stage proche** consomme le service exposé en **3.2 AC4**, qui n'émettait rien faute de centre de notifications à ce jalon. ⛔ Un test vérifie que l'échéance détectée en 3.2 produit bien une notification ici.
2. ⛔ Chaque notification permet d'atteindre l'action attendue en **au plus 3 interactions** ; mesuré pour les trois plus fréquentes.
3. Les tâches planifiées d'émission sont **idempotentes** : un test rejoue la tâche et vérifie qu'aucune notification n'est dupliquée.
4. ⛔ **Aucun canal externe n'est appelé** ; testé à nouveau en fin de MVP (FR34).
5. Une notification dont l'objet a été supprimé du périmètre de l'utilisateur n'expose pas son contenu ; l'accès est refusé proprement.
6. La file d'attente est supervisée (11.3) : un travail échoué est visible et alerte.

---

## ✅ Critères de fin de l'epic 9

1. ⛔ Les trois niveaux d'alerte sont calculés correctement sur jeux de données dédiés, et l'assiette provient **exclusivement** du paramétrage.
2. ⛔ Les quatre effets du rouge et de l'orange sont prouvés — dont le fait que **les parts restent payables en rouge** et qu'**aucune personne n'est jamais bloquée**.
3. La dépendance avant de 7.3 est fermée : l'activation d'un compte en rouge est refusée, testée.
4. Le tableau de bord direction se rend **sous 3 secondes en 3G** et reste lisible à 320 px.
5. Aucun bloc de tableau de bord non autorisé n'est rendu, même vide.
6. Toutes les notifications de FR31 existent, idempotentes, sans canal externe.

---

# Epic 10 — Recherche, exports et qualité finale

**Objectif.** Rendre les données retrouvables et exportables **strictement dans la limite des droits
de chacun**, puis prononcer la qualité du MVP sur des mesures et non des impressions.

**Dépend de :** Epic 9. **Jalon 4 — clôture du MVP.**

---

### Story 10.1 — Recherche transverse

*En tant qu'utilisateur autorisé, je veux retrouver une personne, un projet ou un objectif rapidement,
afin de ne pas naviguer d'écran en écran.* — [FR173]

1. La recherche couvre personne, projet, objectif, période et statut.
2. ⛔ Elle n'expose **que** les objets que le demandeur a le droit de voir ; un test compare les résultats de six rôles sur le même jeu de données.
3. Un résultat interdit n'apparaît **ni en titre, ni en extrait, ni en compteur** — un compteur qui révèle l'existence d'un objet caché est une fuite.
4. La recherche répond sous 1 seconde sur le volume attendu à 100 utilisateurs.
5. Vide : « Aucun résultat pour « xyz ». » distinct de l'état initial.
6. Utilisable au pouce sur téléphone, champ atteignable depuis toute page.

---

### Story 10.2 — Listes filtrables, triables et filtres enregistrés

*En tant qu'utilisateur autorisé, je veux filtrer et trier les listes principales, afin de préparer une
vérification sans extraction manuelle.* — [FR174]

1. Les listes principales sont filtrables et triables.
2. `direction` peut **enregistrer un filtre** pour réutilisation ; le filtre enregistré est privé à son auteur.
3. ⛔ Un filtre ne contourne jamais une restriction de permission ; testé par manipulation directe des paramètres d'URL.
4. Vide par filtre distinct du vide réel, avec **Réinitialiser les filtres**.
5. Les filtres actifs sont visibles en permanence et retirables un par un.
6. Sur téléphone, les filtres sont dans un panneau escamotable qui ne masque pas les résultats une fois appliqué.

---

### Story 10.3 — Export CSV sous permissions, audité

*En tant qu'utilisateur autorisé, je veux exporter mes données dans la limite de mes droits, afin de
préparer un contrôle.* — [PRD 4.17]

1. Les listes principales sont exportables en **CSV**. ⛔ Aucun export PDF ni Excel en MVP (FR108, FR175).
2. ⛔ L'export applique **exactement** les mêmes restrictions de permission que l'écran d'origine ; un test **compare le contenu exporté au contenu affiché pour chaque rôle** (PERM-06).
3. ⛔ Un utilisateur ne peut pas exporter, **par manipulation de paramètres d'URL**, des lignes qu'il ne voit pas à l'écran ; testé explicitement.
4. ⛔ Tout export produit une entrée d'audit avec **auteur, nature des données et nombre de lignes** (FR176).
5. L'export d'un gros volume passe en file d'attente et notifie à la disponibilité, plutôt que de faire expirer la requête.
6. Le fichier est encodé pour être lisible sans manipulation dans un tableur en français (séparateur et encodage explicites).

---

### Story 10.4 — Invariants et campagne d'autorisation complète

*En tant que direction, je veux une vérification automatique qui détecte une dérive dans les données
elles-mêmes, afin qu'une manipulation en base ou une régression ne passe pas inaperçue.*

1. Cette story **complète la commande créée en 2.3** et enrichie en 4.5 et 11.1 ; elle ne la crée pas. Elle porte sa version finale, son exécution **quotidienne** planifiée et son alerte.
2. La commande vérifie l'ensemble cumulé : `APP_DEBUG=false` et `APP_ENV=production` ; **exactement 2 comptes** porteurs de `depense.approuver` ; aucun `super_admin` porteur d'une permission métier ; déclencheurs d'immuabilité présents sur `audit_logs` ; utilisateur applicatif dépourvu de `DELETE` sur `audit_logs` ; dernière sauvegarde de moins de 26 h.
3. ⛔ Il vérifie **dans les données** qu'aucune dépense `payee` n'existe sans deux approbations distinctes. C'est le point le plus important : il détecte une manipulation en base ou une régression **déjà passée en production**.
4. La **campagne d'autorisation complète** (2.9) couvre l'intégralité des ressources du MVP, tous rôles × toutes ressources protégées ; ⛔ elle est verte, et aucune route protégée n'est absente de la matrice.
5. Les quatorze règles métier bloquantes de l'architecture § 23.2 disposent chacune d'un test nommé ; ⛔ **l'absence d'un seul de ces tests bloque la porte de qualité**.
6. Un échec d'invariant alerte l'exploitant et est consigné.

---

### Story 10.5 — Recette de performance et d'accessibilité

*En tant que direction, je veux que les exigences non fonctionnelles soient mesurées et non
supposées, afin que « ça marche sur mon téléphone » cesse d'être une opinion.*

1. ⛔ **NFR1** : chaque page du parcours quotidien atteint son premier rendu utile **sous 3 secondes** à 400 kbit/s et 400 ms de latence. Mesuré et consigné page par page.
2. ⛔ **NFR2** : poids transféré ≤ **300 Ko** au premier chargement et ≤ **80 Ko** ensuite, hors pièces jointes. Vérifié en CI et en recette.
3. ⛔ **NFR3** : aucune ressource tierce chargée à l'exécution ; vérifié par inspection du trafic réseau réel.
4. ⛔ **NFR4** : saisie du rapport quotidien **sous 3 minutes** sur téléphone réel — mesure rejouée en fin de MVP.
5. ⛔ **NFR7 / NFR8** : aucun défilement horizontal à 320 px sur l'ensemble des écrans ; toutes les cibles tactiles ≥ 44 × 44 px. Vérifié écran par écran.
6. ⛔ **WCAG 2.1 AA** : contraste, libellés associés, navigation clavier complète. Vérifié à l'outil **et** au lecteur d'écran sur les cinq parcours critiques.
7. ⛔ **NFR31** : aucune information portée par la couleur seule ; vérifié en niveaux de gris sur tous les écrans porteurs d'un code couleur.
8. Compatibilité vérifiée sur Chrome Android (priorité 1), Chrome desktop, Safari courant et n-1 (NFR9).
9. ⛔ **NFR27 — capacité.** L'application est exercée sur un jeu représentatif de **100 utilisateurs actifs** avec un volume de données correspondant à une année d'exploitation, **sans ajout de composant ni changement de topologie** : même VPS, même base, même Redis. Sont mesurés et consignés : temps de réponse du tableau de bord direction et du rapport quotidien, nombre de requêtes base par rendu, occupation disque et mémoire.
   *La charge simultanée à simuler reste **à confirmer** — 100 comptes ne signifient pas 100 sessions concurrentes. À défaut d'arbitrage, l'hypothèse retenue et testée est consignée explicitement plutôt que laissée implicite.*
10. Les écarts constatés sont consignés avec leur décision — corrigé, accepté, reporté — jamais laissés implicites.

---

## ✅ Critères de fin de l'epic 10 — porte du MVP

1. ⛔ Un export ne révèle **jamais** une ligne que son auteur ne voit pas à l'écran, y compris par manipulation d'URL, et **tout export est audité**.
2. ⛔ Les **quatorze règles métier bloquantes** disposent chacune d'un test nommé qui passe.
3. ⛔ La campagne d'autorisation est complète et verte ; aucune route protégée n'échappe à la matrice.
4. ⛔ `ptr:check-invariants` passe, **détection de dérive dans les données comprise**.
5. ⛔ Les mesures NFR1, NFR2, NFR4, NFR7, NFR8 et WCAG AA sont **consignées avec leur valeur**, pas déclarées conformes.
6. Les **18 critères d'acceptation du brief** (PRD § 12) passent tous.

---

# Epic 11 — Exploitation, sauvegarde, supervision et mise en service

**Objectif.** Faire en sorte que l'application survive à un incident et que sa mise en production soit
une décision humaine réversible. Cet epic ne livre aucune fonctionnalité visible ; il livre la
condition pour que les autres puissent exister en production.

**Dépend de :** Epic 1. **Les stories 11.1 à 11.6 sont un prérequis de la première mise en production**, à la fin du
Jalon 1. **La story 11.7 est rejouée à chaque jalon.**

---

### Story 11.1 — Sauvegarde quotidienne chiffrée hors site

*En tant que direction, je veux que les données de l'entreprise survivent à la perte du serveur, afin
qu'un incident matériel ne referme pas l'entreprise une seconde fois.* — [NFR24]

1. `spatie/laravel-backup` s'exécute par tâche planifiée à **02 h 00 heure de Niamey**.
2. La sauvegarde comprend un `mysqldump` **`--single-transaction`** (cohérent sans verrouiller l'application) **et** l'archive de `storage/app/private`.
3. L'archive est **chiffrée par mot de passe**, la clé étant conservée **hors du serveur**.
4. Elle est envoyée vers un **stockage objet hors site** ; une copie locale est conservée 48 h pour restauration rapide.
5. **Deux horizons distincts, à ne pas confondre.** *Rotation des archives* : 7 quotidiennes, 4 hebdomadaires, 12 mensuelles — c'est la profondeur de restauration. *Conservation métier* : les **données du personnel et les justificatifs financiers sont conservés au moins dix ans** (NFR26), ce que la rotation des sauvegardes ne garantit pas à elle seule. Le dispositif de conservation longue est documenté et son coût disque chiffré, **sous réserve de DEC-11**.
6. ⛔ **`backup:monitor` alerte quand la dernière sauvegarde est trop ancienne.** Une sauvegarde qui cesse silencieusement est le mode de défaillance normal de ce dispositif — c'est l'absence, pas l'échec, qu'il faut surveiller.
7. `.env` est sauvegardé **séparément et manuellement**, hors du dispositif automatique : il contient les secrets et ne doit pas voyager avec les données.
8. Redis, `storage/logs`, `node_modules` et `vendor` ne sont pas sauvegardés — reconstructibles.
9. **RPO ≤ 24 h** ; l'objectif est documenté et vérifié.
10. `/up` est **étendu** pour exposer l'âge de la dernière sauvegarde (reporté de 1.1, la sauvegarde n'existant pas avant cette story).
11. ⛔ `ptr:check-invariants` (2.3, étendue en 4.5) reçoit l'invariant **« dernière sauvegarde de moins de 26 h »**.
12. Les **valeurs** des secrets du stockage de sauvegarde sont renseignées dans les emplacements préparés en 1.5, une fois DEC-06 arbitré.

> **DEC-06 — décision de direction, non technique.** Les sauvegardes contiennent des données de
> personnel et des justificatifs financiers, et **sortiront du Niger** vers l'hébergeur retenu
> (Backblaze B2, Scaleway, OVH…). Le chiffrement côté application s'applique quel qu'en soit le
> destinataire, mais le choix du pays d'hébergement vous appartient.

---

### Story 11.2 — Test de restauration automatisé et journal

*En tant que direction, je veux la preuve datée que la sauvegarde se restaure, afin que la sauvegarde
cesse d'être une intention.* — [NFR25]

1. `php artisan ptr:test-restore` s'exécute **mensuellement** par tâche planifiée.
2. Il récupère la dernière sauvegarde **hors site** et la restaure dans une **base jetable**.
3. Les **invariants vérifiés sont cumulatifs** et suivent les tables réellement présentes — ⛔ aucun accès n'est tenté sur une table absente, sous peine d'un test de restauration qui échoue pour la mauvaise raison :

   | Invariant | Disponible à partir de |
   |---|---|
   | Nombre de lignes d'audit, présence des déclencheurs d'immuabilité | Jalon 1 (1.4) |
   | Dernier utilisateur créé, nombre de comptes actifs | Jalon 1 (2.1) |
   | Nombre de dépenses et d'approbations | Jalon 1 (4.4) |
   | Somme des encaissements, soldes de comptes | **Jalon 4 (8.5)** |

4. ⛔ Le résultat daté est écrit dans un **registre persistant du répertoire partagé** (`shared/ops/restore-log.md`), **jamais dans le `docs/` d'une release** : les releases sont atomiques par lien symbolique, et un journal écrit à l'intérieur serait **effacé au déploiement suivant**. La procédure elle-même est versionnée dans `docs/ops/restore-procedure.md` ; le registre des exécutions, non. NFR25 exige la conservation des deux.
5. Il alerte en cas d'échec.
6. Une **restauration complète manuelle en préproduction est exécutée et chronométrée avant chaque mise en service de jalon**, pour valider le **RTO de 4 h** avec un opérateur humain dans la boucle.
7. Un test vérifie que la commande **échoue bruyamment** si la sauvegarde est corrompue ou illisible — un test de restauration qui réussit toujours ne prouve rien.

---

### Story 11.3 — Supervision et alertes

*En tant qu'exploitant, je veux être averti d'une panne avant les utilisateurs, afin qu'une
interruption ne se découvre pas par un appel téléphonique.*

1. Surveillance externe (UptimeRobot ou équivalent) sur `/up`, avec **alerte SMS** — le canal externe est ici légitime : il concerne l'exploitation, pas les notifications applicatives interdites par FR34.
2. `queue:monitor` alerte sur les travaux échoués et sur une file qui s'allonge anormalement.
3. `backup:monitor` alerte sur l'absence de sauvegarde récente (11.1).
4. `DB::whenQueryingForLongerThan(500ms)` écrit dans un journal d'alerte ; les requêtes lentes sont revues à chaque jalon.
5. Le **renouvellement du certificat Let's Encrypt est surveillé** — un renouvellement silencieusement cassé se découvre le jour de l'expiration.
6. Les erreurs applicatives sont suivies selon DEC-07 ; les journaux techniques sont en **JSON**, canal `daily`, rétention 30 jours.
7. ⛔ Aucun secret, jeton ni donnée personnelle n'apparaît dans les journaux ni dans les alertes ; testé (NFR12, NFR17).

> **DEC-07 en attente.** Sentry auto-hébergé (diagnostic nettement plus rapide pour un développeur
> unique, mais des extraits d'erreur partent chez un tiers si l'instance est hébergée ailleurs) ou
> journaux fichiers seuls avec alerte courriel sur exception `500`.

---

### Story 11.4 — Ordonnanceur et files d'attente en production

*En tant qu'exploitant, je veux que les tâches planifiées et les files tournent de façon fiable, afin
qu'un rappel manqué ne soit pas un incident silencieux.*

1. `cron` déclenche `schedule:run` à la minute ; Supervisor maintient `queue:work` avec redémarrage automatique.
2. Un travail échoué est conservé, visible et rejouable ; il n'est jamais perdu silencieusement.
3. `queue:restart` est appelé à chaque déploiement — un worker qui tourne encore sur l'ancien code est un mode de défaillance classique.
4. Les tâches planifiées sont **idempotentes** : rejouer une tâche ne duplique ni notification ni écriture. **Au Jalon 1, seules trois tâches existent et sont testées** : sauvegarde quotidienne, `ptr:check-invariants`, relances de dépense J+1 / J+2. Le **rappel de rapport quotidien est ajouté et testé en 6.2**, le **recalcul d'alerte financière en 9.1**.
5. Le registre des tâches critiques de `docs/ops/` reflète le **périmètre réellement livré à chaque jalon**, pas le périmètre cible : un registre qui annonce une tâche inexistante rend la supervision inexploitable.
6. Une tâche qui ne s'est pas exécutée à l'heure attendue **alerte** — c'est l'absence, non l'échec, qui est dangereuse ici aussi.

---

### Story 11.5 — Anonymisation et alimentation de la préproduction

*En tant qu'exploitant, je veux une préproduction alimentée par des données réelles anonymisées, afin
que le test de restauration soit une vérification continue plutôt qu'une théorie.*

1. `php artisan ptr:anonymize` remplace noms, téléphones et pièces jointes par des valeurs factices.
2. ⛔ La commande **refuse de s'exécuter en production** ; testé.
3. **Deux régimes d'alimentation, selon qu'une production existe ou non** — avant la première mise en service, il n'y a aucune sauvegarde de production à restaurer :

   | Moment | Source de la préproduction |
   |---|---|
   | **Amorçage, avant la première production** | Jeu de données de recette **construit par factories**, sans aucune donnée personnelle réelle |
   | **À partir du Jalon 1 en service** | **Restauration de la sauvegarde de production, anonymisée** — la préproduction devient alors la vérification permanente de la sauvegarde |

   Le basculement d'un régime à l'autre est explicite et documenté ; il ne se déduit pas.
4. Aucune donnée personnelle réelle ne subsiste après anonymisation ; un test parcourt les colonnes sensibles et échoue s'il en trouve.
5. La préproduction ne reçoit **aucune sauvegarde** et n'envoie aucune notification externe.
6. La procédure d'alimentation est documentée dans `docs/ops/`.

---

### Story 11.6 — Déploiement en production et retour arrière

*En tant qu'exploitant, je veux un déploiement réversible et une mise en production décidée par un
humain, afin qu'une régression se corrige en secondes.*

1. Releases **atomiques par lien symbolique** ; le retour arrière consiste à repointer le lien et est **testé au moins une fois** avant la première mise en production réelle.
2. Séquence : récupération du code → `composer install --no-dev -o` → `npm ci && npm run build` → lien de `shared` → `migrate --force` **sous l'utilisateur privilégié** → `config:cache route:cache view:cache event:cache` → bascule du lien → `php-fpm reload` → `queue:restart`.
3. `php artisan down` **n'est pas utilisé pour un déploiement ordinaire** ; réservé aux migrations lourdes, avec `--render` pour afficher une page française plutôt qu'une erreur brute.
4. Sur fusion dans `main` : déploiement automatique en **préproduction**, puis `ptr:check-invariants` sur préproduction.
5. ⛔ **La mise en production exige une approbation manuelle explicite.** Sur une application qui porte la comptabilité de l'entreprise et dont les données ne se suppriment pas, le déploiement continu jusqu'en production serait une erreur de jugement.
6. Après déploiement : vérification de `/up` et des invariants ; **retour arrière automatique en cas d'échec**.
7. La procédure et le contact d'astreinte sont documentés dans `docs/ops/`.

---

### Story 11.7 — Recette de mise en service d'un jalon

*En tant que direction, je veux qu'une mise en service soit prononcée sur des vérifications
consignées, afin qu'aucun jalon ne parte en production sur une impression.* — **rejouée à chaque
jalon**

1. ⛔ **Campagne d'autorisation** intégralement verte sur le périmètre cumulé du jalon.
2. ⛔ **Recette manuelle sur téléphone réel en conditions réseau dégradées**, obligatoire et opposable avant mise en service (PRD § 8.5, NFR1, NFR4).
3. ⛔ Les **règles métier bloquantes du périmètre** disposent chacune d'un test qui passe.
4. ⛔ **Restauration complète chronométrée en préproduction**, validant le RTO de 4 h.
5. ⛔ `ptr:check-invariants` vert sur préproduction **puis** sur production après bascule.
6. Les mesures de performance du jalon sont consignées avec leur valeur, non déclarées conformes.
7. Le retour arrière a été vérifié pour ce jalon.
8. La décision de mise en service est **prononcée par la direction**, datée, et consignée dans `docs/ops/`.

---

## ✅ Critères de fin de l'epic 11

1. ⛔ Une sauvegarde chiffrée part chaque nuit hors site, et **l'absence de sauvegarde alerte**.
2. ⛔ `ptr:test-restore` s'est exécuté au moins une fois avec succès, et son résultat daté figure dans le registre persistant `shared/ops/restore-log.md` — **hors du répertoire de release**, qu'un déploiement remplace. La procédure est versionnée dans `docs/ops/restore-procedure.md`.
3. ⛔ Une restauration complète a été **chronométrée** et le RTO de 4 h est validé avec un opérateur humain.
4. La supervision alerte sur : indisponibilité, travaux échoués, sauvegarde absente, requêtes lentes, certificat proche de l'expiration.
5. ⛔ Le retour arrière de déploiement a été exécuté avec succès **en conditions réelles**, pas seulement documenté.
6. ⛔ La mise en production reste une **décision humaine explicite**.
7. Les procédures d'exploitation, de rotation des secrets et d'astreinte sont écrites dans `docs/ops/`.

---

## 5. Traçabilité PRD → plan d'exécution

Les **51** stories du § 10 du PRD (13 + 8 + 13 + 17) sont intégralement couvertes. Vérification PO
du 18/07/2026 : **176 FR sur 176 couvertes, aucune orpheline.**

| PRD | Plan | PRD | Plan |
|---|---|---|---|
| 1.1 | 1.1, 1.2 | 3.1 | 6.1 |
| 1.2 | 1.4, 2.10 | 3.2 | 6.2 |
| 1.3 | 2.2, 2.7 | 3.3 | 6.3 |
| 1.4 | 2.1 | 3.4 | 6.4 |
| 1.5 | 2.4, 2.5, 2.6, 2.8 | 3.5 | 6.5 |
| 1.6 | 3.1, 3.2, 3.3, 3.6 | 3.6 | 6.6 |
| 1.7 | 3.4 | 3.7 | 7.1 |
| 1.8 | 3.7 | 3.8 | 7.2 |
| 1.9 | 4.1 | 3.9 | 7.3 *(+ 9.2)* |
| 1.10 | 4.2 | 3.10 | 7.4 |
| 1.11 | 4.4 | 3.11 | 7.5 |
| 1.12 | 4.5 | 3.12 | 7.6 |
| 1.13 | 4.6 | 3.13 | 3.8 *(avancée)* |
| 2.1 | 5.8 | 4.1 | 8.1 |
| 2.2 | 5.1 | 4.2 | 4.3 *(avancée)*, 8.2 |
| 2.3 | 5.2 | 4.3 → 4.13 | 8.3 → 8.13 |
| 2.4 | 5.3 | 4.14 | 9.1, 9.2, 9.3 |
| 2.5 | 5.4 | 4.15 | 9.4 |
| 2.6 | 5.5 | 4.16 | 9.5 |
| 2.7 | 5.6 *(+ 3.5)* | 4.17 | 10.1, 10.2, 10.3 |
| 2.8 | 5.7 | | |

### Couverture des exigences non fonctionnelles

**32 NFR sur 32 couvertes**, dont 14 par le socle transverse plutôt que par une story isolée — c'est
le rôle du socle : une exigence qui s'applique partout ne doit pas dépendre d'une story qui pourrait
être oubliée.

| NFR | Objet | Couvert par |
|---|---|---|
| NFR1 | Premier rendu utile < 3 s en 3G | SOC-09, 5.8, 9.5, **10.5** |
| NFR2 | ≤ 300 Ko / 80 Ko | SOC-09, 1.3 (budget en CI), **10.5** |
| NFR3 | Aucune ressource tierce | SOC-09, 1.7, **10.5** |
| NFR4 | Rapport quotidien < 3 min | **6.1 AC7**, 10.5 |
| NFR5 | Brouillon ≤ 10 s | 1.7 (`useDraft`), **6.1 AC5** |
| NFR6 | Aucun enregistrement partiel | **6.1 AC9**, SOC-02 |
| NFR7 / NFR8 | 320 px, cibles 44 px | SOC-09, 1.7, **10.5** |
| NFR9 | Navigateurs supportés | **10.5 AC8** |
| NFR10 | Aucun écran exigeant un ordinateur | SOC-09, 8.13 AC9, 9.5 AC6 |
| NFR11 | HTTPS, HSTS, aucun contenu mixte | **1.6** |
| NFR12 | Hachage, aucun secret journalisé | **1.6 AC6**, 2.4 AC3, 11.3 AC7 |
| NFR13 | CSRF, XSS, injection, bourrage | **1.6 AC3**, 2.6 |
| NFR14 | Autorisation serveur, URL directe | SOC-01, **2.9**, 10.4 |
| NFR15 | Pièces jointes hors racine web | **3.5** |
| NFR16 | Types et taille paramétrables | **3.5 AC3-AC5** |
| NFR17 | Erreurs sans secret ni donnée | SOC-08, **1.6 AC7**, 11.3 |
| NFR18 | Moindre privilège | SOC-01, 2.2 |
| NFR19 | Stagiaires sans donnée financière | **8.1 AC4**, 10.4 |
| NFR20 | Immuabilité au niveau du modèle | SOC-03, **1.4** |
| NFR21 | Audit dans la même transaction | SOC-02, **1.4 AC2** |
| NFR22 | Entiers XOF | SOC-11, **1.2**, 8.3 AC4 |
| NFR23 | Dates non ambiguës, Niamey | SOC-11, **1.2 AC5** |
| NFR24 | Sauvegarde quotidienne | **11.1** |
| NFR25 | Test de restauration documenté | **11.2** |
| NFR26 | Conservation 10 ans | **11.1 AC5** *(sous réserve DEC-11)* |
| **NFR27** | **5 à 100 utilisateurs sans changement d'architecture** | **10.5 AC9** *(charge simultanée à confirmer)* |
| NFR28 | Pas de multi-entreprise | **3.1 AC1**, tech-stack |
| NFR29 | Français simple, vocabulaire de contribution | SOC-10, 6.2 AC5, 7.2 AC5 |
| NFR30 | WCAG 2.1 AA | SOC-10, 1.7 AC7, **10.5 AC6** |
| NFR31 | Rien porté par la couleur seule | SOC-10, **10.5 AC7** |
| NFR32 | Messages d'erreur exploitables | SOC-08, **1.6 AC4** |

---

**Stories sans équivalent dans le PRD** — comblant les manques signalés au § 1 de ce document :
1.3 (CI), 1.5 (préproduction et secrets), 1.6 (durcissement HTTP), 1.7 (socle d'interface et
états transverses), 2.3 (**premier administrateur et données de référence**), 2.9 (campagne
d'autorisation), 9.6 (notifications métier complètes), 10.4 (invariants), 10.5 (recette NFR),
11.1 à 11.7 (**sauvegarde, restauration, supervision, staging, livraison**).

---

## 6. Phase 2 — hors périmètre, à ne pas anticiper

Rappelé ici pour qu'aucune story du MVP n'aille au-devant de ces sujets. Par ordre de valeur
décroissante (PRD § 3.2) : présence complète et pointage ; clients et ventes complets ; abonnements
SaaS et commissions récurrentes ; exports PDF et Excel ; réunions et décisions ; workflow de
recrutement complet ; notifications SMS / WhatsApp ; 2FA et réinitialisation par OTP ; PWA
installable et brouillons hors ligne ; matériel et accès numériques ; rôle Auditeur lecture seule.

**Deux exigences structurelles anticipent la phase 2 sans la livrer**, et ne doivent pas être
retirées comme du superflu :

- La **séparation personne / compte** (2.1) — sans elle, les commissions survivant au départ d'un bénéficiaire imposeraient une reprise du modèle (CONTRA-02, A-06).
- Le **modèle de permission autorisant un rôle lecture seule sans changement de schéma** (2.2 AC7) — sans lui, le rôle Auditeur imposerait une refonte (PERM-07, C7).

Hors périmètre définitif, ni MVP ni phase 2 : paie et déclarations, intégrations bancaires directes,
**gestion de stock**, module « Partage de bénéfices », biométrie et géolocalisation, classement entre
employés, application native, multi-entreprise.

---

## 7. Journal des versions

| Date | Version | Description | Auteur |
|---|---|---|---|
| 18/07/2026 | 1.0 | Plan d'exécution initial. 11 epics, 82 stories, 4 jalons. Couvre les 51 stories du PRD et comble dix manques : CI, sécurité HTTP, socle d'interface, amorçage du premier administrateur, données initiales, campagne d'autorisation, sauvegarde, restauration, supervision et livraison. Trois écarts d'ordonnancement signalés au § 4. | John (PM) |
| 18/07/2026 | 1.1 | Corrections après revue PO : compte de stories PRD rétabli à 51 ; couverture FR confirmée 176/176. | John (PM) |
| 18/07/2026 | 1.2 | **Séquencement corrigé — 24 corrections issues de la revue PO.** Les sept dépendances avant intra-epic sont levées : notification de fin de contrat (3.2 → 9.6), tableau de bord personnel livré par incréments (5.8 → 6.1, 6.6, 7.5), test de solde reporté (8.1 → 8.6), statuts de facture (8.4 → 8.5), calcul des parts (8.5 → 8.7), `MonthGuard` avancé en 8.5, calculateur d'alerte créé en 8.13 et réutilisé en 9.1, `ptr:check-invariants` créée en 2.3 puis enrichie en 4.5, 11.1 et 10.4. NFR27 couverte par 10.5 AC9. Ajouts : table de couverture NFR 32/32, registre complet des arbitrages, dépôt git et README en 1.1. Trois défauts d'exploitation corrigés : `/up` n'expose la sauvegarde qu'à partir de 11.1, le journal de restauration sort du `docs/` d'une release, la préproduction a un régime d'amorçage distinct. | John (PM) |

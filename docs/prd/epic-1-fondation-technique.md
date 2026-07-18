<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

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

---

**Règles transverses applicables à toutes les stories de cet epic :** voir `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
**Écarts d'ordonnancement et décisions en attente :** voir `docs/prd/ecarts-et-decisions.md`.

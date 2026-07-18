<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

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

---

**Règles transverses applicables à toutes les stories de cet epic :** voir `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
**Écarts d'ordonnancement et décisions en attente :** voir `docs/prd/ecarts-et-decisions.md`.

<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

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

1. `php artisan ptr:check-invariants` s'exécute **quotidiennement** et alerte en cas d'écart.
2. Il vérifie : `APP_DEBUG=false` et `APP_ENV=production` ; **exactement 2 comptes** porteurs de `depense.approuver` ; aucun `super_admin` porteur d'une permission métier ; déclencheurs d'immuabilité présents sur `audit_logs` ; utilisateur applicatif dépourvu de `DELETE` sur `audit_logs` ; dernière sauvegarde de moins de 26 h.
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
9. Les écarts constatés sont consignés avec leur décision — corrigé, accepté, reporté — jamais laissés implicites.

---

## ✅ Critères de fin de l'epic 10 — porte du MVP

1. ⛔ Un export ne révèle **jamais** une ligne que son auteur ne voit pas à l'écran, y compris par manipulation d'URL, et **tout export est audité**.
2. ⛔ Les **quatorze règles métier bloquantes** disposent chacune d'un test nommé qui passe.
3. ⛔ La campagne d'autorisation est complète et verte ; aucune route protégée n'échappe à la matrice.
4. ⛔ `ptr:check-invariants` passe, **détection de dérive dans les données comprise**.
5. ⛔ Les mesures NFR1, NFR2, NFR4, NFR7, NFR8 et WCAG AA sont **consignées avec leur valeur**, pas déclarées conformes.
6. Les **18 critères d'acceptation du brief** (PRD § 12) passent tous.

---

---

**Règles transverses applicables à toutes les stories de cet epic :** voir `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
**Écarts d'ordonnancement et décisions en attente :** voir `docs/prd/ecarts-et-decisions.md`.

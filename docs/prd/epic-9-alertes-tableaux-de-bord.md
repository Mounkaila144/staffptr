<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

# Epic 9 — Alertes, tableaux de bord et notifications

**Objectif.** Faire en sorte que l'alerte de trésorerie se déclenche **seule**, sans dépendre de la
vigilance de quiconque, et réunir le travail et l'argent sur un seul écran.

**Dépend de :** Epic 8 (toutes les données financières). **Jalon 4.**

---

### Story 9.1 — Niveau d'alerte vert, orange et rouge

*En tant que direction, je veux être avertie automatiquement avant la séquence qui a déjà fermé
l'entreprise, afin que le mécanisme ne dépende de la vigilance de personne.* — [PRD 4.14]

1. ⛔ L'assiette d'alerte est la **somme des charges fixes actives du paramétrage** ; **aucune liste codée en dur** (FR161).
2. ⛔ **Vert** : encaissements du mois ≥ assiette. **Orange** : un mois sous l'assiette. **Rouge** : **deux mois consécutifs** sous l'assiette. Les trois cas sont testés sur des jeux de données dédiés (FR162 à FR164, CA-15).
3. ⛔ L'ajout d'une charge fixe modifie l'assiette et **peut changer le niveau au recalcul suivant** ; testé (FR147).
4. Le niveau est affiché en permanence sur le tableau de bord direction, **avec libellé textuel en plus de la couleur** (NFR31).
5. Le recalcul est une tâche planifiée idempotente ; le niveau figé à la clôture mensuelle (8.13) fait foi pour le mois clos.
6. Le calcul affiche sa **méthode et la date des données source**.

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

---

**Règles transverses applicables à toutes les stories de cet epic :** voir `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
**Écarts d'ordonnancement et décisions en attente :** voir `docs/prd/ecarts-et-decisions.md`.

<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

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

---

**Règles transverses applicables à toutes les stories de cet epic :** voir `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
**Écarts d'ordonnancement et décisions en attente :** voir `docs/prd/ecarts-et-decisions.md`.

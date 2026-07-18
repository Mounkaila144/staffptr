<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

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

---

**Règles transverses applicables à toutes les stories de cet epic :** voir `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
**Écarts d'ordonnancement et décisions en attente :** voir `docs/prd/ecarts-et-decisions.md`.

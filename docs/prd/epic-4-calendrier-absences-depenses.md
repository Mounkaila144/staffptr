<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

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

---

**Règles transverses applicables à toutes les stories de cet epic :** voir `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
**Écarts d'ordonnancement et décisions en attente :** voir `docs/prd/ecarts-et-decisions.md`.

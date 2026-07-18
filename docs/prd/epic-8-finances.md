<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

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
3. ⛔ Un test vérifie qu'après un encaissement de 50 000 et une dépense payée de 20 000, le solde progresse **exactement** de 30 000.
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
4. ⛔ Aucun poste n'est codé en dur ; un test ajoute une charge et vérifie que **l'assiette d'alerte change sans redéploiement**.
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
2. ⛔ Le statut `impayee` / `partiellement_payee` / `payee` / `annulee` est **déduit** des encaissements imputés ; **aucune interface ne permet de le saisir** (FR106).
3. ⛔ Une créance est déduite **automatiquement** de toute facture non intégralement payée dont l'échéance est atteinte (FR107).
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
4. Un encaissement imputé à un contrat déclenche le calcul des parts (8.7) — **dans la même transaction**.
5. L'application **signale les encaissements créés plus de 24 h** après leur date de réception déclarée (FR112).
6. ⛔ Toute tentative d'imputation à un **mois clôturé** est refusée avec un message nommant le mois : « Le mois de juin 2026 est clôturé. Aucune écriture ne peut y être imputée. » (FR114).
7. L'écriture est atomique et protégée par verrou : deux encaissements simultanés sur le même contrat ne produisent pas de parts en double ; testé.

---

### Story 8.6 — Paiement des dépenses et imputation

*En tant que responsable financier, je veux payer une dépense approuvée et l'imputer, afin que
l'approbation, le paiement et l'écriture comptable restent trois faits distincts.* — [PRD 4.6]

1. ⛔ **Seule une dépense `approuvee` est payable** ; le paiement d'une dépense `demandee` ou `refusee` est refusé côté serveur.
2. Le paiement enregistre compte débité, date, mode de paiement, référence, puis fait passer la dépense à `payee`.
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
8. La validation **recalcule et fige le niveau d'alerte du mois** (FR160) — consommé par 9.1.
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

---

**Règles transverses applicables à toutes les stories de cet epic :** voir `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
**Écarts d'ordonnancement et décisions en attente :** voir `docs/prd/ecarts-et-decisions.md`.

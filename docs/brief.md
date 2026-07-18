# Product Brief : PTR Staff

**Version :** 1.1 — 18 juillet 2026
**Auteur :** Mary, Business Analyst (BMAD)
**Source principale :** `docs/Brief_Analyste_BMAD_PTR_Staff.md` (v1.0)
**Mise à jour v1.1 :** intègre les décisions de la direction du 18 juillet 2026 (§ 7bis, C2, C3, C10
résolus ; ordre de livraison arrêté ; nouveau modèle de répartition des bénéfices révélé).
**Statut :** ✅ **Complet — prêt pour l'Agent Product Manager.** Aucune question bloquante restante
(§ 12.1 vide). Les questions du § 12.2 relèvent de l'architecture et peuvent être traitées en
parallèle du PRD.

> **Note de méthode.** Le besoin est validé en amont. Ce document ne refait pas d'étude de marché.
> Il reformule le problème, confirme le périmètre, **relève les contradictions internes du brief
> d'entrée**, propose un MVP réaliste pour une petite équipe au Niger, et prépare le PRD.
> Aucune règle marquée « décision prise » (§ 17 du brief d'entrée) n'a été modifiée.

---

## 1. Résumé exécutif

PTR Staff est une application web interne, mobile-first, réservée au personnel de PTR Niger. Elle
relie deux choses qui, jusqu'ici, n'étaient pas reliées dans l'entreprise : **le travail que
chacun produit** et **l'argent qui entre et qui sort**.

Le problème n'est pas un manque d'outils. C'est une **absence de trace**. Les objectifs n'étaient
pas écrits, les résultats n'étaient pas prouvés, les dépenses n'étaient pas autorisées, et la
trésorerie n'était pas surveillée. L'entreprise a déjà fermé une fois par manque de trésorerie —
ce n'est pas un risque théorique, c'est un antécédent.

La proposition de valeur tient en une phrase : **chaque personne sait quoi faire et le prouve ;
chaque franc laisse une trace ; la direction voit les deux sur un même écran.**

Le public est petit et connu : 5 à 100 utilisateurs, un seul établissement, francophone, sur
téléphone Android et connexion parfois instable. Cela oriente tout le produit vers la sobriété.

---

## 2. Énoncé du problème

### 2.1 État actuel

Les dix problèmes du § 2 du brief d'entrée se regroupent en **quatre défaillances de système**,
et non en quatre défauts de personnes. C'est une distinction importante pour la conception :
on corrige des mécanismes, pas des individus.

| # | Défaillance | Problèmes d'origine | Conséquence constatée |
|---|---|---|---|
| D1 | **Travail non cadré** — pas d'objectif écrit, pas de mission | 1, 2, 4 | Stagiaires oisifs, temps de direction absorbé |
| D2 | **Résultat non prouvé** — pas de preuve attachée au travail | 5, 10 | Rémunération sans contrepartie vérifiable, démotivation |
| D3 | **Argent non tracé** — pas d'autorisation, pas de justificatif | 7, 8 | Fonds projet dépensés personnellement |
| D4 | **Trésorerie non surveillée** — pas de seuil, pas d'alerte | 6, 9 | Plusieurs mois à perte sans réaction ; fermeture |

**D3 et D4 sont les défaillances qui ont déjà coûté l'entreprise.** D1 et D2 sont celles qui la
font saigner lentement. Cette hiérarchie détermine le séquencement du MVP au § 6.

### 2.2 Pourquoi les solutions existantes ne suffisent pas

Ce point n'appelle pas d'étude de marché, mais il faut le dire explicitement pour le PRD : un
outil générique (Trello, Excel partagé, WhatsApp, un logiciel comptable) résout **une** des quatre
défaillances et laisse les trois autres ouvertes. Le cœur du besoin de PTR Niger est précisément
la **jointure** — voir sur un même tableau de bord « qui n'a pas d'objectif » et « combien de mois
de charges la réserve couvre ». Aucun outil du marché ne fait cette jointure avec les règles
propres à PTR Niger (double signature systématique, trois stagiaires par tuteur, répartition
10/60/30, réserve à 20 % de la marge).

### 2.3 Urgence

Le coût d'inaction est déjà démontré par l'historique. L'urgence n'est donc pas commerciale mais
**existentielle** : sans mécanisme d'alerte, la prochaine séquence de deux mois déficitaires se
déroulera comme la précédente.

---

## 3. Solution proposée

### 3.1 Concept

Une application unique, en français simple, structurée autour de **trois boucles** :

1. **Boucle quotidienne** — objectif → tâche → rapport du jour avec preuve → validation du
   responsable. Cycle de 24 h.
2. **Boucle hebdomadaire** — revue du vendredi (objectifs, écarts, plan d'amélioration) et
   rapprochement de caisse. Cycle de 7 jours.
3. **Boucle mensuelle** — objectifs du mois, rapport financier, niveau d'alerte vert/orange/rouge,
   décisions de direction. Cycle de 30 jours.

Chaque boucle produit une trace immuable. Le journal d'audit est ce qui rend la boucle crédible :
sans lui, l'application ne fait qu'enregistrer des déclarations.

### 3.2 Principes de conception non négociables

Ces principes doivent survivre au PRD et à l'architecture :

- **P1 — La preuve avant la déclaration.** Un objectif « atteint » sans preuve attachée n'est pas
  atteint. Le modèle de données doit rendre la preuve structurelle, pas optionnelle.
- **P2 — Rien ne se supprime.** Correction, annulation ou contre-écriture, jamais `DELETE`. Vaut
  pour la finance (§ 14.6 du brief d'entrée) et pour tout objet validé.
- **P3 — Le logiciel constate, l'humain décide.** Aucune sanction, rupture ou blocage de personne
  automatique. Le système peut bloquer une *écriture* (quatrième stagiaire, quatrième objectif),
  jamais une *personne*.
- **P4 — Le contrôle d'accès est côté serveur.** L'URL directe doit échouer, pas seulement le menu.
- **P5 — Symétrie hiérarchique.** Les dirigeants sont soumis aux mêmes objectifs et rapports. Une
  fonctionnalité qui exempte la direction est un défaut, pas une commodité.
- **P6 — Sobriété.** Chaque écran doit être utilisable sur un téléphone en 3G. Un écran qui exige
  le desktop est un écran mal conçu pour cette entreprise.

### 3.3 Pourquoi cette solution peut réussir ici

Trois raisons concrètes : le périmètre est **une seule entreprise** (pas de multi-tenant, pas de
paramétrage générique) ; les **règles métier sont déjà arrêtées** par la direction (§ 17), donc
pas de dérive de spécification ; et la **taille de l'équipe** permet d'imposer l'usage par
décision managériale plutôt que par séduction produit.

Le risque n'est donc pas l'adoption technique. C'est **l'abandon d'usage** si la saisie quotidienne
coûte plus de 3 minutes par personne. Ce chiffre doit devenir une contrainte de conception.

---

## 4. Utilisateurs cibles

### 4.1 Segment principal : l'exécutant redevable (employé, contractuel, stagiaire)

C'est le segment le plus nombreux et celui qui détermine le succès ou l'échec du produit.

- **Profil.** 5 à 20 personnes au lancement. Téléphone Android en usage principal, ordinateur
  partagé ou personnel selon le poste. Français courant, aisance numérique variable, très forte
  chez les stagiaires (profils tech), plus faible sur les fonctions support.
- **Comportement actuel.** Rend compte oralement ou par WhatsApp. Ne conserve pas de trace de ce
  qu'il a produit. Découvre ses priorités en réunion.
- **Besoin réel.** Non pas « être suivi », mais **être reconnu**. Le brief d'entrée le dit en creux
  au problème 10 : le manque de visibilité sur les progrès baisse la motivation. L'application doit
  donc être vécue comme un **relevé de contribution**, pas comme un pointage.
- **Objectif.** Savoir en 10 secondes quoi faire aujourd'hui ; en 2 minutes prouver ce qui a été
  fait ; ne jamais être accusé sans trace.
- **Point de friction à surveiller.** Le rapport quotidien à 17 h 45 comporte 6 champs obligatoires
  (§ 8/F.3). Sur téléphone, en 3G, en fin de journée, c'est le moment de vérité du produit.

### 4.2 Segment principal : la direction

- **Profil.** 1 à 3 personnes. Utilise le produit sur téléphone et sur ordinateur. Fortement
  sollicitée, faible disponibilité, forte responsabilité juridique et financière.
- **Comportement actuel.** Arbitre au fil de l'eau, sans données consolidées. Passe trop de temps
  en discussion informelle (problème 3).
- **Besoin réel.** Un **écran de vérité unique** : qui n'a pas d'objectif, qui n'a pas rendu son
  rapport, quelles dépenses attendent une approbation, combien de mois de charges sont couverts.
- **Objectif.** Décider vite, avec trace, sans réunion supplémentaire.
- **Point de friction.** L'approbation de dépense doit être faisable en 3 taps depuis une
  notification, sinon elle sera contournée par appel téléphonique — et la trace sera perdue.

### 4.3 Segment principal : le responsable financier

- **Profil.** 1 personne, potentiellement cumulée avec un autre rôle (voir contradiction C3).
- **Besoin réel.** Saisir vite, justifier, rapprocher, et surtout **ne pas être seul responsable**
  d'une validation qui devrait être partagée.
- **Point de friction.** Le rapprochement hebdomadaire et le rapport mensuel exigent deux personnes
  distinctes (préparateur/contrôleur). Avec un seul financier, la règle est inapplicable telle
  qu'écrite (voir C3).

### 4.4 Segment secondaire : le tuteur / responsable d'équipe

- **Profil.** 1 à 4 personnes, généralement cumulant leur propre travail d'exécutant.
- **Besoin réel.** Valider les rapports de son équipe rapidement, et **limiter le temps consacré
  aux stagiaires** — le problème 3 du brief d'entrée est explicitement un problème de temps de
  l'encadrant. Le regroupement des demandes de stagiaires en créneaux (§ 8/I.11) est une réponse
  directe à ce problème et doit être traité comme une fonctionnalité, pas comme un détail.

### 4.5 Segments hors MVP

- **Super administrateur technique** — nécessaire dès le jour 1, mais c'est un rôle d'exploitation,
  pas un segment produit.
- **Auditeur lecture seule** — marqué optionnel au § 6.7 et absent de la matrice d'accès § 7.
  Recommandation : **hors MVP** (voir C7).

---

## 5. Objectifs et indicateurs de réussite

### 5.1 Objectifs métier (reformulés en SMART)

- **OB1 — Cadrage universel.** 100 % des membres actifs disposent d'objectifs écrits et validés
  avant le 1er de chaque mois, dirigeants inclus, dès le 2ᵉ mois d'usage.
- **OB2 — Redevabilité quotidienne.** ≥ 90 % des rapports quotidiens envoyés avant 17 h 45, mesuré
  en moyenne glissante sur 4 semaines, atteint au 3ᵉ mois d'usage.
- **OB3 — Encadrement maîtrisé.** 100 % des stagiaires actifs ont un tuteur, un plan de stage et
  3 objectifs ; aucun tuteur au-delà de **3 stagiaires actifs**. Contrainte bloquante dès le jour 1.
- **OB4 — Traçabilité financière intégrale.** 100 % des dépenses ont une autorisation conforme au
  seuil et un justificatif attaché ; 0 suppression de mouvement validé. Dès le jour 1.
- **OB5 — Discipline de rapprochement.** Rapprochement caisse / Mobile Money / banque réalisé
  chaque semaine, écart expliqué. Dès la 1ʳᵉ semaine d'usage financier.
- **OB6 — Clôture à date.** Rapport financier mensuel validé au plus tard le 5 du mois suivant,
  12 mois sur 12.
- **OB7 — Visibilité de survie.** Le nombre de mois de charges couverts par la réserve est affiché
  en permanence et le niveau d'alerte est recalculé automatiquement à chaque clôture.

### 5.2 Indicateurs de succès utilisateur

- **Temps de saisie du rapport quotidien ≤ 3 minutes** sur téléphone, mesuré du premier champ à
  l'envoi. *C'est l'indicateur qui protège l'adoption.*
- **Temps d'approbation d'une dépense ≤ 24 h** entre soumission et décision.
- **Délai de prise en charge d'un blocage ≤ 4 h ouvrées** entre signalement et première réponse.
- **Zéro contournement** : aucune dépense payée sans demande préalable enregistrée dans l'outil.

### 5.3 KPI de pilotage

| KPI | Définition | Cible |
|---|---|---|
| Taux de couverture d'objectifs | Membres actifs avec ≥ 1 objectif validé / membres actifs | 100 % |
| Ponctualité des rapports | Rapports envoyés avant 17 h 45 / rapports attendus | ≥ 90 % |
| Taux de preuve | Objectifs clos avec preuve attachée / objectifs clos | 100 % |
| Conformité d'approbation | Dépenses conformes au seuil / dépenses payées | 100 % |
| Écart de rapprochement | Somme des écarts non expliqués | 0 FCFA |
| Mois de charges couverts | Réserve / charges fixes mensuelles moyennes | ≥ 3 |
| Délai de clôture | Jours entre fin de mois et validation du rapport | ≤ 5 |
| Charge d'encadrement | Stagiaires actifs par tuteur | ≤ 3 |

---

## 6. Périmètre MVP

### 6.1 Constat de dimensionnement

Le § 15 du brief d'entrée liste **17 blocs fonctionnels en MVP**. C'est, en pratique, un produit
complet de gestion d'entreprise. Livré en une seule fois par une petite équipe, ce périmètre
présente deux risques : une mise en service tardive (donc D3/D4 restent non traités pendant tout
le développement) et un rejet à l'usage (trop de nouveautés simultanées pour l'équipe).

**Recommandation : conserver l'intégralité du périmètre MVP, mais le livrer en 4 étapes
séquentielles et utilisables.** Le périmètre n'est pas réduit ; il est ordonné. Chaque étape est
déployable et apporte une valeur autonome.

> ### ⚖️ Décision direction du 18 juillet 2026 — ordre de livraison
>
> L'analyste recommandait de livrer **la finance en premier** (les défaillances D3 et D4 sont
> celles qui ont déjà coûté l'entreprise). **La direction a retenu l'ordre du brief d'entrée**
> (modules A → S) : socle, puis objectifs et projets, puis rapports et encadrement, puis finance.
>
> **Conséquence assumée :** la partie financière est mise en service **en dernier**. Jusqu'à sa
> livraison, les dépenses, encaissements et alertes de trésorerie restent gérés comme aujourd'hui,
> hors application et sans trace structurée.
>
> **Atténuation obligatoire proposée par l'analyste :** avancer dès l'Étape 1 deux éléments
> minimaux et peu coûteux, qui n'appartiennent à aucun module métier lourd :
> 1. le **journal d'audit** (module R) — il doit exister avant la première écriture sensible ;
> 2. un **registre des demandes de dépense avec approbation** — formulaire, double validation,
>    justificatif, sans comptabilité ni rapprochement.
>
> Ces deux éléments ferment le trou le plus dangereux (D3) sans déplacer l'ordre général retenu.
> **✅ Accepté par la direction le 18/07/2026.**

### 6.2 Fonctionnalités MVP par étape (ordre retenu : A → S)

#### Étape 1 — Socle (modules A, B, S, R)

- **Authentification téléphone + mot de passe :** numéro normalisé `+227`, mot de passe temporaire
  avec changement obligatoire, limitation des tentatives, historique des connexions.
- **Rôles et permissions :** modèle rôle + permission fine, cumul de rôles possible, contrôle
  systématiquement côté serveur.
- **Cycle de vie du compte :** invité, actif, suspendu, terminé, archivé ; suspension = coupure
  immédiate des sessions.
- **Organisation et profils :** entreprise, services, fonctions, fiche utilisateur, type de
  relation, dates de contrat, responsable direct, historique des changements.
- **Journal d'audit :** écriture systématique sur finances, objectifs validés, comptes,
  permissions et documents internes. Non modifiable depuis l'interface.
- **Notifications dans l'application** et **paramètres généraux** (§ 8/S).

*Rationale : rien de métier n'est fiable sans ce socle. L'audit doit exister avant la première
écriture financière, pas après — sinon les premiers mois sont non auditables.*

#### Étape 2 — Objectifs et projets (modules C, D, E)

- **Tableau de bord personnel :** objectifs du mois, tâches du jour, rapports à envoyer, blocages,
  échéances, notifications, dernière évaluation, demandes en attente.
- **Objectifs d'entreprise et objectifs individuels :** 5 priorités max par mois pour l'entreprise,
  3 objectifs majeurs max par personne et par mois, indicateur, cible, preuve attendue, échéance,
  états et couleurs, modification tracée avec motif, proposition d'objectif par l'utilisateur.
- **Projets et tâches simples :** projet, membres, statut ; tâche avec responsable, échéance,
  priorité, lien à un objectif ; pièces jointes et commentaires ; liste simple.

#### Étape 3 — Redevabilité et encadrement (modules F, G, H, I, N)

- **Rapport quotidien :** un par personne et par jour travaillé, brouillon auto-sauvegardé,
  6 champs, preuve, états, heure limite paramétrable (17 h 45), rappel et retard, validation ou
  retour du responsable sans modification silencieuse, historique des versions.
- **Blocages et demandes d'aide :** création depuis tâche, objectif ou rapport ; urgence ;
  notification immédiate ; mesure du temps de résolution.
- **Revue hebdomadaire :** synthèse de la semaine, écart et cause, commentaires des deux parties,
  validation électronique simple, plan d'amélioration 7–14 jours, historique.
- **Gestion des stagiaires :** fiche d'entrée minimale, plan de stage, checklist d'intégration,
  limite bloquante de **3 stagiaires actifs par tuteur** (paramétrable), évaluation hebdomadaire,
  évaluation finale,
  checklist de sortie, regroupement des demandes en créneaux de suivi.
- **Documents internes :** bibliothèque, versions, accusé de lecture et d'acceptation.

#### Étape 4 — Argent et pilotage (modules L, P, Q + noyau client)

- **Comptes financiers manuels :** caisse, banque, Mobile Money, solde initial et mouvements.
- **Encaissements :** client, projet, montant, mode, référence, numéro de reçu unique, justificatif.
  Aucune suppression : correction ou annulation motivée.
- **Demandes de dépenses et approbations :** workflow demandée → approuvée → payée, séparé de
  l'état de paiement ; **deux approbateurs distincts pour toute dépense, sans seuil** (C14) ;
  interdiction d'être seul approbateur de sa propre demande ; justificatif de paiement
  post-paiement ; catégorie « gratification de stagiaire » (§ 7bis.1.1).
- **Budgets et charges fixes :** budget mensuel par catégorie, liste des charges fixes,
  comparaison budget / réalisé.
- **Réserve :** montant, mois couverts, affectation, utilisation soumise à motif + double
  approbation + plan de reconstitution.
- **Rapprochement hebdomadaire :** comparaison physique / écritures, écart, explication, action.
- **Rapport financier mensuel :** les 12 lignes du § 8/L, préparation, contrôle, validation,
  clôture du mois, réouverture tracée.
- **Répartition des bénéfices** selon le modèle du § 7bis (apporteur, exécutants, entreprise).
- **Alertes vert / orange / rouge**, **tableau de bord financier** et **tableau de bord direction**
  consolidé.
- **Recherche et listes filtrables**, **export CSV** respectant les permissions.

*Rationale : cette étape est celle qui traite D3 et D4 — les défaillances qui ont déjà coûté
l'entreprise. Elle arrive en dernier par décision de la direction ; l'atténuation du § 6.1
(audit + registre d'approbation des dépenses dès l'Étape 1) est d'autant plus nécessaire.*

### 6.3 Hors périmètre MVP

Confirmé et inchangé par rapport au § 15 du brief d'entrée :

- Présence, retards, permissions et absences → **sauf le strict minimum** décrit en C4.
- Workflow complet de recrutement → **sauf la fiche d'entrée minimale** décrite en C5.
- Réunions et décisions ; matériel et accès numériques.
- Clients, prospects, devis, factures et créances avancés → **sauf le minimum client / créance**
  décrit en C6.
- Exports PDF et Excel complets ; PWA et brouillons hors ligne ; OTP SMS et WhatsApp ;
  authentification renforcée (2FA) des rôles sensibles.
- **Module « Partage de bénéfices » (§ 8/L)** — supprimé : sans objet depuis la répartition
  automatique 10 / 60 / 30 (C15).
- Paie, déclarations CNSS ou fiscales, intégrations bancaires ou Mobile Money directes.
- Biométrie, géolocalisation, surveillance d'écran, classement public, sanction automatique,
  application mobile native, multi-entreprise.

### 6.4 Critères de succès du MVP

Le MVP est réussi si, **après 60 jours d'usage réel** :

1. Les 18 critères d'acceptation du § 16 du brief d'entrée passent tous.
2. Aucune dépense n'a été payée hors du circuit d'approbation de l'application.
3. Le niveau d'alerte financier affiché correspond à la réalité vérifiée manuellement.
4. Le taux de rapports quotidiens envoyés à l'heure dépasse 80 % (90 % étant la cible à 90 jours).
5. Le rapport financier du mois écoulé a été validé avant le 5.
6. Aucun utilisateur n'a accédé à un écran interdit à son rôle, y compris par URL directe.
7. Le temps moyen de saisie du rapport quotidien reste sous 3 minutes.

---

## 7. Contradictions et zones à trancher

C'est la section la plus importante de ce brief. Ces points **doivent être résolus avant ou
pendant le PRD** — chacun bloque une implémentation ou crée un risque de conformité.
Aucune de ces contradictions n'est résolue unilatéralement ici : les résolutions marquées
« proposition » attendent l'arbitrage de la direction.

---

### C1 — « Un responsable autorisé » n'existe pas dans la matrice d'accès ✅ Sans objet

**Contradiction.** Le § 8/L (Dépenses, règle 2) autorise « un responsable autorisé » à valider une
dépense ≤ 25 000 FCFA prévue au budget. Mais la matrice § 7 attribue l'approbation de dépense à la
**Direction** uniquement ; le Tuteur, l'Employé et le Stagiaire peuvent seulement « créer », et la
Finance « prépare / paie ».

**Conséquence.** Le rôle qui exerce l'approbation simple n'est identifié nulle part. Impossible
d'implémenter la règle du seuil sans le nommer.

**Résolution (18/07/2026).** Cette contradiction **disparaît** : la direction ayant décidé que
toute dépense exige deux signatures (C14), il n'existe plus d'« approbation simple » ni de
« responsable autorisé ». Une seule permission subsiste, `approuver_depense`, détenue par les
**deux associés** uniquement.

---

### C2 — La double approbation ✅ Résolu (direction, 18/07/2026)

**Décision de la direction.** PTR Niger compte **deux associés propriétaires**. **Deux personnes**
détiennent le pouvoir d'approbation d'une dépense. **Aucune dérogation :** si un seul associé est
disponible, la dépense n'est pas approuvée. Il faut le consentement des deux, toujours.

**Règle à implémenter.** Toute dépense soumise à double approbation exige les deux comptes
associés, distincts. Pas d'approbation dérogatoire, pas de délégation. Une dépense reste en attente
tant que le second consentement manque.

**Conséquence à assumer.** Une absence, un déplacement ou une indisponibilité d'un associé **gèle
les dépenses** concernées. C'est un choix volontaire de rigueur ; le PRD doit prévoir une
notification insistante à l'approbateur manquant et un écran « dépenses en attente de mon
approbation » très visible, pour que le gel reste court.

**⚠️ Nouvelle contradiction ouverte → voir C14.** Cette réponse dit « aucune dépense sans les deux
consentements », alors que la décision prise du brief d'entrée autorise **un seul** approbateur
pour une dépense ≤ 25 000 FCFA prévue au budget. Les deux règles ne peuvent pas coexister.

---

### C3 — Séparation préparateur / contrôleur ✅ Résolu (direction, 18/07/2026)

**Décision de la direction.** Les deux associés propriétaires **se contrôlent mutuellement**. L'un
prépare, l'autre contrôle, sur le rapprochement hebdomadaire comme sur le rapport mensuel.

**Règle à implémenter.** Le contrôleur ne peut jamais être le préparateur — contrainte système sur
comptes distincts. Les deux associés détiennent chacun les deux permissions ; c'est l'application
qui empêche qu'une même personne exerce les deux rôles sur une même écriture.

**Réserve de l'analyste.** Ce dispositif fonctionne tant que les deux associés sont réellement
actifs et attentifs. Il ne protège pas contre une collusion des deux — et aucune application ne
le peut. Le journal d'audit reste donc la seule trace opposable en cas de litige entre associés.

---

### C4 — Le MVP a besoin des absences alors que la présence est en phase 2 🟠 Important

**Contradiction.** Le module J (présence, retards, absences) est renvoyé en phase 2. Mais le
§ 8/F.12 impose : « Aucun rapport n'est attendu pendant une absence approuvée ou un jour non
travaillé. » Sans notion d'absence, le MVP marquera « en retard » toute personne absente,
malade ou en congé.

**Conséquence.** Les indicateurs de ponctualité (OB2, cible 90 %) seront faux dès le premier mois,
et l'application produira des reproches injustifiés — atteinte directe à la confiance des
utilisateurs.

**Proposition.** Inclure dans l'**Étape 1** un minimum indispensable : **calendrier des jours
travaillés** (lundi–vendredi par défaut, jours fériés saisissables) et **déclaration d'absence
simple** avec approbation du responsable, qui suspend l'attente de rapport. Le pointage
arrivée/départ, les retards et le calendrier d'équipe restent en phase 2.

---

### C5 — Le MVP active des stagiaires alors que le workflow d'entrée est en phase 2 🟠 Important

**Contradiction.** « Gestion des stagiaires » est en MVP et le critère d'acceptation 3 exige qu'un
stagiaire ne puisse être activé sans tuteur et sans trois objectifs. Le § 8/I.4 ajoute : « Aucun
compte actif avant l'approbation de la fiche d'entrée. » Or « workflow complet de recrutement »
est renvoyé en phase 2.

**Proposition.** Distinguer **fiche d'entrée** (MVP) et **workflow de recrutement** (phase 2). Le
MVP retient : besoin, mission, responsable/tuteur, durée, trois résultats obligatoires, approbation
en une étape, puis activation du compte. Le circuit multi-états (brouillon → soumis → approuvé →
refusé → préparé), le coût et le financement restent en phase 2.

---

### C6 — La réserve à « 20 % de la marge nette » n'est pas calculable en MVP 🔴 Bloquant

**Contradiction.** Décision prise : « au moins 20 % de la **marge nette de chaque projet** est
affectée à la réserve ». Calculer une marge nette par projet suppose (a) un chiffre d'affaires
projet, donc de la facturation — renvoyée en phase 2 ; (b) des coûts directs imputés au projet ;
et (c) un budget par projet — explicitement « optionnel » au § 8/L. Aucune de ces trois sources
n'est garantie en MVP.

De plus, le rapport financier mensuel MVP exige les lignes « chiffre d'affaires facturé » et
« créances clients », et le tableau de bord direction affiche « créances » — trois éléments qui
appartiennent au module M classé phase 2.

**Conséquence.** Sans arbitrage, soit la réserve est calculée sur des données absentes, soit le
rapport mensuel MVP est incomplet.

**Proposition.** La règle des 20 % est une décision prise et **n'est pas modifiée**. Ce qui change
est la **source de calcul en MVP** : introduire à l'Étape 4 un **noyau client / facture minimal**
— fiche client simple, facture (numéro, montant, échéance, statut payé/impayé), créance déduite —
et imputer les coûts directs au projet via la catégorie de dépense. La marge nette projet devient
alors calculable comme *encaissements imputés au projet − dépenses imputées au projet*. Le CRM
complet (prospects, opportunités, devis, relances, export PDF) reste en phase 2. *Validation
direction requise sur la définition retenue de « marge nette ».*

---

### C5bis — Capacité d'encadrement portée à 3 stagiaires ✅ *(direction, 18/07/2026)*

**Décision de la direction.** Un tuteur peut encadrer **jusqu'à 3 stagiaires actifs**, contre 2
dans le brief d'entrée. **Les employés recrutés pourront eux aussi être tuteurs**, en plus des deux
propriétaires.

**Éléments du brief d'entrée modifiés par cette décision** — à répercuter dans le PRD :

| Référence | Ancien texte | Nouveau |
|---|---|---|
| § 17, décisions prises | « Maximum de deux stagiaires actifs par tuteur » | **trois** |
| § 5, indicateurs | « Aucun tuteur ne supervise plus de deux stagiaires actifs » | **trois** |
| § 16, critère 4 | « empêche l'affectation d'un **troisième** stagiaire actif » | **quatrième** |
| § 8/I.5 | « Un tuteur ne peut avoir plus de deux stagiaires actifs » | **trois** |

**Exigence d'implémentation.** La limite doit être un **paramètre** (module S), pas une valeur codée
en dur. Elle vient de passer de 2 à 3 ; elle peut bouger encore. Le contrôle reste **bloquant** :
l'application refuse l'affectation d'un stagiaire au-delà de la limite.

**Capacité d'accueil qui en découle :**

| Effectif tuteurs | Stagiaires actifs maximum |
|---|---:|
| 2 propriétaires seuls (aujourd'hui) | **6** |
| 2 propriétaires + 2 employés tuteurs | **12** |

**Réserve de l'analyste — à garder à l'œil.** La limite de 2 venait directement du problème 3 du
brief d'entrée : « les dirigeants passaient parfois trop de temps à discuter avec les stagiaires au
lieu de réaliser leurs propres tâches ». Passer à 3 augmente cette charge de 50 % pour chaque
tuteur, et les deux propriétaires sont aussi les seuls exécutants des contrats (§ 7bis.2.1). Le
risque n'est pas théorique : c'est celui qui a déjà été constaté.

**Atténuation intégrée au produit :** le regroupement des demandes de stagiaires en créneaux de
suivi (§ 8/I.11) devient d'autant plus important, et l'indicateur « stagiaires par tuteur » du
tableau de bord direction doit signaler visuellement un tuteur à 3 stagiaires. Si la charge se
révèle excessive à l'usage, le paramètre permet de revenir à 2 sans développement.

---

### C7 — Le rôle Auditeur existe au § 6.7 mais pas dans la matrice § 7 🟡 Mineur

**Proposition.** Le rôle est marqué « optionnel ». **Hors MVP.** Le modèle de permissions doit
toutefois permettre de créer plus tard un rôle strictement lecture seule sans refonte — c'est une
contrainte pour l'architecte, pas une fonctionnalité du MVP.

---

### C8 — La Finance consulte le journal d'audit « Finance seulement » 🟠 Important

**Contradiction implicite.** La matrice § 7 donne au responsable financier l'accès au journal
d'audit du périmètre financier. Or ce journal est précisément le mécanisme qui documente ses
propres actions. Le § 14.1 pose le principe du moindre privilège.

**Proposition.** La Finance accède aux **écritures financières** et à leur historique de
correction, mais **le journal d'audit reste en lecture Direction uniquement**. Un acteur ne doit
pas consulter le registre qui le contrôle. *Décision direction requise.*

---

### C9 — Le niveau rouge « gèle les recrutements » sans module de recrutement 🟡 Mineur

**Contradiction.** Le niveau rouge déclenche « gel des recrutements, dépenses non essentielles et
partages de bénéfices ». Le recrutement complet est en phase 2 et la notion de « dépense non
essentielle » n'est définie nulle part.

**Proposition.** En MVP, le niveau rouge **bloque effectivement** : l'activation de tout nouveau
compte employé/stagiaire, et l'enregistrement d'un partage de bénéfices. Il **signale sans
bloquer** les dépenses, avec un avertissement explicite à l'approbateur. Ajouter à l'Étape 4 un
marqueur booléen « dépense essentielle » sur les catégories de dépense, paramétrable au § S.

---

### C10 — Assiette du seuil d'alerte ✅ Résolu (direction, 18/07/2026)

**Décision de la direction.** L'alerte se déclenche quand les encaissements du mois ne couvrent pas
les **charges fixes réellement supportées par l'entreprise**. Aujourd'hui, elles sont au nombre de
quatre :

1. Loyer
2. Électricité
3. Internet
4. Salaires

Les autres postes envisagés dans le brief d'entrée (eau, charges sociales, taxes, logiciels,
transport) **n'existent pas actuellement** chez PTR Niger. La direction pourra les ajouter plus tard.

**Règle à implémenter — la liste doit être paramétrable, pas codée en dur.**

```
Assiette d'alerte = somme des charges fixes ACTIVES déclarées au paramétrage (module S)

Vert   : encaissements du mois ≥ assiette
Orange : 1 mois où encaissements < assiette   → plan correctif sous 48 h
Rouge  : 2 mois consécutifs                   → voir C9
```

C'est le point important pour le PRD : les quatre postes ci-dessus sont les **valeurs initiales**
d'une liste que la direction administre elle-même. Ajouter « taxes » ou « transport » plus tard doit
être une saisie dans les paramètres, **jamais une modification de code**. Le module S du brief
d'entrée prévoit déjà « catégories de dépenses et charges » — c'est là que cela se règle.

**Exclusion confirmée.** Les **coûts directs de projet** n'entrent pas dans l'assiette : ils sont
censés être couverts par le revenu du projet lui-même.

**Non modifié.** La **réserve** reste indexée sur ces mêmes charges fixes, avec un objectif de trois
mois de couverture (décision prise, § 17). Conséquence mécanique à signaler : **si la direction
ajoute une charge fixe plus tard, l'objectif de réserve augmente automatiquement** et le
prélèvement de 20 % peut redémarrer. C'est le comportement attendu, mais il doit être affiché
clairement pour ne pas surprendre.

---

### C11 — Le numéro de téléphone est identifiant unique et permanent 🟡 Mineur

**Tension.** Le § 8/A.4 impose l'unicité du numéro ; le § 14.8 impose que les comptes sortis soient
archivés et jamais réutilisés. Au Niger, un numéro peut être recyclé par l'opérateur, et une même
personne peut revenir dans l'entreprise après une sortie.

**Proposition.** Contrainte d'unicité **sur les comptes actifs uniquement**. Un compte archivé
libère son numéro pour un nouveau compte, en conservant l'historique. Le retour d'une personne crée
un **nouveau compte** rattaché à la même fiche personne. *Point à confirmer avec la direction.*

---

### C12 — L'heure limite du rapport précède de 15 minutes la fin de journée 🟡 Observation

Horaires par défaut 14 h–18 h, rapport dû à 17 h 45. La fenêtre est étroite, et le rappel
doit donc partir suffisamment tôt. Ce n'est pas une contradiction — l'heure limite est un
paramètre (§ S.3) — mais le PRD doit spécifier **le délai du rappel** (proposition : 60 minutes
avant l'heure limite, paramétrable) plutôt que de le laisser à l'implémentation.

---

### C13 — Le super administrateur « ne doit pas modifier silencieusement » sans mécanisme 🟡 Mineur

**Tension.** Le § 6.1 pose l'interdiction mais aucun mécanisme ne la garantit : un administrateur
technique dispose par nature de l'accès à la base.

**Proposition.** Traiter cela comme une exigence d'architecture : le super admin **n'a aucune
permission métier par défaut** (pas d'approbation, pas de validation financière), et toute
attribution de permission à un compte est elle-même auditée. La protection contre l'accès direct
à la base relève de la procédure d'exploitation, pas du logiciel — à documenter comme tel.

---

### C14 — Approbation des dépenses ✅ Résolu (direction, 18/07/2026)

**Décision de la direction.** **Deux signatures pour toutes les dépenses**, sans exception de
montant. Le seuil de 25 000 FCFA est **abandonné**.

**Règle à implémenter.** Toute demande de dépense, quel que soit son montant et qu'elle soit ou non
prévue au budget, exige l'approbation des **deux comptes associés distincts**. Aucune dérogation,
aucune délégation. Le demandeur ne peut jamais compter comme approbateur de sa propre demande.

**Conséquences à répercuter dans le PRD :**

1. Le § 17 du brief d'entrée (« Seuil de double approbation : plus de 25 000 FCFA ») est
   **remplacé** par cette décision.
2. Le **critère d'acceptation 9** du § 16 (« Une dépense prévue jusqu'à 25 000 FCFA demande une
   validation autorisée ») devient **caduc** et doit être réécrit en « toute dépense demande deux
   approbateurs distincts ».
3. Le paramètre « seuil d'approbation » du module S disparaît, ou reste présent avec la valeur
   0 FCFA pour un éventuel assouplissement futur.
4. La contradiction C1 (« un responsable autorisé ») **disparaît d'elle-même** : il n'y a plus
   d'approbation simple. Seule subsiste la permission `approuver_depense`, détenue par les deux
   associés.

**⚠️ Risque assumé — à surveiller (R2).** L'indisponibilité d'un seul associé gèle **tous** les
achats, y compris un achat de 1 000 FCFA. Le comportement de contournement attendu est le paiement
personnel suivi d'une demande de remboursement — qui échappe au circuit. Le PRD doit donc prévoir :

- une **notification insistante** vers l'approbateur manquant (rappel à J+1, J+2) ;
- un écran **« en attente de mon approbation »** en tête du tableau de bord des associés ;
- une **procédure de remboursement** explicite et tracée, pour que le paiement personnel — s'il
  survient malgré tout — rentre dans le circuit au lieu de rester invisible.

---

### C15 — Nature des parts 10 % / 30 % ✅ Résolu (direction, 18/07/2026)

**Décision de la direction.** Les parts de 10 % (apporteur) et 30 % (exécutants) sont dues
**en toutes circonstances**, quels que soient les résultats de l'entreprise. PTR Niger ne perçoit
jamais plus que ses 60 %. *Motif énoncé par la direction : encourager tout le monde, associés
compris, à aller chercher des contrats.*

**Qualification retenue.** Ces parts sont donc une **charge variable liée au contrat** (un coût de
vente et de production), et **non un partage de bénéfices**. Conséquence directe : les interdictions
du § 8/L (« partage interdit si les charges ne sont pas réglées, si la réserve n'est pas
constituée ») **ne s'appliquent pas** à ces parts. Elles restent dues même en alerte rouge.

**Le module « Partage de bénéfices » est retiré du périmètre ✅ (direction, 18/07/2026).** Il
n'a plus d'objet : la répartition 10 / 60 / 30 étant automatique et permanente, il n'existe plus
d'opération distincte de distribution de bénéfices. Le § 8/L du brief d'entrée (« Partage de
bénéfices », 5 règles) est **supprimé du MVP et de la phase 2**.

**Ce qui le remplace, et pourquoi c'est mieux.** Si un associé souhaite retirer de l'argent des
60 % qui appartiennent à PTR Niger, ce retrait est une **dépense ordinaire** : demande, motif,
montant, justificatif, **deux signatures** (C14) et inscription au journal d'audit. Aucun mécanisme
particulier n'est nécessaire.

C'est un gain de sécurité, pas une simplification de confort : le problème 7 du brief d'entrée
(« l'argent reçu pour des projets était parfois dépensé personnellement par les dirigeants ») est
traité par le circuit de dépense normal, sans porte dérobée réservée aux associés.

**⚠️ Risque de trésorerie à surveiller (nouveau R11).** 40 % de chaque bénéfice sortent de
l'entreprise quelle que soit sa santé financière. En période difficile, l'entreprise paie les parts
et se retrouve avec 60 % pour couvrir 100 % de ses charges fixes. C'est un choix assumé de la
direction, mais il rend la réserve **plus nécessaire, pas moins**.

**Garde-fou ✅ adopté par la direction (18/07/2026).** Les parts ne se versent qu'**au moment de
l'encaissement réel**, jamais à la facturation, et **au prorata** si le client paie en plusieurs
fois. Détail et exemple chiffré au **§ 7bis.4bis**. L'entreprise ne distribue ainsi jamais d'argent
qu'elle n'a pas reçu.

---

### C16 — Revenus récurrents SaaS ✅ Résolu (direction, 18/07/2026)

**Décision de la direction.** Deux métiers commerciaux distincts, deux taux, tous deux **récurrents
chaque mois** jusqu'à la fin de l'abonnement :

| Origine du client | Bénéficiaire | Part | PTR Niger |
|---|---|---:|---:|
| Démarchage actif | Commercial | 30 % / mois | 70 % |
| Publicité entrante | Communicateur | 10 % / mois | 90 % |
| Contact direct spontané | — | — | 100 % |

Il n'y a pas de part « exécutant » sur les abonnements. Détail complet au **§ 7bis.3**.

**Recommandation de l'analyste — maintenue : automatisation hors MVP.** Le suivi automatique
demande de nouveaux objets (*produit SaaS*, *abonnement client*, *échéance mensuelle*, *commission
due*) et un mécanisme qui génère la commission chaque mois tant que l'abonnement vit. En MVP,
l'abonnement encaissé est saisi comme un encaissement ordinaire et la commission comme une dépense
ordinaire, calculée hors application. L'automatisation rejoint la **phase 2**, avec le module
Clients et ventes complet. *Confirmation direction attendue — non bloquante.*

---

## 7bis. Modèle économique et répartition des bénéfices

> **Source :** réponses de la direction du 18 juillet 2026. **Cette section est nouvelle** — elle
> ne figure pas dans le brief d'entrée v1.0. Elle est consignée ici telle que décrite, sans
> interprétation. Les zones grises sont signalées et reprises en questions au § 12.

### 7bis.1 Rôles économiques

| Rôle économique | Description |
|---|---|
| **Apporteur d'affaires** | Celui qui trouve le client, porte la relation et obtient le contrat. Peut être un associé ou un membre de l'équipe. |
| **Exécutant** | Celui ou ceux qui réalisent effectivement le travail. |
| **PTR Niger** | L'entreprise elle-même — c'est sa part qui alimente les charges et la réserve. |
| **Commercial SaaS** | Prospecte pour les produits SaaS de l'entreprise et amène des abonnés. |

Ces rôles sont **économiques**, pas hiérarchiques : ils s'ajoutent aux rôles applicatifs du § 4 et
peuvent être cumulés. Une même personne peut être apporteur sur un contrat et exécutant sur un
autre.

**Qui peut percevoir une part ✅ (direction, 18/07/2026)**

| Statut | Part exécutant (30 %) | Rémunération |
|---|---|---|
| **Associé propriétaire** | ✅ Oui | Parts 10 % / 30 % + dividendes éventuels |
| **Employé** | ❌ Non | **Salaire** uniquement |
| **Stagiaire** | ❌ Non | **Non rémunéré par principe.** Gratification possible, à la seule décision des deux propriétaires (voir § 7bis.1.1) |

Les employés et stagiaires travaillent **sous la tutelle des deux propriétaires**. La part
exécutant de 30 % est réservée aux associés, quelle que soit la personne ayant matériellement
réalisé le travail.

### 7bis.1.1 Gratification de stagiaire ✅ *(direction, 18/07/2026)*

Un stagiaire n'est pas rémunéré par principe. Les propriétaires peuvent néanmoins décider de le
gratifier. **Dans ce cas :**

1. Le versement est **obligatoirement enregistré dans l'application**.
2. Il passe par une **demande d'autorisation** approuvée par **les deux propriétaires** (cohérent
   avec C14 : deux signatures pour toute dépense).
3. Il porte un motif, un montant, un bénéficiaire et un justificatif.

**Exigence pour le PRD :** prévoir une catégorie de dépense **« gratification de stagiaire »**
distincte des salaires. Coût d'implémentation faible — c'est une dépense ordinaire dans le circuit
existant — mais l'enjeu est réel : c'est exactement le type de versement informel qui échappait
au suivi par le passé.

> ⚠️ **Point à vérifier hors application.** Le cadre légal nigérien applicable aux conventions de
> stage (gratification obligatoire ou non selon la durée, charges éventuelles) n'entre pas dans le
> périmètre logiciel, mais l'application conservera la trace de ces engagements. Il vaut mieux que
> la règle interne soit conforme avant d'être outillée. → **Q12bis**

### 7bis.2 Répartition sur les contrats de prestation

**Cas 1 — contrat apporté et exécuté** *(cas nominal)*

| Bénéficiaire | Part |
|---|---:|
| Apporteur d'affaires | **10 %** |
| PTR Niger | **60 %** |
| Exécutant(s) | **30 %** |

**Cas 2 — contrat apporté, sans travail d'exécution** ✅ *défini par la direction le 18/07/2026*

| Bénéficiaire | Part |
|---|---:|
| Apporteur d'affaires | **10 %** |
| PTR Niger | **90 %** |

**Définition retenue de « sans exécution ».** La prestation se limite à une **mise en place ou une
installation d'un produit existant**, sans travail de création.

> **Exemple donné par la direction.** Un commerçant voit la publicité réalisée par un communicateur
> de PTR Niger et contacte l'entreprise pour obtenir une application. PTR Niger la lui installe.
> Le communicateur qui a produit la publicité ayant amené le client compte comme **apporteur**
> (10 %) ; l'installation ne constituant pas un travail de création, il n'y a pas de part
> exécutant, et PTR Niger perçoit **90 %**.

**Enseignement à retenir pour le PRD :** un client attiré par une action de communication d'un
membre de l'équipe **a un apporteur**. L'apporteur n'est donc pas seulement celui qui négocie en
face à face, c'est aussi celui dont le travail de communication a produit le contact.

**Cas 3 — aucun apporteur** ✅ *confirmé par la direction le 18/07/2026*

| Bénéficiaire | Part |
|---|---:|
| PTR Niger | **100 %** |

**Quand ce cas s'applique.** Le client contacte PTR Niger **de sa propre initiative**, sans qu'aucun
membre de l'équipe ne l'ait amené. Exemple donné : une personne demande directement un abonnement.

**Règle de distinction — c'est le point à retenir pour le PRD :**

| Origine du client | Apporteur | Répartition |
|---|---|---|
| Le client vient **seul**, sans intermédiaire | Aucun | **100 % PTR Niger** |
| Le client a **contacté un communicateur** de PTR Niger, ou vient d'une action de communication | Ce communicateur | **10 % / 90 %** |

Chaque contrat porte donc un champ **« apporteur »** qui peut être **vide**. Vide → 100 % PTR Niger.
Rempli → 10 % à cette personne. C'est ce seul champ qui détermine la répartition, ce qui rend la
règle simple à implémenter et facile à auditer.

### 7bis.2.1 Partage des 30 % entre plusieurs exécutants ✅ *résolu*

**Décision de la direction.** Les 30 % se partagent en **parts égales**. Trois exécutants →
**10 % chacun**.

**Situation réelle aujourd'hui.** Le cas ne se présente pas : PTR Niger ne compte que **deux
associés**, et l'exécution est toujours assurée par **un seul d'entre eux, en entier**, selon la
nature du travail :

| Nature du travail | Exécutant |
|---|---|
| Création d'applications | Un associé (le seul à savoir le faire) |
| Impression de casquettes, vêtements | L'autre associé, seul également |

✅ **Résolu (direction, 18/07/2026).** Les **employés et stagiaires ne perçoivent aucune part** des
30 %, même s'ils exécutent matériellement le travail. La part exécutant est réservée aux deux
associés. Voir § 7bis.1 pour le tableau complet des rémunérations par statut.

✅ **Périmètre confirmé (direction, 18/07/2026).** L'impression de casquettes et de vêtements est
une activité de production physique, mais **aucune gestion de stock n'est demandée**. Les achats
de matière première sont enregistrés comme des **dépenses ordinaires**, dans le circuit normal à
deux signatures. Pas de module d'inventaire, ni en MVP ni en phase 2.

### 7bis.3 Commission récurrente sur les abonnements SaaS ✅ *(direction, 18/07/2026)*

**Deux métiers distincts, deux taux distincts.** Le taux dépend de l'effort commercial fourni :

| Origine du client | Bénéficiaire | Part | Périodicité |
|---|---|---:|---|
| **Démarchage actif** — le commercial va chercher le client et le convainc | Commercial | **30 %** | Chaque mois |
| **Publicité entrante** — le client vient seul après avoir vu la communication | Communicateur | **10 %** | Chaque mois |
| **Aucun intermédiaire** — le client contacte PTR Niger de lui-même | — | **0 %** | — |

Dans les trois cas, **PTR Niger perçoit le solde** : 70 %, 90 % ou 100 %.

**Les deux commissions sont récurrentes** et courent **chaque mois jusqu'à la fin de l'abonnement
du client**. Il n'y a pas de part « exécutant » sur les abonnements.

**Logique retenue.** L'écart 30 % / 10 % reflète l'effort : démarcher, convaincre et conclure n'est
pas la même chose que produire une communication qui fait venir un contact. La distinction est
portée par le **rôle économique attaché au contrat d'abonnement**, pas par la personne : une même
personne peut être commercial sur un abonnement et communicateur sur un autre.

**Exigences pour le PRD :**

1. Chaque abonnement porte un champ **origine** : `démarchage` (30 %), `publicité` (10 %) ou
   `direct` (0 %), et le **bénéficiaire** correspondant lorsqu'il y en a un.
2. Chaque **encaissement mensuel** d'abonnement déclenche le calcul de la commission au taux de
   l'origine.
3. La commission suit la règle du § 7bis.4bis : elle n'est due qu'**à l'encaissement réel**. Un mois
   d'abonnement impayé ne génère aucune commission.
4. Le versement passe par le circuit de dépense à **deux signatures** (C14).
5. L'écran de l'abonnement affiche : montant mensuel, origine, bénéficiaire, taux, total déjà versé,
   date de fin si connue.

### 7bis.3.1 La commission survit au départ du bénéficiaire ✅ *(direction, 18/07/2026)*

**Décision.** La commission continue d'être versée **même si la personne quitte PTR Niger**. Elle
est attachée à l'abonnement, pas au statut de la personne dans l'entreprise. Tant que le client
paie, le bénéficiaire perçoit sa part.

**C'est une décision structurante, pas un détail.** Elle crée un engagement financier qui peut
courir des années après le départ d'un collaborateur, et elle a trois conséquences que le PRD doit
absolument traiter :

1. **Le compte est archivé, la personne ne l'est pas.** Le § 14.8 du brief d'entrée impose
   d'archiver les comptes sortis. Mais un bénéficiaire archivé doit continuer à apparaître dans les
   calculs de commission. Le modèle de données doit donc séparer **la personne** (permanente, porteuse
   des droits financiers) du **compte applicatif** (qui peut être fermé). Une commission ne doit
   jamais disparaître parce qu'un compte a été désactivé.
2. **Un ancien collaborateur doit être payable.** Il faut conserver ses coordonnées de paiement et
   pouvoir lui verser sa part chaque mois, alors qu'il n'a plus accès à l'application. Le PRD doit
   prévoir comment il reçoit l'information de ce qui lui est dû — relevé envoyé, ou accès en lecture
   seule strictement limité à ses commissions.
3. **L'engagement doit exister sur papier avant d'exister dans le logiciel.** Une commission
   perpétuelle promise oralement est une source de litige garantie le jour où la relation se
   termine mal. L'application doit conserver le **document d'engagement signé** rattaché à
   l'abonnement (module N, documents internes).

> ⚠️ **Risque à surveiller (R14).** Le cumul de ces engagements réduit durablement la marge de
> PTR Niger sur ses revenus récurrents. Si dix abonnements portent chacun 30 % de commission
> perpétuelle, l'entreprise ne conserve que 70 % d'un revenu qu'elle produit et maintient seule.
> Le tableau de bord financier doit afficher le **total des commissions récurrentes engagées**,
> pour que la direction voie cet engagement grandir.

> ⚠️ **Reste ouvert (non bloquant).** Le bénéficiaire est-il **salarié** (commission s'ajoutant au
> salaire, avec charges sociales) ou **externe** (prestataire qui facture sa commission) ? Et le
> taux porte-t-il sur le **montant encaissé** de l'abonnement — lecture retenue par défaut — ou sur
> son **bénéfice** après coûts d'hébergement ? → **Q3ter**

### 7bis.4 Articulation avec la réserve — **résolue dans son principe**

**Question posée par la direction : « les réserves, ça sortent d'où ? »**

Réponse consignée ici parce qu'elle conditionne tout le module financier.

**La réserve n'est pas une quatrième part.** Les trois parts (10 % / 60 % / 30 %) épuisent 100 % du
bénéfice. La réserve se prélève **à l'intérieur des 60 % qui reviennent à PTR Niger** — c'est-à-dire
sur l'argent de fonctionnement de l'entreprise, jamais sur les parts versées aux personnes.

```
Bénéfice du contrat : 1 000 000 FCFA

  Apporteur          10 %  →   100 000   (versé à la personne — intouchable)
  Exécutants         30 %  →   300 000   (versé aux personnes — intouchable)
  PTR NIGER          60 %  →   600 000
                                  │
                    ┌─────────────┴─────────────┐
              Réserve                     Fonctionnement
              200 000                        400 000
        (épargne bloquée)        (loyer, électricité, Internet,
                                  salaires, matériel, impôts)
```

**À quoi sert la réserve.** À couvrir **trois mois de charges fixes** sans aucune rentrée d'argent.
C'est la réponse directe aux problèmes 6 et 9 du brief d'entrée (« plusieurs mois sans couvrir les
charges », « l'entreprise a déjà dû fermer par manque de trésorerie »). Ce qu'elle achète n'est pas
de l'argent, c'est **du temps** : trois mois pour chercher des clients sans devoir licencier ni
accepter n'importe quel contrat dans l'urgence.

**Le prélèvement s'arrête** dès que l'objectif de trois mois de charges est atteint. Il redémarre
si la réserve est entamée. C'est un effort temporaire, pas une ponction permanente.

**Base de calcul retenue ✅ (direction, 18/07/2026) : 20 % du bénéfice total du contrat.**

Sur un bénéfice de 1 000 000 FCFA, la réserve reçoit **200 000 FCFA**, prélevés sur les 600 000 qui
reviennent à PTR Niger — il reste donc **400 000 FCFA** de fonctionnement. Les parts de 10 % et
30 % ne sont pas touchées.

### 7bis.4bis Versement des parts au rythme des encaissements ✅ *(direction, 18/07/2026)*

**Décision.** Les parts se versent **au moment où le client paie réellement**, jamais à la
facturation. Si le client paie en plusieurs fois, **les parts suivent au prorata**.

**Cas courant chez PTR Niger :** moitié à la commande, moitié à la livraison.

```
Contrat de 1 000 000 FCFA de bénéfice, payé en deux fois

── Encaissement 1 : 50 % à la commande ────────────────
   Apporteur      50 000        (moitié de ses 10 %)
   Exécutant     150 000        (moitié de ses 30 %)
   PTR Niger     300 000  → dont réserve 100 000

── Encaissement 2 : 50 % à la livraison ───────────────
   Apporteur      50 000        (le solde)
   Exécutant     150 000        (le solde)
   PTR Niger     300 000  → dont réserve 100 000
```

**Protection obtenue.** Un contrat facturé mais jamais payé ne fait sortir **aucun** argent. Un
contrat payé à moitié puis abandonné n'a fait sortir que la moitié des parts. L'entreprise ne
distribue jamais d'argent qu'elle n'a pas reçu.

**Exigences pour le PRD :**

1. Un contrat porte un **bénéfice total prévu** et une **répartition prévue** (10 / 60 / 30 ou
   10 / 90).
2. Chaque **encaissement** rattaché au contrat déclenche le calcul des parts correspondantes, au
   prorata du montant reçu sur le total attendu.
3. L'écran du contrat affiche en permanence : total attendu, total encaissé, parts déjà versées,
   parts restant à verser.
4. Chaque versement de part passe par le **circuit de dépense à deux signatures** (C14), avec la
   base de calcul affichée.

### 7bis.5 Ce que l'application doit garantir, quelle que soit l'option retenue

Ces exigences découlent directement du problème 7 du brief d'entrée et ne sont pas négociables :

1. Chaque versement de part ou de commission est **enregistré comme une dépense**, avec bénéficiaire,
   contrat d'origine, base de calcul, taux appliqué et justificatif.
2. Il passe par le **circuit d'approbation** normal — **y compris quand le bénéficiaire est un
   associé**. Un associé ne peut pas être seul approbateur de sa propre part (§ 8/L, règle 7).
3. Il apparaît au **journal d'audit** et au **rapport financier mensuel**.
4. Le calcul est **affiché avec sa méthode** à l'écran : bénéfice retenu, période, taux, montant.
   Un calcul opaque sur ce sujet ruinerait la confiance entre associés.

---

## 8. Considérations techniques

### 8.1 Plateformes et performance

- **Cible :** application web responsive **mobile-first**, servie sur `staff.ptrniger.com`.
- **Navigateurs :** Chrome Android (priorité 1), Chrome desktop, Safari récent.
- **Performance :** utilisable sur 3G instable. Objectif indicatif à confirmer par l'architecte —
  premier rendu utile < 3 s sur 3G, poids de page initiale contenu, pas de dépendance à des CDN
  externes non maîtrisés.
- **Sauvegarde automatique des brouillons** sur le rapport quotidien et les formulaires longs.
- **Langue :** français simple, textes courts. Fuseau `Africa/Niamey`. Montants **XOF sans
  décimales** — à stocker en entier, jamais en flottant.

### 8.2 Préférences technologiques (imposées)

- **Backend :** **Laravel 13 / PHP 8.3**. Structure « slim » (middleware, exceptions et routes dans
  `bootstrap/app.php`).
- **Frontend :** **Vue 3 intégré dans Laravel** — application unique, pas de SPA séparée ni d'API
  publique distincte. Build via **Vite 8**, styles **Tailwind CSS 4** (config CSS-first).
- **Base de données :** SQLite en développement ; **le choix de production reste à trancher par
  l'architecte** (`docs/architecture/tech-stack.md`, § « À décider »).
- **Hébergement et sauvegardes :** non tranchés — voir question ouverte Q13.

> **Point d'attention pour l'architecte.** « Vue intégré dans Laravel » admet deux mises en œuvre :
> Inertia.js (pages Vue rendues par des contrôleurs Laravel, sans API) ou composants Vue montés
> dans des vues Blade. La première convient à une application riche en formulaires et en
> permissions ; la seconde reste plus légère au téléchargement. **Ce choix appartient à l'agent
> Architect**, avec le poids de page en 3G comme critère principal.

### 8.3 Considérations d'architecture

- **Structure de dépôt :** monodépôt Laravel unique. Pas de séparation front/back.
- **Modularité :** l'application couvre 4 domaines faiblement couplés (Identité & organisation,
  Redevabilité, Finance, Encadrement). Un découpage en modules internes est recommandé pour
  contenir la complexité — sans introduire de microservices, hors de proportion ici.
- **Intégrations MVP :** **aucune.** Pas de banque, pas de Mobile Money, pas de SMS, pas de
  WhatsApp. Toutes les intégrations sont en phase 2 ou hors périmètre.
- **Pièces jointes :** stockage privé, jamais exposé par URL publique ; accès via lien signé à
  durée limitée ou contrôle d'autorisation serveur.
- **Sécurité et conformité :**
  - HTTPS obligatoire, mots de passe hachés par algorithme moderne.
  - Protection brute force, injection, XSS, CSRF, accès direct aux fichiers.
  - **Autorisation systématiquement côté serveur**, testée par URL directe (critère
    d'acceptation 2).
  - Journal d'audit en écriture seule depuis l'application.
  - Sauvegarde quotidienne base + pièces jointes, **avec test de restauration régulier** — cette
    seconde partie est souvent omise et doit être une tâche planifiée, pas une intention.
- **Capacité :** 5 à 100 utilisateurs sans refonte. Pas de multi-entreprise.
- **Contrainte transversale :** l'immuabilité (P2) et l'audit (§ 8/R) doivent être conçus **au
  niveau du modèle de données dès l'Étape 1**. Les ajouter après coup impose une reprise complète.

---

## 9. Contraintes et hypothèses

### 9.1 Contraintes

- **Budget :** non communiqué. Hypothèse de travail : développement interne, aucun budget d'outil
  tiers payant en MVP (contrainte cohérente avec le report du SMS/WhatsApp pour raison de coût).
- **Calendrier :** non communiqué. Hypothèse : mise en service par étapes successives, dans l'ordre A → S retenu par la direction.
- **Ressources :** petite équipe de développement. C'est la contrainte structurante : elle justifie
  le séquencement en étapes et l'absence d'intégrations externes.
- **Technique :** Laravel + Vue intégré imposés ; hébergement et base de production non tranchés ;
  connexion utilisateur potentiellement instable ; usage téléphone majoritaire.
- **Organisationnelle :** l'effectif réduit rend structurellement difficile la séparation des
  tâches exigée par les règles financières (voir C2 et C3). C'est une contrainte réelle, pas un
  détail d'implémentation.

### 9.2 Hypothèses clés

Chacune est à confirmer ; une hypothèse fausse change le périmètre.

- **H1 —** ✅ Confirmé — l'équipe au lancement compte **2 associés propriétaires** plus quelques
  employés et stagiaires. Effectif estimé sous 15 personnes. *(précision attendue : Q5bis)*
- **H2 —** ✅ Confirmé — deux comptes associés distincts détiennent l'approbation financière et se
  contrôlent mutuellement. Aucune dérogation en cas d'indisponibilité de l'un d'eux.
- **H3 —** Tous les utilisateurs disposent d'un téléphone Android avec navigateur récent et d'un
  accès Internet quotidien au bureau. *(question ouverte Q12)*
- **H4 —** La direction assumera la réinitialisation manuelle des mots de passe en MVP.
  *(question ouverte Q3)*
- **H5 —** Les factures sont produites hors de PTR Staff en MVP et seulement enregistrées ici.
  *(à confirmer — impacte directement C6)*
- **H8 —** Les commissions SaaS récurrentes sont calculées hors application en MVP et saisies comme
  des dépenses ordinaires. *(à confirmer — voir C16 et Q3ter)*
- **H9 —** L'indisponibilité d'un associé restera courte, de sorte que le gel de **tous** les
  achats induit par la règle des deux signatures (C14) ne bloque pas l'activité. *C'est
  l'hypothèse la plus fragile du document — voir R12.*
- **H6 —** La direction imposera l'usage de l'application ; l'adoption n'a pas besoin d'être
  incitative, mais elle doit rester rapide.
- **H7 —** Les données du personnel et les justificatifs financiers doivent être conservés au moins
  10 ans (durée usuelle en matière comptable). *À confirmer — voir Q11.*

---

## 10. Risques

Classement par **impact × probabilité**, du plus critique au moins critique.

| # | Risque | Description et impact | Atténuation proposée |
|---|---|---|---|
| R1 | **Abandon du rapport quotidien** | Si la saisie dépasse 3 minutes ou échoue en 3G, l'équipe cesse de rendre compte en 2 à 3 semaines. Toute l'Étape 3 perd sa valeur. | Formulaire court, brouillon auto-sauvegardé, pré-remplissage depuis les tâches du jour, test réel sur téléphone en conditions dégradées avant mise en service. |
| R2 | **Contournement du circuit de dépense** | Si l'approbation est lente ou bloquée (C2), les dépenses repartent hors application. D3 réapparaît intégralement. | Approbation en 3 taps depuis notification ; procédure d'exception tracée plutôt que blocage ; revue mensuelle des approbations dérogatoires. |
| R3 | **Séparation des tâches fictive** | Avec un effectif réduit, la même personne prépare, contrôle et approuve. Le contrôle devient décoratif et le problème 7 redevient possible. | Contraintes système sur comptes distincts (C2, C3) ; à défaut, contrôle par la direction avec trace explicite. |
| R4 | **Périmètre MVP trop large** | 17 blocs livrés d'un bloc → mise en service tardive, D3/D4 non traités pendant tout le développement. | Livraison en 4 étapes utilisables. La direction ayant placé l'argent en dernier, appliquer l'atténuation du § 6.1 (audit + registre d'approbation des dépenses dès l'Étape 1). |
| R5 | **Perte de données ou de justificatifs** | Sauvegardes non testées, pièces jointes perdues → l'entreprise perd sa preuve comptable et légale. | Sauvegarde quotidienne base + fichiers, **test de restauration planifié et documenté**, hébergement à trancher tôt (Q13). |
| R6 | **Dérive de surveillance** | Perçu comme un outil de flicage, le produit génère de la résistance. Le brief d'entrée l'interdit explicitement (pas de géoloc, pas de classement public). | Vocabulaire de l'interface orienté contribution ; aucun classement ; symétrie hiérarchique (P5) visible et vérifiable par tous. |
| R7 | **Fuite de données financières ou personnelles** | Un accès mal contrôlé expose salaires, dossiers du personnel, comptes. Impact humain et juridique. | Autorisation serveur systématique, tests d'accès par URL directe, pièces jointes non publiques, audit des exports. |
| R8 | **Calcul de réserve erroné** | Si la marge nette est calculée sur des données incomplètes (C6), la réserve et le nombre de mois couverts sont faux — l'alerte qui doit sauver l'entreprise devient mensongère. | Trancher C6 avant le PRD financier ; afficher la méthode de calcul et la période source à l'écran. |
| R9 | **Contrôle d'accès contourné par le super admin** | Accès technique illimité sans garde-fou. | Aucune permission métier par défaut, attribution de permissions auditée, procédure d'exploitation écrite (C13). |
| R11 | **Sortie de trésorerie insensible à la santé de l'entreprise** | 40 % de chaque bénéfice sortent quels que soient les résultats (C15). En période difficile, l'entreprise paie les parts et couvre 100 % de ses charges avec 60 % du bénéfice. | Choix assumé de la direction. Rend la réserve **plus** nécessaire. Garde-fou **adopté** : versement des parts à l'encaissement réel uniquement, au prorata (§ 7bis.4bis). |
| R14 | **Engagements de commission perpétuels** | Les commissions SaaS survivent au départ du bénéficiaire (§ 7bis.3.1). Cumulés, ces engagements réduisent durablement la marge sur les revenus récurrents, y compris envers des personnes qui ne travaillent plus pour l'entreprise. | Document d'engagement signé rattaché à chaque abonnement ; affichage du **total des commissions récurrentes engagées** au tableau de bord financier ; modèle de données séparant la personne du compte applicatif. |
| R13 | **Asymétrie effort / récompense** | Employés et stagiaires produisent, prouvent et sont évalués quotidiennement, mais aucune part variable ne leur revient (§ 7bis.1). Le brief d'entrée cite pourtant la démotivation comme problème 10. | L'application ne doit pas exposer la répartition aux non-associés (elle ne le fait pas : § 7 matrice d'accès). Compenser par la reconnaissance du travail — objectifs atteints, preuves, évaluations positives visibles par l'intéressé — et par la gratification discrétionnaire tracée (§ 7bis.1.1). |
| R12 | **Gel total des achats** | Deux signatures obligatoires pour tout montant (C14) : l'absence d'un associé bloque même un achat de 1 000 FCFA. Contournement attendu par paiement personnel. | Notifications insistantes, écran « en attente de mon approbation », procédure de remboursement tracée. |
| R10 | **Indicateurs faussés par les absences** | Sans notion d'absence (C4), les taux de ponctualité sont faux et l'outil produit des reproches injustifiés. | Inclure le minimum absence/jours travaillés à l'Étape 1. |

---

## 11. Vision post-MVP

### 11.1 Phase 2

Par ordre de valeur décroissante, telle que je la recommande :

1. **Présence complète** — pointage, retards, calendrier d'équipe, télétravail (dépend de Q10).
2. **Clients et ventes complets** — prospects, devis, factures générées, relances de créances,
   objectifs commerciaux.
3. **Exports PDF et Excel** — documents officiels et rapports formels.
4. **Réunions et décisions** — dont la réunion de direction du vendredi.
5. **Workflow de recrutement complet** — circuit multi-états, coût et financement.
6. **Notifications SMS / WhatsApp** — après choix du fournisseur et arbitrage de coût.
7. **Authentification renforcée (2FA)** pour direction et finance ; réinitialisation par OTP SMS.
8. **PWA installable et brouillons hors ligne** — forte valeur en connexion instable, mais coût
   technique réel : à évaluer après mesure de la gêne effective.
9. **Matériel et accès numériques.**

### 11.2 Vision à 1–2 ans

PTR Staff devient la mémoire opérationnelle de PTR Niger : tout engagement, toute preuve, toute
décision et tout mouvement d'argent y sont consultables sur plusieurs années. La direction pilote
sur des tendances (charge par tuteur, délai de résolution des blocages, saisonnalité de la
trésorerie) et non plus sur des instantanés. Les attestations de stage, dossiers du personnel et
rapports financiers sont produits depuis l'outil sans ressaisie.

### 11.3 Opportunités d'extension

Trois pistes, **explicitement hors périmètre actuel** et mentionnées sans engagement :

- **Paie et déclarations** (CNSS, fiscal) — extension naturelle du module financier, mais forte
  dépendance réglementaire.
- **Intégration Mobile Money** — supprimerait la saisie manuelle des encaissements ; dépend de la
  disponibilité d'API opérateur.
- **Multi-entreprise** — le brief d'entrée l'exclut du MVP. Si cette piste devait être ouverte un
  jour, l'architecte doit le savoir maintenant : la reprise serait coûteuse a posteriori.
  **Question à poser à la direction avant l'architecture** (voir Q15).

---

## 12. Questions ouvertes à trancher avant le PRD

### 12.1 Questions bloquantes — ✅ toutes résolues

**Aucune question bloquante ne subsiste.** Les 17 questions initiales et les 9 questions nées de
l'analyse ont été tranchées par la direction le 18 juillet 2026. Le PRD peut être écrit sans
supposition sur les règles métier.

**Questions résolues par la direction le 18/07/2026 :**

- **Q1** → deux approbateurs, aucune dérogation (C2).
- **Q1bis** → **deux signatures pour toutes les dépenses**, seuil de 25 000 FCFA abandonné (C14).
- **Q2** → les deux associés se contrôlent mutuellement (C3).
- **Q3ter (partiel)** → les parts de 10 % et 30 % sont **toujours dues**, quels que soient les
  résultats. Ce sont des charges variables, pas un partage de bénéfices (C15).
- **Q3septies** → abonnements : **deux métiers, deux taux, tous deux mensuels** — commercial qui
  démarche 30 %, communicateur dont la publicité fait venir le client 10 %, contact spontané 0 %
  (C16 et § 7bis.3).
- **Q4 / Q4bis** → assiette d'alerte = **liste paramétrable de charges fixes**, initialisée à
  loyer + électricité + Internet + salaires. Les autres postes n'existent pas encore chez
  PTR Niger et seront ajoutés par la direction si besoin (C10).
- **Q5 / Q5bis** → au démarrage, **2 propriétaires** uniquement. Des employés seront recrutés
  ensuite et **pourront eux-mêmes encadrer des stagiaires**.
- **Limite d'encadrement** → portée de 2 à **3 stagiaires actifs par tuteur** (§ 6.5bis). Modifie
  une décision prise du brief d'entrée.
- **Atténuation § 6.1** → **acceptée** : journal d'audit et registre d'approbation des dépenses
  remontés à l'Étape 1.
- **Q3** → réserve = **20 % du bénéfice total**, prélevée sur la part de 60 % de PTR Niger
  (§ 7bis.4).
- **Q3quinquies** → parts versées **à l'encaissement réel**, au prorata en cas de paiement
  échelonné (§ 7bis.4bis).
- **Q3bis** → résolu en entier. « Sans exécution » = mise en place ou installation d'un produit
  existant → 10 / 90. Client venu **seul**, sans intermédiaire → **100 % PTR Niger**. Les 30 % se
  partagent en **parts égales** entre exécutants.
- **Q3sexies** → les **employés et stagiaires ne touchent aucune part** des 30 %. Employés :
  salaire. Stagiaires : non rémunérés, gratification possible sur décision des deux propriétaires,
  obligatoirement tracée et doublement approuvée (§ 7bis.1 et § 7bis.1.1).
- **Q6bis** → **aucune gestion de stock**. Les achats de matière première de l'activité impression
  sont des dépenses ordinaires dans le circuit à deux signatures.
- **Départ du bénéficiaire** → les commissions SaaS **continuent après le départ** de la personne
  (§ 7bis.3.1). Conséquence structurante : le modèle de données doit séparer la **personne** du
  **compte applicatif**.
- **Q3quater** → le module **« Partage de bénéfices » est supprimé** du périmètre. Un retrait
  d'associé est une dépense ordinaire à deux signatures (C15).

### 12.2 Questions importantes — réponse requise avant l'architecture

| # | Question | Impact |
|---|---|---|
| **Q3ter** | SaaS : 30 % du **montant encaissé** de l'abonnement ou de son **bénéfice** ? Le commercial est-il salarié ou externe ? La commission continue-t-elle s'il quitte l'entreprise ? | Détermine C16 et l'engagement financier de long terme de l'entreprise. |
| **Q6** | Quels comptes financiers existent exactement : caisse, banque (laquelle), Airtel Money, Moov Money, autre ? | Modèle de données financier et écrans de rapprochement. |
| **Q7** | Quel hébergement et quelle stratégie de sauvegarde/restauration ? | R5. Conditionne aussi le choix de base de production. |
| **Q8** | Le journal d'audit doit-il rester en lecture Direction uniquement, ou la Finance y accède-t-elle sur son périmètre ? | C8. Principe de moindre privilège. |
| **Q9** | La réinitialisation de mot de passe en MVP est-elle assurée par la direction (H4) ? Qui exactement, et selon quelle procédure de vérification d'identité ? | Sécurité du compte, et charge opérationnelle réelle. |
| **Q11** | Quels types de fichiers accepter et quelle taille maximale ? | Stockage, coût de bande passante en 3G, sécurité des téléversements. |

### 12.3 Questions à clarifier — non bloquantes pour démarrer le PRD

| # | Question | Impact |
|---|---|---|
| **Q12bis** | Le cadre légal nigérien impose-t-il une gratification minimale aux stagiaires selon la durée du stage, et avec quelles charges ? | Hors périmètre logiciel, mais l'application tracera ces engagements. La règle interne doit être conforme avant d'être outillée. |
| **Q12** | Quelle durée de conservation pour les données du personnel et les justificatifs financiers ? | Valide H7. Politique d'archivage. |
| **Q13** | Quels appareils et navigateurs sont réellement utilisés par l'équipe aujourd'hui ? | Valide H3 et cadre les tests de compatibilité. |
| **Q14** | Le télétravail est-il autorisé, et comment doit-il être validé ? | Phase 2 (présence), mais influence le modèle d'absence minimal de l'Étape 1. |
| **Q15** | Le multi-entreprise est-il définitivement exclu, y compris à 2 ans ? | Le brief l'exclut du MVP. Une réponse ferme évite une reprise coûteuse plus tard. |
| **Q16** | Le nom visible de l'application sera-t-il « PTR Staff » ou un autre nom ? | Interface, documents générés, communication interne. |
| **Q17** | Un compte archivé peut-il libérer son numéro de téléphone pour un nouveau compte ? | C11. Contrainte d'unicité en base. |

> **Note.** Les questions Q1 à Q17 reprennent et réordonnent les 14 questions du § 18 du brief
> d'entrée, en y ajoutant celles nées de l'analyse des contradictions (Q3, Q4, Q8, Q15, Q17).
> Aucune n'a été supprimée : les questions d'origine 6 (présence en MVP ?) et 7 (facturation)
> sont désormais tranchées par C4 et C6, sous réserve de confirmation via Q3.

---

## 13. Annexes

### A. Synthèse des livrables attendus

Correspondance avec le § 19 du brief d'entrée :

| Livrable attendu | Section de ce document |
|---|---|
| 1. Product Brief final | Ce document |
| 2. Reformulation du problème et de la vision | § 2, § 3 |
| 3. Personas et besoins par rôle | § 4 |
| 4. Périmètre MVP confirmé | § 6 |
| 5. Fonctionnalités par priorité | § 6.2 (étapes 1 à 4) |
| 6. Parcours utilisateurs prioritaires | § 3.1 (boucles) + § 9 du brief d'entrée, inchangé |
| 7. Règles métier validées et contradictions | § 7 (C1 à C13) |
| 8. Risques produit, humains, financiers, sécurité | § 10 (R1 à R10) |
| 9. Indicateurs de réussite | § 5 |
| 10. Questions à valider avant le PRD | § 12 |
| 11. Recommandation de passage au PM | § 14 |

### B. Références

- `docs/Brief_Analyste_BMAD_PTR_Staff.md` — brief d'entrée v1.0, source de toutes les règles métier.
- `docs/architecture/tech-stack.md` — stack technique et décisions en attente.
- `docs/architecture/coding-standards.md` — standards de code.
- `docs/architecture/source-tree.md` — arborescence du projet.

### C. Éléments repris sans modification

Les sections suivantes du brief d'entrée sont reprises **telles quelles** et font foi pour le PRD :
§ 9 (parcours principaux), § 12 (données principales), § 13 (exigences non fonctionnelles),
§ 14 (sécurité).

### D. Éléments du brief d'entrée modifiés par la direction le 18/07/2026

Le PRD doit appliquer ces modifications, et non le texte d'origine :

| Référence d'origine | Modification |
|---|---|
| § 17 — seuil de 25 000 FCFA | **Supprimé.** Deux signatures pour toute dépense (C14). |
| § 16, critère 9 | **Caduc.** À réécrire : « toute dépense demande deux approbateurs distincts ». |
| § 17 et § 8/I.5 — 2 stagiaires par tuteur | **Porté à 3**, paramétrable (C5bis). |
| § 16, critère 4 — « troisième stagiaire » | Devient « **quatrième** stagiaire ». |
| § 5 — indicateur « pas plus de deux stagiaires » | Devient « **trois** ». |
| § 8/L — Partage de bénéfices | **Supprimé du périmètre** (C15). |
| § 8/L — charges fixes (10 postes) | **Liste paramétrable**, initialisée à 4 postes réels (C10). |
| § 7 — matrice d'accès, ligne « Demandes de dépenses » | À corriger : plus d'approbation simple (C1, C14). |
| § 6.7 / § 7 — rôle Auditeur | **Hors MVP** (C7). |
| **Nouveau** — § 7bis | Modèle économique et répartition des bénéfices, absent du brief d'origine. |

---

## 14. Prochaines étapes

### 14.1 Actions immédiates

1. **Passer à l'agent PM.** Aucune question bloquante ne subsiste : le § 12.1 est vide. Les
   questions du § 12.2 concernent l'architecture et peuvent être traitées en parallèle.
2. **Valider l'atténuation du § 6.1** — remonter le journal d'audit et le registre d'approbation
   des dépenses à l'Étape 1, puisque la finance complète arrive en dernier.
3. **Valider les résolutions proposées** pour C1, C4, C5, C9, C11, C13 — celles qui n'exigent pas
   d'arbitrage financier.
4. **Trancher C15 et C16** — le modèle de répartition des bénéfices et les abonnements SaaS sont
   apparus après la rédaction du brief d'entrée et n'ont jamais été spécifiés.
5. **Obtenir les réponses Q6 à Q11** avant l'intervention de l'agent Architect.
6. **Passer à l'agent PM** pour la rédaction de `docs/prd.md`.

### 14.2 Recommandation à l'agent Product Manager

Trois recommandations, dans l'ordre :

**a. Structurer les epics sur les 4 étapes du § 6.2.** Le découpage A→S du brief d'entrée
est une bonne nomenclature fonctionnelle mais un mauvais plan de livraison : il livre
l'authentification, puis l'organisation, puis les objectifs… et l'argent en dernier. Le
séquencement du § 6.2 livre d'abord ce qui a déjà coûté l'entreprise.

**b. Ne pas écrire les exigences financières avant les réponses du § 12.1.** Écrire le PRD financier
sur des hypothèses produirait des stories à réécrire, et surtout un risque de règle de contrôle
fictive (R3).

**c. Traiter le § 16 du brief d'entrée comme un jeu de tests d'acceptation, pas comme de la prose.**
Les 18 critères sont directement testables et doivent être rattachés un par un aux stories
correspondantes, pour que l'agent QA puisse tracer la couverture.

### 14.3 Passation PM

Ce Product Brief fournit le contexte complet de **PTR Staff**. Merci de démarrer en mode
« PRD Generation », de relire ce brief intégralement — **en particulier le § 7 (contradictions) et
le § 12 (questions ouvertes)** — et de construire le PRD section par section avec l'utilisateur, en
demandant les clarifications nécessaires et en proposant les améliorations utiles.

**Contrainte de rappel pour tout le flux BMAD :** l'application doit rester **simple, rapide et
utilisable par une petite équipe au Niger**, sur téléphone et en connexion instable. Toute
fonctionnalité qui alourdit la saisie quotidienne doit être justifiée par un risque financier ou
légal explicite.

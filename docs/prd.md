# PTR Staff Product Requirements Document (PRD)

**Version :** 1.0 — 18 juillet 2026
**Auteur :** John, Product Manager (BMAD)
**Sources :** `docs/brief.md` (Product Brief v1.1) et `docs/Brief_Analyste_BMAD_PTR_Staff.md` (brief d'entrée v1.0)
**Type de projet :** nouvelle application web interne (greenfield)
**Statut :** ✅ Prêt pour l'agent Architect et l'agent PO (shard)

---

## 1. Goals and Background Context

### 1.1 Goals

- Aucun membre actif — dirigeants inclus — ne travaille sans objectif écrit et validé.
- Chaque résultat déclaré est adossé à une preuve consultable, jamais à une simple déclaration.
- Chaque franc qui entre ou sort laisse une trace opposable, autorisée avant le paiement.
- La direction voit sur un seul écran l'état du travail et l'état de l'argent.
- L'entreprise est avertie automatiquement avant, et non après, une séquence de mois déficitaires.
- La réserve couvre trois mois de charges fixes et son niveau est visible en permanence.
- Aucun tuteur n'encadre plus de stagiaires que la limite paramétrée ne l'autorise.
- La saisie quotidienne reste faisable en moins de 3 minutes sur un téléphone en 3G.
- Aucun utilisateur n'accède à une donnée interdite à son rôle, y compris par URL directe.
- Aucune donnée validée n'est supprimée : correction, annulation ou contre-écriture, jamais autre chose.

### 1.2 Background Context

PTR Niger est une entreprise de services numériques de moins de quinze personnes, à Niamey. Elle a
déjà fermé une fois par manque de trésorerie. Le brief d'analyse regroupe les dix problèmes vécus en
quatre défaillances de système : travail non cadré (D1), résultat non prouvé (D2), argent non tracé
(D3) et trésorerie non surveillée (D4). D3 et D4 sont celles qui ont déjà coûté l'entreprise ; D1 et
D2 sont celles qui la font saigner lentement.

Le besoin n'est pas un outil de plus : c'est la **jointure** entre le travail produit et l'argent
qui circule, avec les règles propres à PTR Niger — deux signatures sur toute dépense, trois
stagiaires par tuteur, répartition 10/60/30 au rythme des encaissements, réserve à 20 % du bénéfice.
Aucun outil générique ne fait cette jointure. PTR Staff est une application web interne unique,
mobile-first, en français simple, réservée au personnel, servie sur `staff.ptrniger.com`.

Le périmètre MVP n'est pas réduit par rapport au brief d'entrée : il est **ordonné en quatre étapes
livrables**, chacune déployable et porteuse de valeur autonome. La direction a retenu l'ordre A → S
— socle, objectifs, redevabilité, finance — en assumant que la partie financière arrive en dernier,
avec pour atténuation obligatoire la remontée du journal d'audit et du registre d'approbation des
dépenses dès l'Étape 1.

### 1.3 Change Log

| Date | Version | Description | Auteur |
|---|---|---|---|
| 2026-07-18 | 1.0 | Création du PRD à partir du Product Brief v1.1. Intègre les quatre arbitrages produit du § 2.1 et signale onze contradictions résiduelles au § 11. | John (PM) |

---

## 2. Décisions produit prises dans ce PRD

### 2.1 Arbitrages demandés au commanditaire et obtenus le 18/07/2026

Ces quatre points changeaient le contenu du MVP ; ils ont été tranchés avant rédaction.

| Réf. | Question | Décision retenue |
|---|---|---|
| **D-01** *(brief C6)* | Base de calcul du bénéfice de contrat | **Noyau client + facture minimal en Étape 4.** Bénéfice = Σ encaissements imputés au contrat − Σ dépenses imputées au contrat. Débloque aussi les lignes « CA facturé » et « créances clients » du rapport mensuel. |
| **D-02** *(brief C4)* | Absences dans le MVP | **Minimum inclus en Étape 1** : calendrier des jours travaillés (lun–ven + jours fériés saisissables) et déclaration d'absence approuvée par le responsable, qui suspend l'attente de rapport. Pointage, retards et calendrier d'équipe restent en phase 2. |
| **D-03** *(brief C16)* | Abonnements SaaS et commissions récurrentes | **Hors MVP.** L'abonnement encaissé est un encaissement ordinaire ; la commission est calculée hors application et saisie comme dépense ordinaire à deux signatures. L'objet « abonnement » et le calcul automatique rejoignent la phase 2. |
| **D-04** *(brief C8 / Q8)* | Accès au journal d'audit | **Direction uniquement.** La Finance accède aux écritures financières et à leur historique de correction, mais pas au registre qui la contrôle. |

### 2.2 Résolutions appliquées d'office (propositions de l'analyste, sans arbitrage financier)

Reprises telles quelles du brief ; elles sont signalées ici pour que la direction puisse les
contester avant l'architecture.

| Réf. | Résolution appliquée |
|---|---|
| C5 | La **fiche d'entrée** (besoin, mission, tuteur, durée, trois résultats, approbation en une étape) est en MVP ; le **workflow de recrutement multi-états** reste en phase 2. |
| C7 | Rôle **Auditeur lecture seule hors MVP**, mais le modèle de permissions doit permettre de le créer plus tard sans refonte. |
| C9 | Le niveau **rouge bloque** l'activation de tout nouveau compte employé/stagiaire, et **signale sans bloquer** les dépenses non essentielles via un marqueur booléen sur les catégories. |
| C11 | Unicité du numéro de téléphone **sur les comptes non archivés uniquement**. Un retour dans l'entreprise crée un nouveau compte rattaché à la même fiche **personne**. |
| C12 | Rappel du rapport quotidien **60 minutes avant l'heure limite**, paramétrable. |
| C13 | Le super administrateur **n'a aucune permission métier par défaut** ; toute attribution de permission est auditée. |

### 2.3 Règles métier validées, non renégociables dans ce PRD

Reprises sans modification du § 17 du brief d'entrée et des décisions direction du 18/07/2026.

| # | Règle |
|---|---|
| RM-01 | Application privée, aucune inscription publique. Connexion par téléphone `+227` et mot de passe. |
| RM-02 | Français, fuseau `Africa/Niamey`, devise XOF sans décimales. |
| RM-03 | Les dirigeants sont soumis aux mêmes objectifs et rapports que le reste de l'équipe. |
| RM-04 | Maximum **5 priorités mensuelles** pour l'entreprise. |
| RM-05 | Maximum **3 objectifs majeurs validés** par personne et par mois. |
| RM-06 | Maximum **3 stagiaires actifs par tuteur**, paramétrable, contrôle **bloquant**. |
| RM-07 | Rapport quotidien attendu avant **17 h 45**, heure paramétrable. |
| RM-08 | Revue hebdomadaire le **vendredi**. |
| RM-09 | **Deux approbateurs distincts pour toute dépense, sans seuil de montant.** Aucune dérogation, aucune délégation. |
| RM-10 | Le demandeur ne peut jamais être approbateur de sa propre demande. |
| RM-11 | Réserve cible : **3 mois de charges fixes** ; alimentation **20 % du bénéfice total du contrat**, prélevés sur la part de 60 % de PTR Niger. |
| RM-12 | Répartition d'un contrat : apporteur **10 %**, PTR Niger **60 %**, exécutants **30 %** ; sans exécution 10/90 ; sans apporteur 100 % PTR Niger. |
| RM-13 | Les parts se versent **à l'encaissement réel**, au prorata, jamais à la facturation. |
| RM-14 | Les parts 10 % et 30 % sont **dues en toutes circonstances**, y compris en alerte rouge : ce sont des charges variables, pas un partage de bénéfices. |
| RM-15 | Part exécutant réservée aux **associés** ; employés au salaire ; stagiaires non rémunérés, gratification possible sur décision des deux propriétaires, obligatoirement tracée. |
| RM-16 | Le préparateur et le contrôleur d'une même écriture sont **deux comptes distincts**. |
| RM-17 | **Aucune suppression** de donnée financière ou d'objet validé : correction ou annulation motivée. |
| RM-18 | **Aucune sanction, rupture ou blocage de personne automatique.** Le système peut bloquer une écriture, jamais une personne. |
| RM-19 | Aucune gestion de stock, aucun inventaire, ni en MVP ni en phase 2. |
| RM-20 | Le module « Partage de bénéfices » est **supprimé du périmètre**. Un retrait d'associé est une dépense ordinaire à deux signatures. |

### 2.4 Principes de conception non négociables

| # | Principe |
|---|---|
| P1 | **La preuve avant la déclaration.** Un objectif « atteint » sans preuve attachée n'est pas atteint ; la preuve est structurelle, pas optionnelle. |
| P2 | **Rien ne se supprime.** Correction, annulation ou contre-écriture, jamais de suppression physique. |
| P3 | **Le logiciel constate, l'humain décide.** |
| P4 | **Le contrôle d'accès est côté serveur.** L'URL directe doit échouer, pas seulement le menu. |
| P5 | **Symétrie hiérarchique.** Une fonctionnalité qui exempte la direction est un défaut. |
| P6 | **Sobriété.** Chaque écran doit être utilisable sur un téléphone en 3G. |

---

## 3. Périmètre

### 3.1 MVP — les quatre étapes

Le périmètre MVP du brief d'entrée est **conservé intégralement** et livré en quatre étapes
séquentielles, chacune déployable.

| Étape | Contenu | Valeur autonome livrée |
|---|---|---|
| **1 — Socle** | Authentification, rôles et permissions, cycle de vie du compte, organisation et profils, journal d'audit, notifications, paramètres, calendrier et absences *(D-02)*, registre des demandes de dépense avec double approbation *(atténuation § 6.1)* | L'accès est contrôlé et audité ; aucune dépense ne peut plus être engagée sans autorisation tracée |
| **2 — Objectifs et projets** | Tableau de bord personnel, objectifs d'entreprise et individuels, projets, tâches et livrables | Chacun sait ce qu'il doit produire ce mois-ci et le prouve |
| **3 — Redevabilité et encadrement** | Rapport quotidien, blocages, revue hebdomadaire, gestion des stagiaires, documents internes | La boucle quotidienne et hebdomadaire fonctionne ; l'encadrement est plafonné |
| **4 — Argent et pilotage** | Comptes financiers, clients et factures minimales, encaissements, dépenses et paiements, répartition des parts, budgets et charges fixes, réserve, rapprochement, rapport mensuel, alertes, tableaux de bord, recherche et export CSV | La trésorerie est surveillée et l'alerte se déclenche seule |

### 3.2 Phase 2 — explicitement hors MVP, prévu ensuite

Par ordre de valeur décroissante :

1. Présence complète — pointage arrivée/départ, retards, calendrier d'équipe, télétravail.
2. Clients et ventes complets — prospects, opportunités, devis numérotés, factures générées, relances de créances, objectifs commerciaux.
3. **Abonnements SaaS et commissions récurrentes automatisées** *(D-03)* — objet abonnement, origine `démarchage`/`publicité`/`direct`, génération mensuelle de la commission, relevé au bénéficiaire sorti de l'entreprise.
4. Exports PDF et Excel complets.
5. Réunions et décisions, dont la réunion de direction du vendredi.
6. Workflow de recrutement complet — circuit multi-états, coût et financement.
7. Notifications SMS / WhatsApp, après choix du fournisseur.
8. Authentification renforcée (2FA) pour direction et finance ; réinitialisation par OTP SMS.
9. PWA installable et brouillons hors ligne.
10. Matériel et accès numériques.
11. Rôle Auditeur lecture seule.

### 3.3 Hors périmètre — ni MVP, ni phase 2

- Paie, déclarations CNSS ou fiscales.
- Intégrations bancaires ou Mobile Money directes.
- **Gestion de stock ou d'inventaire**, y compris pour l'activité d'impression.
- **Module « Partage de bénéfices »** — sans objet depuis la répartition automatique 10/60/30.
- Biométrie, géolocalisation, surveillance d'écran.
- Classement public entre employés, sanction ou rupture automatique.
- Application mobile native séparée.
- Multi-entreprise / multi-tenant.

---

## 4. Rôles et permissions

### 4.1 Rôles applicatifs

Un utilisateur peut **cumuler plusieurs rôles**. Les permissions sont attribuées par rôle et, si
nécessaire, par permission unitaire. Le contrôle est systématiquement côté serveur (P4).

| Rôle | Description | Effectif attendu au lancement |
|---|---|---|
| `super_admin` | Administrateur technique. Configure l'application, gère les rôles, restaure un compte, consulte les journaux techniques. **Aucune permission métier par défaut.** | 1 |
| `direction` | Associé propriétaire. Pilotage global, gestion des comptes, objectifs d'entreprise, approbation des dépenses, validation financière, lecture du journal d'audit. | **2** |
| `finance` | Responsable financier. Saisit encaissements et dépenses, gère les comptes, prépare rapprochements et rapports, suit les créances, joint les justificatifs. **N'approuve jamais une dépense.** | 1 (cumulable) |
| `tuteur` | Responsable d'équipe. Voit son équipe, attribue des tâches, valide ou retourne les rapports, mène les revues hebdomadaires, encadre jusqu'à 3 stagiaires actifs. | 1 à 4 |
| `employe` | Employé ou contractuel. Objectifs, rapports, preuves, blocages, demandes. | variable |
| `stagiaire` | Plan de stage, objectifs, rapports, livrables, demandes adressées au tuteur. | variable |

### 4.2 Rôles économiques

Distincts des rôles applicatifs, cumulables, attachés **au contrat et non à la personne** :

| Rôle économique | Portée |
|---|---|
| `apporteur` | Celui qui a amené le client — négociation directe **ou** action de communication ayant produit le contact. Champ pouvant être **vide** ; vide → 100 % PTR Niger. |
| `executant` | Celui qui réalise le travail de création. Réservé aux associés pour le versement de la part de 30 % (RM-15). |
| `preparateur` / `controleur` | Sur une écriture de rapprochement ou un rapport mensuel. Toujours deux comptes distincts (RM-16). |

### 4.3 Matrice d'accès

Corrigée par rapport au § 7 du brief d'entrée : plus d'approbation simple (C14), journal d'audit
fermé à la Finance (D-04), rôle Auditeur retiré (C7).

| Domaine | `super_admin` | `direction` | `finance` | `tuteur` | `employe` | `stagiaire` |
|---|---|---|---|---|---|---|
| Son tableau de bord | — | Oui | Oui | Oui | Oui | Oui |
| Tableau de bord global | — | Tout | Financier seulement | Son équipe | Non | Non |
| Gestion des comptes | Technique | Oui | Non | Non | Non | Non |
| Rôles et permissions | Oui | Oui | Non | Non | Non | Non |
| Paramètres généraux | Oui | Oui | Non | Non | Non | Non |
| Objectifs d'entreprise | Non | Gérer | Lire | Lire | Lire | Lire |
| Objectifs individuels | Non | Tous | Les siens | Son équipe | Les siens | Les siens |
| Projets et tâches | Non | Tous | Lire | Son équipe | Les siens | Les siens |
| Rapports quotidiens | Non | Tous | Les siens | Son équipe | Les siens | Les siens |
| Revue hebdomadaire | Non | Tous | Les siens | Son équipe | Les siennes | Les siennes |
| Blocages | Non | Tous | Les siens | Son équipe | Les siens | Les siens |
| Gestion des stagiaires | Non | Oui | Non | Ses stagiaires | Non | Son dossier |
| Absences | Non | Toutes | Les siennes | Son équipe | Les siennes | Les siennes |
| Documents internes | Non | Gérer | Lire | Lire | Lire | Lire |
| Demandes de dépense | Non | **Approuver** | Préparer / payer | Créer | Créer | Créer |
| Comptes et écritures financières | Non | Toutes | Toutes | Non | Ses demandes | Ses demandes |
| Clients, factures, créances | Non | Toutes | Toutes | Non | Non | Non |
| Répartition des parts | Non | Toutes | Toutes | Non | **Les siennes uniquement** | Non |
| Réserve | Non | Gérer | Préparer | Non | Non | Non |
| Rapport financier mensuel | Non | Valider | Préparer / contrôler | Non | Non | Non |
| **Journal d'audit** | Journaux techniques | **Lire — exclusif** | **Non** | Non | Non | Non |
| Recherche et export CSV | Non | Selon droits | Selon droits | Selon droits | Selon droits | Selon droits |

### 4.4 Règles de permission structurelles

| # | Règle |
|---|---|
| PERM-01 | Toute vérification d'accès est exécutée côté serveur, sur la requête, indépendamment de l'affichage du menu. |
| PERM-02 | Une requête vers une ressource non autorisée retourne un refus, jamais un contenu partiel ni une redirection silencieuse. |
| PERM-03 | `super_admin` ne détient aucune permission métier par défaut : pas d'approbation de dépense, pas de validation financière, pas de validation d'objectif. |
| PERM-04 | Toute attribution, modification ou retrait de permission ou de rôle est écrite au journal d'audit avec ancienne et nouvelle valeur. |
| PERM-05 | La permission `approuver_depense` est détenue par les **deux comptes `direction`** et par eux seuls. |
| PERM-06 | Un export applique exactement les mêmes filtres de permission que l'écran dont il est issu, et est lui-même audité. |
| PERM-07 | Le modèle de permission doit permettre la création ultérieure d'un rôle strictement lecture seule sans refonte du modèle de données. |
| PERM-08 | La suspension d'un compte invalide immédiatement toutes ses sessions actives. |

---

## 5. Requirements — Fonctionnels

> Convention : chaque FR est formulé pour être vérifiable par un test automatisé ou une procédure
> de recette explicite. L'étape de livraison est indiquée entre crochets.

### 5.1 Identité, accès et cycle de vie du compte — [Étape 1]

- **FR1 :** L'application ne propose aucun formulaire d'inscription publique ; un compte est créé exclusivement par un utilisateur portant `direction` ou `super_admin`.
- **FR2 :** L'authentification se fait par numéro de téléphone et mot de passe. Le numéro est normalisé au format international avec l'indicatif `+227` par défaut avant enregistrement et avant comparaison.
- **FR3 :** Le numéro de téléphone est unique parmi les comptes dont l'état n'est pas `archive`. Un numéro libéré par archivage peut être réattribué à un nouveau compte.
- **FR4 :** Une **fiche personne** persiste indépendamment du compte applicatif. Le retour d'une personne dans l'entreprise crée un nouveau compte rattaché à la fiche personne existante ; l'historique reste consultable.
- **FR5 :** À la création, un mot de passe temporaire est généré ; la première connexion impose le changement de mot de passe avant tout autre accès.
- **FR6 :** En MVP, la réinitialisation d'un mot de passe oublié est effectuée par un utilisateur `direction` ou `super_admin` ; l'opération est auditée avec l'auteur et la cible.
- **FR7 :** Les états du compte sont : `invite`, `actif`, `suspendu`, `termine`, `archive`. Seul l'état `actif` autorise la connexion.
- **FR8 :** Le passage à `suspendu` ou tout changement de mot de passe invalide immédiatement toutes les sessions du compte sur tous les appareils.
- **FR9 :** L'application conserve l'historique des connexions réussies, des tentatives échouées et des sessions ouvertes, consultable par `direction`.
- **FR10 :** Après un nombre paramétrable de tentatives échouées consécutives, le compte est temporairement bloqué pour une durée paramétrable ; le blocage et son expiration sont journalisés.
- **FR11 :** Un utilisateur peut porter plusieurs rôles simultanément ; ses permissions effectives sont l'union des permissions de ses rôles et de ses permissions unitaires.
- **FR12 :** Toute création, modification d'état, attribution de rôle ou réinitialisation de mot de passe est écrite au journal d'audit.

### 5.2 Organisation et profils — [Étape 1]

- **FR13 :** L'application gère une fiche entreprise unique (nom, coordonnées, logo optionnel). Aucune notion de seconde entreprise n'existe.
- **FR14 :** L'application gère une liste de services et une liste de fonctions, administrables par `direction`.
- **FR15 :** La fiche utilisateur porte : nom, téléphone, photo optionnelle, rôles, service, fonction, responsable direct, type de relation (`dirigeant`, `employe`, `contractuel`, `stagiaire`), dates de début et de fin de contrat ou de stage.
- **FR16 :** Le statut opérationnel de la personne (`actif`, `absent`, `suspendu`, `sorti`) est distinct de l'état du compte applicatif.
- **FR17 :** Des documents peuvent être rattachés au dossier d'une personne (contrat, convention, fiche de poste, engagement signé) ; ils ne sont visibles que par la personne concernée, son responsable direct et `direction`.
- **FR18 :** Tout changement de rôle, service, responsable direct ou statut est historisé avec date, auteur, ancienne et nouvelle valeur.
- **FR19 :** L'application affiche, pour toute personne, la liste de ses responsables et subordonnés directs à la date courante.

### 5.3 Journal d'audit — [Étape 1]

- **FR20 :** Chaque entrée d'audit contient : auteur, horodatage, type d'objet, identifiant de l'objet, action, ancienne valeur et nouvelle valeur lorsque la nature de l'action le permet.
- **FR21 :** L'écriture au journal d'audit est obligatoire pour : toute opération financière, tout objectif validé et sa modification, tout compte et tout changement d'état de compte, toute attribution de rôle ou de permission, tout document interne et toute version de document, tout export de données sensibles.
- **FR22 :** Aucune interface de l'application ne permet de modifier ni de supprimer une entrée d'audit.
- **FR23 :** Le journal d'audit est consultable **par le rôle `direction` uniquement** *(D-04)*. Le rôle `finance` n'y accède pas.
- **FR24 :** Le journal d'audit est filtrable par auteur, période, type d'objet et action, et exportable en CSV par `direction` ; l'export est lui-même audité.

### 5.4 Paramètres généraux — [Étape 1]

- **FR25 :** Sont paramétrables sans intervention sur le code : jours travaillés de la semaine, jours fériés, heure limite du rapport quotidien, délai du rappel avant l'heure limite, limite de stagiaires actifs par tuteur, pourcentage de réserve, objectif de réserve en nombre de mois, types de pièces jointes autorisés et taille maximale, catégories de dépense, liste des charges fixes.
- **FR26 :** Toute modification d'un paramètre est auditée avec ancienne et nouvelle valeur, et porte une date d'effet.
- **FR27 :** La limite de stagiaires actifs par tuteur a pour valeur initiale **3**.
- **FR28 :** Le pourcentage de réserve a pour valeur initiale **20 %** et l'objectif de réserve **3 mois**.
- **FR29 :** L'heure limite du rapport quotidien a pour valeur initiale **17 h 45** et le délai de rappel **60 minutes**.

### 5.5 Notifications dans l'application — [Étape 1]

- **FR30 :** L'application dispose d'un centre de notifications interne, avec compteur de non-lues, accessible depuis toute page.
- **FR31 :** Les événements notifiés en MVP sont : rapport quotidien bientôt en retard, rapport quotidien en retard, objectif proche de l'échéance, commentaire ou correction demandée, blocage affecté, dépense à approuver, rapprochement ou rapport financier à préparer, document interne à accepter, fin de contrat ou de stage proche.
- **FR32 :** Une notification porte un lien direct vers l'objet concerné et permet d'atteindre l'action attendue en **au plus 3 interactions**.
- **FR33 :** Une dépense en attente d'approbation génère un rappel à J+1 et à J+2 vers l'approbateur manquant, tant que la décision n'est pas prise.
- **FR34 :** Aucune notification n'est envoyée par SMS, WhatsApp ou courriel en MVP.

### 5.6 Calendrier des jours travaillés et absences — [Étape 1] *(D-02)*

- **FR35 :** Un calendrier des jours travaillés est défini au niveau de l'entreprise : jours de la semaine travaillés par défaut, plus une liste de jours fériés ou de fermeture saisissables par `direction`.
- **FR36 :** Un utilisateur peut déclarer une absence : type (congé, maladie, autre), date de début, date de fin, motif court, justificatif optionnel.
- **FR37 :** Une absence est soumise à l'approbation du responsable direct ; états : `demandee`, `approuvee`, `refusee`, `annulee`.
- **FR38 :** Aucun rapport quotidien n'est attendu ni compté comme manquant sur un jour non travaillé ou couvert par une absence `approuvee`.
- **FR39 :** Les indicateurs de ponctualité excluent du dénominateur les jours non travaillés et les jours d'absence approuvée.

### 5.7 Objectifs — [Étape 2]

- **FR40 :** `direction` définit au maximum **5 priorités d'entreprise** par mois. La création d'une sixième priorité validée pour le même mois est refusée.
- **FR41 :** Une personne ne peut pas avoir plus de **3 objectifs majeurs à l'état validé ou postérieur** pour un même mois. La validation d'un quatrième est refusée avec un message explicite.
- **FR42 :** Un objectif porte : titre, description courte, responsable, indicateur, valeur cible, **preuve attendue**, date limite, moyens nécessaires, priorité.
- **FR43 :** Un objectif individuel peut être rattaché à une priorité d'entreprise et/ou à un projet.
- **FR44 :** Les états d'un objectif sont : `brouillon`, `valide`, `en_cours`, `atteint`, `partiellement_atteint`, `non_atteint`, `bloque`, `annule`.
- **FR45 :** Les couleurs d'affichage sont : vert = atteint, orange = en risque, rouge = non atteint, gris = bloqué. Le code couleur n'est jamais le seul porteur d'information.
- **FR46 :** Toute modification d'un objectif à l'état `valide` ou postérieur exige un motif saisi, conserve la valeur précédente et l'auteur, et écrit une entrée d'audit.
- **FR47 :** Un objectif ne peut pas passer à l'état `atteint` sans qu'au moins une preuve soit attachée (P1).
- **FR48 :** L'utilisateur peut mettre à jour le progrès et attacher une preuve à tout moment ; le responsable peut commenter, valider ou demander une correction.
- **FR49 :** Un utilisateur peut **proposer** un objectif ; celui-ci reste à l'état `brouillon` et ne devient officiel qu'après validation de son responsable.
- **FR50 :** L'application offre une vue liste, une vue calendrier et une synthèse mensuelle des objectifs, filtrables selon les permissions du demandeur.
- **FR51 :** La copie d'un objectif récurrent vers le mois suivant est possible, mais crée un objectif à l'état `brouillon` soumis à nouvelle validation.
- **FR52 :** Les objectifs des utilisateurs `direction` sont soumis aux mêmes règles, limites et validations que ceux des autres membres (P5, RM-03).

### 5.8 Projets, tâches et livrables — [Étape 2]

- **FR53 :** Un projet porte : nom, client optionnel, responsable, dates, statut, membres.
- **FR54 :** Les statuts de projet sont : `prevu`, `actif`, `bloque`, `en_validation`, `livre`, `cloture`, `annule`.
- **FR55 :** Une tâche porte : titre, responsable, échéance, priorité, lien optionnel vers un objectif, statut.
- **FR56 :** Une tâche accepte des sous-tâches simples à un seul niveau de profondeur.
- **FR57 :** Une tâche et un projet acceptent pièces jointes, liens et commentaires.
- **FR58 :** Un livrable porte : responsable, date prévue, date réelle, statut de validation.
- **FR59 :** La partie budgétaire d'un projet n'est visible que par `direction` et `finance`.

### 5.9 Rapport quotidien — [Étape 3]

- **FR60 :** Il existe au plus un rapport par personne et par jour travaillé.
- **FR61 :** Le rapport porte six champs obligatoires : tâche prévue, résultat obtenu, preuve ou lien, blocage, prochaine action, aide demandée.
- **FR62 :** Le formulaire pré-remplit la « tâche prévue » à partir des tâches assignées à l'utilisateur pour la journée, sans empêcher la modification.
- **FR63 :** Le brouillon est sauvegardé automatiquement ; une saisie interrompue par une perte de connexion est restaurée intégralement à la réouverture sur le même appareil.
- **FR64 :** Le rapport accepte l'attachement d'une image, d'un document ou d'un lien.
- **FR65 :** Les états du rapport sont : `brouillon`, `envoye`, `valide`, `retourne`, `en_retard`.
- **FR66 :** Un rappel est envoyé au délai paramétré avant l'heure limite ; une notification de retard est envoyée après l'heure limite si le rapport n'est pas à l'état `envoye`.
- **FR67 :** Le responsable peut commenter, valider ou retourner un rapport, mais **ne peut modifier aucun champ saisi par l'auteur**.
- **FR68 :** Toute correction par l'auteur après envoi crée une nouvelle version et conserve la version précédente, consultable.
- **FR69 :** Si aucune tâche n'est disponible, l'utilisateur peut émettre depuis le rapport une **demande de nouvelle tâche** adressée à son responsable.
- **FR70 :** L'application offre une vue quotidienne, hebdomadaire et mensuelle des rapports, selon les permissions du demandeur.
- **FR71 :** Un rapport envoyé après l'heure limite affiche le retard constaté et sollicite une explication courte, facultative.

### 5.10 Blocages et demandes d'aide — [Étape 3]

- **FR72 :** Un blocage peut être créé depuis une tâche, un objectif ou un rapport quotidien, et conserve le lien vers son origine.
- **FR73 :** Un blocage porte : problème, niveau d'urgence, personne sollicitée, date, effet sur l'échéance, action déjà essayée.
- **FR74 :** Les états d'un blocage sont : `ouvert`, `pris_en_charge`, `resolu`, `ferme_sans_solution`.
- **FR75 :** La création d'un blocage notifie immédiatement la personne sollicitée.
- **FR76 :** L'application mesure et affiche le délai entre la création du blocage et son passage à `pris_en_charge`, puis à `resolu`.

### 5.11 Revue hebdomadaire et plan d'amélioration — [Étape 3]

- **FR77 :** Une revue hebdomadaire peut être ouverte par le responsable pour chaque membre de son équipe, avec une périodicité hebdomadaire par défaut le vendredi.
- **FR78 :** La revue présente automatiquement les objectifs, tâches, rapports et blocages de la semaine concernée.
- **FR79 :** Pour chaque objectif de la période, la revue enregistre : résultat, preuve, statut, cause de l'écart, prochaine action.
- **FR80 :** La revue enregistre le commentaire de la personne évaluée **et** celui du responsable, puis une validation électronique simple des deux parties, horodatée.
- **FR81 :** Un plan d'amélioration de 7 à 14 jours peut être créé depuis la revue, avec actions, aide fournie, dates et résultat constaté.
- **FR82 :** Aucun classement comparatif entre personnes n'est affiché nulle part dans l'application.

### 5.12 Stagiaires et encadrement — [Étape 3]

- **FR83 :** Une **fiche d'entrée** porte : besoin réel, mission, responsable/tuteur, durée, outils, et **trois résultats obligatoires**. Le circuit multi-états, le coût et le financement restent hors MVP.
- **FR84 :** Aucun compte de stagiaire ne peut passer à l'état `actif` sans fiche d'entrée approuvée, tuteur désigné et trois objectifs enregistrés.
- **FR85 :** L'affectation d'un stagiaire à un tuteur ayant déjà atteint la limite paramétrée de stagiaires actifs est **refusée** par le serveur, avec un message nommant le tuteur et sa charge actuelle.
- **FR86 :** Le plan de stage porte : compétences à apprendre, objectifs, tâches hebdomadaires, preuves attendues.
- **FR87 :** Une checklist d'intégration est générée à l'activation : contrat ou convention, matériel, accès, règlement intérieur, première tâche, présentation du tuteur.
- **FR88 :** Le tuteur enregistre une évaluation hebdomadaire du stagiaire.
- **FR89 :** Une évaluation finale est enregistrée en fin de stage ; l'application indique si les conditions d'attestation sont remplies, sans produire de document en MVP.
- **FR90 :** Une checklist de sortie est générée : livrables remis, matériel rendu, accès fermés, documents sauvegardés, évaluation finale enregistrée.
- **FR91 :** Les demandes non urgentes d'un stagiaire sont regroupées et présentées au tuteur par **créneaux de suivi** paramétrables, plutôt que notifiées à l'unité.
- **FR92 :** Un blocage marqué urgent échappe au regroupement et notifie le tuteur immédiatement.
- **FR93 :** L'application affiche pour chaque tuteur le nombre de stagiaires actifs encadrés et signale visuellement un tuteur ayant atteint la limite.

### 5.13 Documents internes — [Étape 3]

- **FR94 :** Une bibliothèque de documents internes gère règles, procédures et engagements, avec une version et une date d'application par document.
- **FR95 :** Chaque document peut exiger un accusé de lecture et d'acceptation, enregistré par utilisateur avec horodatage.
- **FR96 :** La publication d'une nouvelle version notifie les utilisateurs concernés et réinitialise l'exigence d'acceptation.
- **FR97 :** L'historique complet des versions reste consultable ; aucune version n'est supprimée.
- **FR98 :** Un document rattaché au dossier d'une personne n'est visible que par cette personne, son responsable direct et `direction`.

### 5.14 Comptes financiers — [Étape 4]

- **FR99 :** L'application gère des comptes financiers de type `caisse`, `banque` et `mobile_money`, avec libellé, devise XOF, solde initial et date de solde initial.
- **FR100 :** Le solde d'un compte est la somme du solde initial et des mouvements validés qui lui sont imputés ; il n'est jamais saisi directement.
- **FR101 :** Aucune intégration bancaire ou Mobile Money automatique n'existe en MVP ; toute écriture est saisie manuellement.
- **FR102 :** L'accès aux comptes financiers est limité à `direction` et `finance`.

### 5.15 Clients, factures et créances — noyau minimal — [Étape 4] *(D-01)*

- **FR103 :** Une fiche client minimale porte : nom, téléphone, contact optionnel, notes.
- **FR104 :** Un contrat rattache un client, un projet optionnel, un montant total attendu, un **bénéfice prévisionnel**, un apporteur (pouvant être vide), un ou plusieurs exécutants, et la répartition prévue déduite de ces champs.
- **FR105 :** Une facture porte : numéro unique, client, contrat, montant, date d'émission, date d'échéance, statut `impayee` / `partiellement_payee` / `payee` / `annulee`.
- **FR106 :** Le statut d'une facture est déduit des encaissements qui lui sont imputés ; il n'est pas saisi directement.
- **FR107 :** Une créance est déduite automatiquement de toute facture non intégralement payée dont l'échéance est atteinte ; la liste des créances est affichée avec l'ancienneté en jours.
- **FR108 :** Aucun devis, prospect, opportunité ni relance automatisée n'existe en MVP. Aucune facture n'est générée au format PDF en MVP.

### 5.16 Encaissements — [Étape 4]

- **FR109 :** Un encaissement porte : client, contrat ou projet, facture rattachée optionnelle, montant, date, compte financier crédité, mode de paiement, référence, justificatif.
- **FR110 :** Chaque encaissement reçoit un **numéro de reçu unique** attribué par le système, non réutilisable même après annulation.
- **FR111 :** Un encaissement validé ne peut pas être supprimé ; il peut être **corrigé** (nouvelle version motivée) ou **annulé** (contre-écriture motivée). Les deux opérations sont auditées.
- **FR112 :** Un paiement reçu personnellement par un membre doit être enregistré comme encaissement dans les 24 heures et rattaché au compte qui l'a effectivement reçu ; l'application signale les encaissements créés plus de 24 h après leur date de réception déclarée.
- **FR113 :** L'imputation d'un encaissement à un contrat déclenche le calcul des parts dues (FR128 à FR136).
- **FR114 :** Aucun encaissement ne peut être imputé à un mois financièrement clôturé sans réouverture explicite (FR159).

### 5.17 Dépenses, approbations et paiements — [Étapes 1 et 4]

> Le circuit demande → double approbation → justificatif est livré **dès l'Étape 1** (atténuation
> § 6.1 du brief). L'Étape 4 y ajoute le paiement, l'imputation comptable, la catégorie budgétaire
> et le rattachement au contrat.

- **FR115 :** [Étape 1] Une demande de dépense porte : demandeur, motif, montant, bénéficiaire, projet ou contrat optionnel, résultat attendu, justificatif prévisionnel optionnel, catégorie.
- **FR116 :** [Étape 1] Les états d'une dépense sont : `demandee`, `approuvee`, `refusee`, `payee`, `annulee`. L'état de paiement est distinct de l'état d'approbation : une approbation ne vaut jamais paiement.
- **FR117 :** [Étape 1] **Toute** dépense, quel que soit son montant et qu'elle soit ou non prévue au budget, exige l'approbation des **deux comptes `direction` distincts**. Aucune dérogation, aucune délégation, aucun seuil (RM-09).
- **FR118 :** [Étape 1] Tant que le second consentement manque, la dépense reste à l'état `demandee` et n'est jamais payable.
- **FR119 :** [Étape 1] Le demandeur ne peut jamais compter comme approbateur de sa propre demande, y compris lorsqu'il porte le rôle `direction` (RM-10).
- **FR120 :** [Étape 1] Chaque compte `direction` dispose d'un écran **« En attente de mon approbation »** présenté en tête de son tableau de bord, avec le nombre de dépenses concernées et leur ancienneté.
- **FR121 :** [Étape 1] Une dépense peut être approuvée ou refusée en **au plus 3 interactions** depuis la notification, hors saisie du motif de refus.
- **FR122 :** [Étape 1] Un refus exige un motif saisi ; une annulation également. Aucune dépense n'est supprimée.
- **FR123 :** [Étape 4] Le paiement d'une dépense `approuvee` est enregistré par `finance` avec compte financier débité, date, mode de paiement et référence.
- **FR124 :** [Étape 4] Un **justificatif de paiement** est attaché après le paiement ; une dépense payée sans justificatif est signalée dans une liste dédiée jusqu'à régularisation.
- **FR125 :** [Étape 4] Une **procédure de remboursement** existe : un membre ayant avancé personnellement une somme dépose une demande de remboursement portant le justificatif d'origine et suivant le même circuit à deux signatures.
- **FR126 :** [Étape 4] Une catégorie de dépense **« gratification de stagiaire »**, distincte des salaires, existe au paramétrage et suit le circuit ordinaire à deux signatures.
- **FR127 :** [Étape 4] Chaque catégorie de dépense porte un marqueur booléen **« dépense essentielle »**, paramétrable, utilisé par l'alerte rouge (FR164).

### 5.18 Répartition des parts de contrat — [Étape 4]

- **FR128 :** Un contrat porte un champ **apporteur** pouvant être vide. Vide → 100 % PTR Niger. Rempli → 10 % à cette personne.
- **FR129 :** Un contrat porte un indicateur **« avec exécution »** : avec exécution → 10 / 60 / 30 ; sans exécution (mise en place ou installation d'un produit existant) → 10 / 90.
- **FR130 :** Lorsqu'il existe plusieurs exécutants, les 30 % se partagent en **parts strictement égales**.
- **FR131 :** Chaque encaissement imputé à un contrat déclenche le calcul des parts **au prorata du montant encaissé rapporté au montant total attendu du contrat**, appliqué au bénéfice retenu.
- **FR132 :** Une part n'est jamais due tant que l'encaissement correspondant n'est pas enregistré (RM-13). Un contrat facturé et non payé ne génère aucune part.
- **FR133 :** L'écran du contrat affiche en permanence : montant total attendu, total encaissé, bénéfice retenu, parts déjà versées par bénéficiaire, parts restant à verser.
- **FR134 :** Chaque versement de part est enregistré comme une **dépense ordinaire** et suit le circuit à deux signatures, y compris lorsque le bénéficiaire est un associé.
- **FR135 :** L'écran de la dépense de versement affiche la **méthode de calcul** : bénéfice retenu, période, encaissement d'origine, taux appliqué, montant. Un calcul opaque est un défaut.
- **FR136 :** Un bénéficiaire non-associé consulte **sa propre part uniquement** : montant, base de calcul, taux, contrat d'origine. Il n'accède à aucune autre ligne de répartition.

### 5.19 Budgets et charges fixes — [Étape 4]

- **FR137 :** Un budget mensuel est défini par catégorie de dépense.
- **FR138 :** La liste des charges fixes est **entièrement paramétrable**, initialisée aux quatre postes réels : loyer, électricité, Internet, salaires. L'ajout d'un poste est une saisie, jamais une modification de code.
- **FR139 :** Chaque charge fixe porte un état `active` / `inactive` et un montant mensuel ; seules les charges actives entrent dans l'assiette d'alerte et dans l'objectif de réserve.
- **FR140 :** L'application affiche la comparaison budget / réalisé par catégorie et par mois.
- **FR141 :** Les **coûts directs de projet n'entrent pas** dans l'assiette des charges fixes.

### 5.20 Réserve — [Étape 4]

- **FR142 :** L'objectif de réserve est égal au nombre de mois paramétré multiplié par la somme des charges fixes actives.
- **FR143 :** Tant que l'objectif n'est pas atteint, chaque encaissement imputé à un contrat affecte à la réserve **20 % du bénéfice correspondant**, prélevés sur la part de 60 % de PTR Niger, sans jamais toucher les parts de 10 % et 30 %.
- **FR144 :** Le prélèvement s'interrompt automatiquement dès l'objectif atteint et reprend automatiquement si la réserve descend sous l'objectif.
- **FR145 :** L'application affiche en permanence le montant de la réserve et le **nombre de mois de charges couverts**, avec la méthode de calcul et la date des données source.
- **FR146 :** Toute utilisation de la réserve exige un motif, la double approbation `direction` et un plan de reconstitution enregistré.
- **FR147 :** L'ajout d'une charge fixe augmente mécaniquement l'objectif de réserve ; l'application affiche l'impact chiffré **avant** la confirmation de l'ajout.

### 5.21 Rapprochement hebdomadaire — [Étape 4]

- **FR148 :** Un rapprochement compare, pour chaque compte financier, le solde physique constaté saisi et le solde issu des écritures.
- **FR149 :** L'écart est calculé et affiché systématiquement, y compris lorsqu'il est nul.
- **FR150 :** Tout écart non nul exige une explication saisie, un responsable et une action corrective avant validation.
- **FR151 :** Le rapprochement est validé par un **préparateur** et un **contrôleur** qui sont deux comptes distincts ; le système refuse la validation lorsque les deux sont identiques (RM-16).
- **FR152 :** Un rapprochement validé n'est pas modifiable ; une correction crée un nouveau rapprochement rattaché au précédent, avec motif.

### 5.22 Rapport financier mensuel et clôture — [Étape 4]

- **FR153 :** Le rapport mensuel présente douze lignes : chiffre d'affaires facturé, encaissements reçus, créances clients, coûts directs des projets, salaires et rémunérations, charges fixes, taxes et charges sociales, dettes, trésorerie totale, résultat estimé, réserve disponible, nombre de mois de charges couverts.
- **FR154 :** Chaque ligne affiche la période source et la méthode d'obtention du montant.
- **FR155 :** Une ligne sans donnée applicable affiche `0` accompagné de la mention « poste non applicable à ce jour », et n'est jamais masquée.
- **FR156 :** Le rapport est préparé, contrôlé puis validé ; le préparateur et le contrôleur sont deux comptes distincts, et la validation appartient à `direction`.
- **FR157 :** La date limite de validation est le **5 du mois suivant** ; l'application notifie à l'approche et signale un dépassement.
- **FR158 :** Après validation, le mois est **clôturé** : aucune écriture ne peut plus y être imputée.
- **FR159 :** La réouverture d'un mois clôturé exige une autorisation `direction` avec motif et laisse une entrée d'audit ; toute écriture postérieure à la réouverture est marquée comme telle.
- **FR160 :** La validation du rapport recalcule et fige le niveau d'alerte du mois.

### 5.23 Alertes financières — [Étape 4]

- **FR161 :** L'assiette d'alerte est la **somme des charges fixes actives** déclarées au paramétrage, jamais une liste codée en dur.
- **FR162 :** Niveau **vert** : encaissements du mois ≥ assiette.
- **FR163 :** Niveau **orange** : un mois où les encaissements sont inférieurs à l'assiette. L'application demande l'enregistrement d'un plan correctif sous 48 heures et notifie `direction` jusqu'à ce qu'il existe.
- **FR164 :** Niveau **rouge** : deux mois consécutifs sous l'assiette. En rouge, l'application **bloque** l'activation de tout nouveau compte employé ou stagiaire, et **avertit sans bloquer** l'approbateur d'une dépense dont la catégorie n'est pas marquée « essentielle ».
- **FR165 :** Les parts de 10 % et 30 % restent dues et payables en niveau rouge (RM-14) ; l'alerte rouge ne les bloque jamais.

### 5.24 Tableaux de bord — [Étapes 2 et 4]

- **FR166 :** [Étape 2] Le tableau de bord personnel affiche : objectifs du mois et leur progression, tâches du jour, rapport du jour à envoyer, blocages ouverts, prochaines échéances, notifications, dernière évaluation, demandes en attente.
- **FR167 :** [Étape 1] Le tableau de bord d'un compte `direction` affiche en **première position** le bloc « En attente de mon approbation ».
- **FR168 :** [Étape 4] Le tableau de bord direction affiche : membres sans objectif, rapports du jour envoyés / manquants, objectifs verts / orange / rouges / bloqués, projets en retard, stagiaires par tuteur, encaissements du mois, charges du mois, solde disponible, créances, réserve et mois couverts, niveau d'alerte.
- **FR169 :** [Étape 4] Le tableau de bord financier affiche : soldes par compte, dépenses en attente, encaissements du mois, créances échues, écarts de rapprochement, budget contre réalisé, réserve disponible.
- **FR170 :** [Étape 4] Le tableau de bord direction signale visuellement tout tuteur ayant atteint la limite de stagiaires actifs.
- **FR171 :** [Étape 4] Le tableau de bord financier affiche le **total des engagements de parts restant à verser** sur les contrats en cours.
- **FR172 :** Chaque bloc de tableau de bord n'est rendu que si le demandeur détient la permission correspondante ; l'absence de permission ne produit ni bloc vide ni message d'erreur technique.

### 5.25 Recherche, listes et export — [Étape 4]

- **FR173 :** L'application permet la recherche par personne, projet, objectif, période et statut.
- **FR174 :** Les listes principales sont filtrables et triables ; `direction` peut enregistrer un filtre pour réutilisation.
- **FR175 :** Les listes sont exportables en **CSV** ; l'export applique exactement les mêmes restrictions de permission que l'écran d'origine.
- **FR176 :** Tout export est écrit au journal d'audit avec l'auteur, la nature des données et le nombre de lignes.

---

## 6. Requirements — Non fonctionnels

### 6.1 Performance et conditions réseau

- **NFR1 :** Toute page du parcours quotidien (connexion, tableau de bord, rapport quotidien, approbation de dépense) doit atteindre son premier rendu utile en **moins de 3 secondes** sur une liaison 3G dégradée simulée à 400 kbit/s et 400 ms de latence.
- **NFR2 :** Le poids transféré d'une page du parcours quotidien, hors pièces jointes, ne dépasse pas **300 Ko** après compression au premier chargement, et **80 Ko** aux chargements suivants.
- **NFR3 :** L'application ne dépend d'aucun CDN externe ni d'aucune ressource tierce chargée à l'exécution. Toutes les polices, feuilles de style et scripts sont servis par l'application.
- **NFR4 :** La **saisie complète d'un rapport quotidien** doit être réalisable en **moins de 3 minutes** sur téléphone, mesurée du premier champ à la confirmation d'envoi, avec pré-remplissage actif. Cette mesure est une condition de recette de l'Étape 3.
- **NFR5 :** Le brouillon d'un formulaire long est sauvegardé automatiquement au plus tard **10 secondes** après la dernière frappe, et restauré intégralement après une interruption réseau ou une fermeture d'onglet.
- **NFR6 :** Une action interrompue par une perte de connexion ne produit jamais d'enregistrement partiel : soit l'opération complète est enregistrée, soit rien ne l'est.

### 6.2 Compatibilité et mobile-first

- **NFR7 :** L'application est utilisable sans défilement horizontal sur une largeur de viewport de **320 px**.
- **NFR8 :** Toute cible tactile interactive mesure au moins **44 × 44 px**.
- **NFR9 :** Navigateurs supportés : Chrome Android (priorité 1), Chrome desktop, Safari en version courante et n-1.
- **NFR10 :** Aucun écran du parcours quotidien n'exige un ordinateur pour être utilisé. Les écrans de consolidation financière peuvent être optimisés pour le grand écran mais doivent rester consultables sur téléphone.

### 6.3 Sécurité

- **NFR11 :** HTTPS obligatoire sur l'ensemble du domaine, sans exception ni contenu mixte.
- **NFR12 :** Les mots de passe sont hachés par un algorithme moderne à coût paramétrable. Aucun mot de passe, jeton ou secret n'est jamais journalisé ni stocké en clair.
- **NFR13 :** L'application est protégée contre le bourrage d'identifiants, l'injection, le XSS, le CSRF et l'accès direct aux fichiers.
- **NFR14 :** L'autorisation est vérifiée **côté serveur sur chaque requête**. Une campagne de tests d'accès par URL directe, couvrant chaque combinaison rôle × ressource protégée, fait partie de la recette de chaque étape.
- **NFR15 :** Les pièces jointes sont stockées hors de la racine web publique et servies uniquement après contrôle d'autorisation serveur ou via un lien signé à durée limitée.
- **NFR16 :** Les types de fichiers acceptés et la taille maximale sont paramétrables ; tout téléversement non conforme est refusé côté serveur, indépendamment du contrôle côté client.
- **NFR17 :** Les erreurs sont journalisées sans exposer de secret, de requête complète ni de donnée personnelle, et ne sont jamais affichées en détail technique à l'utilisateur final.
- **NFR18 :** Le principe du moindre privilège s'applique : un utilisateur ne voit que ses données et celles que son rôle autorise explicitement.
- **NFR19 :** Les stagiaires n'accèdent à aucune donnée financière globale, en aucune circonstance.

### 6.4 Traçabilité et intégrité

- **NFR20 :** L'immuabilité (P2) et l'audit sont conçus **au niveau du modèle de données dès l'Étape 1**. Aucune table portant une donnée financière ou un objet validé n'expose d'opération de suppression physique depuis l'application.
- **NFR21 :** Toute écriture au journal d'audit est effectuée dans la même transaction que l'opération métier qu'elle documente. L'échec de l'écriture d'audit fait échouer l'opération.
- **NFR22 :** Les montants sont stockés en **entiers XOF**, jamais en nombres à virgule flottante.
- **NFR23 :** Les dates et heures sont stockées de façon non ambiguë et affichées dans le fuseau `Africa/Niamey`.

### 6.5 Exploitation et données

- **NFR24 :** La base de données et les pièces jointes font l'objet d'une sauvegarde quotidienne automatique.
- **NFR25 :** Un **test de restauration** est exécuté et documenté selon une périodicité définie ; il constitue une tâche planifiée, pas une intention. La procédure et son dernier résultat sont consignés.
- **NFR26 :** Les données du personnel et les justificatifs financiers sont conservés au moins **10 ans**, sous réserve de confirmation *(question ouverte Q12)*.
- **NFR27 :** L'application supporte **5 à 100 utilisateurs** sans changement d'architecture.
- **NFR28 :** L'application n'est **pas multi-entreprise** ; aucune notion de locataire n'existe dans le modèle de données du MVP.

### 6.6 Ergonomie et accessibilité

- **NFR29 :** Interface en **français simple**, phrases courtes, vocabulaire orienté contribution et non surveillance.
- **NFR30 :** Accessibilité raisonnable : navigation au clavier sur tous les formulaires, libellés explicites associés aux champs, contraste conforme **WCAG 2.1 niveau AA**.
- **NFR31 :** Aucune information n'est portée par la couleur seule ; tout code couleur est doublé d'un libellé ou d'une icône.
- **NFR32 :** Tout message d'erreur indique ce qui s'est passé et l'action attendue de l'utilisateur, en français, sans terme technique.

---

## 7. User Interface Design Goals

### 7.1 Overall UX Vision

PTR Staff doit être vécu comme un **relevé de contribution**, pas comme un pointage. Le besoin réel
de l'exécutant n'est pas d'être suivi, c'est d'être reconnu et de ne jamais être accusé sans trace.
Le vocabulaire, la hiérarchie visuelle et les écrans de synthèse doivent servir cette lecture.

La règle de conception dominante est la **sobriété** : un écran qui exige le desktop est un écran mal
conçu pour cette entreprise. Chaque ajout à un formulaire quotidien se paie en taux d'abandon.

### 7.2 Key Interaction Paradigms

- **Un objectif par écran.** Pas de tableau de bord dense sur téléphone ; des blocs empilés, le plus
  urgent en haut.
- **L'action attendue en tête.** Le tableau de bord d'un associé s'ouvre sur « En attente de mon
  approbation » ; celui d'un exécutant sur « Mon rapport du jour ».
- **Trois interactions maximum** entre une notification et l'action qu'elle demande.
- **Pré-remplissage systématique** partout où une donnée est déjà connue du système.
- **La preuve est un champ de premier plan**, pas une option repliée en bas de formulaire.
- **Aucun écran ne présente de classement comparatif entre personnes.**

### 7.3 Core Screens and Views

| Écran | Étape | Rôle principal |
|---|---|---|
| Connexion et changement de mot de passe obligatoire | 1 | Tous |
| Tableau de bord personnel | 2 | Tous |
| Bloc « En attente de mon approbation » | 1 | `direction` |
| Gestion des comptes et des rôles | 1 | `direction` |
| Journal d'audit | 1 | `direction` |
| Paramètres généraux | 1 | `direction` |
| Déclaration et approbation d'absence | 1 | Tous |
| Demande de dépense — création et décision | 1 | Tous / `direction` |
| Objectifs du mois — liste, détail, validation | 2 | Tous |
| Projet, tâches et livrables | 2 | Tous |
| Rapport quotidien — saisie et historique | 3 | Tous |
| Validation des rapports de l'équipe | 3 | `tuteur`, `direction` |
| Blocage — création et suivi | 3 | Tous |
| Revue hebdomadaire | 3 | `tuteur`, `direction` |
| Dossier de stagiaire et plan de stage | 3 | `tuteur`, `direction` |
| Bibliothèque de documents internes | 3 | Tous |
| Comptes financiers et encaissements | 4 | `finance`, `direction` |
| Contrat — répartition et parts | 4 | `finance`, `direction` |
| Rapprochement hebdomadaire | 4 | `finance`, `direction` |
| Rapport financier mensuel et clôture | 4 | `finance`, `direction` |
| Réserve et niveau d'alerte | 4 | `direction` |
| Tableau de bord direction consolidé | 4 | `direction` |

### 7.4 Accessibility

**WCAG 2.1 niveau AA** sur le contraste, les libellés de champ et la navigation clavier. Un niveau
supérieur n'est pas exigé compte tenu de la taille et du profil de la population utilisatrice.

### 7.5 Branding

Aucune charte graphique formelle n'a été fournie. Contraintes retenues : identité PTR Niger sobre,
interface neutre et lisible en plein soleil, aucun élément décoratif coûteux en bande passante.
Le nom visible de l'application reste à confirmer *(question ouverte Q16)*.

### 7.6 Target Device and Platforms

**Web Responsive, mobile-first.** Chrome Android en cible prioritaire ; Chrome desktop et Safari
récent supportés. Aucune application native, aucune PWA installable en MVP.

---

## 8. Technical Assumptions

> Cette section consigne **uniquement** les contraintes déjà arrêtées par la direction et les
> exigences produit qui pèsent sur la conception. Les choix laissés ouverts au § 8.4 relèvent de
> l'agent Architect et ne sont volontairement pas tranchés ici.

### 8.1 Repository Structure

**Monorepo.** Dépôt Laravel unique, sans séparation front/back, sans API publique distincte.

### 8.2 Service Architecture

**Monolithe modulaire.** L'application couvre quatre domaines faiblement couplés — Identité &
organisation, Redevabilité, Encadrement, Finance. Un découpage en modules internes est recommandé
pour contenir la complexité. Les microservices sont hors de proportion pour 5 à 100 utilisateurs et
une petite équipe de développement.

### 8.3 Contraintes techniques imposées par la direction

- **Backend :** Laravel 13 / PHP 8.3, structure « slim » (middleware, exceptions et routes dans `bootstrap/app.php`).
- **Frontend :** Vue 3 intégré dans Laravel — application unique, pas de SPA séparée.
- **Build et styles :** Vite 8, Tailwind CSS 4 en configuration CSS-first.
- **Base de données de développement :** SQLite.
- **Domaine :** `staff.ptrniger.com`.
- **Intégrations externes en MVP : aucune.** Pas de banque, pas de Mobile Money, pas de SMS, pas de WhatsApp.

### 8.4 Décisions explicitement laissées à l'Architecte

| # | Décision ouverte | Critère produit à respecter |
|---|---|---|
| A-01 | **Inertia.js** (pages Vue rendues par les contrôleurs) **ou composants Vue montés dans des vues Blade** | Le poids de page en 3G (NFR1, NFR2) est le critère principal, devant le confort de développement. |
| A-02 | Base de données de production | Doit supporter l'immuabilité et l'audit transactionnel (NFR21) et 100 utilisateurs (NFR27). |
| A-03 | Hébergement et stratégie de sauvegarde / restauration | Doit permettre le test de restauration documenté (NFR25). *Question ouverte Q7.* |
| A-04 | Stockage des pièces jointes | Hors racine web, accès contrôlé ou lien signé (NFR15). |
| A-05 | Mécanisme d'immuabilité et de versionnement | Doit couvrir finance, objectifs validés et rapports quotidiens dès l'Étape 1 (NFR20). |
| A-06 | Modèle **personne / compte applicatif séparé** | Exigence structurelle dès l'Étape 1, même si les commissions récurrentes sont hors MVP *(voir CONTRA-02)*. |
| A-07 | Stratégie de notification en application | Doit permettre l'ajout ultérieur de SMS/WhatsApp sans refonte. |

### 8.5 Testing Requirements

**Unit + Integration**, avec une exigence particulière :

- Un test par changement, conformément aux standards du dépôt.
- **Toute règle métier bloquante fait l'objet d'un test dédié** : limite de 3 objectifs, limite de 5 priorités, limite de stagiaires par tuteur, deux approbateurs distincts, demandeur ≠ approbateur, préparateur ≠ contrôleur, interdiction de suppression financière, blocage d'écriture sur mois clôturé.
- **Tests d'accès par URL directe** pour chaque combinaison rôle × ressource protégée, exécutés à chaque étape (NFR14, critère d'acceptation 2).
- Recette manuelle obligatoire **sur téléphone réel en conditions réseau dégradées** avant mise en service de chaque étape (NFR1, NFR4).

---

## 9. Epic List

| Epic | Titre | Objectif en une phrase | Étape |
|---|---|---|---|
| **1** | Socle de confiance : accès, audit et autorisation des dépenses | Rendre l'accès contrôlé, chaque action sensible traçable, et fermer immédiatement le trou le plus dangereux en interdisant toute dépense sans double autorisation enregistrée. | 1 |
| **2** | Cadrage du travail : objectifs et projets | Faire en sorte que chaque personne, dirigeants inclus, dispose d'objectifs écrits, mesurables et rattachés à des projets et des tâches. | 2 |
| **3** | Redevabilité quotidienne et encadrement | Installer la boucle quotidienne et hebdomadaire — rapport avec preuve, blocage, revue — et plafonner la charge d'encadrement des stagiaires. | 3 |
| **4** | Argent et pilotage | Tracer chaque franc de bout en bout, calculer les parts et la réserve à l'encaissement réel, et déclencher seule l'alerte de trésorerie. | 4 |

**Justification du découpage.** Quatre epics correspondant aux quatre étapes de livraison validées.
Chaque epic est déployable et apporte une valeur autonome. L'Epic 1 est volumineux car il porte à
la fois le socle technique et l'atténuation exigée par la direction (audit et registre de dépenses
avancés) ; il peut être scindé en « 1a — accès et audit » et « 1b — absences et dépenses » si la
vélocité constatée l'impose, sans changer l'ordre des stories.

---

## 10. Epic Details

---

### Epic 1 — Socle de confiance : accès, audit et autorisation des dépenses

**Objectif.** Établir l'ossature sur laquelle tout le reste repose : un accès strictement contrôlé
côté serveur, un journal d'audit opérationnel **avant** la première écriture sensible, et le
paramétrage de l'entreprise. Cet epic livre en outre deux éléments avancés par décision de la
direction : le calendrier des jours travaillés avec les absences, sans lesquels les indicateurs de
ponctualité seraient faux, et le registre des demandes de dépense à double approbation, qui ferme
dès la première mise en service la défaillance ayant déjà coûté l'entreprise.

---

#### Story 1.1 — Fondation applicative et page de santé

En tant qu'**équipe de développement**,
je veux une application Laravel initialisée, versionnée et déployable,
afin que toute story ultérieure s'appuie sur une base testée et livrable.

**Critères d'acceptation**

1. Le dépôt contient une application Laravel 13 / PHP 8.3 démarrable par `php artisan serve` sans erreur.
2. Une route `/sante` retourne un statut applicatif (version, connexion base, horodatage en `Africa/Niamey`) en HTTP 200.
3. La suite de tests s'exécute par `php artisan test` et passe intégralement.
4. Le formatage `vendor/bin/pint --dirty` ne remonte aucune violation.
5. Les montants monétaires disposent d'un type ou d'un cast partagé stockant des **entiers XOF** ; un test échoue si une valeur à virgule flottante est persistée.
6. Le fuseau applicatif est `Africa/Niamey` et la locale `fr` ; un test vérifie l'affichage d'une date connue.

---

#### Story 1.2 — Journal d'audit

En tant que **direction**,
je veux que toute action sensible soit enregistrée de façon inaltérable,
afin de disposer d'une trace opposable, y compris en cas de litige entre associés.

**Critères d'acceptation**

1. Une entrée d'audit contient auteur, horodatage, type d'objet, identifiant d'objet, action, ancienne valeur et nouvelle valeur.
2. L'écriture de l'audit se fait dans la **même transaction** que l'opération métier ; un test prouve qu'un échec d'écriture d'audit annule l'opération métier.
3. Aucune route, aucun formulaire et aucune commande applicative ne permet de modifier ou supprimer une entrée d'audit ; un test le vérifie pour les verbes `PUT`, `PATCH` et `DELETE`.
4. Un écran de consultation filtrable par auteur, période, type d'objet et action est accessible **au rôle `direction` uniquement**.
5. Un utilisateur `finance`, `tuteur`, `employe`, `stagiaire` ou `super_admin` accédant à l'URL du journal par saisie directe reçoit un refus (FR23, D-04).
6. L'export CSV du journal est réservé à `direction` et génère lui-même une entrée d'audit.

---

#### Story 1.3 — Rôles, permissions et contrôle d'accès serveur

En tant que **direction**,
je veux attribuer des rôles et des permissions fines contrôlés côté serveur,
afin qu'aucun utilisateur n'atteigne un écran ou une donnée interdite, même par URL directe.

**Critères d'acceptation**

1. Le modèle gère rôles et permissions unitaires ; un utilisateur peut porter plusieurs rôles et ses permissions effectives sont l'union de celles-ci.
2. Les six rôles `super_admin`, `direction`, `finance`, `tuteur`, `employe`, `stagiaire` existent avec les permissions de la matrice § 4.3.
3. `super_admin` ne détient **aucune** permission métier par défaut ; un test vérifie qu'il ne peut ni approuver une dépense, ni valider un objectif, ni valider un rapport financier.
4. Toute requête vers une ressource non autorisée retourne un refus explicite, sans contenu partiel ni redirection silencieuse.
5. Une suite de tests couvre chaque combinaison rôle × ressource protégée par accès URL direct ; elle est exécutée à chaque étape.
6. Toute attribution, modification ou retrait de rôle ou de permission produit une entrée d'audit avec ancienne et nouvelle valeur.
7. Le modèle permet la création d'un rôle strictement lecture seule sans modification de schéma ; un test crée un tel rôle et vérifie qu'il ne peut effectuer aucune écriture.

---

#### Story 1.4 — Fiche personne et compte applicatif

En tant que **direction**,
je veux que l'identité d'une personne survive à la fermeture de son compte,
afin qu'un départ ne fasse disparaître ni son historique ni ses droits financiers.

**Critères d'acceptation**

1. Une **fiche personne** existe indépendamment du compte applicatif et porte l'identité durable.
2. Un compte applicatif est rattaché à exactement une fiche personne ; une fiche personne peut porter plusieurs comptes successifs.
3. La désactivation ou l'archivage d'un compte laisse la fiche personne intacte et consultable.
4. Le retour d'une personne crée un nouveau compte rattaché à la fiche existante ; l'historique des deux comptes reste consultable depuis la fiche.
5. Un test vérifie qu'aucune opération applicative ne supprime physiquement une fiche personne.

---

#### Story 1.5 — Authentification par téléphone et cycle de vie du compte

En tant qu'**utilisateur de PTR Niger**,
je veux me connecter avec mon numéro et mon mot de passe,
afin d'accéder à mon espace sans dépendre d'une adresse électronique.

**Critères d'acceptation**

1. Aucune route publique ne permet la création d'un compte ; seule `direction` ou `super_admin` en crée un.
2. Le numéro saisi est normalisé au format international avec `+227` par défaut avant enregistrement et avant comparaison ; les saisies `90123456`, `+22790123456` et `00227 90 12 34 56` désignent le même compte.
3. L'unicité du numéro s'applique aux comptes dont l'état n'est pas `archive` ; un test crée un compte avec le numéro d'un compte archivé et réussit.
4. La création génère un mot de passe temporaire ; la première connexion redirige vers le changement de mot de passe et bloque tout autre accès tant qu'il n'est pas effectué.
5. Les états `invite`, `actif`, `suspendu`, `termine`, `archive` existent ; seul `actif` autorise la connexion, vérifié pour les quatre autres.
6. Le passage à `suspendu` et tout changement de mot de passe invalident **toutes** les sessions du compte ; un test ouvre deux sessions, suspend le compte et vérifie que les deux sont rejetées à la requête suivante.
7. Après N tentatives échouées consécutives (N paramétrable), le compte est bloqué pour une durée paramétrable ; blocage et expiration sont journalisés.
8. Les connexions réussies, tentatives échouées et sessions ouvertes sont consultables par `direction`.
9. La réinitialisation d'un mot de passe par `direction` ou `super_admin` produit une entrée d'audit nommant l'auteur et la cible.

---

#### Story 1.6 — Organisation, profils et historique

En tant que **direction**,
je veux décrire l'entreprise, ses services et ses fonctions, et tenir à jour chaque fiche,
afin que chaque personne ait un responsable, une fonction et un statut identifiés.

**Critères d'acceptation**

1. Une fiche entreprise unique existe ; aucune interface ne permet d'en créer une seconde.
2. Services et fonctions sont administrables par `direction`.
3. La fiche utilisateur porte nom, téléphone, photo optionnelle, rôles, service, fonction, responsable direct, type de relation et dates de contrat ou de stage.
4. Le statut opérationnel (`actif`, `absent`, `suspendu`, `sorti`) est distinct de l'état du compte ; un test vérifie qu'ils évoluent indépendamment.
5. Tout changement de rôle, service, responsable ou statut est historisé avec date, auteur, ancienne et nouvelle valeur, et audité.
6. Un document rattaché au dossier d'une personne n'est accessible qu'à cette personne, son responsable direct et `direction` ; l'accès par URL directe depuis un autre compte est refusé.

---

#### Story 1.7 — Paramètres généraux

En tant que **direction**,
je veux administrer moi-même les règles chiffrées de l'application,
afin de changer une limite ou une charge fixe sans demander de développement.

**Critères d'acceptation**

1. Sont paramétrables depuis l'interface : jours travaillés, jours fériés, heure limite du rapport, délai de rappel, limite de stagiaires par tuteur, pourcentage de réserve, objectif de réserve en mois, types et taille des pièces jointes, catégories de dépense, charges fixes.
2. Les valeurs initiales sont : limite stagiaires **3**, réserve **20 %**, objectif **3 mois**, heure limite **17 h 45**, rappel **60 minutes**.
3. Aucune de ces valeurs n'apparaît en dur dans le code ; un test modifie chaque paramètre et vérifie le changement de comportement associé.
4. Toute modification de paramètre est auditée avec ancienne et nouvelle valeur et porte une date d'effet.
5. La modification d'un paramètre est réservée à `direction` ; l'accès par un autre rôle est refusé, y compris par URL directe.

---

#### Story 1.8 — Notifications dans l'application

En tant qu'**utilisateur**,
je veux être averti dans l'application de ce qui m'attend,
afin de ne pas découvrir un retard ou une demande après coup.

**Critères d'acceptation**

1. Un centre de notifications avec compteur de non-lues est accessible depuis toute page authentifiée.
2. Une notification porte un lien direct vers l'objet concerné.
3. Depuis la notification, l'action attendue est atteignable en **au plus 3 interactions** ; la mesure est consignée en recette pour l'approbation de dépense et la validation de rapport.
4. Une notification est marquée lue explicitement par l'utilisateur ou implicitement à l'ouverture de l'objet ; les deux comportements sont testés.
5. Aucun envoi SMS, WhatsApp ou courriel n'est déclenché ; un test vérifie qu'aucun canal externe n'est appelé.

---

#### Story 1.9 — Calendrier des jours travaillés

En tant que **direction**,
je veux déclarer les jours travaillés et les jours fériés,
afin que l'application n'attende pas de rapport un jour où l'entreprise est fermée.

**Critères d'acceptation**

1. Les jours travaillés de la semaine sont paramétrables, initialisés du lundi au vendredi.
2. Des jours fériés ou de fermeture ponctuels sont saisissables avec libellé et date.
3. Une fonction applicative répond « jour travaillé : oui/non » pour toute date donnée, et est testée sur un jour ouvré, un week-end et un jour férié saisi.
4. Aucun rapport quotidien n'est attendu ni compté comme manquant sur un jour non travaillé.
5. Toute modification du calendrier est auditée.

---

#### Story 1.10 — Déclaration et approbation d'absence

En tant qu'**employé ou stagiaire**,
je veux déclarer mon absence et la faire approuver,
afin de ne pas être compté en retard alors que j'étais en congé ou malade.

**Critères d'acceptation**

1. Une absence porte type (congé, maladie, autre), date de début, date de fin, motif court, justificatif optionnel.
2. Les états sont `demandee`, `approuvee`, `refusee`, `annulee` ; le refus exige un motif.
3. L'approbation appartient au responsable direct ; un utilisateur ne peut pas approuver sa propre absence.
4. Aucun rapport quotidien n'est attendu sur un jour couvert par une absence `approuvee` ; un test crée une absence approuvée et vérifie que le jour n'apparaît pas dans les rapports manquants.
5. Une absence `demandee` ou `refusee` ne suspend pas l'attente de rapport.
6. Les indicateurs de ponctualité excluent du dénominateur les jours non travaillés et les jours d'absence approuvée ; un test calcule le taux sur un mois comportant l'un et l'autre.
7. Un utilisateur consulte ses propres absences ; un responsable celles de son équipe ; `direction` toutes. Les autres accès sont refusés.

---

#### Story 1.11 — Demande de dépense et registre

En tant que **membre de l'équipe**,
je veux enregistrer toute demande de dépense avant de payer,
afin qu'aucun franc ne sorte de l'entreprise sans trace ni autorisation.

**Critères d'acceptation**

1. Une demande porte demandeur, motif, montant en XOF entier, bénéficiaire, projet ou contrat optionnel, résultat attendu, catégorie, justificatif prévisionnel optionnel.
2. Les états `demandee`, `approuvee`, `refusee`, `payee`, `annulee` existent et sont distincts ; l'état d'approbation et l'état de paiement ne sont jamais confondus.
3. Tout rôle applicatif peut créer une demande, y compris `stagiaire`.
4. Une demande créée est immédiatement visible dans le registre des dépenses de `direction` et `finance`.
5. Aucune interface ne permet de supprimer une demande ; l'annulation exige un motif et conserve l'enregistrement.
6. La création, la modification et l'annulation d'une demande produisent chacune une entrée d'audit.

---

#### Story 1.12 — Double approbation obligatoire des dépenses

En tant qu'**associé propriétaire**,
je veux que toute dépense exige nos deux consentements,
afin qu'aucune sortie d'argent ne dépende d'une seule personne.

**Critères d'acceptation**

1. Une dépense passe à `approuvee` uniquement après enregistrement de l'approbation de **deux comptes `direction` distincts**, quel que soit le montant et qu'elle soit ou non prévue au budget.
2. Aucun seuil de montant n'existe dans le code ni dans le paramétrage ; un test soumet une dépense de 1 000 FCFA et vérifie que deux approbations restent exigées.
3. Le demandeur ne peut jamais compter comme approbateur de sa propre demande, y compris s'il porte le rôle `direction` ; un test le vérifie.
4. Tant qu'une seule approbation est enregistrée, la dépense reste à `demandee` et n'est pas payable ; toute tentative de paiement est refusée côté serveur.
5. Aucune route, aucune permission et aucun paramètre ne permet une approbation dérogatoire ou déléguée ; un test cherche à approuver avec un seul compte `direction` et échoue.
6. Un refus exige un motif ; le refus d'un seul approbateur suffit à placer la dépense à `refusee`.
7. Chaque approbation et chaque refus produit une entrée d'audit nommant l'auteur et l'horodatage.

---

#### Story 1.13 — Écran « En attente de mon approbation » et relances

En tant qu'**associé propriétaire**,
je veux voir immédiatement ce qui attend ma signature et être relancé,
afin que le gel des dépenses reste court et que personne ne paie de sa poche.

**Critères d'acceptation**

1. Le tableau de bord d'un compte `direction` affiche **en première position** le bloc « En attente de mon approbation », avec le nombre de dépenses et l'ancienneté de la plus ancienne.
2. Le bloc ne liste que les dépenses que ce compte n'a pas encore traitées et dont il n'est pas le demandeur.
3. Depuis une notification de dépense à approuver, la décision d'approbation est atteignable en **au plus 3 interactions** ; mesuré en recette sur téléphone.
4. Une dépense sans décision de l'un des deux approbateurs déclenche un rappel à **J+1** et à **J+2** vers l'approbateur manquant uniquement.
5. Les rappels cessent dès que la décision est prise ; un test vérifie qu'aucun rappel n'est émis après approbation ou refus.
6. Le bloc est vide et n'affiche aucun message d'erreur lorsqu'aucune dépense n'attend le compte connecté.

---

### Epic 2 — Cadrage du travail : objectifs et projets

**Objectif.** Rendre le travail explicite avant qu'il ne soit exécuté. Cet epic livre les objectifs
d'entreprise et individuels avec leurs limites bloquantes, la preuve comme condition d'atteinte, et
le rattachement aux projets et aux tâches. À l'issue de cet epic, la question « qui n'a pas
d'objectif ce mois-ci » a une réponse à l'écran, et les dirigeants figurent dans cette réponse au
même titre que les autres.

---

#### Story 2.1 — Tableau de bord personnel

En tant qu'**utilisateur**,
je veux voir en dix secondes ce que j'ai à faire aujourd'hui,
afin de ne pas découvrir mes priorités en réunion.

**Critères d'acceptation**

1. Le tableau de bord affiche : objectifs du mois avec progression, tâches du jour, rapport du jour à envoyer, blocages ouverts, prochaines échéances, notifications, dernière évaluation, demandes en attente.
2. Chaque bloc n'est rendu que si l'utilisateur détient la permission correspondante ; un bloc non autorisé est absent, sans bloc vide ni message d'erreur.
3. Les blocs sont empilés verticalement et lisibles sans défilement horizontal à 320 px de largeur.
4. Le bloc le plus urgent du jour figure en tête : « En attente de mon approbation » pour `direction`, « Mon rapport du jour » pour les autres.
5. Le premier rendu utile intervient en moins de 3 secondes en conditions 3G dégradées simulées.

---

#### Story 2.2 — Priorités d'entreprise du mois

En tant que **direction**,
je veux fixer au plus cinq priorités par mois,
afin que l'entreprise poursuive un nombre d'objectifs qu'elle peut réellement tenir.

**Critères d'acceptation**

1. Une priorité d'entreprise porte titre, description courte, responsable, indicateur, cible, échéance, priorité.
2. La création d'une **sixième** priorité validée pour un même mois est refusée côté serveur avec un message nommant la limite et le mois concerné.
3. Une priorité annulée ne compte plus dans la limite ; un test annule puis crée une nouvelle priorité avec succès.
4. Les priorités d'entreprise sont en **lecture** pour tous les rôles applicatifs et en **gestion** pour `direction` seule.
5. Toute modification d'une priorité validée exige un motif et produit une entrée d'audit avec ancienne et nouvelle valeur.

---

#### Story 2.3 — Objectifs individuels et limite de trois par mois

En tant qu'**utilisateur**,
je veux au plus trois objectifs majeurs par mois,
afin de concentrer mon effort sur ce qui compte réellement.

**Critères d'acceptation**

1. Un objectif porte titre, description courte, responsable, indicateur, valeur cible, **preuve attendue**, date limite, moyens nécessaires, priorité.
2. Un objectif individuel peut être rattaché à une priorité d'entreprise et/ou à un projet.
3. La validation d'un **quatrième** objectif pour une même personne et un même mois est refusée côté serveur avec un message nommant la personne, le mois et les trois objectifs existants.
4. La limite compte les objectifs aux états `valide`, `en_cours`, `atteint`, `partiellement_atteint`, `non_atteint` et `bloque` ; elle ignore `brouillon` et `annule`. Chaque cas est testé.
5. La règle s'applique **identiquement aux comptes `direction`** ; un test valide trois objectifs pour un associé et vérifie le refus du quatrième (P5, RM-03).
6. Un utilisateur peut **proposer** un objectif : celui-ci reste `brouillon` et ne devient officiel qu'après validation de son responsable.

---

#### Story 2.4 — États, progression et preuve d'un objectif

En tant que **responsable**,
je veux qu'un objectif ne puisse être déclaré atteint sans preuve,
afin qu'aucun résultat ne repose sur une simple déclaration.

**Critères d'acceptation**

1. Les huit états `brouillon`, `valide`, `en_cours`, `atteint`, `partiellement_atteint`, `non_atteint`, `bloque`, `annule` existent et leurs transitions autorisées sont testées.
2. Le passage à `atteint` est **refusé** si aucune preuve n'est attachée ; le message indique la preuve attendue déclarée à la création (P1, FR47).
3. L'utilisateur met à jour la progression et attache une preuve à tout moment ; le responsable commente, valide ou demande une correction.
4. Le code couleur vert / orange / rouge / gris est systématiquement doublé d'un libellé textuel.
5. Toute modification après validation exige un motif, conserve valeur précédente et auteur, et produit une entrée d'audit ; un test lit l'ancienne valeur dans le journal.

---

#### Story 2.5 — Vues et synthèse mensuelle des objectifs

En tant que **responsable ou membre**,
je veux consulter les objectifs en liste, en calendrier et en synthèse mensuelle,
afin de repérer les retards avant l'échéance.

**Critères d'acceptation**

1. Trois vues existent : liste, calendrier, synthèse mensuelle.
2. Chaque vue applique strictement la matrice § 4.3 : `direction` tous, `tuteur` son équipe, autres les leurs.
3. Un accès par URL directe aux objectifs d'une personne hors périmètre est refusé.
4. La synthèse mensuelle affiche le nombre d'objectifs par état et la liste des **membres sans objectif validé** pour le mois.
5. La copie d'un objectif récurrent vers le mois suivant crée un objectif à l'état `brouillon` exigeant une nouvelle validation.

---

#### Story 2.6 — Projets et membres

En tant que **responsable de projet**,
je veux créer un projet et y rattacher des membres,
afin que le travail collectif ait un cadre identifié.

**Critères d'acceptation**

1. Un projet porte nom, client optionnel, responsable, dates, statut, membres.
2. Les statuts `prevu`, `actif`, `bloque`, `en_validation`, `livre`, `cloture`, `annule` existent et leurs transitions sont testées.
3. La partie budgétaire d'un projet n'est visible que par `direction` et `finance` ; l'accès par URL directe depuis un autre rôle est refusé.
4. Tout changement de statut d'un projet est historisé avec auteur et date.
5. Un membre retiré d'un projet conserve la trace de sa participation passée.

---

#### Story 2.7 — Tâches, sous-tâches et pièces jointes

En tant que **membre d'un projet**,
je veux gérer mes tâches et y joindre mes éléments,
afin que mon travail du jour soit identifiable et prouvable.

**Critères d'acceptation**

1. Une tâche porte titre, responsable, échéance, priorité, lien optionnel vers un objectif, statut.
2. Les sous-tâches sont limitées à un seul niveau de profondeur ; la création d'une sous-sous-tâche est refusée.
3. Tâches et projets acceptent pièces jointes, liens et commentaires.
4. Les pièces jointes respectent les types et la taille maximale paramétrés ; un téléversement non conforme est refusé **côté serveur** même si le contrôle client est contourné.
5. Une pièce jointe n'est jamais accessible par URL publique ; l'accès sans autorisation est refusé.
6. La liste des tâches est filtrable par responsable, échéance, statut et projet.

---

#### Story 2.8 — Livrables

En tant que **responsable de projet**,
je veux suivre les livrables et leur validation,
afin de savoir ce qui a réellement été remis au client.

**Critères d'acceptation**

1. Un livrable porte responsable, date prévue, date réelle, statut de validation.
2. Un livrable ne peut être marqué validé que par le responsable du projet ou `direction`.
3. L'écart entre date prévue et date réelle est calculé et affiché.
4. Tout changement de statut d'un livrable est historisé.

---

### Epic 3 — Redevabilité quotidienne et encadrement

**Objectif.** Installer les deux boucles courtes du produit : le rapport quotidien avec preuve,
validé ou retourné par le responsable, et la revue du vendredi. Cet epic porte le point de vérité
du produit — la saisie de fin de journée sur téléphone en 3G — et plafonne la charge d'encadrement
des stagiaires par une limite bloquante paramétrable.

---

#### Story 3.1 — Saisie du rapport quotidien

En tant qu'**utilisateur**,
je veux rendre compte de ma journée en moins de trois minutes depuis mon téléphone,
afin que rendre compte reste un geste tenable tous les jours.

**Critères d'acceptation**

1. Il existe au plus un rapport par personne et par jour travaillé ; une seconde création pour le même jour ouvre le rapport existant.
2. Les six champs obligatoires sont présents : tâche prévue, résultat obtenu, preuve ou lien, blocage, prochaine action, aide demandée.
3. Le champ « tâche prévue » est pré-rempli à partir des tâches assignées pour la journée, et reste modifiable.
4. Le rapport accepte l'attachement d'une image, d'un document ou d'un lien, dans les limites de type et de taille paramétrées.
5. Le brouillon est sauvegardé automatiquement au plus tard **10 secondes** après la dernière frappe.
6. Une saisie interrompue par une coupure réseau est restaurée intégralement à la réouverture sur le même appareil ; testé en simulant une coupure au milieu de la saisie.
7. **La saisie complète est réalisable en moins de 3 minutes** sur téléphone réel avec pré-remplissage actif ; la mesure est consignée en recette et conditionne la mise en service de l'étape.
8. Le formulaire est utilisable sans défilement horizontal à 320 px et chaque cible tactile mesure au moins 44 × 44 px.

---

#### Story 3.2 — Envoi, heure limite, rappel et retard

En tant que **responsable**,
je veux que l'heure limite et les rappels soient gérés par l'application,
afin de ne plus courir après les rapports.

**Critères d'acceptation**

1. Les états `brouillon`, `envoye`, `valide`, `retourne`, `en_retard` existent et leurs transitions sont testées.
2. Un rappel est émis au délai paramétré avant l'heure limite (60 minutes par défaut) à toute personne dont le rapport n'est pas `envoye`.
3. Une notification de retard est émise après l'heure limite si le rapport n'est pas `envoye`.
4. Aucun rappel ni retard n'est émis pour un jour non travaillé ou couvert par une absence approuvée ; testé pour les deux cas.
5. Un rapport envoyé après l'heure limite affiche le retard constaté et propose une explication courte facultative.
6. La modification de l'heure limite au paramétrage change le comportement sans redéploiement ; un test le vérifie.

---

#### Story 3.3 — Validation, retour et historique des versions

En tant qu'**utilisateur**,
je veux que mon responsable ne puisse pas réécrire mon rapport,
afin que ce qui est enregistré à mon nom soit exactement ce que j'ai écrit.

**Critères d'acceptation**

1. Le responsable peut commenter, valider ou retourner un rapport.
2. Le responsable **ne peut modifier aucun champ saisi par l'auteur** ; une requête de modification par un compte autre que l'auteur est refusée côté serveur, y compris pour `direction`.
3. Une correction par l'auteur après envoi crée une **nouvelle version** ; la version précédente reste consultable avec son horodatage.
4. Un rapport retourné revient à l'auteur avec le motif du retour et une notification.
5. Le périmètre de validation respecte la matrice : `tuteur` son équipe, `direction` tous ; l'accès hors périmètre par URL directe est refusé.
6. Chaque validation, retour et nouvelle version produit une entrée d'audit.

---

#### Story 3.4 — Vues des rapports et rapports manquants

En tant que **direction**,
je veux voir qui a rendu son rapport et qui ne l'a pas rendu,
afin de traiter le manquement le jour même plutôt qu'en fin de mois.

**Critères d'acceptation**

1. Trois vues existent : quotidienne, hebdomadaire, mensuelle.
2. Une liste « rapports manquants du jour » affiche les personnes attendues sans rapport `envoye`.
3. Cette liste **exclut** les personnes en absence approuvée et les jours non travaillés ; testé sur un mois comportant les deux.
4. Le taux de ponctualité affiché est le rapport des rapports envoyés avant l'heure limite sur les rapports attendus, dénominateur corrigé des absences.
5. Chaque vue applique strictement la matrice § 4.3.

---

#### Story 3.5 — Demande de nouvelle tâche

En tant qu'**utilisateur sans tâche disponible**,
je veux demander une tâche depuis mon rapport,
afin de ne pas rester sans travail en attendant une réunion.

**Critères d'acceptation**

1. Une demande de nouvelle tâche est créable depuis le formulaire de rapport quotidien.
2. La demande est notifiée immédiatement au responsable direct.
3. La demande porte un état ouvert / traité et conserve le lien vers le rapport d'origine.
4. Les demandes non urgentes d'un `stagiaire` sont regroupées selon la règle des créneaux de suivi (Story 3.9).

---

#### Story 3.6 — Blocages et demandes d'aide

En tant qu'**utilisateur bloqué**,
je veux signaler mon blocage et obtenir une réponse rapide,
afin qu'un obstacle ne consomme pas une journée entière.

**Critères d'acceptation**

1. Un blocage est créable depuis une tâche, un objectif ou un rapport, et conserve le lien vers son origine ; les trois chemins sont testés.
2. Le blocage porte problème, niveau d'urgence, personne sollicitée, date, effet sur l'échéance, action déjà essayée.
3. Les états `ouvert`, `pris_en_charge`, `resolu`, `ferme_sans_solution` existent.
4. La création notifie **immédiatement** la personne sollicitée, sans regroupement, lorsque l'urgence est marquée.
5. Le délai création → `pris_en_charge` et le délai création → `resolu` sont calculés et affichés.
6. La fermeture sans solution exige un motif.

---

#### Story 3.7 — Revue hebdomadaire

En tant que **responsable**,
je veux mener la revue du vendredi sur une base factuelle,
afin que l'échange porte sur des résultats et non sur des impressions.

**Critères d'acceptation**

1. Une revue est ouvrable par le responsable pour chaque membre de son équipe, avec périodicité hebdomadaire par défaut le vendredi.
2. La revue présente automatiquement les objectifs, tâches, rapports et blocages de la semaine concernée, sans ressaisie.
3. Pour chaque objectif : résultat, preuve, statut, cause de l'écart, prochaine action sont enregistrables.
4. La revue enregistre le commentaire de la personne évaluée **et** celui du responsable ; la validation électronique des deux parties est horodatée et nominative.
5. **Les comptes `direction` suivent la même procédure** ; un test crée une revue pour un associé (P5).
6. Aucun classement comparatif entre personnes n'apparaît sur aucun écran de revue.
7. L'historique des revues est consultable et aucune revue validée n'est modifiable.

---

#### Story 3.8 — Plan d'amélioration

En tant que **responsable**,
je veux formaliser un plan d'amélioration court quand c'est nécessaire,
afin que l'aide apportée soit tracée aussi bien que l'écart constaté.

**Critères d'acceptation**

1. Un plan d'amélioration est créable depuis une revue, avec une durée comprise entre **7 et 14 jours** ; une durée hors bornes est refusée.
2. Le plan porte actions, aide fournie, dates, résultat constaté.
3. Le plan est visible par la personne concernée, son responsable et `direction`, et par personne d'autre.
4. **Aucune conséquence disciplinaire n'est déclenchée automatiquement** par la clôture d'un plan ; un test vérifie qu'aucun changement d'état de compte n'en découle (RM-18, P3).

---

#### Story 3.9 — Fiche d'entrée et activation d'un stagiaire

En tant que **direction**,
je veux qu'aucun stagiaire ne soit activé sans cadre défini,
afin de ne plus accueillir de stagiaires sans mission ni objectifs.

**Critères d'acceptation**

1. Une fiche d'entrée porte besoin réel, mission, responsable/tuteur, durée, outils et **trois résultats obligatoires** ; la soumission avec moins de trois résultats est refusée.
2. L'approbation de la fiche se fait en **une seule étape** par `direction` ; aucun circuit multi-états n'est implémenté en MVP.
3. Un compte `stagiaire` ne peut passer à `actif` sans fiche d'entrée approuvée, tuteur désigné **et** trois objectifs enregistrés ; les trois conditions sont testées séparément.
4. L'activation en niveau d'alerte **rouge** est refusée (C9, FR164) ; le message nomme le niveau d'alerte en cause.
5. Une checklist d'intégration est générée à l'activation : contrat ou convention, matériel, accès, règlement, première tâche, présentation du tuteur.
6. L'activation produit une entrée d'audit.

---

#### Story 3.10 — Limite bloquante de stagiaires par tuteur

En tant que **direction**,
je veux que l'application refuse d'affecter un stagiaire de trop,
afin que la charge d'encadrement ne reprenne pas le temps des exécutants.

**Critères d'acceptation**

1. L'affectation d'un stagiaire à un tuteur ayant atteint la limite paramétrée est **refusée côté serveur** ; le message nomme le tuteur et sa charge actuelle.
2. Avec la valeur initiale de 3, l'affectation d'un **quatrième** stagiaire actif est refusée ; l'affectation du troisième réussit.
3. La limite est lue depuis le paramétrage à chaque contrôle ; un test la porte à 2 puis vérifie le refus du troisième, sans redéploiement.
4. Seuls les stagiaires **actifs** comptent dans la charge ; un stagiaire terminé ou archivé libère une place, ce qui est testé.
5. Un employé porteur du rôle `tuteur` est soumis exactement à la même limite que les associés.
6. L'écran de gestion affiche pour chaque tuteur le nombre de stagiaires encadrés et signale visuellement celui qui a atteint la limite.

---

#### Story 3.11 — Plan de stage, évaluations et sortie

En tant que **tuteur**,
je veux suivre mon stagiaire sur un cadre écrit du début à la fin,
afin que le stage produise des compétences et une trace, pas seulement une présence.

**Critères d'acceptation**

1. Un plan de stage porte compétences à apprendre, objectifs, tâches hebdomadaires, preuves attendues.
2. Une évaluation hebdomadaire du stagiaire est enregistrable par le tuteur et consultable par le stagiaire.
3. Une évaluation finale est enregistrable ; l'application indique si les conditions d'attestation sont remplies, **sans générer de document** en MVP.
4. Une checklist de sortie est générée : livrables remis, matériel rendu, accès fermés, documents sauvegardés, évaluation finale enregistrée.
5. Le stagiaire consulte son dossier ; le tuteur ceux de ses stagiaires ; `direction` tous. Tout autre accès est refusé, y compris par URL directe.

---

#### Story 3.12 — Créneaux de suivi et regroupement des demandes

En tant que **tuteur**,
je veux que les demandes non urgentes de mes stagiaires me parviennent groupées,
afin de ne pas être interrompu toute la journée.

**Critères d'acceptation**

1. Des créneaux de suivi sont paramétrables par tuteur (jours et heures).
2. Les demandes non urgentes d'un stagiaire sont accumulées et présentées au tuteur au créneau suivant, en une seule notification.
3. Un blocage marqué **urgent** échappe au regroupement et notifie immédiatement le tuteur ; testé en comparant les deux chemins.
4. Le stagiaire voit à quel moment sa demande sera examinée, afin de ne pas relancer.
5. Aucune demande n'est perdue ni fusionnée : chaque demande reste un objet distinct dans la notification groupée.

---

#### Story 3.13 — Documents internes et accusés d'acceptation

En tant que **direction**,
je veux publier les règles internes et savoir qui les a acceptées,
afin qu'un engagement soit opposable.

**Critères d'acceptation**

1. Un document interne porte titre, contenu ou fichier, version, date d'application.
2. Un document peut exiger un accusé de lecture et d'acceptation, enregistré par utilisateur avec horodatage.
3. La publication d'une nouvelle version notifie les utilisateurs concernés et **réinitialise** l'exigence d'acceptation ; testé.
4. L'historique complet des versions reste consultable ; aucune version n'est supprimable.
5. Un document rattaché au dossier d'une personne n'est visible que par cette personne, son responsable direct et `direction`.
6. Publication, nouvelle version et accusé d'acceptation produisent chacun une entrée d'audit.

---

### Epic 4 — Argent et pilotage

**Objectif.** Traiter les deux défaillances qui ont déjà coûté l'entreprise. Cet epic livre la chaîne
complète — client, facture, encaissement, imputation, parts, réserve, rapprochement, clôture
mensuelle — et l'alerte vert / orange / rouge qui se déclenche sans intervention humaine. Le circuit
d'approbation à deux signatures existe déjà depuis l'Epic 1 ; il est ici prolongé jusqu'au paiement
et à l'écriture comptable.

---

#### Story 4.1 — Comptes financiers et soldes

En tant que **responsable financier**,
je veux tenir les comptes caisse, banque et Mobile Money,
afin de connaître à tout moment l'argent réellement disponible.

**Critères d'acceptation**

1. Un compte porte type (`caisse`, `banque`, `mobile_money`), libellé, solde initial en XOF entier, date du solde initial.
2. Le solde affiché est **calculé** à partir du solde initial et des mouvements validés ; aucune interface ne permet de saisir un solde courant directement.
3. Un test vérifie qu'après un encaissement de 50 000 et une dépense payée de 20 000, le solde progresse exactement de 30 000.
4. L'accès aux comptes est limité à `direction` et `finance` ; l'accès par URL directe depuis tout autre rôle est refusé.
5. Aucune intégration bancaire ou Mobile Money automatique n'existe ; un test vérifie qu'aucun appel externe n'est émis.

---

#### Story 4.2 — Catégories de dépense et charges fixes paramétrables

En tant que **direction**,
je veux administrer moi-même les catégories et les charges fixes,
afin d'ajouter un poste sans demander une modification de code.

**Critères d'acceptation**

1. Les catégories de dépense sont créables, renommables et désactivables depuis le paramétrage.
2. Une catégorie **« gratification de stagiaire »** existe, distincte des salaires.
3. Chaque catégorie porte un marqueur booléen **« dépense essentielle »**.
4. La liste des charges fixes est initialisée aux quatre postes réels : loyer, électricité, Internet, salaires ; aucun autre poste n'est présent.
5. Chaque charge fixe porte un montant mensuel et un état `active` / `inactive` ; seules les actives entrent dans l'assiette d'alerte et l'objectif de réserve.
6. L'ajout d'une charge fixe affiche **avant confirmation** l'impact chiffré sur l'objectif de réserve (FR147).
7. Aucun de ces éléments n'est codé en dur ; un test ajoute une charge et vérifie que l'assiette d'alerte change sans redéploiement.

---

#### Story 4.3 — Fiche client et contrat

En tant que **responsable financier**,
je veux enregistrer le client et le contrat avec sa répartition,
afin que le calcul des parts repose sur un cadre écrit et non sur un accord oral.

**Critères d'acceptation**

1. Une fiche client porte nom, téléphone, contact optionnel, notes.
2. Un contrat porte client, projet optionnel, montant total attendu, **bénéfice prévisionnel**, apporteur (pouvant être vide), exécutants, indicateur « avec exécution ».
3. La répartition prévue est **déduite** de ces champs : apporteur vide → 100 % PTR Niger ; apporteur rempli et sans exécution → 10 / 90 ; apporteur rempli et avec exécution → 10 / 60 / 30. Les trois cas sont testés.
4. Avec plusieurs exécutants, les 30 % sont répartis en parts **strictement égales** ; testé avec deux et trois exécutants.
5. La répartition affichée nomme chaque bénéficiaire, son taux et le montant prévisionnel correspondant.
6. Aucun prospect, devis ni opportunité n'existe en MVP.
7. Création et modification d'un contrat produisent une entrée d'audit.

---

#### Story 4.4 — Facture minimale et créance

En tant que **responsable financier**,
je veux enregistrer les factures et voir ce qui reste dû,
afin que les créances cessent d'être suivies de mémoire.

**Critères d'acceptation**

1. Une facture porte numéro unique, client, contrat, montant, date d'émission, date d'échéance.
2. Le statut `impayee` / `partiellement_payee` / `payee` / `annulee` est **déduit** des encaissements imputés ; aucune interface ne permet de le saisir.
3. Une créance est déduite automatiquement de toute facture non intégralement payée dont l'échéance est atteinte.
4. La liste des créances affiche le montant restant dû et l'ancienneté en jours, triable par ancienneté.
5. L'annulation d'une facture exige un motif et ne supprime jamais l'enregistrement.
6. Aucune facture n'est générée en PDF en MVP ; aucune relance automatisée n'est envoyée.

---

#### Story 4.5 — Encaissements et reçus

En tant que **responsable financier**,
je veux enregistrer chaque encaissement avec son reçu,
afin que tout argent reçu soit rattaché à un compte et à un client.

**Critères d'acceptation**

1. Un encaissement porte client, contrat ou projet, facture optionnelle, montant, date, compte crédité, mode de paiement, référence, justificatif.
2. Chaque encaissement reçoit un **numéro de reçu unique** attribué par le système, non réutilisable même après annulation ; testé en annulant puis en créant un nouvel encaissement.
3. Aucune interface ne permet de supprimer un encaissement validé ; seules la **correction** (nouvelle version motivée) et l'**annulation** (contre-écriture motivée) existent, et les deux sont auditées.
4. Un encaissement imputé à un contrat déclenche le calcul des parts dues (Story 4.7).
5. L'application signale les encaissements créés plus de 24 h après leur date de réception déclarée (FR112).
6. Toute tentative d'imputation à un mois clôturé est refusée avec un message nommant le mois.

---

#### Story 4.6 — Paiement des dépenses et imputation

En tant que **responsable financier**,
je veux payer une dépense approuvée et l'imputer,
afin que l'approbation, le paiement et l'écriture comptable restent trois faits distincts.

**Critères d'acceptation**

1. Seule une dépense à l'état `approuvee` est payable ; la tentative de paiement d'une dépense `demandee` ou `refusee` est refusée côté serveur.
2. Le paiement enregistre compte débité, date, mode de paiement, référence, puis fait passer la dépense à `payee`.
3. Un **justificatif de paiement** est attaché après le paiement ; une dépense payée sans justificatif apparaît dans une liste dédiée jusqu'à régularisation.
4. Une dépense peut être imputée à un contrat ou à un projet ; cette imputation alimente les coûts directs du contrat.
5. Une **demande de remboursement** d'une avance personnelle suit le même circuit à deux signatures et porte le justificatif d'origine (FR125).
6. En niveau d'alerte **rouge**, l'approbation d'une dépense dont la catégorie n'est pas « essentielle » affiche un avertissement explicite mais **n'est pas bloquée** (C9).
7. Aucune dépense payée n'est supprimable ; l'annulation après paiement crée une contre-écriture motivée.

---

#### Story 4.7 — Calcul des parts au prorata des encaissements

En tant qu'**associé propriétaire**,
je veux que les parts se calculent seules au rythme des paiements du client,
afin que l'entreprise ne distribue jamais un argent qu'elle n'a pas reçu.

**Critères d'acceptation**

1. Chaque encaissement imputé à un contrat calcule les parts **au prorata du montant encaissé rapporté au montant total attendu**, appliqué au bénéfice retenu.
2. Cas de référence testé : contrat de bénéfice 1 000 000 payé en deux fois à 50 % → apporteur 50 000 puis 50 000 ; exécutants 150 000 puis 150 000 ; PTR Niger 300 000 puis 300 000.
3. Un contrat facturé et **non encaissé** génère **zéro** part due ; testé explicitement.
4. Un contrat encaissé à moitié puis abandonné n'a généré que la moitié des parts ; testé.
5. L'écran du contrat affiche en permanence : montant total attendu, total encaissé, bénéfice retenu, parts déjà versées par bénéficiaire, parts restant à verser.
6. Le calcul est affiché avec sa **méthode** : bénéfice retenu, période, encaissement d'origine, taux appliqué, montant.
7. Les parts restent dues et calculées en niveau d'alerte **rouge** ; un test place l'entreprise en rouge et vérifie que le calcul et le paiement demeurent possibles (RM-14, FR165).

---

#### Story 4.8 — Versement d'une part par le circuit de dépense

En tant qu'**associé propriétaire**,
je veux que ma propre part passe par le circuit d'approbation ordinaire,
afin qu'aucune porte dérobée n'existe pour les associés.

**Critères d'acceptation**

1. Un versement de part est enregistré comme une **dépense ordinaire**, avec bénéficiaire, contrat d'origine, base de calcul, taux appliqué et justificatif.
2. Il exige les **deux approbations `direction` distinctes**, y compris lorsque le bénéficiaire est un associé.
3. Un associé bénéficiaire ne peut pas être approbateur de sa propre part ; testé.
4. La dépense de versement apparaît au journal d'audit et dans le rapport financier mensuel.
5. Un bénéficiaire non-associé (apporteur employé ou communicateur) consulte **sa propre part uniquement** : montant, base, taux, contrat d'origine ; toute autre ligne de répartition lui est refusée, y compris par URL directe (FR136).
6. Un retrait d'argent par un associé sur la part de 60 % est une dépense ordinaire, sans mécanisme particulier (RM-20).

---

#### Story 4.9 — Imputation des coûts directs et bénéfice réalisé du contrat

En tant que **direction**,
je veux comparer le bénéfice prévu et le bénéfice réellement réalisé,
afin que la base des parts et de la réserve cesse d'être une estimation permanente.

**Critères d'acceptation**

1. Une dépense peut être imputée à un contrat ; la somme des dépenses imputées constitue les **coûts directs** du contrat.
2. Le **bénéfice réalisé** est calculé comme Σ encaissements imputés − Σ dépenses imputées, et affiché à côté du bénéfice prévisionnel.
3. L'écart prévu / réalisé est calculé et affiché en montant et en pourcentage.
4. À la clôture d'un contrat, l'application propose une **régularisation** chiffrée lorsque l'écart est non nul, sous forme d'une dépense complémentaire ou d'un titre de reversement, soumise au circuit à deux signatures.
5. Aucune régularisation n'est appliquée automatiquement : le montant est proposé, la décision reste humaine (P3).
6. Les coûts directs de projet **n'entrent pas** dans l'assiette des charges fixes ; testé.

---

#### Story 4.10 — Budgets mensuels et comparaison au réalisé

En tant que **direction**,
je veux fixer un budget par catégorie et voir l'écart,
afin de constater un dérapage pendant le mois et non après.

**Critères d'acceptation**

1. Un budget mensuel est saisissable par catégorie de dépense.
2. La comparaison budget / réalisé est affichée par catégorie et par mois, en montant et en pourcentage.
3. Le dépassement d'une catégorie est signalé visuellement et par un libellé, jamais par la couleur seule.
4. L'absence de budget sur une catégorie ne bloque aucune dépense — la double approbation reste le seul contrôle bloquant (RM-09).

---

#### Story 4.11 — Réserve, alimentation et utilisation

En tant que **direction**,
je veux savoir combien de mois de charges la réserve couvre,
afin de disposer du temps de réagir avant la rupture de trésorerie.

**Critères d'acceptation**

1. L'objectif de réserve = nombre de mois paramétré × somme des charges fixes **actives** ; recalculé à chaque modification du paramétrage.
2. Tant que l'objectif n'est pas atteint, chaque encaissement imputé à un contrat affecte **20 % du bénéfice correspondant** à la réserve, prélevés sur la part de 60 % de PTR Niger.
3. Un test vérifie que sur un bénéfice de 1 000 000, la réserve reçoit 200 000, l'apporteur 100 000, les exécutants 300 000, et qu'il reste 400 000 de fonctionnement.
4. Les parts de 10 % et 30 % ne sont **jamais** entamées par le prélèvement de réserve ; testé.
5. Le prélèvement s'interrompt automatiquement à l'atteinte de l'objectif et reprend automatiquement si la réserve repasse sous l'objectif ; les deux bascules sont testées.
6. Le montant de la réserve et le **nombre de mois couverts** sont affichés en permanence avec la méthode de calcul et la date des données source.
7. Toute utilisation de la réserve exige motif, **double approbation `direction`** et plan de reconstitution enregistré ; l'absence de l'un des trois bloque l'opération.
8. L'ajout d'une charge fixe augmente l'objectif et peut relancer le prélèvement ; l'impact est affiché avant confirmation.

---

#### Story 4.12 — Rapprochement hebdomadaire

En tant que **responsable financier**,
je veux comparer chaque semaine l'argent physique et les écritures,
afin qu'un écart soit expliqué pendant qu'on s'en souvient encore.

**Critères d'acceptation**

1. Un rapprochement compare, pour chaque compte, le solde physique constaté saisi et le solde issu des écritures.
2. L'écart est calculé et affiché **systématiquement**, y compris lorsqu'il vaut zéro.
3. Un écart non nul exige explication, responsable et action corrective avant validation ; la validation sans explication est refusée.
4. Le **préparateur et le contrôleur sont deux comptes distincts** ; la validation par un compte unique jouant les deux rôles est refusée côté serveur, y compris s'il détient les deux permissions (RM-16, C3).
5. Un rapprochement validé n'est pas modifiable ; une correction crée un nouveau rapprochement rattaché au précédent avec motif.
6. Chaque validation produit une entrée d'audit nommant préparateur et contrôleur.

---

#### Story 4.13 — Rapport financier mensuel, validation et clôture

En tant que **direction**,
je veux un rapport mensuel validé avant le 5,
afin de décider sur des chiffres arrêtés plutôt que sur une impression.

**Critères d'acceptation**

1. Le rapport présente les **douze lignes** du FR153, dans cet ordre.
2. Chaque ligne affiche la période source et la méthode d'obtention du montant.
3. Une ligne sans donnée applicable affiche `0` avec la mention « poste non applicable à ce jour » et n'est jamais masquée ; testé sur « taxes et charges sociales ».
4. Le préparateur et le contrôleur sont deux comptes distincts ; la validation finale appartient à `direction`.
5. L'application notifie à l'approche du **5 du mois suivant** et signale un dépassement.
6. Après validation, le mois est **clôturé** : toute écriture imputée à ce mois est refusée côté serveur, ce qui est testé pour un encaissement et pour une dépense.
7. La réouverture exige une autorisation `direction` avec motif, produit une entrée d'audit, et marque comme telle toute écriture postérieure.
8. La validation recalcule et fige le niveau d'alerte du mois.

---

#### Story 4.14 — Alertes vert, orange et rouge

En tant que **direction**,
je veux être avertie automatiquement avant la séquence qui a déjà fermé l'entreprise,
afin que le mécanisme d'alerte ne dépende de la vigilance de personne.

**Critères d'acceptation**

1. L'assiette d'alerte est la somme des charges fixes **actives** du paramétrage ; aucune liste n'est codée en dur.
2. **Vert** : encaissements du mois ≥ assiette. **Orange** : un mois sous l'assiette. **Rouge** : deux mois consécutifs sous l'assiette. Les trois cas sont testés sur des jeux de données dédiés.
3. En **orange**, l'application demande l'enregistrement d'un plan correctif sous 48 heures et notifie `direction` jusqu'à ce qu'il existe.
4. En **rouge**, l'activation de tout nouveau compte employé ou stagiaire est **refusée** côté serveur avec un message nommant le niveau d'alerte.
5. En **rouge**, l'approbation d'une dépense de catégorie non essentielle affiche un avertissement mais n'est pas bloquée.
6. En **rouge**, le calcul et le versement des parts de 10 % et 30 % restent possibles (RM-14).
7. L'ajout d'une charge fixe modifie l'assiette et peut changer le niveau d'alerte au recalcul suivant ; testé.
8. Le niveau d'alerte est affiché en permanence sur le tableau de bord direction, avec libellé textuel en plus de la couleur.

---

#### Story 4.15 — Tableau de bord financier

En tant que **responsable financier**,
je veux un écran unique sur l'état de l'argent,
afin de préparer rapprochements et rapports sans reconstituer les chiffres.

**Critères d'acceptation**

1. L'écran affiche : soldes par compte, dépenses en attente, encaissements du mois, créances échues, écarts de rapprochement, budget contre réalisé, réserve disponible.
2. Il affiche le **total des engagements de parts restant à verser** sur les contrats en cours (FR171).
3. Chaque bloc est cliquable vers la liste détaillée correspondante.
4. L'écran est accessible à `finance` et `direction` uniquement ; l'accès par URL directe depuis tout autre rôle est refusé.
5. Aucun bloc contenant une donnée non autorisée n'est rendu, même vide.

---

#### Story 4.16 — Tableau de bord direction consolidé

En tant que **direction**,
je veux le travail et l'argent sur le même écran,
afin de décider vite, avec trace, sans réunion supplémentaire.

**Critères d'acceptation**

1. L'écran affiche : membres sans objectif, rapports du jour envoyés / manquants, objectifs verts / orange / rouges / bloqués, projets en retard, stagiaires par tuteur, encaissements du mois, charges du mois, solde disponible, créances, réserve et mois couverts, niveau d'alerte.
2. Le bloc « En attente de mon approbation » reste en **première position** (FR167).
3. Tout tuteur ayant atteint la limite de stagiaires actifs est signalé visuellement et par un libellé.
4. « Membres sans objectif » inclut les comptes `direction` eux-mêmes (P5).
5. « Rapports manquants » exclut les absences approuvées et les jours non travaillés.
6. L'écran reste consultable sur téléphone à 320 px sans défilement horizontal, blocs empilés.
7. Le premier rendu utile intervient en moins de 3 secondes en conditions 3G dégradées simulées.

---

#### Story 4.17 — Recherche, listes filtrables et export CSV

En tant qu'**utilisateur autorisé**,
je veux retrouver et exporter mes données dans la limite de mes droits,
afin de préparer un contrôle sans passer par une extraction manuelle.

**Critères d'acceptation**

1. La recherche couvre personne, projet, objectif, période et statut.
2. Les listes principales sont filtrables et triables ; `direction` peut enregistrer un filtre pour réutilisation.
3. L'export CSV applique **exactement** les mêmes restrictions de permission que l'écran d'origine ; un test compare le contenu exporté au contenu affiché pour chaque rôle.
4. Un utilisateur ne peut pas exporter, par manipulation de paramètres d'URL, des lignes qu'il ne voit pas à l'écran ; testé explicitement.
5. Tout export produit une entrée d'audit avec auteur, nature des données et nombre de lignes.
6. Aucun export PDF ni Excel n'est produit en MVP.

---

## 11. Contradictions signalées, non résolues unilatéralement

> Conformément à la consigne : ces points sont **signalés, pas tranchés en silence**. Chacun porte
> une résolution provisoire appliquée dans le corps du PRD, explicitement marquée, que la direction
> peut renverser sans réécriture profonde.

| Réf. | Contradiction | Résolution provisoire appliquée | Arbitrage requis |
|---|---|---|---|
| **CONTRA-01** | *(né de D-01)* Le brief impose de verser les parts **au prorata du montant encaissé sur le total attendu** (§ 7bis.4bis), mais la base retenue est le bénéfice **réalisé** (encaissements − dépenses), inconnu tant que le contrat n'est pas terminé. Les deux ne peuvent pas coexister sans convention. | Le contrat porte un **bénéfice prévisionnel** servant de base provisoire aux versements au fil des encaissements ; à la clôture du contrat, une **régularisation chiffrée** est proposée sur l'écart avec le bénéfice réalisé, soumise au circuit à deux signatures (Story 4.9). | **Direction** — valider le principe de régularisation, ou imposer que les parts ne soient versées qu'à la clôture du contrat. |
| **CONTRA-02** | Les commissions SaaS survivent au départ du bénéficiaire (§ 7bis.3.1), ce qui impose de séparer **personne** et **compte applicatif** dans le modèle de données — mais les abonnements sont hors MVP *(D-03)*. | La séparation personne / compte est traitée comme **exigence structurelle de l'Étape 1** (Story 1.4, A-06), même sans fonctionnalité d'abonnement. Le relevé au bénéficiaire sorti reste en phase 2. | Aucun — signalé pour information. L'Architecte doit en tenir compte dès maintenant. |
| **CONTRA-03** | Le risque R2 propose une « procédure d'exception tracée plutôt que blocage » et une « revue mensuelle des approbations dérogatoires », alors que la décision C14 du même document affirme « aucune dérogation, aucune délégation ». | **C14 fait foi** : décision de direction postérieure et explicite. Aucune dérogation n'est implémentée. La procédure de remboursement tracée (FR125) remplace la dérogation. | **Direction** — confirmer qu'aucune soupape d'exception ne doit exister, en connaissance du risque R12 de gel total des achats. |
| **CONTRA-04** | Le § 7bis.1 qualifie l'apporteur d'affaires de personne pouvant être « un associé **ou un membre de l'équipe** » et attribue 10 % à ce titre, alors que le tableau du même paragraphe affirme qu'un employé est rémunéré « **salaire uniquement** ». | La part **apporteur (10 %)** est ouverte aux employés ; seule la part **exécutant (30 %)** est réservée aux associés. C'est la lecture qui rend cohérents l'exemple du communicateur (§ 7bis.2, cas 2) et la règle RM-15. | **Direction** — confirmer qu'un employé apporteur perçoit bien 10 %. Si non, l'exemple du communicateur doit être réécrit. |
| **CONTRA-05** | Le risque R13 demande que « l'application n'expose pas la répartition aux non-associés », alors qu'un employé apporteur doit pouvoir vérifier la part qui lui revient. | Le bénéficiaire non-associé voit **sa propre ligne uniquement** — montant, base, taux, contrat d'origine — et aucune autre (FR136, Story 4.8 AC5). | **Direction** — confirmer ce niveau d'exposition. |
| **CONTRA-06** | Le rapport mensuel exige une ligne « **taxes et charges sociales** » (§ 8/L), alors que la décision C10 constate que ces postes « n'existent pas actuellement » chez PTR Niger. | La ligne est **conservée** et affiche `0` avec la mention « poste non applicable à ce jour » ; elle n'est jamais masquée (FR155). | Aucun — signalé pour information. |
| **CONTRA-07** | L'alerte **rouge** doit « geler les partages de bénéfices » (§ 8/L), alors que le module Partage de bénéfices est supprimé (C15) et que les parts 10 % / 30 % restent dues en toutes circonstances (RM-14). | En rouge, **rien de ce qui touche aux parts n'est gelé** : seules l'activation de nouveaux comptes est bloquée et les dépenses non essentielles avertissent (C9, FR164, FR165). | **Direction** — confirmer que l'alerte rouge n'a aucun effet sur le versement des parts. |
| **CONTRA-08** | Le rapport quotidien impose **six champs obligatoires** (§ 8/F.3), alors que l'objectif d'adoption est une saisie sous **3 minutes** sur téléphone en 3G en fin de journée. | Les six champs sont conservés, compensés par le **pré-remplissage** depuis les tâches du jour (FR62) et la sauvegarde automatique (FR63). La mesure des 3 minutes devient une **condition de recette bloquante** de l'Étape 3 (NFR4, Story 3.1 AC7). | **Produit** — si la mesure dépasse 3 minutes en recette réelle, il faudra arbitrer entre réduire les champs obligatoires et accepter la friction. |
| **CONTRA-09** | Le § 8/A.4 impose l'unicité **permanente** du numéro de téléphone, alors que le § 14.8 impose l'archivage sans réutilisation des comptes et que les numéros sont recyclés par les opérateurs au Niger. | Unicité sur les comptes **non archivés uniquement** ; l'historique est porté par la fiche personne (C11, FR3, FR4). | **Direction** — question ouverte Q17, à confirmer. |
| **CONTRA-10** | La matrice § 7 du brief d'entrée donne à `finance` l'accès au journal d'audit « périmètre finance », ce qui revient à laisser un acteur consulter le registre qui documente ses propres actions. | **Fermé à `finance`** *(D-04)*. La Finance accède aux écritures financières et à leur historique de correction, pas au journal d'audit. | Tranché — décision D-04 du 18/07/2026. |
| **CONTRA-11** | Le critère d'acceptation 9 du § 16 (« une dépense jusqu'à 25 000 FCFA demande une validation autorisée ») contredit frontalement C14. | Le critère 9 est **réécrit** : « toute dépense, quel que soit son montant, demande deux approbateurs distincts » (voir § 12, CA-09). | Tranché — décision C14 du 18/07/2026. |

---

## 12. Traçabilité des critères d'acceptation du brief

Les 18 critères du § 16 du brief d'entrée sont traités comme un jeu de tests, rattachés aux stories.
Deux sont réécrits par décision de la direction.

| # | Critère d'acceptation du brief | Story | Statut |
|---|---|---|---|
| CA-01 | Un utilisateur actif peut se connecter avec son numéro et son mot de passe | 1.5 | Inchangé |
| CA-02 | Un utilisateur ne peut pas ouvrir un écran interdit à son rôle, même par URL directe | 1.3 + campagne NFR14 | Inchangé |
| CA-03 | Un stagiaire ne peut pas être activé sans tuteur et trois objectifs | 3.9 | Inchangé |
| CA-04 | Le système empêche l'affectation d'un **quatrième** stagiaire actif à un même tuteur | 3.10 | **Réécrit** (C5bis : limite portée de 2 à 3) |
| CA-05 | Une personne ne peut pas avoir plus de trois objectifs majeurs validés pour le même mois | 2.3 | Inchangé |
| CA-06 | Un objectif validé modifié conserve la valeur précédente, le motif et l'auteur | 2.4 | Inchangé |
| CA-07 | Un rapport quotidien peut être sauvegardé, envoyé, validé ou retourné | 3.1, 3.2, 3.3 | Inchangé |
| CA-08 | Le responsable ne peut pas modifier silencieusement le rapport d'un membre | 3.3 | Inchangé |
| CA-09 | **Toute dépense demande deux approbateurs distincts, quel que soit son montant** | 1.12 | **Réécrit** (C14 : seuil de 25 000 FCFA abandonné) |
| CA-10 | Une dépense non prévue demande deux approbateurs différents | 1.12 | Absorbé par CA-09 |
| CA-11 | L'auteur d'une dépense ne peut pas être son seul approbateur | 1.12 | Inchangé |
| CA-12 | Une transaction financière validée ne peut pas être supprimée sans trace | 4.5, 4.6 | Inchangé |
| CA-13 | Le rapprochement calcule et affiche toute différence | 4.12 | Inchangé |
| CA-14 | Le rapport mensuel affiche encaissements, charges, dettes, trésorerie, résultat et réserve | 4.13 | Inchangé |
| CA-15 | Le système affiche orange après un mois non couvert, rouge après deux mois consécutifs | 4.14 | Inchangé |
| CA-16 | Les dirigeants sont soumis aux objectifs et rapports comme le reste de l'équipe | 2.3, 3.7, 4.16 | Inchangé |
| CA-17 | Toutes les actions financières sensibles apparaissent dans le journal d'audit | 1.2 + stories 4.x | Inchangé |
| CA-18 | L'application reste utilisable sur téléphone et avec une connexion faible | NFR1–NFR10, recette de chaque étape | Inchangé |

---

## 13. Critères de succès du MVP

Le MVP est réussi si, **après 60 jours d'usage réel** :

1. Les 18 critères du § 12 passent tous.
2. Aucune dépense n'a été payée hors du circuit d'approbation de l'application.
3. Le niveau d'alerte financier affiché correspond à la réalité vérifiée manuellement.
4. Le taux de rapports quotidiens envoyés à l'heure dépasse **80 %** (90 % étant la cible à 90 jours).
5. Le rapport financier du mois écoulé a été validé avant le 5.
6. Aucun utilisateur n'a accédé à un écran interdit à son rôle, y compris par URL directe.
7. Le temps moyen de saisie du rapport quotidien reste **sous 3 minutes**.

### Indicateurs de pilotage

| KPI | Définition | Cible |
|---|---|---|
| Taux de couverture d'objectifs | Membres actifs avec ≥ 1 objectif validé / membres actifs | 100 % |
| Ponctualité des rapports | Rapports envoyés avant l'heure limite / rapports attendus | ≥ 90 % |
| Taux de preuve | Objectifs clos avec preuve attachée / objectifs clos | 100 % |
| Conformité d'approbation | Dépenses à deux approbations / dépenses payées | 100 % |
| Écart de rapprochement | Somme des écarts non expliqués | 0 FCFA |
| Mois de charges couverts | Réserve / charges fixes mensuelles actives | ≥ 3 |
| Délai de clôture | Jours entre fin de mois et validation du rapport | ≤ 5 |
| Charge d'encadrement | Stagiaires actifs par tuteur | ≤ 3 |
| Délai d'approbation d'une dépense | Soumission → décision | ≤ 24 h |
| Délai de prise en charge d'un blocage | Signalement → première réponse | ≤ 4 h ouvrées |

---

## 14. Questions ouvertes restantes

Aucune ne bloque le démarrage de l'architecture ni de l'Étape 1.

### 14.1 Requises avant l'architecture

| # | Question | Impact |
|---|---|---|
| Q6 | Quels comptes financiers existent exactement : caisse, banque (laquelle), Airtel Money, Moov Money, autre ? | Modèle de données financier et écrans de rapprochement (Story 4.1). |
| Q7 | Quel hébergement et quelle stratégie de sauvegarde / restauration ? | A-03, NFR24, NFR25, risque R5. |
| Q9 | Qui exactement réinitialise un mot de passe en MVP, et selon quelle vérification d'identité ? | Sécurité du compte et charge opérationnelle (FR6). |
| Q11 | Quels types de fichiers accepter et quelle taille maximale ? | NFR16, coût de bande passante en 3G. |

### 14.2 Non bloquantes

| # | Question | Impact |
|---|---|---|
| Q12 | Durée de conservation des données du personnel et des justificatifs financiers ? | Valide NFR26. |
| Q12bis | Le cadre légal nigérien impose-t-il une gratification minimale aux stagiaires ? | Hors périmètre logiciel, mais l'application trace ces engagements (FR126). |
| Q13 | Quels appareils et navigateurs sont réellement utilisés aujourd'hui ? | Cadre les tests de compatibilité (NFR9). |
| Q14 | Le télétravail est-il autorisé et comment est-il validé ? | Influence le modèle d'absence de l'Étape 1 (Story 1.10). |
| Q15 | Le multi-entreprise est-il définitivement exclu, y compris à deux ans ? | NFR28. Une réponse ferme évite une reprise coûteuse. |
| Q16 | Le nom visible de l'application sera-t-il « PTR Staff » ? | Interface et communication interne. |
| Q17 | Un compte archivé peut-il libérer son numéro de téléphone ? | CONTRA-09, FR3. |
| Q3ter | SaaS : la commission porte-t-elle sur le montant encaissé ou sur le bénéfice ? Le bénéficiaire est-il salarié ou externe ? | Reporté avec les abonnements en phase 2 *(D-03)*. |

---

## 15. Next Steps

### 15.1 UX Expert Prompt

> Prends `docs/prd.md` comme entrée et produis la spécification d'expérience utilisateur de PTR Staff.
> Contraintes non négociables : mobile-first à 320 px, premier rendu utile sous 3 secondes en 3G
> dégradée, **saisie du rapport quotidien sous 3 minutes sur téléphone** (§ 6.1 NFR4, Story 3.1),
> approbation d'une dépense en 3 interactions depuis une notification (Story 1.13), vocabulaire de
> contribution et non de surveillance, aucun classement entre personnes, aucune information portée
> par la couleur seule, WCAG 2.1 AA. Priorise les écrans du § 7.3 dans l'ordre des quatre étapes.
> Le point de vérité du produit est le formulaire de rapport quotidien à six champs en fin de
> journée sur 3G : conçois-le en premier et fais-en la démonstration.

### 15.2 Architect Prompt

> Prends `docs/prd.md` comme entrée et produis `docs/architecture.md`. Les contraintes imposées par
> la direction figurent au § 8.3 ; les décisions qui **t'appartiennent** figurent au § 8.4 (A-01 à
> A-07) — notamment Inertia.js contre composants Vue dans Blade, avec le poids de page en 3G comme
> critère principal, et la base de production. Traite en priorité : l'immuabilité et l'audit
> transactionnel au niveau du modèle de données dès l'Étape 1 (NFR20, NFR21), la séparation
> **personne / compte applicatif** (CONTRA-02, A-06), le stockage privé des pièces jointes (NFR15),
> et l'autorisation serveur testable par URL directe (NFR14). Réponds aux questions Q6, Q7, Q9 et
> Q11 du § 14.1 avec la direction avant de figer le modèle financier.

### 15.3 Actions immédiates

1. **Faire arbitrer les cinq points de contradiction ouverts** du § 11 : CONTRA-01, CONTRA-03, CONTRA-04, CONTRA-05, CONTRA-07. Aucun ne bloque l'Étape 1 ; CONTRA-01, CONTRA-04, CONTRA-05 et CONTRA-07 doivent être tranchés avant l'Étape 4.
2. **Obtenir les réponses Q6, Q7, Q9 et Q11** avant que l'Architecte ne fige le modèle financier.
3. **Lancer l'agent Architect** sur `docs/architecture.md`.
4. **Lancer l'agent PO** pour sharder ce PRD vers `docs/prd/` et valider la cohérence PRD / architecture.
5. **Démarrer la boucle `/sm` → `/dev` → `/qa`** sur l'Epic 1, story 1.1.

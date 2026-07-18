# Brief d’entrée pour l’Agent Analyste BMAD — PTR Staff

**Projet :** application web interne de PTR Niger  
**Nom de travail :** PTR Staff  
**Domaine prévu :** `staff.ptrniger.com`  
**Type de projet :** nouvelle application web interne (greenfield)  
**Langue :** français simple  
**Pays / fuseau :** Niger — `Africa/Niamey`  
**Devise :** franc CFA — XOF  
**Version du brief :** 1.0 — 18 juillet 2026

---



## 1. Présentation de PTR Niger

PTR Niger est une entreprise de services numériques. Elle travaille avec des dirigeants, des employés, des contractuels et des stagiaires. L’application sera réservée au personnel interne.

L’objectif n’est pas de surveiller les personnes minute par minute. L’objectif est de donner à chacun des tâches claires, vérifier les résultats, réduire les bavardages et protéger l’argent de l’entreprise.

---

## 2. Problèmes vécus dans le passé

1. Trop de stagiaires étaient recrutés sans plan de stage ni objectifs.
2. Certains stagiaires venaient au bureau, restaient sans tâche et discutaient.
3. Les dirigeants passaient parfois trop de temps à discuter avec les stagiaires au lieu de réaliser leurs propres tâches.
4. Les objectifs mensuels et hebdomadaires n’étaient pas écrits.
5. Certains employés étaient payés sans produire de résultat vérifiable.
6. L’entreprise pouvait travailler plusieurs mois sans couvrir ses charges et sans déclencher de plan d’urgence.
7. L’argent reçu pour des projets était parfois dépensé personnellement par les dirigeants.
8. Les ventes, dépenses, reçus, annulations et soldes n’étaient pas toujours bien tracés.
9. L’entreprise a déjà dû fermer par manque de trésorerie.
10. Le manque de visibilité sur les progrès entraînait une baisse de motivation.

---

## 3. Vision du produit

PTR Staff doit devenir le point central de gestion quotidienne de PTR Niger.

Chaque personne doit pouvoir savoir immédiatement :

- ce qu’elle doit faire aujourd’hui ;
- ses trois objectifs majeurs du mois ;
- les projets auxquels elle participe ;
- les résultats déjà obtenus ;
- les blocages à résoudre ;
- les évaluations et décisions qui la concernent.

La direction doit pouvoir savoir immédiatement :

- qui a un objectif et qui n’en a pas ;
- qui travaille réellement et avec quelles preuves ;
- quels objectifs sont en retard ou bloqués ;
- combien de stagiaires chaque tuteur encadre ;
- combien l’entreprise a encaissé et dépensé ;
- si les charges du mois sont couvertes ;
- le niveau de la réserve ;
- quelles dépenses attendent une autorisation ;
- quels clients doivent encore payer.

---

## 4. Objectifs métier

1. Aucun employé ou stagiaire actif sans mission et objectifs écrits.
2. Chaque membre du personnel, y compris les dirigeants, rend compte de son travail.
3. Chaque objectif possède un responsable, une date, un indicateur et une preuve.
4. Chaque dépense et chaque encaissement laisse une trace.
5. Aucun argent de l’entreprise n’est utilisé personnellement sans autorisation légale et comptable.
6. Les difficultés financières déclenchent automatiquement une alerte et un plan d’action.
7. La direction dispose d’un tableau de bord simple pour prendre des décisions rapidement.

---

## 5. Indicateurs de réussite

- 100 % des membres actifs ont des objectifs écrits avant le début du mois.
- 90 % ou plus des rapports quotidiens sont envoyés avant l’heure limite.
- 100 % des stagiaires ont un tuteur et un plan de stage.
- Aucun tuteur ne supervise plus de deux stagiaires actifs.
- 100 % des dépenses ont une autorisation et un justificatif.
- 100 % des mouvements financiers sont enregistrés.
- Le rapprochement caisse / Mobile Money / banque est fait chaque semaine.
- Le rapport financier est validé au plus tard le 5 du mois suivant.
- La direction connaît à tout moment le nombre de mois de charges couverts par la réserve.
- Les objectifs bloqués sont signalés avant la revue du vendredi.

---

## 6. Utilisateurs et rôles

### 6.1 Super administrateur technique

- configure l’application ;
- gère les rôles et permissions ;
- peut aider à restaurer un compte ;
- consulte les journaux techniques ;
- ne doit pas modifier silencieusement les données métier.

### 6.2 Direction

- voit le tableau de bord global ;
- gère les utilisateurs et l’organisation ;
- fixe les objectifs de l’entreprise ;
- attribue les objectifs individuels ;
- valide les recrutements et stages ;
- consulte les rapports de toute l’équipe ;
- approuve les dépenses ;
- consulte et valide les rapports financiers ;
- consulte le journal d’audit.

### 6.3 Responsable financier

- enregistre les encaissements et dépenses ;
- gère les comptes caisse, banque et Mobile Money ;
- prépare les rapprochements et rapports ;
- suit les créances clients ;
- joint les justificatifs ;
- ne valide pas seul une dépense nécessitant deux approbations.

### 6.4 Responsable d’équipe / tuteur

- voit les membres de son équipe ;
- attribue ou propose des tâches ;
- valide ou retourne les rapports quotidiens ;
- réalise les revues hebdomadaires ;
- encadre au maximum deux stagiaires ;
- crée un plan d’amélioration si nécessaire.

### 6.5 Employé / contractuel

- consulte ses objectifs et projets ;
- enregistre son rapport quotidien ;
- joint des preuves ;
- signale un blocage ;
- consulte ses évaluations ;
- demande une absence, une dépense ou un remboursement ;
- consulte uniquement les informations financières qui le concernent.

### 6.6 Stagiaire

- consulte son plan de stage et ses objectifs ;
- enregistre son rapport quotidien ;
- joint ses livrables ;
- adresse ses demandes à son tuteur ;
- consulte ses évaluations ;
- n’accède pas aux finances, sauf à ses propres demandes autorisées.

### 6.7 Auditeur / lecture seule — optionnel

- consulte les rapports et justificatifs autorisés ;
- ne peut créer, modifier, approuver ou supprimer aucune donnée.

**Règle :** un utilisateur peut cumuler plusieurs rôles. Les permissions doivent être gérées par rôle et, si nécessaire, par permission précise.

---

## 7. Matrice d’accès simplifiée

| Module | Direction | Finance | Tuteur | Employé | Stagiaire |
|---|---:|---:|---:|---:|---:|
| Son propre tableau de bord | Oui | Oui | Oui | Oui | Oui |
| Tableau de bord global | Oui | Finance seulement | Équipe seulement | Non | Non |
| Gestion des comptes | Oui | Non | Non | Non | Non |
| Objectifs de l’entreprise | Gérer | Lire | Lire | Lire | Lire |
| Objectifs individuels | Tous | Les siens | Son équipe | Les siens | Les siens |
| Rapports quotidiens | Tous | Les siens | Son équipe | Les siens | Les siens |
| Évaluation hebdomadaire | Tous | Les siens | Son équipe | Consulter | Consulter |
| Gestion des stagiaires | Oui | Non | Ses stagiaires | Non | Son dossier |
| Demandes de dépenses | Approuver | Préparer / payer | Créer | Créer | Créer si autorisé |
| Données financières | Toutes | Toutes | Non | Ses demandes | Ses demandes |
| Journal d’audit | Oui | Finance seulement | Non | Non | Non |

---

## 8. Fonctionnalités détaillées

### Module A — Authentification et comptes

1. Aucun compte ne peut être créé librement depuis Internet.
2. La direction ou un administrateur crée le compte.
3. Connexion avec numéro de téléphone et mot de passe.
4. Le numéro de téléphone est unique et normalisé, par défaut avec l’indicatif `+227`.
5. Premier mot de passe temporaire avec changement obligatoire à la première connexion.
6. Mot de passe oublié : réinitialisation sécurisée par un administrateur dans le MVP, puis OTP SMS si retenu.
7. États du compte : invité, actif, suspendu, terminé, archivé.
8. Déconnexion de tous les appareils en cas de suspension ou de changement de mot de passe.
9. Historique des connexions, tentatives échouées et appareils/sessions.
10. Limitation des tentatives et blocage temporaire contre les attaques.
11. Authentification renforcée recommandée pour la direction et les finances.

### Module B — Organisation et profils

1. Fiche de l’entreprise et paramètres généraux.
2. Services ou départements.
3. Fonctions et postes.
4. Fiche utilisateur : nom, téléphone, photo optionnelle, rôle, service, fonction et responsable direct.
5. Type de relation : dirigeant, employé, contractuel ou stagiaire.
6. Dates de début et de fin du contrat ou du stage.
7. Statut actif, absent, suspendu ou sorti.
8. Documents liés au dossier : contrat, convention, fiche de poste, engagement signé.
9. Historique des changements de rôle, service, responsable et statut.

### Module C — Tableau de bord personnel

Chaque utilisateur voit :

- ses objectifs du mois ;
- ses tâches du jour ;
- ses projets ;
- les rapports à envoyer ;
- les blocages ouverts ;
- les prochaines échéances ;
- les notifications ;
- sa dernière évaluation ;
- les demandes en attente.

Le tableau de bord doit rester simple, lisible et adapté au téléphone.

### Module D — Objectifs de l’entreprise et objectifs individuels

1. La direction définit au maximum cinq priorités majeures pour l’entreprise par mois.
2. Chaque personne reçoit au maximum trois objectifs majeurs par mois.
3. Un objectif contient : titre, description courte, responsable, indicateur, valeur cible, preuve attendue, date limite, moyens nécessaires et priorité.
4. Un objectif individuel peut être rattaché à un objectif d’entreprise ou à un projet.
5. États : brouillon, validé, en cours, atteint, partiellement atteint, non atteint, bloqué, annulé.
6. Couleurs : vert = atteint, orange = en risque, rouge = non atteint, gris = bloqué.
7. Toute modification après validation demande un motif et laisse une trace.
8. L’utilisateur peut mettre à jour le progrès et ajouter une preuve.
9. Le responsable peut commenter, valider ou demander une correction.
10. Vue calendrier, liste et synthèse mensuelle.
11. Copie contrôlée des objectifs récurrents vers le mois suivant.
12. Un utilisateur peut proposer un objectif ; il ne devient officiel qu’après validation du responsable.

### Module E — Projets, tâches et livrables

1. Création d’un projet avec nom, client optionnel, responsable, dates, statut et membres.
2. Statuts : prévu, actif, bloqué, en validation, livré, clôturé, annulé.
3. Création de tâches avec responsable, échéance, priorité et lien avec un objectif.
4. Sous-tâches simples si nécessaires.
5. Pièces jointes, liens, commentaires et preuves.
6. Liste ou tableau Kanban simple.
7. Livrables avec responsable, date prévue, date réelle et validation.
8. Historique des changements.
9. La partie budget du projet est visible uniquement par les rôles autorisés.

### Module F — Rapport quotidien

1. Un rapport par personne et par jour travaillé.
2. Enregistrement en brouillon avec sauvegarde automatique.
3. Champs obligatoires : tâche prévue, résultat obtenu, preuve ou lien, blocage, prochaine action et aide demandée.
4. Possibilité de joindre une image, un document ou un lien.
5. États : brouillon, envoyé, validé, retourné pour correction, en retard.
6. Heure limite par défaut : 17 h 45.
7. Rappel avant l’heure limite et notification en cas de retard.
8. Le responsable peut commenter et valider, mais ne peut pas modifier silencieusement le rapport de l’utilisateur.
9. Les corrections laissent l’ancienne version dans l’historique.
10. Si aucune tâche n’est disponible, l’utilisateur doit envoyer une demande de nouvelle tâche au responsable.
11. Vue quotidienne, hebdomadaire et mensuelle.
12. Aucun rapport n’est attendu pendant une absence approuvée ou un jour non travaillé.
13. Un rapport envoyé en retard doit afficher le retard et peut demander une courte explication.

### Module G — Blocages et demandes d’aide

1. Un blocage peut être créé depuis une tâche, un objectif ou un rapport.
2. Champs : problème, niveau d’urgence, personne sollicitée, date, effet sur l’échéance et action déjà essayée.
3. États : ouvert, pris en charge, résolu, fermé sans solution.
4. Notification immédiate au responsable concerné.
5. Mesure du temps de résolution.
6. Un blocage non signalé ne peut pas être utilisé tardivement comme justification sans explication.

### Module H — Revue hebdomadaire et performance

1. Revue prévue chaque vendredi.
2. Vue de tous les objectifs et tâches de la semaine.
3. Pour chaque objectif : résultat, preuve, statut, cause de l’écart et prochaine action.
4. Commentaire de la personne évaluée et du responsable.
5. Signature ou validation électronique simple des deux parties.
6. Historique des revues.
7. Plan d’amélioration de 7 à 14 jours avec actions, aide fournie, dates et résultat.
8. Les dirigeants suivent la même procédure.
9. Aucun licenciement, suspension de contrat ou sanction ne doit être déclenché automatiquement par le logiciel.
10. Le logiciel enregistre les faits, explications, décisions et documents ; la direction applique ensuite la procédure légale.
11. Aucun classement public humiliant entre les employés.

### Module I — Recrutement, entrée et gestion des stagiaires

1. Demande préalable de recrutement ou de stage.
2. Champs : besoin réel, mission, coût, financement, responsable, durée, outils et trois résultats obligatoires.
3. Workflow : brouillon, soumis, approuvé, refusé, préparé, actif, terminé.
4. Aucun compte actif avant l’approbation de la fiche d’entrée.
5. Un tuteur ne peut avoir plus de deux stagiaires actifs.
6. Plan de stage avec compétences à apprendre, objectifs, tâches hebdomadaires et preuves.
7. Checklist d’intégration : contrat/convention, matériel, accès, règlement, première tâche et présentation du tuteur.
8. Évaluation hebdomadaire du stagiaire.
9. Évaluation finale et attestation si les conditions sont remplies.
10. Checklist de sortie : livrables, matériel, fermeture des accès, sauvegarde des documents et évaluation finale.
11. Les demandes normales du stagiaire sont regroupées pour les créneaux de suivi du tuteur ; un blocage urgent peut être signalé immédiatement.

### Module J — Présence, retards et absences

1. Horaires par défaut : lundi à vendredi, 8 h–12 h et 14 h–18 h.
2. Enregistrement de présence, arrivée, départ et sortie autorisée.
3. Demande d’absence ou de permission avec motif, dates et justificatif optionnel.
4. Workflow d’approbation par le responsable.
5. Retards et absences visibles par la personne et son responsable.
6. Calendrier d’équipe.
7. Pas de géolocalisation ni de biométrie dans le MVP.
8. L’autorisation de partir après réalisation des objectifs reste une décision du responsable et est enregistrée.

### Module K — Réunions et décisions

1. Création d’une réunion avec but, durée, participants et ordre du jour.
2. Durée normale maximale recommandée : 30 minutes.
3. Compte rendu très court.
4. Chaque décision crée si nécessaire une tâche avec responsable et date.
5. Réunion obligatoire de direction chaque vendredi : objectifs, ventes, encaissements, caisse, blocages et décisions.
6. Historique consultable des décisions.

### Module L — Gestion financière

#### Comptes financiers

1. Comptes : caisse, banque et Mobile Money.
2. Solde initial et mouvements.
3. Droits d’accès stricts.
4. Pas d’intégration bancaire automatique dans le MVP.

#### Encaissements

1. Enregistrement du client, projet, montant, date, mode de paiement et référence.
2. Pièce justificative obligatoire si disponible.
3. Numéro de reçu unique.
4. Un paiement reçu personnellement doit être reversé à PTR Niger sous 24 heures et tracé.
5. Aucune suppression : correction ou annulation avec motif.

#### Dépenses

1. Demande de dépense avec demandeur, projet, motif, montant, budget, résultat attendu et justificatif.
2. Jusqu’à 25 000 FCFA et prévue au budget : un responsable autorisé peut valider.
3. Plus de 25 000 FCFA ou non prévue : deux dirigeants doivent valider.
4. États séparés : demandée, approuvée, refusée, payée, annulée.
5. Une approbation ne signifie pas automatiquement que la dépense est déjà payée.
6. Les deux approbateurs doivent être deux comptes différents.
7. Une personne ne peut pas être le seul approbateur de sa propre demande.
8. Justificatif de paiement ajouté après le paiement.
9. Dépenses personnelles interdites.

#### Budgets et charges

1. Budget mensuel par catégorie.
2. Liste des charges fixes : loyer, eau, électricité, Internet, salaires, charges sociales, taxes, logiciels, transport et autres.
3. Comparaison budget / réalisé.
4. Budget optionnel par projet.

#### Réserve

1. Objectif : au moins trois mois de charges fixes.
2. Tant que l’objectif n’est pas atteint, au moins 20 % de la marge nette de chaque projet est affectée à la réserve.
3. Affichage du montant de la réserve et du nombre de mois couverts.
4. Toute utilisation de la réserve exige un motif, une double approbation et un plan de reconstitution.

#### Rapprochement hebdomadaire

1. Comparaison de la caisse physique, du Mobile Money, de la banque et des écritures.
2. Différence, explication, responsable et action corrective.
3. Validation par le préparateur et le contrôleur.
4. Historique non modifiable sans trace.

#### Rapport financier mensuel

1. Chiffre d’affaires facturé.
2. Encaissements reçus.
3. Créances clients.
4. Coûts directs des projets.
5. Salaires et rémunérations.
6. Charges fixes.
7. Taxes et charges sociales.
8. Dettes.
9. Trésorerie totale.
10. Résultat estimé.
11. Réserve disponible.
12. Nombre de mois de charges couverts.
13. Préparateur, contrôleur et validation de la direction.
14. Date limite : le 5 du mois suivant.
15. Après validation, le mois est clôturé. Toute réouverture exige une autorisation de la direction et laisse une trace d’audit.

#### Alertes financières

- Vert : encaissements du mois supérieurs ou égaux aux charges.
- Orange : un mois sous le niveau des charges ; plan correctif sous 48 heures.
- Rouge : deux mois consécutifs sous le niveau des charges ; gel des recrutements, dépenses non essentielles et partages de bénéfices.

#### Partage de bénéfices

1. Enregistrement séparé d’une décision de distribution.
2. Interdit si les projets concernés ne sont pas livrés.
3. Interdit si les charges, taxes ou dettes ne sont pas réglées.
4. Interdit si la réserve minimale n’est pas disponible.
5. Validation écrite de la direction et conservation du rapport utilisé.

### Module M — Clients, ventes, factures et créances

1. Fiche client simple.
2. Prospects et opportunités commerciales.
3. Étapes : prospect, contacté, offre envoyée, négociation, gagné, perdu.
4. Devis numérotés.
5. Contrats ou bons de commande joints.
6. Factures numérotées et statut de paiement.
7. Reçus et preuves de paiement.
8. Créances avec échéance, dernière relance et prochaine action.
9. Objectifs commerciaux mensuels : prospects, offres, contrats, factures et encaissements.
10. Export PDF des documents commerciaux dans une phase ultérieure si nécessaire.

### Module N — Documents internes et engagements

1. Bibliothèque des règles internes et procédures.
2. Version et date d’application de chaque document.
3. Accusé de lecture et d’acceptation par utilisateur.
4. Historique des versions.
5. Notification lorsqu’une nouvelle version doit être acceptée.
6. Documents personnels visibles uniquement par les personnes autorisées.

### Module O — Matériel et accès

1. Inventaire du matériel : ordinateur, téléphone, clé, carte, autre.
2. Affectation à un utilisateur avec date et état.
3. Signalement de panne, perte ou dommage.
4. Retour lors de la sortie.
5. Checklist des accès numériques ouverts et fermés.

### Module P — Notifications

1. Notifications dans l’application.
2. Rapport quotidien bientôt en retard.
3. Objectif proche de l’échéance.
4. Nouveau commentaire ou correction demandée.
5. Blocage affecté.
6. Dépense à approuver.
7. Rapprochement ou rapport financier à préparer.
8. Document interne à accepter.
9. Fin de contrat ou de stage proche.
10. SMS ou WhatsApp uniquement dans une phase ultérieure après choix du fournisseur et du coût.

### Module Q — Recherche, rapports et exports

1. Recherche par personne, projet, objectif, période et statut.
2. Filtres enregistrables pour les dirigeants.
3. Rapport d’activité par personne, équipe, projet et période.
4. Rapport des objectifs atteints, à risque, non atteints et bloqués.
5. Liste des rapports manquants.
6. Rapport des stagiaires et capacité des tuteurs.
7. Rapport financier selon les droits.
8. Export CSV / Excel pour les listes et PDF pour les rapports officiels.
9. Les exports respectent les mêmes permissions que l’écran.

### Module R — Journal d’audit

1. Enregistre les créations, modifications, validations, refus, annulations et changements de statut sensibles.
2. Contient l’utilisateur, la date, l’action, l’ancienne valeur et la nouvelle valeur si nécessaire.
3. Obligatoire pour les finances, objectifs validés, comptes, permissions et documents internes.
4. Non modifiable depuis l’interface normale.
5. Consultation limitée aux rôles autorisés.

### Module S — Paramètres

1. Nom et informations de PTR Niger.
2. Horaires et jours travaillés.
3. Heure limite du rapport quotidien.
4. Seuil d’approbation des dépenses, fixé par défaut à 25 000 FCFA.
5. Pourcentage de réserve, fixé par défaut à 20 % de la marge nette.
6. Objectif de réserve en nombre de mois, fixé par défaut à trois.
7. Types de pièces jointes et taille maximale.
8. Catégories de dépenses et charges.
9. Modèles de notifications.

---

## 9. Parcours principaux

### Parcours 1 — Création d’un employé ou stagiaire

1. La direction remplit la demande d’entrée.
2. Elle précise le besoin, le coût, le responsable et trois objectifs.
3. Les validations nécessaires sont données.
4. Le compte est créé avec un mot de passe temporaire.
5. L’utilisateur accepte les règles internes.
6. Les outils et accès sont affectés.
7. Le compte devient actif.

### Parcours 2 — Journée normale

1. L’utilisateur se connecte.
2. Il consulte sa priorité et ses tâches.
3. Il met à jour ses tâches pendant la journée.
4. Il signale immédiatement les blocages.
5. Il envoie son rapport avant 17 h 45.
6. Le responsable valide ou demande une correction.

### Parcours 3 — Revue du vendredi

1. Le responsable ouvre la synthèse de l’équipe.
2. Il vérifie les objectifs, tâches, preuves et blocages.
3. La personne donne son explication.
4. Les actions et dates sont enregistrées.
5. Un plan d’amélioration est créé seulement si nécessaire.

### Parcours 4 — Dépense

1. L’utilisateur soumet une demande.
2. Le système vérifie le montant et si la dépense est prévue.
3. Il demande une ou deux approbations selon la règle.
4. Le responsable financier effectue le paiement.
5. La preuve de paiement est jointe.
6. L’écriture apparaît dans le compte et le rapport.

### Parcours 5 — Clôture financière du mois

1. Toutes les opérations sont enregistrées.
2. Les comptes sont rapprochés.
3. Les créances et dettes sont mises à jour.
4. Le rapport mensuel est généré.
5. Le préparateur et le contrôleur valident.
6. Le système calcule le niveau vert, orange ou rouge.
7. La direction enregistre ses décisions.

---

## 10. Navigation recommandée

### Menu de tous les utilisateurs

- Tableau de bord
- Mes objectifs
- Mes tâches
- Mon rapport quotidien
- Mes blocages
- Mes évaluations
- Mes demandes
- Documents internes
- Notifications
- Mon profil

### Menu direction

- Vue entreprise
- Équipe
- Objectifs
- Projets
- Stagiaires
- Revues
- Réunions
- Finances
- Clients et ventes
- Rapports
- Documents
- Matériel
- Audit
- Paramètres

### Menu finance

- Tableau de bord financier
- Encaissements
- Dépenses
- Comptes
- Rapprochements
- Créances
- Budgets
- Réserve
- Rapports mensuels

---

## 11. Tableaux de bord

### Tableau personnel

- progression des trois objectifs ;
- tâches du jour ;
- rapport du jour ;
- blocages ;
- échéances ;
- demandes et notifications.

### Tableau direction

- membres sans objectif ;
- rapports du jour envoyés / manquants ;
- objectifs verts, orange, rouges et bloqués ;
- projets en retard ;
- stagiaires par tuteur ;
- encaissements du mois ;
- charges du mois ;
- solde disponible ;
- créances ;
- réserve et mois couverts ;
- niveau d’alerte financier.

### Tableau finance

- soldes caisse, Mobile Money et banque ;
- dépenses en attente ;
- encaissements du mois ;
- créances échues ;
- écarts de rapprochement ;
- budget contre réalisé ;
- réserve disponible.

---

## 12. Données principales

Entités à prévoir au niveau fonctionnel :

- Utilisateur, rôle, permission, session ;
- service, fonction, relation de travail, contrat ou stage ;
- objectif d’entreprise, objectif individuel, progression, preuve ;
- projet, membre, tâche, livrable, commentaire ;
- rapport quotidien, blocage, demande d’aide ;
- revue hebdomadaire, plan d’amélioration ;
- présence, retard, absence, permission ;
- demande d’entrée, plan de stage, évaluation de stage ;
- réunion, décision, action ;
- client, prospect, offre, contrat, facture, paiement, créance ;
- compte financier, transaction, encaissement, dépense, approbation ;
- budget, charge fixe, réserve, rapprochement, rapport mensuel ;
- document interne, version, accusé de réception ;
- matériel, affectation, incident, retour ;
- notification et journal d’audit.

---

## 13. Exigences non fonctionnelles

1. Interface mobile-first et responsive.
2. Utilisable sur Chrome Android, Chrome desktop et Safari récent.
3. Chargement rapide sur une connexion 3G ou instable.
4. Sauvegarde automatique des brouillons.
5. Langue française simple et textes courts.
6. Dates au format local et fuseau `Africa/Niamey`.
7. Montants en XOF, généralement sans décimales.
8. HTTPS obligatoire.
9. Mots de passe hachés avec un algorithme moderne.
10. Protection contre les attaques courantes : brute force, injection, XSS, CSRF et accès direct aux fichiers.
11. Contrôle d’accès effectué côté serveur, pas seulement dans l’interface.
12. Sauvegarde quotidienne de la base et des pièces jointes.
13. Test régulier de restauration.
14. Journalisation des erreurs sans afficher de secrets.
15. Pièces jointes privées avec liens temporaires ou contrôle d’autorisation.
16. Accessibilité raisonnable : navigation clavier, contrastes et libellés clairs.
17. Capacité initiale : environ 5 à 100 utilisateurs sans changement majeur.
18. L’application n’est pas multi-entreprise dans le MVP.
19. Une PWA installable et le mode brouillon hors ligne sont souhaitables, mais peuvent être reportés.

---

## 14. Règles de sécurité et confidentialité

1. Principe du moindre privilège.
2. Un utilisateur ne voit que ses données et celles autorisées par son rôle.
3. Les stagiaires ne voient jamais les finances globales.
4. Les responsables voient uniquement leur équipe, sauf permission supplémentaire.
5. Les documents financiers et personnels ne sont pas publics par URL.
6. Une donnée financière validée n’est pas supprimée ; elle est annulée ou corrigée avec trace.
7. La suspension d’un compte coupe immédiatement les sessions actives.
8. Les comptes sortis sont archivés, pas réutilisés pour une autre personne.
9. Toute exportation sensible est auditée.
10. Les mots de passe, tokens et secrets ne sont jamais enregistrés en clair.

---

## 15. Périmètre MVP recommandé

Le MVP doit résoudre d’abord les problèmes qui ont déjà causé des pertes.

### Inclus dans le MVP

1. Authentification téléphone + mot de passe.
2. Rôles et permissions.
3. Profils et organisation.
4. Objectifs de l’entreprise et objectifs individuels.
5. Projets et tâches simples.
6. Rapports quotidiens, preuves et blocages.
7. Revues hebdomadaires et plans d’amélioration.
8. Gestion des stagiaires et limite de deux par tuteur.
9. Documents internes et accusés de réception.
10. Comptes financiers manuels.
11. Encaissements, dépenses et approbations.
12. Rapprochement hebdomadaire.
13. Réserve et alertes financières.
14. Rapport financier mensuel.
15. Notifications dans l’application.
16. Tableau de bord direction et tableau personnel.
17. Journal d’audit.

### Phase 2

- présence, retards, permissions et absences ;
- workflow complet de recrutement ;
- réunions et décisions ;
- matériel et accès ;
- clients, prospects, devis, factures et créances avancés ;
- exports PDF / Excel complets ;
- PWA et brouillons hors ligne ;
- OTP SMS ou notifications WhatsApp ;
- authentification renforcée pour les rôles sensibles.

### Hors périmètre initial

- paie complète ;
- déclaration automatique CNSS ou fiscale ;
- intégration directe aux banques ou Mobile Money ;
- biométrie ;
- géolocalisation permanente ;
- surveillance d’écran ;
- classement public des employés ;
- licenciement ou sanction automatique ;
- application mobile native séparée ;
- gestion de plusieurs entreprises clientes.

---

## 16. Critères d’acceptation essentiels du MVP

1. Un utilisateur actif peut se connecter avec son numéro et son mot de passe.
2. Un utilisateur ne peut pas ouvrir un écran interdit à son rôle, même avec l’URL directe.
3. Un stagiaire ne peut pas être activé sans tuteur et trois objectifs.
4. Le système empêche l’affectation d’un troisième stagiaire actif à un même tuteur.
5. Une personne ne peut pas avoir plus de trois objectifs majeurs validés pour le même mois.
6. Un objectif validé modifié conserve la valeur précédente, le motif et l’auteur.
7. Un rapport quotidien peut être sauvegardé, envoyé, validé ou retourné.
8. Le responsable ne peut pas modifier silencieusement le rapport d’un membre de son équipe.
9. Une dépense prévue jusqu’à 25 000 FCFA demande une validation autorisée.
10. Une dépense supérieure à 25 000 FCFA ou non prévue demande deux approbateurs différents.
11. L’auteur d’une dépense ne peut pas être son seul approbateur.
12. Une transaction financière validée ne peut pas être supprimée sans trace.
13. Le rapprochement calcule et affiche toute différence.
14. Le rapport mensuel affiche les encaissements, charges, dettes, trésorerie, résultat et réserve.
15. Le système affiche orange après un mois non couvert et rouge après deux mois consécutifs.
16. Les dirigeants sont soumis aux objectifs et rapports comme le reste de l’équipe.
17. Toutes les actions financières sensibles apparaissent dans le journal d’audit.
18. L’application reste utilisable sur un écran de téléphone et avec une connexion faible.

---

## 17. Décisions déjà prises

- Nom de l’entreprise : PTR Niger.
- Domaine : `staff.ptrniger.com`.
- Application privée, sans inscription publique.
- Connexion principale par téléphone et mot de passe.
- Langue principale : français.
- Fuseau : Africa/Niamey.
- Devise : XOF.
- Les dirigeants utilisent le même système d’objectifs et de rapports.
- Maximum de cinq priorités mensuelles pour l’entreprise.
- Maximum de trois objectifs majeurs par personne et par mois.
- Maximum de deux stagiaires actifs par tuteur.
- Rapport quotidien avant 17 h 45.
- Revue hebdomadaire le vendredi.
- Seuil de double approbation : plus de 25 000 FCFA ou toute dépense non prévue.
- Réserve cible : trois mois de charges fixes.
- Affectation à la réserve : au moins 20 % de la marge nette des projets jusqu’à l’objectif.
- Aucune sanction ou rupture automatique par le logiciel.
- Aucune suppression silencieuse des données financières.

---

## 18. Questions encore ouvertes pour l’Analyste

1. Combien de dirigeants doivent valider une dépense si PTR Niger ne compte qu’un dirigeant disponible ?
2. Le seuil de 25 000 FCFA doit-il être unique ou différent selon le rôle et la catégorie ?
3. La réinitialisation du mot de passe du MVP sera-t-elle faite par la direction ou par SMS ?
4. Quels comptes exacts existent : caisse, banque, Airtel Money, Moov Money, autre ?
5. Combien de personnes utiliseront l’application au lancement ?
6. Les présences doivent-elles être incluses dans le MVP ou la deuxième phase ?
7. Faut-il générer les factures directement dans PTR Staff ou seulement enregistrer les factures produites ailleurs ?
8. Quels types de fichiers et quelle taille maximale faut-il accepter ?
9. Le responsable financier est-il aussi un dirigeant ou un rôle séparé ?
10. Faut-il permettre le télétravail et comment doit-il être validé ?
11. Quelle durée de conservation appliquer aux données du personnel et aux justificatifs financiers ?
12. Quels navigateurs et appareils sont réellement utilisés par l’équipe ?
13. Quel hébergement et quel système de sauvegarde seront utilisés ?
14. Le nom final visible de l’application sera-t-il « PTR Staff » ou un autre nom ?

---

## 19. Livrables attendus de l’Agent Analyste BMAD

1. Product Brief final.
2. Reformulation claire du problème et de la vision.
3. Personas et besoins par rôle.
4. Périmètre MVP confirmé.
5. Liste des fonctionnalités par priorité.
6. Parcours utilisateurs prioritaires.
7. Règles métier validées et contradictions éventuelles.
8. Risques produit, humains, financiers et de sécurité.
9. Indicateurs de réussite.
10. Questions à faire valider avant le PRD.
11. Recommandation claire pour le passage à l’Agent Product Manager et au workflow PRD.

---

## 20. Résumé en une phrase

PTR Staff est une application interne, mobile-first et sécurisée qui donne à chaque membre de PTR Niger des objectifs clairs et vérifiables, tout en permettant à la direction de contrôler le travail, les stages, les projets et l’argent de l’entreprise sans revenir aux erreurs du passé.

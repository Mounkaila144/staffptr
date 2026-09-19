# PTR Staff — Manuel d'utilisation

> Version 1.0 · août 2026 · **https://staff.ptrniger.com**
>
> Ce manuel décrit ce que l'application permet de faire, écran par écran, et **les règles qu'elle
> fait respecter**. Il est destiné en priorité à la direction, qui doit pouvoir l'expliquer aux
> équipes.

---

## Sommaire

1. [Pour commencer](#1-pour-commencer)
2. [Qui voit quoi — les six rôles](#2-qui-voit-quoi--les-six-rôles)
3. [Le travail quotidien de chacun](#3-le-travail-quotidien-de-chacun)
4. [Objectifs, projets et tâches](#4-objectifs-projets-et-tâches)
5. [Rapports quotidiens et blocages](#5-rapports-quotidiens-et-blocages)
6. [Dépenses et double approbation](#6-dépenses-et-double-approbation)
7. [Finances](#7-finances)
8. [Alerte financière et plan correctif](#8-alerte-financière-et-plan-correctif)
9. [Stagiaires et tutorat](#9-stagiaires-et-tutorat)
10. [Revues hebdomadaires et accompagnement](#10-revues-hebdomadaires-et-accompagnement)
11. [Absences, calendrier et documents](#11-absences-calendrier-et-documents)
12. [Recherche, listes et exports](#12-recherche-listes-et-exports)
13. [Administration des comptes](#13-administration-des-comptes)
14. [Les tableaux de bord](#14-les-tableaux-de-bord)
15. [**Les règles que l'application refuse d'enfreindre**](#15-les-règles-que-lapplication-refuse-denfreindre)
16. [Ce que l'application ne fait pas](#16-ce-que-lapplication-ne-fait-pas)

---

## 1. Pour commencer

### Se connecter

Rendez-vous sur **https://staff.ptrniger.com**.

- **Identifiant** : votre **numéro de téléphone**, au format `+227` suivi de 8 chiffres. Vous
  pouvez saisir seulement les 8 chiffres, l'indicatif est ajouté automatiquement.
- **Mot de passe** : celui qui vous a été communiqué.

Il n'y a **pas d'inscription** : les comptes sont créés par la direction.

### Première connexion

À la première connexion, l'application **impose le changement du mot de passe**. Vous ne pouvez
accéder à aucun autre écran avant de l'avoir fait. C'est volontaire : un mot de passe transmis par
un tiers n'est jamais un secret.

### Mot de passe oublié

Contactez la direction. Elle déclenche une réinitialisation depuis l'écran **Comptes** ; vous
recevez un code de confirmation par WhatsApp, puis un mot de passe temporaire.

### Si vous vous trompez plusieurs fois

Après un nombre d'échecs paramétrable, le compte est **bloqué temporairement**. Le délai et le
nombre de tentatives se règlent dans **Paramètres**. Attendez, ou demandez une réinitialisation.

### Se repérer

- Sur ordinateur : menu à gauche.
- Sur téléphone : barre en bas, avec un bouton **•••** pour le reste.
- La cloche en haut à droite indique vos **notifications non lues**.

L'application est conçue pour le téléphone : tous les écrans fonctionnent sur un petit écran, sans
défilement horizontal.

---

## 2. Qui voit quoi — les six rôles

| Rôle | Ce qu'il fait |
|---|---|
| **direction** | Tout le pilotage : comptes, objectifs, validation des rapports, approbation des dépenses, finances, stagiaires, paramètres, audit |
| **finance** | Argent : encaissements, factures, dépenses, rapprochements, rapports mensuels, réserve, parts. **N'approuve pas** les dépenses |
| **tuteur** | Encadrement : ses stagiaires, validation de leurs rapports, objectifs de son équipe |
| **employe** | Son travail : rapport quotidien, objectifs, tâches, demandes de dépense, absences |
| **stagiaire** | Comme un employé, plus son parcours de stage |
| **super_admin** | **Technique uniquement** : comptes, rôles, paramètres, journaux. **Ne voit aucune donnée métier** — ni rapport, ni dépense, ni finance |

> **Le rôle `super_admin` ne peut rien faire de métier.** C'est une règle de sécurité : celui qui
> administre les accès ne doit pas pouvoir consulter les données. Si vous êtes connecté en
> `super_admin` et qu'un écran vous est refusé, ce n'est pas un dysfonctionnement.

**Chaque écran est vérifié côté serveur.** Un menu masqué n'est pas une protection : même en tapant
l'adresse directement, un rôle non autorisé reçoit un refus.

---

## 3. Le travail quotidien de chacun

### Un employé ou un stagiaire

1. **Accueil** — ce qui l'attend aujourd'hui
2. **Rapport du jour** — à envoyer avant l'heure limite
3. **Mes tâches du jour**
4. Au besoin : signaler un **blocage**, demander une **dépense**, poser une **absence**

### Un tuteur

Tout ce qui précède, plus :

1. **Rapports à valider** — ceux de son équipe
2. **Mes stagiaires** — parcours, évaluations, créneaux de suivi
3. **Objectifs** de son équipe

### La direction

1. **Tableau de bord direction** — la vue d'ensemble
2. **En attente de mon approbation** — toujours en première position
3. **Rapports à valider**
4. **Tableau de bord financier**

---

## 4. Objectifs, projets et tâches

### Objectifs — `Objectifs`

Un objectif porte un titre, un indicateur, une valeur cible, une preuve attendue, les moyens
nécessaires et une échéance.

**Cycle de vie** : `brouillon` → `validé` → `en cours` → `atteint`, `partiellement atteint`,
`non atteint` ou `bloqué`. Un objectif peut aussi être `annulé`.

- **Validation** : par la direction ou un tuteur.
- **Un objectif « atteint » exige une preuve.** L'application la demande.
- **Modification** : un objectif validé qui change **conserve son ancienne valeur, le motif et
  l'auteur**. Rien n'est écrasé.
- **Copie** : un objectif copié repart en brouillon.

> ⚠️ **Maximum trois objectifs majeurs validés par personne et par mois.** Le quatrième est refusé.
> Cette limite s'applique **aussi à la direction**.

**Écrans utiles** : `Objectifs`, `Objectifs / Calendrier`, `Objectifs / Synthèse`.

### Priorités d'entreprise — `Priorités entreprise`

Les grandes priorités du mois, auxquelles les objectifs individuels se rattachent.

> ⚠️ **Maximum cinq priorités par mois.** Une priorité ne se supprime pas : elle s'annule, avec un
> motif.

### Projets — `Projets`

Nom, client, responsable, dates, budget prévu et budget consommé.

**Cycle de vie** : `prévu` → `actif` → `en validation` → `livré` → `clôturé`. Un projet peut être
`bloqué` ou `annulé`.

Chaque projet accepte des **membres**, des **commentaires**, des **liens** et des **pièces
jointes**. L'historique des changements de statut et des membres est conservé.

L'écran **Budget** d'un projet exige la permission `projet.budget.consulter` — tout le monde ne
voit pas les montants.

### Tâches — `Tâches`, `Tâches du jour`

Les tâches se rattachent à un projet ou à un objectif. **Tâches du jour** est la liste de travail
quotidienne. Une tâche accepte commentaires, liens et pièces jointes.

---

## 5. Rapports quotidiens et blocages

### Le rapport du jour — `Rapport du jour`

C'est le cœur de l'application. Chaque personne rend compte de sa journée.

**Cycle de vie** : `brouillon` → `envoyé` → `validé` ou `retourné`. Un rapport envoyé après l'heure
limite est marqué `en retard`.

- L'**heure limite** et le **délai de rappel** se règlent dans **Paramètres**.
- Vous recevez une notification quand l'heure approche, puis si le rapport est en retard.
- **Un rapport n'est pas attendu** un jour non travaillé, ni pendant une absence approuvée.

> ⚠️ **Un responsable ne peut pas modifier silencieusement le rapport d'un membre.** Il peut le
> **valider**, le **retourner** avec un motif, ou le **commenter**. La correction appartient à
> l'auteur. C'est ce qui fait du rapport un document de contribution, pas de surveillance.

**Demandes de tâche** : depuis un rapport, on peut demander la création d'une tâche. La demande est
ensuite traitée par qui a le droit de gérer les tâches.

### Validation — `Rapports à valider`

Réservée à la **direction** et aux **tuteurs**. Chaque validation, retour et commentaire est tracé.

### Blocages — `Blocages`

Signaler ce qui empêche d'avancer, avec un niveau d'urgence et la personne sollicitée.

**Cycle de vie** : `ouvert` → `pris en charge` → `résolu` ou `fermé sans solution`.

La personne sollicitée est **notifiée**. Les demandes non urgentes adressées à un tuteur sont
**regroupées** et envoyées à son créneau de suivi, pour ne pas l'interrompre en continu.

---

## 6. Dépenses et double approbation

### Demander une dépense — `Créer une dépense`

**Tout compte authentifié** peut demander une dépense : motif, montant, bénéficiaire, résultat
attendu, catégorie, et un justificatif en pièce jointe.

**Cycle de vie** : `demandée` → `approuvée` → `payée`, ou `refusée`, ou `annulée`.

### Approuver — `En attente de mon approbation`

Réservé à la **direction**.

> ⚠️ **Toute dépense exige deux approbateurs distincts, quel que soit son montant.** Il n'y a
> aucun seuil en dessous duquel une seule signature suffirait.

> ⚠️ **Le demandeur n'est jamais son propre approbateur**, même s'il est de la direction. Le
> bénéficiaire d'une part ne peut pas non plus approuver son propre versement.

Un refus **exige un motif** et il est définitif. Deux décisions d'un même compte ne comptent jamais
deux fois.

**Il faut exactement deux comptes `direction`** pour que l'approbation soit possible. Tant qu'ils
n'existent pas, l'application refuse d'approuver et le dit clairement.

### Payer — `Paiements de dépenses`

Réservé à **finance**. Le paiement enregistre le compte débité, la date, le mode et une référence.

### Catégories — `Catégories de dépense`

Réglées dans les paramètres. Une catégorie peut être marquée **« essentielle »** : cette marque
change le comportement en alerte rouge (voir § 8).

---

## 7. Finances

Réservé à **direction** et **finance**.

| Écran | À quoi il sert |
|---|---|
| **Comptes financiers** | Caisses et comptes bancaires, avec leur solde calculé |
| **Clients** | Répertoire des clients |
| **Contrats** | Montant attendu, bénéfice prévisionnel, apporteur, exécutants |
| **Factures** | Émission, suivi des impayés, annulation motivée |
| **Encaissements** | Enregistrement des règlements reçus |
| **Charges fixes** | L'assiette mensuelle — elle sert au calcul de l'alerte (§ 8) |
| **Budgets mensuels** | Budget par catégorie de dépense |
| **Rapprochements** | Comparaison entre solde calculé et solde physique |
| **Rapports mensuels** | Synthèse du mois, puis clôture |
| **Réserve** | Réserve de sécurité et son objectif |
| **Parts** | Parts dues aux apporteurs et exécutants |

### Points à connaître

**Les montants sont des entiers en francs CFA.** Aucun centime, aucun arrondi flottant.

**Un encaissement déclenche automatiquement** le calcul des parts et l'allocation à la réserve,
dans la même opération. Si une étape échoue, rien n'est enregistré.

**Les trois répartitions possibles**, appliquées automatiquement selon le contrat :

| Cas | Répartition |
|---|---|
| Aucun apporteur | **100 %** PTR Niger |
| Apporteur, sans exécutant désigné | **10 %** apporteur · **90 %** PTR Niger |
| Apporteur **et** exécutants | **10 %** apporteur · **30 %** exécutants à parts égales · **60 %** PTR Niger |

**La somme des parts est exactement égale à la base.** La division est entière ; le reliquat revient
au bénéficiaire de rang 1 de chaque répartition. Il ne disparaît jamais. Chaque part affiche sa
**méthode de calcul** en toutes lettres.

**Rapprochements et rapports mensuels** : le préparateur et le contrôleur doivent être **deux
personnes différentes**.

**Un mois clôturé est fermé.** Aucune écriture ne peut plus y être imputée. Rouvrir un mois exige
un motif, et les écritures postérieures sont marquées comme telles.

> ⚠️ **Rien ne se supprime en finance.** Une erreur se corrige par une **contre-écriture** ou une
> **correction versionnée**, jamais par un effacement. C'est vrai au niveau de l'application, des
> adresses web, et de la base de données elle-même.

---

## 8. Alerte financière et plan correctif

L'application calcule en permanence un **niveau d'alerte**, affiché sur le tableau de bord
direction avec son libellé — jamais par la couleur seule.

### Comment il est calculé

L'**assiette** est la somme des **charges fixes actives** du paramétrage. Rien n'est codé en dur :
ajouter une charge fixe change l'assiette, donc le niveau au recalcul suivant.

| Niveau | Situation |
|---|---|
| 🟢 **Vert** | Les encaissements du mois atteignent l'assiette |
| 🟠 **Orange** | **Un** mois sous l'assiette |
| 🔴 **Rouge** | **Deux mois consécutifs** sous l'assiette |

Le calcul affiche sa **méthode** et la **date de ses données**. Le niveau d'un mois clôturé est figé
et n'est jamais recalculé.

### Ce que le niveau change — et ce qu'il ne change pas

**En orange** : l'application demande un **plan correctif sous 48 heures** et relance la direction
chaque jour tant qu'il n'existe pas. Le plan porte constat, actions, responsables, échéance et
résultat attendu. Une fois validé, il ne se modifie plus : une révision crée une nouvelle version,
et l'ancienne reste consultable.

**En rouge** :

- L'**activation d'un nouveau compte** employé ou stagiaire est **refusée**, avec un message qui
  nomme le niveau.
- L'approbation d'une dépense de catégorie **non essentielle** affiche un **avertissement** — mais
  **elle reste possible**. C'est à la direction de décider.

> ⚠️ **Ce que l'alerte ne fait jamais**, quel que soit le niveau :
>
> - Elle **ne bloque aucune personne** — aucune suspension, aucun retrait de rôle, aucune session
>   fermée.
> - Elle **n'empêche pas le versement des parts**. Une part est une créance acquise sur un
>   encaissement déjà reçu ; la retenir ferait porter la tension de trésorerie de l'entreprise à
>   une personne.
> - Elle ne réactive pas un compte suspendu à tort, et n'empêche pas de le réactiver.
>
> L'application peut bloquer une **écriture**. Jamais quelqu'un.

---

## 9. Stagiaires et tutorat

### Fiche d'entrée — `Fiches d'entrée`

Avant tout stage : besoin réel, durée, tuteur désigné, et **trois résultats attendus** au minimum.
La fiche passe par `brouillon` → `soumise` → `approuvée` ou `refusée`.

### Activation d'un stagiaire

Un compte stagiaire ne devient actif que si **quatre conditions** sont réunies :

1. une fiche d'entrée **approuvée** ;
2. un **tuteur désigné** ;
3. **trois objectifs** enregistrés ;
4. le niveau d'alerte financière n'est **pas rouge**.

Si l'une manque, l'application refuse et **dit laquelle**.

### Limite par tuteur

> ⚠️ **Trois stagiaires actifs au maximum par tuteur.** Le quatrième est refusé. L'écran
> **Charge des tuteurs** montre qui est à la limite, avec un libellé explicite.

### Parcours — `Mes stagiaires`

Plan de stage, liste de contrôle d'intégration, évaluations, et sortie avec bilan. Les évaluations
sont validées par le tuteur ou la direction.

### Créneaux de suivi — `Créneaux de suivi`

Le tuteur déclare ses créneaux ; les demandes non urgentes lui sont livrées groupées à ce
moment-là.

---

## 10. Revues hebdomadaires et accompagnement

### Revues — `Revues hebdomadaires`

Réservées à la direction pour la création. Chaque revue passe en revue les objectifs, reçoit des
commentaires, puis est soumise et validée.

### Plans d'accompagnement — `Plans d'accompagnement`

Quand une difficulté est constatée, un plan d'accompagnement est créé depuis une revue. Il se
clôture explicitement.

> Le vocabulaire est celui de la **contribution**, pas de la sanction. L'application ne produit
> **aucun classement entre personnes**.

---

## 11. Absences, calendrier et documents

### Absences — `Absences`

Type, dates, motif. **Cycle de vie** : `demandée` → `approuvée` ou `refusée`. Une absence peut être
`annulée`.

Une absence approuvée **dispense du rapport quotidien** sur la période.

### Calendrier — `Calendrier`

Jours fériés et jours non travaillés. Réservé à la direction. Ces jours retirent l'attente de
rapport.

### Documents internes — `Documents internes`

Publication de documents avec **versions** et **accusés de lecture**. La direction voit qui a
accepté quoi et depuis quand. Chaque publication notifie les personnes concernées.

### Pièces jointes

Photos et PDF, avec un type et une taille maximale réglés dans les paramètres. **Elles ne sont
jamais accessibles publiquement** : chaque téléchargement passe par une vérification de vos droits.

---

## 12. Recherche, listes et exports

### Recherche — `Recherche`

Cherche **personnes, projets et objectifs** en une fois, avec filtres par **période** et par
**statut**.

> ⚠️ **Vous ne voyez que ce que vous avez le droit de voir** — y compris dans les compteurs. Si
> un objectif ne vous est pas accessible, il n'apparaît ni en titre, ni en extrait, ni dans le
> nombre de résultats.

### Listes filtrables

Quatre listes principales : **Dépenses**, **Objectifs**, **Rapports quotidiens**, **Personnes**.

- Filtres et tri sur chaque liste ; les filtres actifs sont visibles et se retirent un par un.
- Sur téléphone, les filtres sont dans un panneau qui se referme après application.
- Une liste vide **par filtre** ne se confond pas avec une liste réellement vide : le message
  diffère, et **Réinitialiser les filtres** est proposé.
- **Enregistrer un filtre** pour le réutiliser est ouvert aux rôles qui ont le tableau de bord
  global : **direction, finance et tuteur**. Le filtre est **privé à son auteur** — même un autre
  compte direction ne le voit pas.

### Exports — bouton `Exporter en CSV`

- **CSV uniquement.** Pas de PDF, pas d'Excel.
- Le fichier s'ouvre directement dans un tableur francophone (séparateur `;`, accents corrects).
- Les montants sont des **entiers bruts**, donc additionnables.
- Un export volumineux est préparé en arrière-plan ; vous êtes notifié quand il est prêt.

> ⚠️ **Un export ne contient jamais une ligne que vous ne voyez pas à l'écran**, même en modifiant
> l'adresse. Et **tout export est enregistré dans le journal d'audit** : qui, quelles données,
> combien de lignes.

---

## 13. Administration des comptes

Réservé à **direction** et **super_admin** — `Comptes`.

- **Créer un compte** : nom, téléphone, rôle. Un mot de passe temporaire est généré.
- **Changer les rôles** d'un compte.
- **Archiver** un compte.
- **Réinitialiser un mot de passe** : un code de confirmation est envoyé par WhatsApp, puis un mot
  de passe temporaire est remis.

**États d'un compte** : `invité` → `actif` → `suspendu`, `terminé` ou `archivé`.

> ⚠️ **Suspendre un compte ferme immédiatement toutes ses sessions.** La personne est déconnectée
> partout, sans délai.

> ⚠️ **Un numéro de téléphone est unique** parmi les comptes non archivés. Un compte archivé libère
> son numéro.

### Fiches et historique — `Personnes`

Chaque personne a une fiche, des documents, et un **historique complet** des changements : qui a
changé quoi, quand, et pourquoi.

### Journal d'audit — `Journal d'audit`

Réservé à la **direction**. Toute action sensible y figure : auteur, date, objet, ancienne et
nouvelle valeur, motif.

> ⚠️ **Le journal d'audit ne peut être ni modifié ni supprimé.** Cette garantie est appliquée par
> la base de données elle-même, pas seulement par l'application. Personne n'y échappe.

Il est **exportable en CSV**, et cet export est lui-même audité.

### Connexions — `Connexions`

Historique des tentatives de connexion, réussies et échouées.

### Paramètres — `Paramètres`

Heure limite des rapports, délai de rappel, jours travaillés, limite de stagiaires par tuteur,
types et taille des pièces jointes, tentatives de connexion, durée de blocage, pourcentage et
objectif de réserve, délai d'alerte de fin de contrat.

**Chaque changement est daté et audité**, avec un aperçu de son effet avant application.

---

## 14. Les tableaux de bord

### Tableau de bord direction — `Tableau de bord direction`

| Bloc | Contenu |
|---|---|
| **En attente de mon approbation** | **Toujours en première position** |
| Niveau d'alerte | Avec libellé, méthode de calcul et date des données |
| Membres sans objectif | **Y compris les comptes direction** |
| Rapports du jour | Envoyés / manquants — hors absences et jours non travaillés |
| Objectifs du mois | Atteints, en cours, non atteints, bloqués |
| Projets en retard | |
| Stagiaires par tuteur | Les tuteurs à la limite sont signalés **par un libellé** |
| Encaissements, charges, solde, créances, réserve | |

### Tableau de bord financier — `Argent`

Soldes par compte, dépenses en attente, encaissements du mois, créances échues, écarts de
rapprochement, budget contre réalisé, réserve, et **engagements de parts restant à verser**.

Chaque bloc est cliquable vers sa liste détaillée.

> **Un bloc auquel vous n'avez pas droit n'est pas affiché vide : il n'existe pas.** Un `tuteur`
> qui ouvre le tableau de bord direction n'y voit aucun bloc financier.

---

## 15. Les règles que l'application refuse d'enfreindre

Ces règles ne sont pas des recommandations. L'application les applique, et vous rencontrerez leurs
refus. Les connaître évite de croire à un dysfonctionnement.

| # | Règle |
|---|---|
| 1 | **Trois objectifs majeurs** validés par personne et par mois, maximum |
| 2 | **Cinq priorités d'entreprise** par mois, maximum |
| 3 | **Trois stagiaires actifs** par tuteur, maximum |
| 4 | **Deux approbateurs distincts** pour toute dépense, sans seuil de montant |
| 5 | **Le demandeur n'est jamais approbateur**, même en direction |
| 6 | **Préparateur ≠ contrôleur** sur les rapprochements et rapports mensuels |
| 7 | **Aucune suppression financière** — ni par l'écran, ni par l'adresse, ni en base |
| 8 | **Aucune écriture sur un mois clôturé** |
| 9 | **`super_admin` n'a aucune permission métier** |
| 10 | **La suspension ferme immédiatement toutes les sessions** |
| 11 | **Si l'audit ne s'écrit pas, l'opération est annulée** |
| 12 | **Téléphone unique** parmi les comptes non archivés |
| 13 | **Les parts 10 % / 30 % restent payables même en alerte rouge** |
| 14 | **La somme des parts est exactement égale à la base** |

### Deux principes qui traversent tout

**Rien ne se supprime.** Une erreur se corrige par une version nouvelle, une annulation motivée ou
une contre-écriture. L'historique reste lisible.

**Toute écriture sensible est auditée dans la même opération.** Si la trace ne peut pas s'écrire,
l'opération entière est annulée. Il n'existe donc pas d'action sensible sans trace.

---

## 16. Ce que l'application ne fait pas

Pour éviter les attentes déçues :

- **Aucune notification par SMS ni par courriel.** Le seul canal externe est **WhatsApp**.
- **WhatsApp ne reçoit pas tout.** *Toutes* les notifications apparaissent dans l'application, sous
  la cloche. Seules six catégories sortent aussi sur WhatsApp : rappel et retard de rapport
  quotidien, blocage signalé, dépense à approuver, relance d'approbation, plan correctif attendu,
  et demandes de suivi groupées d'un tuteur. Le reste — document publié, export prêt, décision sur
  un rapport, échéance d'objectif — se consulte dans l'application.

  > Ce n'est pas une limite technique, c'est une protection. L'application joint WhatsApp par un
  > canal non officiel : un numéro qui envoie chaque jour des dizaines de messages identiques à des
  > personnes qui ne lui ont jamais écrit finit par être bloqué. Or ce même numéro porte les codes
  > de réinitialisation de mot de passe. Le perdre couperait aussi la récupération des comptes.

- **Les messages WhatsApp ne partent pas tous en même temps.** Un rappel peut arriver quelques
  minutes après un collègue : les envois sont étalés volontairement, pour la même raison.
- **Aucun export PDF ni Excel.** CSV seulement.
- **Aucun mode hors ligne.** Une connexion est nécessaire.
- **Aucune application mobile à installer.** L'application s'utilise dans le navigateur, et elle
  est conçue pour le téléphone.
- **Aucun classement entre personnes.** L'application mesure des objectifs et des contributions,
  pas des individus les uns contre les autres.
- **Aucune donnée portée par la couleur seule.** Chaque état a un libellé et un symbole.

---

## Annexe — Les écrans, par rôle

| Écran | direction | finance | tuteur | employé | stagiaire |
|---|:---:|:---:|:---:|:---:|:---:|
| Accueil, Notifications, Recherche, Export CSV | ✅ | ✅ | ✅ | ✅ | ✅ |
| Enregistrer un filtre réutilisable | ✅ | ✅ | ✅ | — | — |
| Rapport du jour, Blocages, Absences | ✅ | ✅ | ✅ | ✅ | ✅ |
| Objectifs, Projets, Tâches, Livrables | ✅ | ✅ | ✅ | ✅ | ✅ |
| Documents internes | ✅ | ✅ | ✅ | ✅ | ✅ |
| Fiche d'une personne, documents personnels ² | ✅ | 👁️ | 👁️ | 👁️ | 👁️ |
| Demander une dépense ¹ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Rapports à valider | ✅ | — | ✅ | — | — |
| **Approbation des dépenses** | ✅ | — | — | — | — |
| Paiement des dépenses | — | ✅ | — | — | — |
| Stagiaires, fiches d'entrée, créneaux de suivi | ✅ | — | ✅ | — | 👁️ |
| Décider une fiche d'entrée, charge des tuteurs | ✅ | — | ✅ | — | — |
| Revues hebdomadaires | ✅ | 👁️ | 👁️ | 👁️ | 👁️ |
| Finances (comptes, factures, encaissements…) | ✅ | ✅ | — | — | — |
| Réserve, rapports mensuels | ✅ | ✅ | — | — | — |
| **Parts** — chacun voit **sa** part | ✅ | ✅ | — | 👁️ | — |
| Tableau de bord financier | ✅ | ✅ | — | — | — |
| Tableau de bord direction | ✅ | ✅ | ✅ | — | — |
| Comptes et rôles | ✅ | — | — | — | — |
| Journal d'audit, Connexions | ✅ | — | — | — | — |
| Paramètres, Calendrier, Organisation | ✅ | — | — | — | — |

✅ accès complet · 👁️ consultation seule, limitée à son périmètre · — pas d'accès

¹ **La demande de dépense est ouverte à tout compte authentifié**, sans permission dédiée. C'est
volontaire : la dépense se demande librement, ce sont l'**approbation** et le **paiement** qui sont
verrouillés, et à des rôles différents.

² Consultation ouverte à tous ; seule la **direction** peut modifier une fiche ou y déposer un
document.

> Le `super_admin` n'apparaît pas dans ce tableau : il n'a accès qu'aux **Comptes**, **Rôles**,
> **Paramètres** et **Journaux techniques**, et à aucune donnée métier.

> **Ce tableau reflète le catalogue de permissions livré** (`config/permission-catalog.php`,
> vérifié le 17 août 2026). Il fait foi ; si un écran se comporte autrement, c'est le tableau
> qu'il faut corriger, pas l'application.

---

## Besoin d'aide

- Un écran refusé n'est pas forcément une panne : vérifiez votre rôle au § 2.
- Un refus d'action s'accompagne toujours d'un **message expliquant ce qui manque**.
- Pour tout le reste, contactez la direction.

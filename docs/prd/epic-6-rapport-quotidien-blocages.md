<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

# Epic 6 — Rapport quotidien et blocages

**Objectif.** Installer la boucle courte du produit. Cet epic porte **le point de vérité** :
la saisie de fin de journée sur téléphone en 3G, en moins de trois minutes. Si cette mesure échoue en
recette, c'est le produit qui échoue, pas la story.

**Dépend de :** Epic 5 (tâches du jour pour le pré-remplissage), Epic 4 (calendrier et absences), Epic 3
(notifications, pièces jointes). **Jalon 3.**

---

### Story 6.1 — Saisie du rapport quotidien et brouillon local

*En tant qu'utilisateur, je veux rendre compte de ma journée en moins de trois minutes depuis mon
téléphone, afin que rendre compte reste un geste tenable tous les jours.* — [PRD 3.1]

1. ⛔ Il existe **au plus un rapport par personne et par jour travaillé** ; une seconde création pour le même jour ouvre le rapport existant, elle n'en crée pas un second. Contrainte posée en base autant qu'en applicatif.
2. Les six champs obligatoires sont présents : tâche prévue, résultat obtenu, **preuve ou lien**, blocage, prochaine action, aide demandée.
3. Le champ « tâche prévue » est **pré-rempli** depuis les tâches assignées pour la journée (5.6) et reste modifiable (FR62).
4. Le rapport accepte image, document ou lien, dans les limites paramétrées.
5. ⛔ Le brouillon est sauvegardé automatiquement **au plus tard 10 secondes** après la dernière frappe (NFR5).
6. ⛔ Une saisie interrompue par une coupure réseau est **restaurée intégralement** à la réouverture sur le même appareil ; testé en simulant une coupure au milieu de la saisie.
7. ⛔ **La saisie complète est réalisable en moins de 3 minutes** sur téléphone réel avec pré-remplissage actif. La mesure est consignée en recette et **conditionne la mise en service du jalon** (NFR4, CONTRA-08).
8. Le formulaire est utilisable à 320 px, cibles ≥ 44 × 44 px, sans défilement horizontal, saisissable à une main.
9. ⛔ Une action interrompue par une perte de connexion ne produit **jamais d'enregistrement partiel** : tout ou rien (NFR6).
10. Hors connexion, le bandeau explique « L'envoi n'a pas abouti — pas de connexion. Votre rapport est conservé sur cet appareil. [Réessayer] » sans promettre d'envoi automatique.

> **Si la mesure des 3 minutes échoue en recette réelle**, l'arbitrage remonte au produit : réduire
> les champs obligatoires ou accepter la friction (CONTRA-08). Ce n'est pas une décision de
> développement.

---

### Story 6.2 — Envoi, heure limite, rappel et retard

*En tant que responsable, je veux que l'heure limite et les rappels soient gérés par l'application,
afin de ne plus courir après les rapports.* — [PRD 3.2]

1. Les états `brouillon`, `envoye`, `valide`, `retourne`, `en_retard` existent ; transitions testées.
2. Un rappel est émis au **délai paramétré avant l'heure limite** (60 minutes par défaut) à toute personne dont le rapport n'est pas `envoye` (FR66, C12).
3. Une notification de retard est émise après l'heure limite si le rapport n'est pas `envoye`.
4. ⛔ **Aucun rappel ni retard** n'est émis pour un jour non travaillé ou couvert par une absence approuvée ; les deux cas sont testés.
5. Un rapport envoyé après l'heure limite affiche le retard constaté et propose une **explication courte facultative** — sans ton accusatoire (FR71, NFR29).
6. ⛔ La modification de l'heure limite au paramétrage change le comportement **sans redéploiement** ; testé.
7. Les rappels sont émis par tâche planifiée ; un test vérifie qu'un rappel n'est pas envoyé deux fois pour le même jour.

---

### Story 6.3 — Validation, retour et historique des versions

*En tant qu'utilisateur, je veux que mon responsable ne puisse pas réécrire mon rapport, afin que ce
qui est enregistré à mon nom soit exactement ce que j'ai écrit.* — [PRD 3.3]

1. Le responsable peut commenter, valider ou retourner un rapport.
2. ⛔ Le responsable **ne peut modifier aucun champ saisi par l'auteur** ; une requête de modification par un compte autre que l'auteur est refusée côté serveur, **y compris pour `direction`** (FR67, CA-08).
3. ⛔ Une correction par l'auteur après envoi crée une **nouvelle version** ; la version précédente reste consultable avec son horodatage (FR68).
4. Un rapport retourné revient à l'auteur avec le **motif du retour** et une notification.
5. ⛔ Le périmètre de validation respecte la matrice : `tuteur` son équipe, `direction` tous ; hors périmètre par URL directe → refus.
6. Chaque validation, retour et nouvelle version produit une entrée d'audit.
7. Depuis la notification, la validation est atteignable en au plus 3 interactions (UX § 4.3).

---

### Story 6.4 — Vues des rapports et rapports manquants

*En tant que direction, je veux voir qui a rendu son rapport et qui ne l'a pas rendu, afin de traiter
le manquement le jour même plutôt qu'en fin de mois.* — [PRD 3.4]

1. Trois vues existent : quotidienne, hebdomadaire, mensuelle.
2. Une liste « rapports manquants du jour » affiche les personnes attendues sans rapport `envoye`.
3. ⛔ Cette liste **exclut** les personnes en absence approuvée et les jours non travaillés ; testé sur un mois comportant les deux.
4. ⛔ Le taux de ponctualité est le rapport des rapports envoyés avant l'heure limite sur les rapports **attendus**, dénominateur corrigé des absences (FR39).
5. Chaque vue applique strictement la matrice § 4.3.
6. ⛔ Aucun classement comparatif entre personnes ; la liste est alphabétique, jamais ordonnée par performance.
7. Vide positif : « Tous les rapports du jour ont été envoyés. »

---

### Story 6.5 — Demande de nouvelle tâche

*En tant qu'utilisateur sans tâche disponible, je veux demander une tâche depuis mon rapport, afin de
ne pas rester sans travail en attendant une réunion.* — [PRD 3.5]

1. Une demande de nouvelle tâche est créable **depuis le formulaire de rapport quotidien**, sans le quitter.
2. Elle est notifiée immédiatement au responsable direct.
3. Elle porte un état ouvert / traité et **conserve le lien vers le rapport d'origine**.
4. Les demandes non urgentes d'un `stagiaire` sont regroupées selon les créneaux de suivi (7.6) ; le contrat est posé ici, la règle appliquée là-bas.
5. Création et traitement sont audités.

---

### Story 6.6 — Blocages et demandes d'aide

*En tant qu'utilisateur bloqué, je veux signaler mon blocage et obtenir une réponse rapide, afin qu'un
obstacle ne consomme pas une journée entière.* — [PRD 3.6]

1. Un blocage est créable depuis une **tâche, un objectif ou un rapport**, et conserve le lien vers son origine ; **les trois chemins sont testés**.
2. Il porte problème, niveau d'urgence, personne sollicitée, date, effet sur l'échéance, action déjà essayée.
3. Les états `ouvert`, `pris_en_charge`, `resolu`, `ferme_sans_solution` existent.
4. ⛔ La création notifie **immédiatement** la personne sollicitée, **sans regroupement**, lorsque l'urgence est marquée (FR92).
5. Les délais création → `pris_en_charge` et création → `resolu` sont calculés et affichés.
6. La fermeture sans solution exige un **motif**.
7. La création d'un blocage depuis le rapport quotidien ne fait pas perdre la saisie en cours.
8. Vide positif : « Aucun blocage ouvert. »

---

## ✅ Critères de fin de l'epic 6

1. ⛔ **Recette bloquante : saisie complète du rapport quotidien en moins de 3 minutes sur téléphone réel en 3G dégradée**, avec pré-remplissage actif, mesurée et consignée. Sans cette mesure, le jalon ne part pas en production.
2. ⛔ Le brouillon est restauré intégralement après coupure réseau au milieu de la saisie.
3. ⛔ Un responsable, `direction` comprise, ne peut modifier aucun champ d'un rapport dont il n'est pas l'auteur.
4. ⛔ La liste des rapports manquants et le taux de ponctualité excluent correctement absences approuvées et jours non travaillés.
5. Un seul rapport par personne et par jour, garanti en base autant qu'en applicatif.
6. Parcours Playwright « rapport quotidien de bout en bout, réseau bridé à 400 kbit/s » vert.
7. Campagne d'autorisation étendue aux rapports et aux blocages — verte.

---

---

**Règles transverses applicables à toutes les stories de cet epic :** voir `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
**Écarts d'ordonnancement et décisions en attente :** voir `docs/prd/ecarts-et-decisions.md`.

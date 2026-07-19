<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

# Epic 3 — Organisation, profils, paramètres et documents internes

**Objectif.** Donner à l'application la connaissance de l'entreprise — qui dépend de qui, quels
services, quelles règles chiffrées — et livrer les deux mécanismes transverses dont tout le reste
dépend : les pièces jointes privées et le centre de notifications.

**Dépend de :** Epic 2. **Bloque :** Epic 4, Epic 5, Epic 6.

---

### Story 3.1 — Fiche entreprise, services et fonctions

*En tant que direction, je veux décrire l'entreprise, ses services et ses fonctions, afin que chaque
personne ait une place identifiée.* — [PRD 1.6]

1. Une **fiche entreprise unique** existe (nom, coordonnées, logo optionnel) ; ⛔ aucune interface ne permet d'en créer une seconde, et aucune colonne de locataire n'existe dans le schéma (NFR28).
2. Services et fonctions sont créables, renommables et désactivables par `direction` ; ils ne sont jamais supprimés.
3. Un service portant encore des membres ne peut pas être désactivé sans réaffectation ; le message nomme le nombre de membres concernés.
4. `CompanySeeder` initialise la fiche PTR Niger, de façon idempotente.
5. Modification de la fiche, création et désactivation d'un service ou d'une fonction sont auditées.

---

### Story 3.2 — Fiche utilisateur, hiérarchie et statut opérationnel

*En tant que direction, je veux tenir à jour chaque fiche, afin que chacun ait un responsable, une
fonction et un statut identifiés.* — [PRD 1.6]

1. La fiche porte nom, téléphone, photo optionnelle, rôles, service, fonction, **responsable direct**, type de relation (`dirigeant`, `employe`, `contractuel`, `stagiaire`), dates de début et de fin de contrat ou de stage.
2. L'application affiche pour toute personne la liste de ses **responsables et subordonnés directs à la date courante** (FR19).
3. Un cycle hiérarchique est refusé : une personne ne peut pas être son propre responsable, directement ou indirectement ; testé sur une chaîne de trois.
4. Un service métier **expose** la liste des fins de contrat ou de stage proches, avec un délai paramétrable ; il est testé directement, sans passer par une notification. **L'émission effective de la notification est en 9.6** — le centre de notifications n'existe qu'en 3.7, et les rappels planifiés en 9.6.
5. Chacun consulte sa fiche ; le responsable celles de son équipe ; `direction` toutes. Tout autre accès est refusé, y compris par URL directe.
6. La fiche est lisible et modifiable à 320 px, les champs empilés, sans défilement horizontal.

---

### Story 3.3 — Historique des changements de fiche

*En tant que direction, je veux savoir qui a changé quoi et quand sur une fiche, afin qu'un
changement de responsable ou de rôle ne se discute pas de mémoire.* — [PRD 1.6, FR18]

1. Tout changement de **rôle, service, responsable direct ou statut** est historisé avec date, auteur, ancienne et nouvelle valeur.
2. L'historique est affiché sur la fiche, du plus récent au plus ancien, en français lisible (« Responsable : Aïcha → Moussa »).
3. Chaque changement produit **également** une entrée au journal d'audit ; les deux registres sont distincts et ne se remplacent pas (architecture § 22.1).
4. Aucune entrée d'historique n'est modifiable ni supprimable.
5. L'historique est visible par la personne concernée, son responsable et `direction` ; les autres accès sont refusés.

---

### Story 3.4 — Paramètres généraux

*En tant que direction, je veux administrer moi-même les règles chiffrées, afin de changer une limite
sans demander de développement.* — [PRD 1.7]

Cette story livre le **mécanisme** de paramétrage et les paramètres **scalaires immédiatement
exploitables**. Les familles de paramètres qui portent une liste d'objets arrivent avec la story qui
les consomme — sinon on livrerait un écran qui configure quelque chose d'inexistant.

1. Sont paramétrables depuis l'interface, et exploitables dès cette story : jours travaillés de la semaine, heure limite du rapport, délai de rappel, limite de stagiaires par tuteur, pourcentage de réserve, objectif de réserve en mois, types et taille maximale des pièces jointes, nombre de tentatives de connexion et durée de blocage.
2. `SettingSeeder` pose les valeurs initiales : stagiaires **3**, réserve **20 %**, objectif **3 mois**, heure limite **17 h 45**, rappel **60 minutes** (FR27 à FR29).
3. ⛔ **Aucune de ces valeurs n'apparaît en dur dans le code.** Un test modifie chaque paramètre livré ici et vérifie le changement de comportement associé, **sans redéploiement**.
4. Toute modification est auditée avec ancienne et nouvelle valeur et porte une **date d'effet** (FR26).
5. La modification est réservée à `direction` ; tout autre rôle est refusé, y compris par URL directe.
6. Un paramètre dont la modification a un effet chiffré affiche cet effet **avant confirmation** ; le mécanisme d'aperçu est livré ici, ses premiers usages chiffrés arrivent en 8.2.
7. Le cache de configuration est invalidé à l'écriture ; un test vérifie que la valeur nouvelle est lue à la requête suivante.

**Familles de paramètres livrées ailleurs**, chacune avec son consommateur :

| Famille | Story | Pourquoi pas ici |
|---|---|---|
| Jours fériés et fermetures | **4.1** | Le calendrier qui les interprète n'existe pas encore |
| Catégories de dépense et marqueur « essentielle » | **4.3** | Consommées par la demande de dépense |
| Créneaux de suivi par tuteur | **7.6** | Le regroupement des demandes n'existe qu'en Epic 7 |
| Charges fixes et montants mensuels | **8.2** | L'assiette d'alerte et l'objectif de réserve sont en Epic 8 |

---

### Story 3.5 — Pièces jointes privées

*En tant qu'utilisateur, je veux joindre une preuve sans qu'elle devienne accessible à qui possède
son adresse, afin qu'un justificatif ne circule pas hors de l'application.* — [NFR15, NFR16, A-04]

1. Les fichiers sont stockés dans `storage/app/private`, **hors de la racine web** ; ⛔ un test vérifie qu'aucune URL publique ne les atteint.
2. La lecture passe par un contrôleur qui **contrôle l'autorisation avant de servir**, avec `X-Sendfile` (DEC-13) pour ne pas faire transiter le fichier par PHP.
3. Types et taille maximale sont **paramétrables** (3.4) ; un téléversement non conforme est refusé **côté serveur**, même si le contrôle client est contourné ; testé en forgeant la requête.
4. Le message de refus est explicite : « Ce fichier fait 8 Mo, la limite est de 5 Mo. Choisissez un fichier plus léger. » — la limite affichée est celle réellement paramétrée.
5. Le type réel du fichier est vérifié, pas seulement son extension ; un exécutable renommé en `.pdf` est refusé.
6. Les images sont redimensionnées côté serveur en vignette pour l'affichage en liste (poids en 3G, UX § 11.2).
7. Toute pièce jointe est rattachée à un objet et hérite de ses règles de visibilité ; l'accès à la pièce d'un objet non autorisé est refusé.
8. Le téléversement affiche une progression et reste utilisable depuis l'appareil photo d'un téléphone.

> **DEC-08 appliqué par défaut :** PDF, JPEG, PNG, WebP, HEIC — 8 Mo. Modifiable au paramétrage.

---

### Story 3.6 — Documents du dossier personnel

*En tant que membre, je veux que mon contrat et mes engagements soient rangés dans mon dossier, afin
qu'ils ne circulent pas par messagerie.* — [PRD 1.6, FR17, FR98]

1. Un document (contrat, convention, fiche de poste, engagement signé) est rattachable au dossier d'une personne.
2. ⛔ Il n'est visible que par **cette personne, son responsable direct et `direction`** ; l'accès par URL directe depuis tout autre compte est refusé, y compris depuis `super_admin`.
3. Le document n'est jamais supprimé ; il est archivé, motivé.
4. Dépôt, consultation et archivage produisent une entrée d'audit.
5. État vide : « Aucun document dans ce dossier. » avec l'action de dépôt si l'utilisateur en a le droit.

---

### Story 3.7 — Centre de notifications interne

*En tant qu'utilisateur, je veux être averti dans l'application de ce qui m'attend, afin de ne pas
découvrir un retard après coup.* — [PRD 1.8]

Livrée ici et non au Jalon 4 : les relances de double approbation (4.6) et les rappels de rapport
(6.2) en dépendent — voir ÉCART-02.

1. Un centre de notifications avec **compteur de non-lues** est accessible depuis toute page authentifiée.
2. Le système de notifications Laravel est utilisé avec le **canal `database` seul** (A-07) ; l'architecture permet d'ajouter SMS ou WhatsApp en phase 2 sans refonte.
3. Chaque notification porte un **lien direct vers l'objet concerné**.
4. ⛔ Depuis la notification, l'objet lié est atteignable en **au plus 3 interactions**, prouvé ici sur une notification générique et son lien autorisé (FR32). **La mesure sur les deux parcours réels est faite là où ils naissent** : approbation de dépense en **4.6**, validation de rapport en **6.3**.
5. Une notification est marquée lue **explicitement** par l'utilisateur ou **implicitement** à l'ouverture de l'objet ; les deux comportements sont testés.
6. ⛔ Aucun envoi SMS, WhatsApp ou courriel n'est déclenché ; un test vérifie qu'aucun canal externe n'est appelé (FR34).
7. État vide : « Vous êtes à jour. » — ton positif, le vide étant ici une bonne nouvelle.
8. Le compteur ne provoque pas de requête à chaque navigation : il est porté par la réponse Inertia partagée.

---

### Story 3.8 — Bibliothèque de documents internes et accusés d'acceptation

*En tant que direction, je veux publier les règles internes et savoir qui les a acceptées, afin qu'un
engagement soit opposable.* — [PRD 3.13] — **avancée au Jalon 1, voir ÉCART-03**

1. Un document interne porte titre, contenu ou fichier, **version** et **date d'application**.
2. Un document peut exiger un **accusé de lecture et d'acceptation**, enregistré par utilisateur avec horodatage.
3. La publication d'une nouvelle version notifie les utilisateurs concernés et **réinitialise l'exigence d'acceptation** ; testé.
4. ⛔ L'historique complet des versions reste consultable ; aucune version n'est supprimable.
5. Un tableau montre à `direction` qui a accepté et qui n'a pas encore accepté chaque document, avec l'ancienneté de la demande.
6. Publication, nouvelle version et accusé d'acceptation produisent chacun une entrée d'audit.
7. La lecture d'un document long est confortable sur téléphone : texte fluide, pas de zoom horizontal, acceptation en pied de document.

---

## ✅ Critères de fin de l'epic 3

1. Chaque membre a une fiche complète avec responsable direct, et la chaîne hiérarchique est sans cycle.
2. Les paramètres livrés à ce jalon — jours travaillés, heure limite, délai de rappel, limite de stagiaires, pourcentage et objectif de réserve, types et taille des pièces jointes, tentatives et durée de blocage — sont modifiables à l'écran, et un test prouve pour chacun le changement de comportement **sans redéploiement**. Les quatre familles restantes de FR25 arrivent en 4.1, 4.3, 7.6 et 8.2.
3. ⛔ Aucune pièce jointe n'est atteignable par URL publique ; le refus de type et de taille est prouvé côté serveur.
4. Le centre de notifications fonctionne et **aucun canal externe n'est appelé**.
5. Le règlement intérieur est publié et l'état des acceptations est visible par `direction`.
6. La campagne d'autorisation couvre les nouvelles ressources, dossiers personnels compris.

---

---

**Règles transverses applicables à toutes les stories de cet epic :** voir `docs/prd/socle-transverse.md` (SOC-01 à SOC-11).
**Écarts d'ordonnancement et décisions en attente :** voir `docs/prd/ecarts-et-decisions.md`.

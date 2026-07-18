# Prompt de génération UI — Écran E3.1 « Rapport quotidien »

**Cible :** v0, Lovable, Claude, ou tout outil de génération de frontend.
**Source :** `docs/front-end-spec.md` § 4.1, 5.1, 6.2 à 6.5, 7, 8, 9 · `docs/prd.md` § 5.9, 6.
**Généré le :** 18/07/2026 par Sally (UX Expert).

> **Note d'architecture.** La décision A-01 du PRD (Inertia.js contre composants Vue montés dans
> des vues Blade) n'est pas encore tranchée. Ce prompt demande donc un **composant Vue 3 autonome,
> sans routeur ni couche de récupération de données** : il se monte à l'identique dans les deux
> hypothèses. La liaison au backend se fera après la décision de l'Architecte.

---

```
# CONTEXTE DU PROJET

Tu génères un écran pour PTR Staff, une application interne de gestion d'équipe pour PTR
Niger, une petite entreprise nigérienne de 5 à 100 utilisateurs. Interface entièrement en
FRANÇAIS. Devise XOF (franc CFA, sans décimales). Fuseau Africa/Niamey.

STACK IMPOSÉE :
- Vue 3 avec <script setup>, Composition API
- Tailwind CSS 4 en configuration CSS-first (@theme dans le CSS, pas de tailwind.config.js)
- AUCUNE dépendance externe : pas de bibliothèque de composants, pas de bibliothèque
  d'icônes, pas de gestionnaire d'état, pas de bibliothèque de dates
- Composant autonome : pas de vue-router, pas d'appel réseau réel (émets des événements)

CONTRAINTE DOMINANTE — lis ceci avant tout le reste :
Cet écran est utilisé par une graphiste à 17h35, en fin de journée, sur un téléphone Android
d'entrée de gamme, en 3G dégradée (400 kbit/s, 400 ms de latence), parfois en plein soleil.
Elle doit remplir le formulaire en MOINS DE 3 MINUTES. Si l'écran est plus lent, plus lourd,
ou perd sa saisie une seule fois, elle cessera de l'utiliser. Chaque décision de conception
doit servir ce budget de 3 minutes. La sobriété prime sur l'esthétique, toujours.

# OBJECTIF

Crée le composant `RapportQuotidien.vue` : le formulaire de rapport quotidien à six champs,
avec sauvegarde automatique de brouillon, gestion hors ligne, et téléversement de preuve en
arrière-plan.

# INSTRUCTIONS DÉTAILLÉES

## 1. Structure de l'écran (mobile d'abord, largeur de référence 320 px)

Dans cet ordre vertical exact :

1. En-tête fixe, hauteur 56 px : bouton retour « ‹ Accueil » à gauche, cloche de
   notifications avec compteur à droite.
2. Titre H1 : « Mon rapport — jeudi 18 juillet » (date longue en français, dynamique).
3. Sous-titre : « ⏱ À envoyer avant 17 h 45 ». Encre ambre #8A5200 s'il reste moins de
   60 minutes, encre grise #54534F sinon.
4. Bandeau « Brouillon restauré (17 h 12) » — affiché seulement si un brouillon existe,
   masquable, avec une action secondaire « Repartir d'un formulaire vide ».
5. Les six champs (détaillés au § 2).
6. Témoin de sauvegarde : « ✓ Enregistré à 17 h 31 », 13 px, gris #6B6A66, discret mais
   PERMANENT une fois la première sauvegarde faite. Il ne clignote pas, ne s'anime pas.
7. Bouton principal pleine largeur, 48 px : « Envoyer mon rapport ».
8. Lien discret sous le bouton : « Je n'ai pas de tâche aujourd'hui ».

## 2. Les six champs — dans cet ordre exact

Les six sont OBLIGATOIRES. Mais deux d'entre eux sont traités différemment, et c'est le
point le plus important de tout ce prompt.

CHAMP 1 — « Ce que je devais faire »
- Zone de texte, hauteur minimale 66 px.
- PRÉ-REMPLI depuis les tâches du jour, et VISIBLEMENT MODIFIABLE. Il doit avoir
  exactement l'apparence d'un champ ordinaire — un champ pré-rempli qui a l'air verrouillé
  produit des rapports faux, ce qui est pire qu'aucun rapport.
- Mention 12 px grise sous le champ : « pré-rempli depuis vos tâches du jour ».

CHAMP 2 — « Ce que j'ai produit » ✱
- Zone de texte, hauteur minimale 110 px. C'est le champ principal, le plus coûteux en
  temps (45 s au budget) : donne-lui la place.

CHAMP 3 — « Ma preuve » ✱
- PREMIER PLAN, jamais replié en bas, jamais présenté comme optionnel.
- Deux boutons côte à côte, 44 px de haut : « 📷 Photo » et « 🔗 Lien ».
- « Photo » ouvre l'appareil photo (input file avec capture).
- « Lien » déplie un champ URL.
- Après sélection : vignette avec nom de fichier et barre de progression.
- L'ENVOI DÉMARRE EN ARRIÈRE-PLAN DÈS LA SÉLECTION, pendant que l'utilisatrice remplit
  les champs 4 à 6. C'est ce qui rend l'envoi final quasi instantané en 3G et protège les
  15 dernières secondes du budget.

CHAMP 4 — « J'ai rencontré un blocage »
- Groupe de deux BOUTONS RADIO : « Non » (sélectionné par défaut) et « Oui ».
- Utilise des radios, PAS un interrupteur à bascule : l'état « Non » doit être
  explicitement lisible, et un radio s'annonce correctement au lecteur d'écran.
- Sur « Oui » seulement : une zone de texte se déplie SOUS le groupe (150 ms, ease-out),
  le focus s'y déplace, et un lien secondaire apparaît : « Créer un blocage et notifier
  quelqu'un ».

CHAMP 5 — « Ma prochaine action » ✱
- Zone de texte, hauteur minimale 66 px.

CHAMP 6 — « J'ai besoin d'aide »
- Identique au champ 4 : radios « Non » (défaut) / « Oui », dépliage conditionnel.

POURQUOI LES CHAMPS 4 ET 6 SONT DES RADIOS — ne change pas ce choix :
Les six champs sont obligatoires, mais « blocage » et « aide demandée » ont une réponse
négative la plupart des jours. En zones de texte vides, ils coûteraient 90 secondes et
casseraient le budget de 3 minutes. En radios à « Non » par défaut, ils coûtent 10 secondes
au total. L'obligation est satisfaite — une réponse explicite est enregistrée dans les deux
cas — sans coût de saisie.

## 3. Sauvegarde automatique du brouillon

- Sauvegarde dans localStorage AU PLUS TARD 10 SECONDES après la dernière frappe
  (anti-rebond), ET immédiatement à la perte de focus de chaque champ.
- Met à jour le témoin « ✓ Enregistré à HH h MM » à chaque sauvegarde.
- Au montage, si un brouillon existe : restaure TOUS les champs et affiche le bandeau de
  restauration avec l'heure de la dernière sauvegarde.
- Annonce chaque sauvegarde dans une région aria-live="polite".

## 4. Comportement hors ligne

- Écoute les événements `online` / `offline`.
- Hors ligne : bandeau ambre sous l'en-tête, « ⚠ Hors connexion. Votre saisie est
  conservée. », masquable.
- LE BANDEAU NE BLOQUE JAMAIS LA SAISIE. On continue d'écrire hors ligne. Ne désactive
  aucun champ.
- Au retour de connexion : le bandeau devient « ✓ Connexion rétablie » en vert #0B6B34
  pendant 3 secondes, puis disparaît.
- Le bouton d'envoi reste cliquable hors ligne. Au clic, il explique : « L'envoi n'a pas
  abouti — pas de connexion. Votre rapport est conservé sur cet appareil. » avec un bouton
  « Réessayer ». Griser un bouton sans explication n'apprend rien à l'utilisateur.

## 5. Validation et erreurs

- Le bouton d'envoi est TOUJOURS ACTIF. Ne le désactive jamais en attendant la validation :
  un bouton désactivé ne dit pas ce qui manque.
- À la soumission, si un champ obligatoire est vide : le focus saute au PREMIER champ
  fautif, et son message d'erreur s'affiche SOUS le champ (jamais uniquement en tête de
  formulaire — sur un formulaire long en téléphone, un message en tête est hors écran au
  moment où on en a besoin).
- Messages d'erreur en français simple, disant ce qui s'est passé et l'action attendue.
  Exemple : « Indiquez ce que vous avez produit aujourd'hui. »
- Erreurs liées au champ par aria-describedby et aria-invalid, annoncées en role="alert".
- Pendant l'envoi : le bouton passe à « Envoi en cours… », GARDE SA LARGEUR, devient non
  cliquable. Aucune superposition modale plein écran.

# CONTRAINTES VISUELLES

## Couleurs — valeurs exactes, contrastes déjà mesurés et validés WCAG AA

  Primaire (bouton, liens, focus)  #1B5FAF   6,36:1 sur blanc
  Encre principale                 #1A1A19   17,42:1
  Encre secondaire (libellés)      #54534F   7,70:1
  Encre discrète (mentions)        #6B6A66   5,41:1
  Succès / validé                  #0B6B34   6,63:1
  Attention / en attente           #8A5200   6,39:1
  Erreur / en retard               #B3261E   6,54:1
  Neutre / bloqué                  #4A4A48   8,88:1
  Surface (cartes)                 #FFFFFF
  Fond de page                     #F7F7F5
  Séparateurs                      #E3E2DE

  Fonds teintés : vert #E6F4EA · ambre #FDF1DC · rouge #FBE9E7 · gris #EFEFED

Les contrastes visent 6:1 et plus, au-delà des 4,5:1 exigés, parce que la cible est un
téléphone consulté en extérieur en plein soleil. Ne les affaiblis pas.

## Typographie

Police système EXCLUSIVEMENT — aucune police téléchargée, aucun Google Fonts :
  system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif
Sur Chrome Android (cible prioritaire) c'est Roboto, déjà sur l'appareil : 0 octet, 0 ms,
aucun décalage de mise en page.

  H1 (titre d'écran)     20 px / 600 / 1,3
  Corps                  16 px / 400 / 1,55
  Libellé de champ       15 px / 600 / 1,4
  Métadonnée             13 px / 400 / 1,45

LE CORPS NE DESCEND JAMAIS SOUS 16 px : en dessous, Safari iOS zoome automatiquement au
focus d'un champ et casse la mise en page.

## Espacement

Échelle de 4 px : 4 · 8 · 12 · 16 · 24 · 32 · 48.
Marge de page 16 px. Entre champs de formulaire : 20 px (assez pour éviter le tap erroné
au pouce). Intérieur de carte 16 px.

## Icônes

Environ 6 glyphes nécessaires (retour, cloche, appareil photo, lien, coche, alerte).
Dessine-les en SVG INLINE : trait 2 px, boîte 24×24, stroke="currentColor" pour qu'ils
héritent du contraste validé. AUCUNE bibliothèque d'icônes, AUCUNE police d'icônes.

# ACCESSIBILITÉ — WCAG 2.1 niveau AA, non négociable

- Cibles tactiles ≥ 44×44 px, 48 px pour le bouton principal, ≥ 8 px entre deux cibles.
- Indicateur de focus TOUJOURS VISIBLE, JAMAIS SUPPRIMÉ : contour 2 px #1B5FAF avec 2 px
  de décalage, sur tout élément interactif. `outline: none` sans remplacement équivalent
  est un défaut bloquant.
- LIBELLÉ VISIBLE EN PERMANENCE au-dessus de chaque champ, associé par <label for>.
  JAMAIS de libellé flottant ni de placeholder tenant lieu d'étiquette : il disparaît à la
  saisie, casse les lecteurs d'écran, et ne survit pas à une interruption.
- Champs obligatoires marqués ✱ visuellement ET « obligatoire » pour le lecteur d'écran.
- HTML sémantique natif d'abord (<button>, <form>, <fieldset>, <legend>, <label>), ARIA
  seulement en complément.
- Les groupes de radios des champs 4 et 6 dans un <fieldset> avec <legend>.
- Ordre de tabulation logique = ordre visuel. Aucun piège au clavier.
- lang="fr" sur la racine du composant.
- Aucune information portée par la couleur seule : toute couleur d'état est doublée d'un
  glyphe ET d'un libellé en français.
- Zoom à 200 % sans perte de contenu ni défilement horizontal.
- Aucune limite de temps, aucune déconnexion automatique pendant la saisie.

# RESPONSIVE

- Conçois à 320 px d'abord. AUCUN défilement horizontal à cette largeur.
- Le formulaire reste sur UNE SEULE COLONNE à toutes les largeurs. Deux colonnes
  multiplient les erreurs de saisie et cassent l'ordre de tabulation ; le gain de place n'a
  aucune valeur ici.
- ≥ 768 px : marge de page 24 px, contenu centré, largeur maximale 720 px.
- ≥ 1024 px : marge 32 px. Le formulaire ne s'élargit pas au-delà de 720 px.

# ANIMATION

Liste EXHAUSTIVE — n'ajoute rien d'autre :
- Dépliage d'un champ conditionnel (« Oui » aux champs 4 et 6) : hauteur, 150 ms, ease-out.
- Apparition d'un bandeau : fondu + glissement de 8 px, 150 ms.
- Bouton pressé : réduction à 0.98, 80 ms.

Rien au-dessus de 200 ms. Respecte prefers-reduced-motion : toute animation devient un
changement d'état instantané, sans perte de fonction.

# STRUCTURE DE DONNÉES

État du formulaire :

  {
    date: '2026-07-18',              // ISO
    tachePrevue: string,             // champ 1, pré-rempli
    resultatObtenu: string,          // champ 2, obligatoire
    preuve: {
      type: 'fichier' | 'lien' | null,
      fichier: File | null,
      url: string | null,
      etatEnvoi: 'inactif' | 'en_cours' | 'termine' | 'echec',
      progression: number            // 0-100
    },
    aBlocage: boolean,               // champ 4, défaut false
    blocageTexte: string,
    prochaineAction: string,         // champ 5, obligatoire
    aBesoinAide: boolean,            // champ 6, défaut false
    aideTexte: string,
    etat: 'brouillon' | 'envoye' | 'valide' | 'retourne' | 'en_retard'
  }

Props attendues :
  tachesDuJour: string[]             // pour le pré-remplissage du champ 1
  heureLimite: string                // '17:45', paramétrable
  brouillonExistant: object | null

Événements émis :
  @envoyer(rapport)                  // soumission
  @creer-blocage(contexte)           // depuis le champ 4
  @demander-tache()                  // lien du bas
  @televerser-preuve(fichier)        // envoi en arrière-plan

# CE QU'IL NE FAUT PAS FAIRE

- PAS de bibliothèque de composants (Vuetify, PrimeVue, shadcn, Element…)
- PAS de bibliothèque d'icônes (Lucide, Heroicons, Font Awesome…)
- PAS de CDN, PAS de Google Fonts, AUCUNE ressource externe chargée à l'exécution
- PAS de placeholder en guise d'étiquette, PAS de libellé flottant
- PAS de bouton d'envoi désactivé en attendant la validation
- PAS de compteur de caractères, PAS de limite de longueur affichée (source d'anxiété sur
  un champ qu'on veut voir rempli)
- PAS de spinner plein écran, PAS de superposition modale bloquante
- PAS de squelette animé à balayage (coût GPU continu) — les squelettes sont STATIQUES
- PAS de transition entre pages, PAS d'animation d'entrée sur les listes
- PAS d'interrupteur à bascule pour les champs 4 et 6 — des radios
- PAS de champ désactivé quand la connexion tombe
- PAS d'image décorative, PAS d'illustration, PAS de dégradé, PAS d'ombre portée marquée
- PAS le mot « Supprimer » nulle part (dans PTR Staff, rien ne se supprime jamais)
- PAS de vocabulaire de surveillance : jamais « pointage », « justifier son temps »,
  « défaillant », « performance », « score ». Le vocabulaire est celui de la contribution.

# PORTÉE

Génère UNIQUEMENT :
1. `RapportQuotidien.vue` — le composant complet
2. Le bloc CSS `@theme` Tailwind 4 déclarant les couleurs et l'échelle d'espacement

Ne génère PAS : routeur, store, appels API réels, couche d'authentification, autres écrans,
tests. Le composant doit être autonome et montable tel quel.
```

---

## Vérification après génération

Passe le résultat à cette grille avant de l'intégrer :

- [ ] Chronométrer une saisie complète sur téléphone réel en 3G — **doit tenir sous 3 min** (NFR4)
- [ ] Couper le réseau en cours de saisie : la saisie continue, rien n'est perdu (NFR5, NFR6)
- [ ] Fermer l'onglet, rouvrir : restauration intégrale avec bandeau
- [ ] Parcours complet au clavier, sans souris
- [ ] TalkBack sur Chrome Android : les six champs sont annoncés avec leur libellé et leur
      caractère obligatoire
- [ ] Affichage en niveaux de gris : aucune information perdue
- [ ] Zoom 200 % : aucun défilement horizontal
- [ ] Poids de la page < 300 Ko au premier chargement (NFR2)
- [ ] Aucune requête sortante vers un domaine tiers (NFR3)

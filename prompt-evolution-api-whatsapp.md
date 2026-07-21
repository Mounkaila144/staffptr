# Mission — Intégrer WhatsApp (Evolution API) dans le PRD et l'architecture de PTR Staff

> Document de passation destiné à une IA travaillant sur le dépôt `staffptr`.
> Il ne contient aucun secret : clé, adresse IP et nom d'instance restent dans le `.env`.

Tu interviens sur `staffptr`, une application Laravel 13 / PHP 8.3 / Inertia 2 / Vue 3,
pilotée par la méthode BMAD. Sa documentation produit et technique fait autorité sur le
code. Ta mission est **documentaire** : amender le PRD, l'architecture et le plan
d'exécution pour intégrer un canal WhatsApp. **Tu n'écris aucun code applicatif.**

## Contexte de la décision

Le MVP interdit explicitement tout canal externe. Le propriétaire du produit a décidé
d'intégrer WhatsApp via **Evolution API 2.3.7** (intégration `WHATSAPP-BAILEYS`) pour
envoyer des messages de confirmation et des notifications aux numéros des utilisateurs.

C'est une **modification du périmètre du MVP**, pas un détail d'implémentation. Le PRD
prévoyait ce canal en phase 2 « après choix du fournisseur » — ce choix vient d'être fait.

## Règle absolue de fonctionnement du dépôt — à lire avant toute édition

Les fichiers `docs/prd/epic-*.md` portent en tête :

    <!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
         Toute évolution se fait dans le document source puis régénération. -->

⛔ **Ne modifie jamais ces fichiers directement.** Modifie `docs/epics-stories.md`, puis
régénère. De même, `docs/architecture.md` est la source de vérité des fichiers
`docs/architecture/*.md` (voir l'en-tête de `docs/architecture/source-tree.md`).

Ordre de travail imposé : `docs/prd.md`, `docs/epics-stories.md` et
`docs/architecture.md` d'abord ; les fichiers shardés ensuite, par régénération, et
tu vérifies leur cohérence après coup.

## Carte d'impact — ce qui interdit WhatsApp aujourd'hui

Tu dois traiter **chacun** de ces points. Ne supprime rien sans écrire ce qui le remplace.

| Emplacement | Contenu actuel | Traitement attendu |
|---|---|---|
| `docs/prd.md` — **FR34** | « Aucune notification n'est envoyée par SMS, WhatsApp ou courriel en MVP. » | Réécrire. FR34 devient la règle du canal WhatsApp : ce qui est envoyé, ce qui ne l'est pas, et ce qui reste interdit (SMS, courriel). |
| `docs/prd.md` — section périmètre | « **Intégrations externes en MVP : aucune.** Pas de banque, pas de Mobile Money, pas de SMS, pas de WhatsApp. » | Corriger : WhatsApp devient la **seule** intégration externe du MVP. Les autres exclusions restent. |
| `docs/prd.md` — phase 2, points 7 et 8 | « Notifications SMS / WhatsApp, après choix du fournisseur » ; « réinitialisation par OTP SMS » | Le point 7 sort de la phase 2 pour WhatsApp seulement ; SMS y reste. Décide et écris le sort du point 8. |
| `docs/architecture.md` § 9.4 + ligne A-07 du sommaire | « canal `database` **uniquement** en MVP (FR34) » | Réécrire : deux canaux, `database` et `whatsapp`. Conserver la phrase sur l'absence de refonte, elle se vérifie. |
| `docs/prd.md` — tableau A-07 | « Doit permettre l'ajout ultérieur de SMS/WhatsApp sans refonte. » | Acter que l'ajout a lieu, et que A-07 est honorée : le canal s'ajoute sans refonte. |
| `docs/epics-stories.md` → **story 3.7**, AC 5 et 6 | « ⛔ Aucun envoi SMS, WhatsApp ou courriel n'est déclenché ; **un test vérifie qu'aucun canal externe n'est appelé** (FR34). » | ⛔ **Ne supprime pas ce test.** Transforme-le : il doit désormais vérifier qu'aucun canal **non autorisé** n'est appelé, et que le canal WhatsApp n'est appelé **que** pour les notifications qui le déclarent. Un test supprimé efface la trace de la décision. |
| `docs/epics-stories.md` → **story 3.7**, AC 2 | « canal `database` seul (A-07) » | Mettre à jour. |
| `docs/epics-stories.md` → **story 3.8**, critère de fin | « aucun canal externe n'est appelé » | Idem. |
| `docs/epics-stories.md` → **story 9.6**, AC 4 et critère de fin 6 | « ⛔ Aucun canal externe n'est appelé ; testé à nouveau en fin de MVP » ; « idempotentes, sans canal externe » | Idem, en conservant l'exigence d'**idempotence**, qui devient plus critique avec un canal externe. |
| `docs/epics-stories.md` → **story 11.x** (surveillance) | « alerte SMS — le canal externe est ici légitime : il concerne l'exploitation » | Vérifier que la distinction exploitation / applicatif reste lisible après le changement. |
| `docs/epics-stories.md` → **story 11.x** (préproduction) | « la préproduction n'envoie aucune notification externe » | ⛔ **À renforcer, pas à assouplir.** Voir la note ci-dessous. |
| `docs/prd/phase-2.md` | « notifications SMS / WhatsApp » | Retirer WhatsApp, garder SMS. |

> **Note sur la préproduction.** Elle est alimentée par une **restauration de production
> anonymisée** (`ptr:anonymize`, architecture § 24.2). Si l'anonymisation des numéros
> échoue ou est incomplète, un test en préproduction enverrait de **vrais messages
> WhatsApp à de vraies personnes**. Ce risque était théorique tant qu'aucun canal externe
> n'existait. La garde doit devenir explicite et testée.

## Contraintes techniques que les amendements doivent porter

Ces règles viennent du dépôt lui-même. Les amendements doivent les respecter et les
rendre explicites, sinon l'implémentation les enfreindra.

1. **Envoi en file, jamais dans le cycle de la requête.** `docs/architecture.md` § 9.4 :
   « Les notifications sont mises en file, jamais envoyées dans le cycle de la requête :
   une notification lente ne doit pas ralentir une approbation de dépense. » L'appel HTTP
   à Evolution API est synchrone par nature — l'amendement doit imposer la file.

2. **Emplacement du code.** La structure est modulaire par sous-dossiers de namespace
   (`docs/architecture/source-tree.md`). Un client Evolution API appartient à
   `app/Services/Platform/`, jamais à `app/Services/` à la racine.

3. **Format de numéro.** Le dépôt stocke la forme canonique `+227XXXXXXXX`
   (architecture § 7.1, `App\Support\PhoneNumber`). Evolution API attend `227XXXXXXXX`
   sans `+`. La conversion appartient à `PhoneNumber`, pas à un `preg_replace` recopié
   dans le client HTTP.

4. **Idempotence.** Les tâches planifiées doivent être idempotentes : rejouer ne duplique
   ni notification ni écriture. Avec un canal externe, une double exécution envoie deux
   messages WhatsApp réels à un utilisateur réel.

5. **Comportement en panne.** L'instance Evolution peut être déconnectée (`state: close`),
   injoignable, ou renvoyer une erreur. L'architecture doit dire ce qui se passe alors :
   la notification `database` doit-elle partir quand même ? Y a-t-il des tentatives
   ultérieures ? Combien ? Une notification métier ne doit jamais être perdue parce qu'un
   service tiers était indisponible.

6. **Aucune ressource tierce côté navigateur** (NFR3) et CSP stricte sans `unsafe-inline`
   (§ 9.5). L'appel part **exclusivement** du backend. Aucun élément du frontend ne doit
   connaître l'existence de l'API ni sa clé.

7. **Secret.** La clé Evolution API est une clé **administrateur globale** : elle permet
   de créer et supprimer des instances. Les amendements doivent exiger qu'elle ne figure
   que dans `.env`, jamais dans Git, les journaux, une réponse destinée au frontend, ni
   une trace d'erreur. Vérifier la cohérence avec `docs/ops/secrets-rotation.md`, et
   documenter la rotation. Si Evolution API permet une clé d'instance à portée réduite,
   l'architecture doit la recommander plutôt que la clé d'administration.

8. **HTTP en clair.** L'accès actuel est en HTTP simple vers une adresse IP, sans TLS,
   alors que NFR11 impose HTTPS et que § 9.5 durcit les en-têtes. La clé et le contenu
   des messages circulent en clair. ⛔ **C'est un écart bloquant pour la production, pas
   une réserve de confort.** L'architecture doit poser la mise derrière un domaine HTTPS
   et la fermeture du port public comme **prérequis de mise en service**, avec une
   décision datée si l'exploitation démarre malgré tout.

## Configuration à documenter (sans valeurs secrètes)

Documente la forme, pas les valeurs. ⛔ **N'écris aucune clé, aucun jeton, aucune adresse
IP réelle dans un fichier versionné.** Utilise des espaces réservés.

    EVOLUTION_API_URL=
    EVOLUTION_API_KEY=
    EVOLUTION_INSTANCE=

Dans `config/services.php`, sous la clé `evolution`, lues par `config()` uniquement —
`env()` n'est autorisé que dans `config/` (règle SOC-11 du socle transverse).

Points d'API utilisés : `GET /instance/connectionState/{instance}` pour l'état,
`POST /message/sendText/{instance}` pour l'envoi. Authentification par en-tête HTTP
`apikey` — **jamais** `Authorization: Bearer`. Un envoi réussi renvoie `201` ; `401`
signale une clé absente ou incorrecte.

## Deux questions à instruire, pas à trancher seul

Présente-les explicitement dans ton rendu, avec ta recommandation et son raisonnement.

### A. La réinitialisation de mot de passe (DEC-10 / Q9)

Cette décision est ouverte dans le PRD et l'architecture § 7.4 : que vérifie-t-on avant
de réinitialiser le mot de passe de quelqu'un ? L'architecture note que sans réponse,
« le circuit le plus simple pour prendre un compte reste l'appel téléphonique ». WhatsApp
permet d'y répondre : la possession du numéro enregistré devient la preuve d'identité.

Mais distingue deux usages, ils n'ont pas le même risque :

- envoyer une **confirmation ou un code de vérification** au numéro enregistré ;
- envoyer le **mot de passe temporaire lui-même**.

Le second fait transiter un identifiant de 32 caractères en HTTP clair vers un serveur
tiers. Le modèle actuel — affiché une seule fois à l'auteur, transmis hors application —
est plus sûr. Recommande, argumente, ne décide pas seul.

Le fichier `docs/stories/2.8.story.md` porte cette question comme tâche bloquante
préalable. Mets-le à jour en conséquence.

### B. Le consentement et la portée

Envoyer des messages WhatsApp à des employés touche à des données personnelles et à un
canal privé. Détermine ce que le PRD doit dire : quelles notifications passent par
WhatsApp et lesquelles restent internes, si l'utilisateur peut refuser le canal, et ce
qu'il advient d'un numéro sans compte WhatsApp actif.

## Ce que tu ne dois pas faire

- ⛔ Ne pas écrire de code applicatif, de migration ni de test. La mission est documentaire.
- ⛔ Ne pas éditer `docs/prd/*.md` ni `docs/architecture/*.md` à la main — passer par les
  sources et régénérer.
- ⛔ Ne pas supprimer les critères d'acceptation qui testent l'absence de canal externe.
  Les transformer, en conservant leur intention.
- ⛔ Ne pas écrire de secret dans un fichier versionné.
- ⛔ Ne pas modifier les stories déjà terminées (`docs/stories/1.*`, `2.1` à `2.7`) —
  sauf pour y ajouter une note de renvoi si un fait les concerne rétroactivement.
- ⛔ Ne pas présenter comme tranché ce qui relève des deux questions ci-dessus.

## Rendu attendu

1. Les modifications appliquées à `docs/prd.md`, `docs/epics-stories.md`,
   `docs/architecture.md` et `docs/prd/phase-2.md`, avec régénération des fichiers shardés.
2. Une mise à jour de `docs/stories/2.8.story.md` reflétant l'état de DEC-10.
3. Une entrée dans le registre des décisions d'architecture (`docs/architecture.md` § 2)
   actant le choix du fournisseur, avec **ce que la décision coûte** — écrit pour ne pas
   être redécouvert plus tard, sur le modèle de la décision **DEC-05** qui figure déjà
   dans ce document et qui énumère explicitement ses conséquences négatives.
4. Une mise à jour des journaux de version des documents touchés.
5. Un **résumé des changements** : ce qui a été modifié et pourquoi, les deux questions
   ouvertes avec ta recommandation, et la liste des prérequis de mise en service
   (reconnexion de l'instance, HTTPS, rotation de la clé).

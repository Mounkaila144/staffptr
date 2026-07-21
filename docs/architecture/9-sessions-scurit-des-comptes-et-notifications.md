# 9. Sessions, sécurité des comptes et notifications

## 9.1 Choix du mécanisme de session

**Sessions de navigateur Laravel, pas de jetons API.** Inertia communique en même origine ; Sanctum
en mode jeton ajouterait une surface d'attaque et une gestion de révocation sans rien apporter.

## 9.2 Configuration

| Paramètre | Valeur | Motif |
|---|---|---|
| `SESSION_DRIVER` | `database` | Voir § 9.3 |
| `SESSION_SECURE_COOKIE` | `true` | NFR11 |
| `SESSION_HTTP_ONLY` | `true` | Vol de session par XSS |
| `SESSION_SAME_SITE` | `lax` | CSRF |
| `SESSION_ENCRYPT` | `true` | Contenu de session chiffré au repos |
| `SESSION_LIFETIME` | 480 min, expiration à l'inactivité | Journée de travail, sans reconnexion permanente |

CSRF actif sur toutes les routes mutantes (NFR13) ; Inertia transmet le jeton via `XSRF-TOKEN`.

## 9.3 Invalidation immédiate des sessions — FR8 / PERM-08

**Exigence :** le passage à `suspendu` ou tout changement de mot de passe invalide immédiatement
toutes les sessions du compte, **sur tous les appareils**.

C'est ce qui commande le pilote de session. Redis ne permet pas d'énumérer les sessions d'un
utilisateur donné sans index secondaire à maintenir à la main. La table `sessions` de Laravel porte
une colonne `user_id` indexée : la révocation devient une suppression ciblée, exacte et immédiate.

```php
// SessionRevocationService — appelé dans la transaction de suspension / changement de mot de passe
DB::table('sessions')->where('user_id', $user->id)->delete();
```

Redis reste utilisé pour le cache et les files (DEC-04) ; seules les sessions vont en base. À 100
utilisateurs, le coût est négligeable et la garantie est exacte. Le middleware
`AuthenticateSession` est activé en complément.

## 9.4 Notifications — A-07

Système de notifications natif de Laravel, **deux canaux en MVP** : `database`, toujours actif, et
**WhatsApp** (Evolution API, DEC-15), actif pour **toutes** les notifications de FR31 (FR34,
DEC-16 — voir § 9.4bis). Le canal `database` reste la source de vérité : une notification y existe
toujours, que l'envoi WhatsApp réussisse ou non — ce qui satisfait A-07 sans refonte, l'ajout de SMS
en phase 2 suivant le même mécanisme.

- Centre de notifications avec compteur de non-lues, exposé en prop Inertia partagée (§ 10.3).
- Chaque notification porte une URL directe vers l'objet (FR32).
- Les rappels J+1 / J+2 sur dépense en attente (FR33) et le rappel de rapport quotidien (FR31)
  sont des tâches planifiées, pas des déclencheurs à l'écriture.
- **Les notifications sont mises en file**, jamais envoyées dans le cycle de la requête : une
  notification lente ne doit pas ralentir une approbation de dépense. Ceci vaut *a fortiori* pour
  WhatsApp, dont l'appel HTTP sortant est synchrone par nature côté fournisseur.

## 9.4bis Canal WhatsApp — Evolution API (DEC-15)

> **DEC-15 — tranché le 20/07/2026 : Evolution API 2.3.7** (intégration `WHATSAPP-BAILEYS`) comme
> fournisseur du canal WhatsApp de FR34.
>
> **Ce que cette décision coûte, écrit pour ne pas être redécouvert plus tard :**
>
> 1. **Evolution API n'est pas l'API officielle WhatsApp Business.** L'intégration Baileys émule un
>    client WhatsApp Web ; le numéro qui l'utilise s'expose au même risque de restriction ou de
>    bannissement par Meta qu'un usage non conforme aux conditions d'utilisation de WhatsApp. Aucune
>    volumétrie ni aucun contenu à risque ne doit être envoyée sans en avoir informé la direction.
> 2. **La clé configurée est une clé administrateur globale** : au-delà de l'envoi de messages, elle
>    permet de créer et de supprimer des instances Evolution. Une fuite compromet plus que le canal
>    de notification — voir la confidentialité du secret ci-dessous.
> 3. **L'accès actuel est en HTTP simple vers une adresse IP, sans TLS.** ⛔ C'est un écart bloquant
>    pour la mise en service, pas une réserve de confort — voir « Prérequis de mise en service ».
> 4. **Aucune garantie de livraison tierce.** Evolution API renvoie un succès HTTP (`201`) à l'appel,
>    pas une confirmation de remise sur l'appareil du destinataire ; l'application ne peut pas savoir
>    si le message a été lu, ni même reçu.
> 5. **Dépendance à un service auto-hébergé unique**, sans redondance connue à ce jour. Une panne de
>    l'instance Evolution prive le canal WhatsApp sans affecter le canal `database` (§ 9.4bis
>    « Comportement en panne »).

**Configuration** (`config/services.php`, clé `evolution`, lue par `config()` uniquement — SOC-11) :

```
EVOLUTION_API_URL=
EVOLUTION_API_KEY=
EVOLUTION_INSTANCE=
```

Aucune valeur, adresse IP ou clé réelle ne figure dans un fichier versionné.

**Points d'API utilisés :**

| Méthode | Point | Usage |
|---|---|---|
| `GET` | `/instance/connectionState/{instance}` | État de l'instance (`open`, `close`, `connecting`) |
| `POST` | `/message/sendText/{instance}` | Envoi d'un message texte |

Authentification par en-tête HTTP `apikey` — **jamais** `Authorization: Bearer`. Un envoi réussi
renvoie `201` ; `401` signale une clé absente ou incorrecte. Si Evolution API expose une clé à
portée d'instance plutôt que la clé d'administration globale, elle doit être **préférée** pour
l'envoi courant ; la clé globale reste réservée aux opérations d'administration d'instance.

**Format du numéro.** Le dépôt stocke la forme canonique `+227XXXXXXXX` (§ 7.1). Evolution API
attend `227XXXXXXXX`, sans `+`. La conversion est une responsabilité de `App\Support\PhoneNumber`
(nouvelle méthode dédiée), **jamais** un `preg_replace` recopié dans le client HTTP — le même motif
que la normalisation à l'écriture.

**Emplacement du code.** Le client Evolution API et le canal de notification associé appartiennent
à `app/Services/Platform/` (structure modulaire par sous-dossiers, § 5) — jamais à `app/Services/`
racine.

**Comportement en panne.** Le canal `database` est écrit en premier et ne dépend d'aucun appel
réseau : une notification métier existe donc **toujours**, indépendamment du sort de WhatsApp.
L'envoi WhatsApp est un travail de file distinct (§ 9.4), avec la politique de nouvelle tentative
standard de Laravel (`tries`, `backoff`) plutôt qu'une boucle maison ; un envoi qui échoue
définitivement est un **travail échoué visible**, supervisé comme tout autre par `queue:monitor`
(story 11.3) — jamais une notification métier silencieusement perdue.

**Idempotence.** Une tâche planifiée qui émet une notification WhatsApp doit être idempotente comme
toute tâche planifiée (§ 20, story 11.4) : rejouer une tâche ne duplique ni l'écriture `database` ni
l'envoi WhatsApp. Avec un canal externe, une double exécution envoie un **second message réel** à un
utilisateur réel — la contrainte n'est plus seulement une propreté de données.

**Confidentialité et rotation de la clé.** La clé Evolution API suit les mêmes règles que les autres
secrets du dépôt (§ 25.4, `docs/ops/secrets-rotation.md`) : `.env` uniquement, jamais journalisée,
jamais renvoyée au frontend, jamais dans une trace d'erreur. Le filtre de rédaction des journaux
(§ 22, `RedactSensitiveDataProcessor`) doit couvrir le nom de la clé de configuration au même titre
que les autres secrets applicatifs. Sa rotation suit le modèle déjà écrit pour `DEPLOY_SSH_PRIVATE_KEY` :
nouvelle clé générée côté Evolution, bascule, vérification d'un envoi de test, puis révocation de
l'ancienne.

**Aucune ressource tierce côté navigateur (NFR3), CSP inchangée (§ 9.5).** L'appel part
exclusivement du backend, en tâche de file ; aucun élément du frontend ne connaît l'existence de
l'API Evolution ni sa clé.

⛔ **Prérequis de mise en service — bloquant.** L'accès actuel en HTTP simple vers une adresse IP,
sans TLS, contredit NFR11 et le durcissement HTTP du § 9.5 : la clé et le contenu des messages
circulent en clair sur le réseau. Avant toute mise en service utilisant ce canal :

1. l'instance Evolution API doit être servie derrière un **domaine HTTPS** (certificat valide) ;
2. le port d'accès direct par adresse IP doit être **fermé au public**.

Si l'exploitation démarre malgré tout sans ces deux conditions, ce doit être une **décision datée et
explicite** de la direction, consignée au même titre que DEC-14 (UFW), pas un oubli qui se découvre
à l'audit.

**Les deux questions posées à la direction sont tranchées le 20/07/2026 :**

- **DEC-10 (Q9), réinitialisation de mot de passe.** Code de confirmation envoyé sur le WhatsApp
  enregistré de la cible, saisi par l'auteur avant que le mot de passe temporaire ne soit généré —
  détail au § 7.4. Le mot de passe temporaire lui-même ne transite jamais par WhatsApp.
- **DEC-16 (Q18), portée et consentement.** **Toutes** les notifications éligibles de FR31 sont
  relayées sur WhatsApp, sans distinction par type ni mécanisme de refus par l'utilisateur : chaque
  compte est garanti porteur d'un numéro WhatsApp actif (postulat opérationnel de la direction, non
  vérifiable par l'application). Aucun paramétrage d'éligibilité par type n'est donc nécessaire en
  MVP — une simplification par rapport à l'hypothèse initiale de ce document.

## 9.5 Durcissement HTTP

En-têtes posés par middleware, vérifiés par test :

| En-tête | Valeur |
|---|---|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` |
| `Content-Security-Policy` | `default-src 'self'; img-src 'self' data:; object-src 'none'; frame-ancestors 'none'; base-uri 'self'` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `same-origin` |
| `Permissions-Policy` | `geolocation=(), camera=(), microphone=()` |

La CSP est stricte et **tenable sans `unsafe-inline`** parce que NFR3 interdit déjà toute ressource
tierce : tout est servi par l'application. Vite injecte les scripts avec un nonce en production.

---

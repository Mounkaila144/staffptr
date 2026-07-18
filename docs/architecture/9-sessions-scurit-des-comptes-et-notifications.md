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

Système de notifications natif de Laravel, **canal `database` uniquement** en MVP (FR34). Le canal
est le seul point d'extension : ajouter SMS ou WhatsApp en phase 2 consiste à écrire un canal et à
l'ajouter au `via()` de notifications déjà écrites — **aucune refonte**, ce qui satisfait A-07.

- Centre de notifications avec compteur de non-lues, exposé en prop Inertia partagée (§ 10.3).
- Chaque notification porte une URL directe vers l'objet (FR32).
- Les rappels J+1 / J+2 sur dépense en attente (FR33) et le rappel de rapport quotidien (FR31)
  sont des tâches planifiées, pas des déclencheurs à l'écriture.
- **Les notifications sont mises en file**, jamais envoyées dans le cycle de la requête : une
  notification lente ne doit pas ralentir une approbation de dépense.

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

# 7. Authentification par téléphone et mot de passe

## 7.1 Normalisation du numéro — FR2

Le numéro est normalisé **avant enregistrement et avant comparaison**, par `App\Support\PhoneNumber` :

1. Suppression des espaces, points, tirets, parenthèses.
2. `00` initial → `+`.
3. Absence d'indicatif → préfixe `+227` par défaut.
4. Validation du format E.164, longueur nationale nigérienne contrôlée.
5. Stockage sous forme canonique unique (`+227XXXXXXXX`).

La normalisation est appliquée dans un `FormRequest::prepareForValidation()` **et** dans un mutateur
du modèle. La double application est intentionnelle : le mutateur garantit qu'aucun chemin
d'écriture — seeder, commande console, import — ne contourne la règle.

## 7.2 Connexion

- Champ d'identification `phone`, jamais `email`. Le fournisseur d'authentification Laravel est
  configuré sur ce champ ; **aucune colonne `email` n'est requise** sur `users`.
- Hachage **bcrypt, coût 12**, paramétré dans `config/hashing.php` (NFR12). Coût révisable sans
  migration : Laravel réhache à la connexion suivante lorsque le coût change.
- **Aucune inscription publique** (FR1) : les routes `register`, `password.request` et
  `password.reset` de Laravel ne sont **pas** déclarées. Leur absence est vérifiée par un test.
- Seul l'état `actif` autorise la connexion (FR7) : middleware `EnsureAccountActive` appliqué après
  authentification, message générique en français.
- Message d'échec **unique et indifférencié** quelle que soit la cause (numéro inconnu, mot de
  passe faux, compte suspendu) : ne pas révéler l'existence d'un compte.

## 7.3 Première connexion et changement imposé — FR5

Un compte créé porte `must_change_password = true`. Le middleware `EnsurePasswordChanged` est
appliqué à **tout le groupe authentifié** et redirige vers l'écran de changement tant que le
drapeau est levé — y compris sur accès par URL directe, y compris sur les requêtes Inertia.

Le mot de passe temporaire est généré aléatoirement (32 caractères), affiché **une seule fois** au
créateur, jamais stocké en clair, jamais journalisé, jamais renvoyé par une requête ultérieure.

## 7.4 Réinitialisation — FR6 / Q9

En MVP, la réinitialisation est effectuée par `direction` ou `super_admin` depuis l'écran de gestion
des comptes. Elle génère un nouveau mot de passe temporaire, lève `must_change_password`, **invalide
toutes les sessions de la cible** (FR8) et écrit une entrée d'audit portant l'auteur et la cible.

> **DEC-10 — tranché le 20/07/2026 : vérification par code de confirmation WhatsApp.** Avant de
> finaliser une réinitialisation, l'application génère un code, l'envoie sur le **numéro WhatsApp
> enregistré de la cible** (canal DEC-15) et exige sa saisie par l'auteur (`direction` ou
> `super_admin`) avant de générer le mot de passe temporaire. La personne qui a appelé pour demander
> la réinitialisation lit le code reçu sur son WhatsApp à l'auteur, qui le confirme à l'écran — la
> preuve de possession du numéro remplace l'absence de vérification d'identité automatisée.
>
> Postulat opérationnel de cette décision, à la charge de l'exploitant : **chaque compte dispose
> d'un numéro WhatsApp actif**, sans exception. L'application n'a pas de repli si ce postulat cesse
> d'être vrai pour un compte donné — voir `stories/2.8.story.md` pour le traitement de ce cas.
>
> ⛔ **Le mot de passe temporaire lui-même ne transite jamais par WhatsApp** — seul le code de
> confirmation, à usage unique et sans valeur au-delà de la preuve de possession du numéro, y est
> envoyé. Voir § 9.4bis, point 3, sur le risque de l'accès HTTP clair.
>
> ⛔ **Si Evolution API est indisponible, la réinitialisation est bloquée** jusqu'au rétablissement
> du service : aucun contournement n'existe qui n'annulerait la vérification elle-même. C'est une
> conséquence opérationnelle à surveiller (story 11.3), pas un défaut à corriger dans le code.

## 7.5 Blocage après échecs — FR10

Deux mécanismes complémentaires :

| Mécanisme | Portée | Paramètre | Rôle |
|---|---|---|---|
| `RateLimiter` Laravel | Par IP + numéro | 5 essais / minute | Absorbe le bourrage distribué (NFR13) |
| Verrou persistant en base | `users.failed_attempts`, `users.locked_until` | Paramétrable (FR25) | Répond à FR10, survit au redémarrage, auditable |

Le compteur est remis à zéro à toute connexion réussie. **Le blocage et son expiration sont tous
deux journalisés** (FR10). Conformément à RM-18, le blocage porte sur la tentative d'authentification,
jamais sur la personne : aucun compte n'est désactivé automatiquement.

## 7.6 Historique de connexion — FR9

Table `login_attempts` : `user_id` (nullable si numéro inconnu), `phone_attempted` (haché si le
compte n'existe pas — ne pas constituer un annuaire de numéros en clair), `successful`, `ip_address`,
`user_agent`, `occurred_at`. Consultable par `direction`. Purge des tentatives échouées au-delà de
12 mois ; les connexions réussies suivent la rétention générale (DEC-11).

---

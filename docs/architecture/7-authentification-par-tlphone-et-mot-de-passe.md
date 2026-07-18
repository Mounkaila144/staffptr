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

> **DEC-10.** L'application ne peut pas vérifier l'identité du demandeur : c'est une procédure
> humaine. Elle doit être écrite et affichée à l'écran de réinitialisation, sans quoi le circuit
> le plus simple pour prendre un compte reste l'appel téléphonique. À formaliser avec vous.

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

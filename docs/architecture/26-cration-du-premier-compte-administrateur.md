# 26. Création du premier compte administrateur

## 26.1 Le problème

FR1 interdit toute inscription publique et un compte n'est créé que par `direction` ou
`super_admin` — mais à l'installation, aucun compte n'existe. Les deux solutions réflexes sont
toutes deux inacceptables ici : un seeder contenant un mot de passe en clair versionné dans Git, ou
une route d'installation ouverte accessible à qui atteint le domaine en premier.

## 26.2 Solution retenue

Commande console `php artisan ptr:create-first-admin`, exécutée **en SSH sur le serveur**, jamais
par HTTP :

1. **Refuse de s'exécuter si un utilisateur existe déjà** — la commande est utilisable une seule
   fois dans la vie de l'installation.
2. Demande interactivement nom et téléphone ; **aucun argument de mot de passe** n'est accepté, ce
   qui évite qu'il finisse dans l'historique du shell.
3. Génère un mot de passe temporaire aléatoire de 32 caractères, **affiché une seule fois** sur la
   sortie standard.
4. Crée la fiche `people` puis le compte `users`, état `actif`, rôle **`super_admin` seul**.
5. Positionne `must_change_password = true` (FR5).
6. Écrit une entrée d'audit `actor_id = NULL`, `actor_label = 'Amorçage système'`.
7. Rappelle à l'écran que `super_admin` **ne détient aucune permission métier** (PERM-03) et que sa
   première tâche est de créer les deux comptes `direction`.

## 26.3 Suite de l'amorçage

Le `super_admin` crée les **deux** comptes `direction` par l'interface, chacun avec son propre mot
de passe temporaire. `depense.approuver` leur est attribué et **à eux seuls** (PERM-05). Tant que
les deux comptes `direction` n'existent pas, **aucune dépense n'est approuvable** — l'application
l'affiche explicitement plutôt que de laisser croire à un dysfonctionnement.

Le compte `super_admin` reste utilisable pour l'exploitation technique. Il ne peut ni approuver une
dépense, ni valider un objectif, ni lire le journal d'audit métier (§ 14.3).

---

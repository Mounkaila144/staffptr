# Porte de qualité de la campagne d’autorisation

Cette procédure rend opposable la campagne rôle × route de la story 2.9. Elle complète la CI de
chaque pull request et doit être rejouée à la fin de chaque jalon. La source fonctionnelle reste la
matrice du **PRD § 4.3** ; `config/authorization-matrix.php` en est la transcription exécutable.

## Deux populations de routes

La configuration sépare explicitement :

- `routes`, les routes réelles déjà livrées par l’application ;
- `fixtures`, les contrats provisoires qui représentent les ressources des prochains epics.

Chaque fixture porte le nom de sa future route réelle et la story chargée de son retrait. La
campagne échoue dès que les deux coexistent. Une story qui livre une route remplaçante doit donc
supprimer sa fixture dans le même commit ; un commentaire ou une promesse de nettoyage ultérieur
ne satisfait pas cette règle. La story 2.10, avec `audit.index`, est le premier cas attendu.

Le nombre de routes réelles et de fixtures est relevé dans la preuve de jalon. Une hausse des
routes réelles doit normalement s’accompagner d’une baisse des fixtures. Tout écart est expliqué
avant validation du jalon.

## Preuve CI à chaque pull request

Le workflow `Pull Request Quality` exécute la suite PHPUnit complète sur MySQL 8. La campagne peut
être rejouée isolément dans un environnement de test éphémère :

```bash
php artisan test tests/Feature/Http/AuthorizationMatrixTest.php
```

Le résultat doit être intégralement vert. Il prouve alors :

1. chaque combinaison des six rôles et de chaque route produit le statut prévu ;
2. une route protégée non déclarée fait échouer la chaîne ;
3. les visiteurs, comptes inactifs et comptes soumis au changement de mot de passe restent
   arrêtés par leur garde respective ;
4. chaque refus authentifié est un `403` Inertia complet, avec le message « Vous n'avez pas accès
   à cette page. », et ne contient que les props partagées `auth` et `errors` ;
5. aucune fixture ne coexiste avec la route réelle qui doit la remplacer.

## Reprise en porte de jalon

La porte comporte deux preuves distinctes, dont la portée ne doit pas être confondue.

### 1. Campagne complète en environnement `testing`

Sur le commit candidat exact, relancer la commande PHPUnit ci-dessus avec une base éphémère. Cette
preuve inclut les routes réelles **et les fixtures**, donc vérifie aussi les permissions des
ressources pas encore développées. Archiver le commit, le décompte des deux populations, la sortie
et le code de retour.

### 2. Contrôle de préproduction

Sur la préproduction déployée, relever l’inventaire avec `php artisan route:list --json`. Les
fixtures n’y existent pas : elles sont une instrumentation de test, pas une surface HTTP de
préproduction. Comparer les routes authentifiées réelles à la section `routes` de la configuration,
puis effectuer les accès directs refusés avec les comptes de recette des six rôles. Pour les routes
mutantes, utiliser une session navigateur et un jeton CSRF valides afin qu’un `419` ne masque pas le
contrôle d’autorisation. Chaque refus attendu doit rendre `403`, le composant `Platform/403`, le
message exact et aucune donnée métier.

Cette seconde preuve ne revalide que les routes réelles déployées. Elle ne prouve rien sur les
fixtures futures ; inversement, la campagne PHPUnit ne prouve pas que le bon commit est réellement
déployé en préproduction. Les deux sorties horodatées sont donc nécessaires.

## Revue datée du PRD

À chaque fin d’epic, un relecteur compare directement `docs/prd.md` § 4.3 à la matrice et au
catalogue de permissions, sans recopier le tableau dans un troisième fichier. Il contrôle au
minimum : journal d’audit réservé à `direction`, `finance` refusé, approbation de dépense réservée à
`direction`, validations d’objectif et de rapport financier refusées à `super_admin`, gestion des
comptes accordée à `direction` et `super_admin` dans leurs portées respectives.

Après revue, mettre à jour uniquement `prd_source.reviewed_on` et
`prd_source.reviewed_for_milestone` dans la configuration, puis joindre au journal de jalon :

- le commit et la version du PRD relus ;
- le nom du relecteur et la date UTC ;
- les écarts trouvés et leur résolution ;
- la sortie de la campagne après correction.

La date courante correspond à la revue de l’epic 2 du 20/07/2026. Une revue implicite ou non datée
ne renouvelle pas cette preuve.

## Éprouvage du dispositif par injection

`AuthorizationMatrixTest` injecte séparément quatre défauts et exige que la campagne les nomme :

| Injection | Échec attendu |
|---|---|
| route protégée absente de la configuration | `Routes protégées non déclarées` |
| statut attendu falsifié | `Statut inattendu` |
| refus remplacé par une redirection | `Refus transformé en redirection` |
| prop contenant une ressource interdite dans la page 403 | `Contenu partiel détecté` |

Chaque test s’exécute dans une application Laravel rafraîchie et une transaction de test dédiée :
la route ou la valeur injectée disparaît à la fin du test. Si une injection cesse de faire échouer
son assertion, la campagne n’est plus une preuve et le jalon reste bloqué.

## Critère de sortie du jalon

L’epic 2 et chaque jalon suivant sont bloqués tant que la campagne ne passe pas intégralement ou
qu’une route protégée reste non déclarée. La preuve archivée doit identifier clairement les deux
couvertures : campagne complète avec fixtures en `testing`, puis routes réelles uniquement en
préproduction.

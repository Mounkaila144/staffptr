# Réinitialisation administrative d’un mot de passe

Ce runbook applique DEC-10. En MVP, aucune réinitialisation en libre-service n’existe : seuls les
rôles `direction` et `super_admin` peuvent agir depuis l’écran **Comptes et rôles**. Un appel, un
message ou une connaissance personnelle ne remplace jamais la preuve de possession décrite ici.

## Prérequis Evolution API

L’exploitant configure `EVOLUTION_API_URL`, `EVOLUTION_API_KEY` et `EVOLUTION_INSTANCE` dans le
magasin de secrets de chaque environnement. L’URL de préproduction et de production doit être en
HTTPS. La clé n’est ni committée, ni affichée, ni journalisée. Après rotation ou modification :

```bash
php artisan config:clear
php artisan config:cache
php artisan about --only=environment
```

Avant mise en service, vérifier que l’instance Evolution répond à
`GET /instance/connectionState/{instance}` avec l’état `open`, puis qu’un envoi de test contrôlé via
`POST /message/sendText/{instance}` retourne HTTP 201. La clé est transmise par l’en-tête `apikey`.

## Procédure opposable

1. L’auteur ouvre la fiche du compte cible et lit à la personne la procédure affichée avant toute
   action.
2. Il confirme l’action sensible. PTR Staff génère alors un code à six chiffres, valable dix
   minutes, et l’envoie au numéro WhatsApp enregistré de la cible.
3. La cible lit le code reçu à l’auteur. L’auteur le saisit dans PTR Staff. Cinq erreurs invalident
   le code ; il faut alors recommencer la procédure.
4. Après confirmation seulement, PTR Staff génère un mot de passe temporaire, l’affiche une seule
   fois à l’auteur, force le changement à la prochaine connexion, lève le verrou persistant,
   révoque toutes les sessions de la cible et écrit les audits auteur/cible.
5. L’auteur transmet le mot de passe temporaire hors application selon le canal humain convenu.
   **Le mot de passe temporaire ne doit jamais être envoyé par Evolution API ou WhatsApp.**

La procédure reste identique pour toutes les cibles, y compris lorsqu’un `super_admin`
réinitialise un compte `direction`. Si Evolution API est indisponible, déconnectée ou refuse
l’envoi, la réinitialisation est bloquée sans aucun contournement. L’exploitant rétablit le service,
contrôle l’état `open`, puis l’auteur recommence depuis l’envoi d’un nouveau code.

## Mot de passe perdu et récupération

Le mot de passe temporaire n’est conservé en clair ni dans la session, ni dans les props partagées,
ni dans l’audit. Fermer la réponse ou masquer le secret le rend irrécupérable. S’il est perdu avant
transmission, l’auteur effectue une nouvelle réinitialisation ; le nouveau secret remplace le
précédent et révoque à nouveau les sessions de la cible.

## Risque résiduel et moyens de détection

Quiconque dispose du droit de réinitialiser peut prendre le contrôle du compte cible après avoir
obtenu le code WhatsApp, y compris un `super_admin` sur un compte `direction`. Ce risque est
inhérent à la capacité accordée par le PRD ; la garde empêchant le cumul des rôles ne couvre pas
l’usurpation par identifiants.

Toute suspicion se recherche par les trois traces complémentaires suivantes :

1. le journal d’audit nomme l’auteur et la cible de la réinitialisation ;
2. `must_change_password` avertit le titulaire légitime par un changement imposé à la connexion ;
3. l’historique de connexion de la story 2.6 montre les accès réussis au compte cible.

L’exploitant conserve les preuves horodatées, informe la direction et révoque les accès compromis
selon le circuit d’incident. L’absence de libre-service est volontaire (FR1), pas un manque à
contourner manuellement.

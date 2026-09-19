# Supervision et astreinte

> Story 11.1, Tasks 5 et 6 (AC 20 à 32, AC 57).

## 1. Les cinq familles d'alerte (AC 57)

| # | Ce qui est surveillé | Par quoi | Seuil | Statut |
|---|---|---|---:|---|
| 1 | Indisponibilité | Moniteur externe sur `/up` → **SMS** | 2 échecs consécutifs | **À mettre en service** (AC 20) |
| 2 | Travaux échoués et file longue | `queue:monitor` + `QueuedNotificationFailureInvariant` | 1 échec / file > 100 | Invariant livré ; `queue:monitor` **à ordonnancer sur le serveur** |
| 3 | Sauvegarde absente | `backup:monitor` | archive > 1 jour | **Livré** |
| 4 | Requêtes lentes | `DB::whenQueryingForLongerThan(500 ms)` | 500 ms cumulés | **Livré** |
| 5 | Certificat proche de l'expiration | `certbot renew` + contrôle d'expiration | < 21 jours | **À mettre en service** (AC 24) |

## 2. Surveillance externe de `/up` (AC 20)

Le moniteur doit être **externe au VPS** : une supervision hébergée sur la machine qu'elle surveille
ne signale jamais la panne qui compte, celle où la machine ne répond plus.

```
URL       : https://<domaine>/up
Intervalle: 60 s
Alerte    : 2 échecs consécutifs → SMS
Codes     : 200 = sain · 200 avec status "degraded" = à regarder · 503 = base indisponible
```

Le canal SMS est **réservé à l'exploitation** (AC 20). Il ne transporte jamais de notification
métier — celles-ci passent par `database` puis WhatsApp.

**Destinataire SMS** : à renseigner par la direction (Task 1).

## 3. Ce que `/up` renvoie

| `status` | Signification | Réaction |
|---|---|---|
| `ok` | Tout va bien | — |
| `degraded` | Cache, disque ou **sauvegarde** en défaut | Regarder dans l'heure |
| `failed` (503) | Base indisponible | **Astreinte immédiate** |

## 4. Cron et files (AC 27 à 32)

```cron
# Ordonnanceur — chaque minute
* * * * * cd /srv/staffptr/production/current && php artisan schedule:run >> /dev/null 2>&1
```

```ini
; /etc/supervisor/conf.d/staffptr-worker.conf
[program:staffptr-worker]
command=php /srv/staffptr/production/current/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/srv/staffptr/production/current
user=staffptr
autostart=true
autorestart=true
stopwaitsecs=3600      ; laisse un travail long finir avant l'arrêt
numprocs=1
redirect_stderr=true
stdout_logfile=/srv/staffptr/production/shared/ops/worker.log
```

> **`stopwaitsecs` élevé est délibéré.** Tuer un worker au milieu d'un envoi produirait une
> notification à moitié traitée. Les tâches sont idempotentes, mais mieux vaut ne pas s'en servir
> comme filet quotidien.

### Travaux échoués (AC 28)

```bash
php artisan queue:failed          # lister
php artisan queue:retry <uuid>    # rejouer
php artisan queue:retry all
```

Les travaux échoués sont **conservés**, jamais purgés automatiquement : un travail disparu est un
incident dont personne n'apprendra rien.

## 5. Requêtes lentes (AC 23)

Seuil de 500 ms, écrit dans le canal technique JSON. **Le SQL est journalisé, jamais ses valeurs
liées** : une requête paramétrée contient des numéros de téléphone et des montants, et une lenteur
n'est pas une raison de les recopier dans un fichier conservé 30 jours (AC 26).

À revoir **à chaque jalon** : une requête lente qui persiste est une régression de conception, pas
un aléa.

## 6. Certificat TLS (AC 24)

```bash
certbot renew --dry-run                    # vérifier le renouvellement automatique
openssl s_client -connect <domaine>:443 </dev/null 2>/dev/null \
  | openssl x509 -noout -enddate           # date d'expiration
```

Alerter à moins de 21 jours : Let's Encrypt renouvelle à 30 jours, donc 21 signifie que deux
tentatives ont déjà échoué.

## 7. Absence d'exécution attendue (AC 32)

Chaque tâche de `docs/ops/scheduled-tasks.md` porte un seuil d'absence. Le signal le plus simple et
le plus fiable est la **fraîcheur d'un effet**, pas la présence d'un processus :

| Tâche | Signal de vie |
|---|---|
| `backup:run` | âge de l'archive (`/up`, `backup:monitor`) |
| `ptr:recalculate-alert-level` | `alert_level_states.calculated_at` |
| `ptr:test-restore` | dernière ligne de `shared/ops/restore-log.md` |
| `ptr:check-invariants` | code de sortie du dernier passage |

## 8. Aucun secret dans les journaux (AC 26)

`RedactSensitiveDataProcessor` masque mots de passe, jetons, en-têtes d'autorisation, numéros de
téléphone et noms avant écriture. `OpsObservabilityTest` le prouve sur un enregistrement contenant
les cinq familles de valeurs sensibles.

## 9. Astreinte

| Rôle | Personne | Joignable |
|---|---|---|
| Astreinte niveau 1 | _à renseigner_ | _à renseigner_ |
| Escalade direction | _à renseigner_ | _à renseigner_ |

**À renseigner par la direction (Task 1, AC 45).** Une procédure d'astreinte sans nom ni numéro
n'est pas une procédure.

# Registre d’ordonnancement

Toutes les heures ci-dessous sont évaluées en `Africa/Niamey`. Le serveur exécute
`php artisan schedule:run` chaque minute. Les horodatages stockés restent en UTC (NFR23, DEC-01).

| Commande | Cadence | Contrat | Alerte attendue |
|---|---:|---|---|
| `ptr:send-daily-report-reminders` | Chaque minute | Lit à chaque passage `report_deadline_time` et `report_reminder_minutes`, puis émet une notification `database` dédupliquée avant de mettre WhatsApp en file. Ignore jours fermés, absences approuvées et rapports déjà envoyés. | La supervision du jalon 11.4 doit alerter si aucun passage réussi n’est observé pendant 5 minutes ou si la file échoue. |
| `ptr:send-expense-approval-reminders` | Tous les jours à 08:00 | Émet les rappels J+1/J+2 des décisions de dépense manquantes. | Alerter si aucun passage réussi n’est observé avant 08:10. |
| `ptr:deliver-support-request-batches` | Toutes les 5 minutes | Émet, au créneau de suivi de chaque tuteur, la notification groupée des demandes non urgentes. Le marquage de livraison sous verrou empêche tout doublon si l’exécution est rejouée. | Alerter si aucun passage réussi n’est observé pendant 15 minutes. |
| `ptr:recalculate-alert-level` | Tous les jours à 06:00 | Recalcule le niveau d’alerte du mois courant, écrit `alert_level_states` en `updateOrCreate` et invalide `alert:level:{aaaa-mm}`. **Ne recalcule jamais un mois clos** : le niveau figé à la clôture fait foi. En orange, relance `direction` tant qu’aucun plan correctif n’existe. | Alerter si `alert_level_states.calculated_at` du mois courant a plus de 26 heures, ou si aucun passage réussi n’est observé avant 06:15. |
| `ptr:send-objective-deadline-reminders` | Tous les jours à 07:00 | Notifie les objectifs ouverts dont l’échéance tombe dans les 3 jours civils. | Alerter si aucun passage réussi n’est observé avant 07:10. |
| `ptr:send-contract-ending-reminders` | Tous les jours à 07:15 | Consomme `ContractEndingService` (story 3.2) et notifie la personne concernée ainsi que `direction`. Le délai vient du paramètre `contract_end_warning_days`. | Alerter si aucun passage réussi n’est observé avant 07:25. |
| `ptr:check-invariants` | Tous les jours à 05:30 | Vérifie l'ensemble cumulé des invariants, dérive dans les données comprise. Lecture seule : il constate, il ne corrige rien. | Sortie en code d'erreur. Alerter sur tout écart, et sur l'absence de passage avant 05:45. |
| `backup:clean` | Tous les jours à 01:45 | Applique la rotation 7/4/12 avant la nouvelle sauvegarde, pour ne pas cumuler deux archives le temps du nettoyage. | Alerter si le nettoyage échoue deux jours de suite. |
| `backup:run` | Tous les jours à **02:00** | Dump `--single-transaction` + `storage/app/private`, chiffré AES-256, copie locale 48 h et stockage hors site. | Alerter si aucune archive n'apparaît avant 03:00. |
| `backup:monitor` | Tous les jours à 03:00 | Contrôle la fraîcheur (< 1 jour) et le volume. Tourne **après** la sauvegarde : le faire avant validerait celle de la veille. | Alerte immédiate sur destination non saine. |
| `ptr:test-restore` | Le 1er du mois à 04:00 | Restaure la dernière archive dans une base jetable, vérifie les tables présentes, détruit la base et écrit dans `shared/ops/restore-log.md`. | Alerte immédiate sur échec : une sauvegarde non restaurable n'est pas une sauvegarde. |
| `ptr:send-financial-preparation-reminders` | Tous les jours à 07:30 | Réclame les deux échéances du **mois précédent** tant qu’elles n’existent pas : le rapprochement bancaire à partir du 5 du mois, et le rapport financier (avant le 5 en « approche », après en « retard »). Une seule commande ordonnance les deux services propriétaires. | Alerter si aucun passage réussi n’est observé avant 07:40. |

## Contrat d’idempotence

Aucune de ces commandes n’a besoin d’être exécutée « exactement une fois ». Une relance manuelle,
un rattrapage après panne ou un double passage de l’ordonnanceur sont sûrs par construction :

- **Notifications.** Chaque notification porte un identifiant UUID v5 dérivé d’une identité
  métier durable, et la clé primaire de `notifications` refuse le doublon. L’écriture `database`
  est réalisée sous verrou dans une transaction ; la mise en file WhatsApp n’a lieu **que si** la
  transaction a effectivement créé la ligne. Une tâche rejouée n’envoie donc jamais deux fois le
  même message WhatsApp réel à la même personne (story 9.1 AC 35, AC 44).

  | Notification | Identité stable |
  |---|---|
  | Rappel / retard de rapport | compte + date civile + type |
  | Rappel d’approbation de dépense | dépense + jour de relance + destinataire |
  | Demandes de suivi groupées | lot + tuteur |
  | Relance de plan correctif | destinataire + mois en orange + date civile |
  | Échéance d’objectif | objectif + destinataire + date civile |
  | Fin de contrat ou de stage | destinataire + compte concerné + date civile |
  | Préparation financière | destinataire + type d’échéance + **mois** (sans date du jour : une échéance mensuelle ne se rappelle qu’une fois) |

- **Recalcul d’alerte.** `alert_level_states` porte une ligne par mois, écrite en `updateOrCreate`.
  `observed_at` — la date de passage au niveau courant, qui fait courir les 48 heures du plan
  correctif — n’est repoussée que si le niveau a réellement changé. Rejouer la commande sur les
  mêmes données réécrit donc exactement les mêmes valeurs.

- **Chevauchement.** Toutes les tâches sont déclarées `withoutOverlapping()`.

## Signaux de supervision

Chaque commande écrit son compte-rendu sur la sortie standard (`n notification(s) créée(s)`,
niveau et assiette pour le recalcul) et **ne journalise aucun contenu personnel ni secret**
(architecture § 22.2). Les signaux exploitables sont :

| Signal | Source | Usage |
|---|---|---|
| Dernier passage réussi | code de sortie de `schedule:run` | Détection d’un ordonnanceur arrêté |
| Fraîcheur du niveau d’alerte | `alert_level_states.calculated_at` | Détection d’un recalcul qui ne tourne plus |
| Travaux échoués | `php artisan queue:monitor` sur Redis | Détection d’un envoi WhatsApp bloqué |

## État réel de la supervision (mis à jour par la story 11.1)

Les seuils de la colonne « Alerte attendue » sont désormais **implémentés côté application**, mais
leur **acheminement vers un humain reste à mettre en service sur le serveur** :

| Contrat | Côté application | Côté serveur |
|---|---|---|
| Fraîcheur de sauvegarde | ✅ `backup:monitor`, invariant, `/up` | ⏳ destinataire d'alerte à renseigner |
| Échec de restauration | ✅ code d'erreur + registre | ⏳ acheminement de l'alerte |
| Travaux échoués | ✅ invariant `QueuedNotificationFailureInvariant` | ⏳ `queue:monitor` à ordonnancer |
| Requêtes lentes | ✅ seuil 500 ms dans le canal technique | ⏳ revue à chaque jalon |
| Indisponibilité | ✅ `/up` complet | ⏳ moniteur externe + SMS |

**Un contrat implémenté n'est pas une alerte reçue.** Tant que le destinataire et le moniteur
externe ne sont pas configurés, la surveillance reste manuelle. Voir `docs/ops/monitoring.md`.

### Reprise des contrats 11.3 et 11.4

La story 9.1 avait rattaché la supervision des files à **11.3** et celle des tâches planifiées à
**11.4**, sans les déclarer livrées. La story 11.1, qui consolide l'Epic 11, reprend ces deux
contrats : les seuils ci-dessus sont implémentés, la commande d'invariants sort en code d'erreur, et
`backup:monitor` alerte sur l'absence de sauvegarde.

Ce qui reste hors du dépôt : le **destinataire** des alertes et le **moniteur externe** de `/up`
avec son SMS. Voir `docs/ops/monitoring.md` § 1 pour l'état famille par famille.

## Prérequis de mise en service — WhatsApp / Evolution API

Avant toute émission WhatsApp réelle en production, et conformément à DEC-15 :

1. Evolution API doit être joignable **derrière HTTPS**, sur un nom de domaine.
2. L’**accès direct par IP publique doit être fermé** au public.

À défaut, la mise en service exige une **décision explicite et datée de la direction**, consignée
dans `docs/architecture/27-points-requrant-votre-accord.md`. En l’absence de l’un ou l’autre,
l’application continue d’écrire les notifications `database` — qui font foi — et la mise en file
WhatsApp doit rester désactivée.

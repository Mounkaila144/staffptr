# Registre des mises en service

> Exigé par l'AC 53 et rappelé à chaque déploiement par le résumé de `.github/workflows/deploy.yml`.
> Une mise en service qui n'est pas consignée ici n'est pas documentée, quand bien même elle aurait
> réussi : c'est ce registre, et non l'historique GitHub, qui fait foi lors d'un incident.

Le déploiement en production n'est jamais automatique. Il exige l'approbation explicite d'un
relecteur requis de l'environnement GitHub `production` (AC 43). **Chaque approbation donne une
ligne ci-dessous**, ajoutée par la personne qui a approuvé, au plus tard le jour même.

## Comment consigner une mise en service

1. Approuver le déploiement dans l'onglet *Actions*, sur le run concerné.
2. Attendre que le job **Production** soit au vert — le script effectue lui-même la porte de santé
   et le retour arrière automatique en cas d'échec (AC 44).
3. Ajouter une ligne au tableau, puis valider ce fichier sur `main` par une pull request.

Une ligne porte : la date, le SHA déployé, ce que la mise en service apporte, qui l'a approuvée, le
numéro du run, et l'issue constatée. En cas de retour arrière, l'écrire dans « Issue » plutôt que de
supprimer la ligne — **une décision annulée reste une décision prise**.

## Conventions

- **Horodatages en `Africa/Niamey` (UTC+1)**, comme partout dans le produit. L'heure retenue est
  celle du **démarrage du job Production**, qui suit immédiatement l'approbation : l'API GitHub ne
  restitue pas l'horodatage de l'approbation elle-même.
- Le SHA est celui déployé par le run, pas celui de la branche au moment de la rédaction.

## Mises en service

| Date et heure | SHA | Contenu | Approbation | Run | Issue |
|---|---|---|---|---|---|
| 2026-09-20 10:53 | `1bf3caf` | Récupération du code par référence explicite, SHA compris — correctif de la chaîne de déploiement elle-même. Relance manuelle du même SHA après l'échec du run 35500744651. | Mounkaila144 | [35501000338](https://github.com/Mounkaila144/staffptr/actions/runs/35501000338) | Réussie |
| 2026-09-20 15:08 | `2748328` | Un ménage raté n'invalide plus un déploiement réussi. | Mounkaila144 | [35515394977](https://github.com/Mounkaila144/staffptr/actions/runs/35515394977) | Réussie |
| 2026-09-21 07:01 | `7b92e40` | Ouverture à la direction du parcours d'activation d'un stagiaire : entrées de menu et bouton d'activation. | Mounkaila144 | [35529741765](https://github.com/Mounkaila144/staffptr/actions/runs/35529741765) | Réussie |
| 2026-09-21 07:19 | `7f9823c` | Story 12.1 — registre des parts de contribution des directeurs. **Migration** : création de `contribution_shares` et `capital_contributions`, déclencheurs d'immuabilité et `GRANT UPDATE` au compte applicatif. | Mounkaila144 | [35567670129](https://github.com/Mounkaila144/staffptr/actions/runs/35567670129) | Réussie |
| 2026-09-21 10:14 | `7256eba` | Écran de rédaction d'une fiche d'entrée de stagiaire, jusque-là absent. | Mounkaila144 | [35581168357](https://github.com/Mounkaila144/staffptr/actions/runs/35581168357) | Réussie |

## En attente d'approbation

| Date de fusion | SHA | Contenu | Run |
|---|---|---|---|
| 2026-09-21 18:03 | `2b72da7` | Un compte encore invité devient désignable comme responsable d'objectif — sans quoi l'activation d'un stagiaire restait impossible. | [35629573349](https://github.com/Mounkaila144/staffptr/actions/runs/35629573349) |

La préproduction de ce run est déployée et ses invariants sont verts. Le job Production attend
l'approbation ; une fois donnée et le job au vert, déplacer cette ligne dans le tableau ci-dessus.

## Portée de ce registre, et sa limite

Les cinq premières lignes ont été **reconstituées le 2026-09-21** depuis l'historique des runs
GitHub, ce fichier n'ayant pas été créé en même temps que le workflow qui le réclame. Elles sont
exactes quant au SHA, au run, à l'approbateur et à l'horodatage, tous relus dans l'API.

Elles ne portent en revanche **aucune trace de la décision elle-même** — ce qui a été pesé, ce qui
a été accepté comme risque, ce qui restait à surveiller. Cette part-là n'existe nulle part et ne
peut pas être reconstituée après coup. Les lignes suivantes doivent l'écrire au moment de
l'approbation, pendant que la raison est encore connue.

Les prérequis de **première** mise en service (AC 49, AC 50, AC 58) restent listés dans
`docs/ops/deployment.md` § 6. Ce registre ne les remplace pas et n'atteste pas qu'ils aient été
tenus.

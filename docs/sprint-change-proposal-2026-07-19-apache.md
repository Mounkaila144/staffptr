# Sprint Change Proposal — Apache au lieu de Nginx (avant story 1.6)

Date : 2026-07-19 · Déclencheur : préparation de la story 1.6 après le déploiement 1.5 · Tâche : `correct-course` (mode groupé)

## 1. Problème identifié

L'architecture impose Nginx et y attache des mécanismes précis : `X-Accel-Redirect` pour les pièces
jointes contrôlées (A-04, § 11.2, repris tel quel par l'AC 2 de la story 3.5 du PRD), Brotli « dans
Nginx » (§ 18), le rôle TLS/en-têtes/ressources statiques (§ 25.1), le diagramme d'ensemble (§ 3) et
les lignes « Serveur web : Nginx + PHP-FPM » (§ 4, tech-stack).

Or le VPS retenu par DEC-05 est **partagé et déjà servi par Apache 2.4** : y installer Nginx sur les
ports 80/443 casserait les vhosts des autres projets. La story 1.5 a d'ailleurs déjà déployé les
deux environnements en vhosts Apache + pools PHP-FPM — c'est l'état réel, vérifié et consigné. La
story 1.6 (HTTPS, HSTS, CSP, en-têtes, UFW) est précisément la story du serveur web : la rédiger
contre Nginx produirait une story inexécutable.

Au passage, deux autres dérives serveur du même ordre sont corrigées : § 25.1 dit « Debian 12 »
(réel : **Ubuntu 24.04 LTS**) et « MySQL 8 » (réel : **MariaDB 10.11**, déjà tranché par DEC-12).

**Nature :** conflit entre artefacts validés et réalité d'exécution — le même type d'écart que le
correct-course précédent, détecté cette fois **avant** la rédaction de la story qui l'aurait subi.

## 2. Impact sur les epics

- **Epic 1 :** la story 1.6 n'est pas encore rédigée — c'est le moment idéal ; elle sera écrite
  contre Apache. Rien d'autre ne bouge.
- **Epic 3 :** l'AC 2 de la story 3.5 (pièces jointes) nomme `X-Accel-Redirect` ; une substitution
  de mécanisme, pas de comportement — la propriété « PHP valide l'autorisation, le serveur transmet
  le fichier » est intégralement conservée par `X-Sendfile`.
- **Epics 2, 4–11 :** aucun impact. Aucune création, suppression ni réordonnancement. MVP inchangé.

## 3. Équivalences retenues (fondement de DEC-13)

| Exigence architecture | Sous Nginx | Sous Apache 2.4 |
|---|---|---|
| TLS Let's Encrypt (§ 25.2) | certbot nginx | certbot apache |
| HSTS, CSP, en-têtes (1.6) | `add_header` | `mod_headers` |
| Brotli, repli gzip (§ 18) | module brotli | `mod_brotli` (natif 2.4) + `mod_deflate` |
| HTTP/2 (§ 18) | natif | `mod_http2` |
| Pièces jointes contrôlées (§ 11.2) | `X-Accel-Redirect` + emplacement `internal` | **`X-Sendfile`** via `mod_xsendfile` (`libapache2-mod-xsendfile`) + `XSendFilePath` borné au répertoire privé ; côté Laravel, `BinaryFileResponse::trustXSendfileTypeHeader()` |
| Refus de `/storage` (§ 11.1) | `deny all` | `Require all denied` |
| Ressources statiques | Nginx | Apache (mêmes en-têtes `Cache-Control: immutable`) |

**Réversibilité** : le patron « PHP valide, le serveur transmet » est portable. Un retour à Nginx
(VPS dédié, condition de révision de DEC-05) ne changerait que la configuration serveur et le nom de
l'en-tête — aucune logique applicative.

## 4. Chemins évalués et voie retenue

- **Option 1 — Ajustement direct (retenue) :** DEC-13 + alignement des documents. Effort faible,
  aucun travail jeté, la 1.6 se rédige ensuite contre une architecture juste.
- **Option 2 — Nginx quand même** : sur d'autres ports derrière Apache en proxy, ou en déplaçant les
  autres projets. Complexité et risque de casse pour un bénéfice nul — les équivalences Apache sont
  complètes. Rejetée.
- **Option 3 — Attendre un VPS dédié** : bloquerait l'epic 1 sur une décision d'infrastructure déjà
  tranchée en sens inverse (DEC-05). Rejetée.

## 5. Modifications proposées, artefact par artefact

### 5.1 `docs/prd/ecarts-et-decisions.md` — enregistrer DEC-13

Nouvelle ligne du registre, après DEC-12 :

> | ~~DEC-13~~ | ✅ **Tranché 19/07/2026** — serveur web **Apache 2.4** (déjà en place sur le VPS partagé de DEC-05) au lieu de Nginx ; les pièces jointes contrôlées passent par **`X-Sendfile`** (`mod_xsendfile`) au lieu de `X-Accel-Redirect`, même propriété « PHP valide, le serveur transmet » ; certbot Apache, `mod_headers`, `mod_brotli`/`mod_deflate`, `mod_http2`. Réversible : un retour à Nginx ne change que la configuration serveur | — | Direction |

### 5.2 `docs/architecture/tech-stack.md` et `docs/architecture/4-stack-technique.md`

Ligne « Serveur web » : `Nginx + PHP-FPM` → `Apache 2.4 + PHP-FPM` avec référence `A-03, DEC-13`.

### 5.3 `docs/architecture/2-registre-des-dcisions-darchitecture.md`

Ligne A-04 : « servi par contrôleur + `X-Accel-Redirect` » → « servi par contrôleur + `X-Sendfile`
(DEC-13) ».

### 5.4 `docs/architecture/11-pices-jointes-prives.md`

- § 11.1 : « Nginx ne sert jamais ce répertoire… `deny all` explicite sur `/storage` » → « Le
  serveur web (Apache 2.4, DEC-13) ne sert jamais ce répertoire, et sa configuration porte un
  `Require all denied` explicite sur `/storage` ».
- § 11.2, tableau mode Contrôlé : « `X-Accel-Redirect` vers un emplacement Nginx `internal` » →
  « `X-Sendfile` (`mod_xsendfile`) vers un chemin borné par `XSendFilePath` ».
- § 11.2, paragraphe : « `X-Accel-Redirect` fait porter la transmission par Nginx » → « `X-Sendfile`
  fait porter la transmission par Apache », le reste inchangé, avec mention de
  `BinaryFileResponse::trustXSendfileTypeHeader()` côté Laravel.

### 5.5 `docs/architecture/18-performance-sur-connexion-faible.md`

Ligne Compression : « Brotli dans Nginx, repli gzip » → « Brotli via `mod_brotli` d'Apache, repli
gzip (`mod_deflate`) ».

### 5.6 `docs/architecture/3-vue-densemble.md` — diagramme

Nœud `N` : `Nginx … X-Accel-Redirect` → `Apache 2.4<br/>TLS Let's Encrypt · HSTS · Brotli<br/>X-Sendfile` ;
nœud `M` : `MySQL 8` → `MariaDB 10.11` (alignement DEC-12).

### 5.7 `docs/architecture/25-dploiement-https-secrets-et-cicd.md` — § 25.1

- « Debian 12 stable » → « Ubuntu 24.04 LTS » (réalité du VPS, constatée en 1.5).
- Tableau : ligne Nginx → « Apache 2.4 | TLS, en-têtes de sécurité, Brotli, `X-Sendfile`,
  ressources statiques » ; ligne MySQL 8 → « MariaDB 10.11 (DEC-12) | Données, sessions ».

### 5.8 `docs/prd/epic-3-organisation-profils.md` — story 3.5, AC 2

« avec `X-Accel-Redirect` pour ne pas faire transiter le fichier par PHP » → « avec `X-Sendfile`
(DEC-13) pour ne pas faire transiter le fichier par PHP ».

## 6. Plan d'action

1. Appliquer les éditions 5.1 à 5.8 (documents uniquement — aucun code, aucun test à modifier :
   aucun test ne pinne « Nginx »), PR unique.
2. **SM (`/sm`)** : rédiger la story 1.6 contre Apache 2.4 — certbot, `mod_headers` (HSTS, CSP,
   en-têtes), UFW, pages d'erreur — en citant DEC-13.
3. La story 3.5 installera `libapache2-mod-xsendfile` le moment venu ; son AC est déjà aligné.
4. Critère de succès : la 1.6 est exécutable telle quelle sur le VPS réel, sans adaptation orale.

## 7. Décisions d'approbation

- [ ] Proposal approuvé (sections 5.1 à 5.8)
- [ ] Application directe des éditions puis passage au SM pour la story 1.6

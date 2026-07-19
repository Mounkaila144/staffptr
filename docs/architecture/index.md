# PTR Staff — Architecture technique

## Fichiers de référence pour les agents

Ces fichiers portent les noms canoniques attendus par `create-next-story` et l'agent `dev`.
Ils font foi ; les sections numérotées ci-dessous en sont le détail.

| Fichier | Contenu | Chargé |
|---|---|---|
| [`tech-stack.md`](tech-stack.md) | Stack, décisions tranchées, interdits | à chaque story |
| [`coding-standards.md`](coding-standards.md) | Conventions PHP / Laravel / front / tests | à chaque story |
| [`source-tree.md`](source-tree.md) | Arborescence, cinq modules, règle de couplage | à chaque story |
| [`testing-strategy.md`](testing-strategy.md) | Pyramide, 14 règles bloquantes, campagne d'autorisation | toutes stories |
| [`unified-project-structure.md`](unified-project-structure.md) | Renvoi vers `source-tree.md` | toutes stories |
| [`data-models.md`](data-models.md) | Règles transverses de modèle, convention monétaire | stories backend |
| [`database-schema.md`](database-schema.md) | Ordre des migrations, règles non négociables | stories backend |
| [`backend-architecture.md`](backend-architecture.md) | Contrôleurs, services, policies, transactions | stories backend |
| [`rest-api-spec.md`](rest-api-spec.md) | *Aucune API publique* | stories backend |
| [`external-apis.md`](external-apis.md) | *Aucune intégration externe en MVP* | stories backend |
| [`frontend-architecture.md`](frontend-architecture.md) | Inertia, contraintes de poids et de rendu | stories front |
| [`components.md`](components.md) | Système de design propre, composants transverses | stories front |
| [`core-workflows.md`](core-workflows.md) | Sept parcours critiques | stories front |

> Les fichiers numérotés (`1-…` à `28-…`) sont générés par `md-tree explode` depuis
> `docs/architecture.md`. Ne pas les éditer à la main : modifier `docs/architecture.md` puis
> régénérer.

---

## Table of Contents

- [PTR Staff — Architecture technique](#table-of-contents)
  - [1. Objet et cadrage](./1-objet-et-cadrage.md)
  - [2. Registre des décisions d'architecture](./2-registre-des-dcisions-darchitecture.md)
    - [2.1 Décisions validées par la direction le 18/07/2026](./2-registre-des-dcisions-darchitecture.md#21-dcisions-valides-par-la-direction-le-18072026)
    - [2.2 Décisions prises par l'Architecte dans le cadre du § 8.4](./2-registre-des-dcisions-darchitecture.md#22-dcisions-prises-par-larchitecte-dans-le-cadre-du-84)
    - [2.3 Décisions requérant votre accord — non définitives](./2-registre-des-dcisions-darchitecture.md#23-dcisions-requrant-votre-accord-non-dfinitives)
  - [3. Vue d'ensemble](./3-vue-densemble.md)
    - [3.1 Style architectural](./3-vue-densemble.md#31-style-architectural)
    - [3.2 Ce que l'architecture ne fait pas](./3-vue-densemble.md#32-ce-que-larchitecture-ne-fait-pas)
  - [4. Stack technique](./4-stack-technique.md)
  - [5. Structure des modules](./5-structure-des-modules.md)
    - [5.1 Principe](./5-structure-des-modules.md#51-principe)
    - [5.2 Les cinq modules](./5-structure-des-modules.md#52-les-cinq-modules)
    - [5.3 Arborescence](./5-structure-des-modules.md#53-arborescence)
  - [6. Modèle de données](./6-modle-de-donnes.md)
    - [6.1 Règles transverses](./6-modle-de-donnes.md#61-rgles-transverses)
    - [6.2 Noyau identité — A-06 / CONTRA-02](./6-modle-de-donnes.md#62-noyau-identit-a-06-contra-02)
    - [6.3 Noyau financier](./6-modle-de-donnes.md#63-noyau-financier)
    - [6.4 Journal d'audit](./6-modle-de-donnes.md#64-journal-daudit)
  - [7. Authentification par téléphone et mot de passe](./7-authentification-par-tlphone-et-mot-de-passe.md)
    - [7.1 Normalisation du numéro — FR2](./7-authentification-par-tlphone-et-mot-de-passe.md#71-normalisation-du-numro-fr2)
    - [7.2 Connexion](./7-authentification-par-tlphone-et-mot-de-passe.md#72-connexion)
    - [7.3 Première connexion et changement imposé — FR5](./7-authentification-par-tlphone-et-mot-de-passe.md#73-premire-connexion-et-changement-impos-fr5)
    - [7.4 Réinitialisation — FR6 / Q9](./7-authentification-par-tlphone-et-mot-de-passe.md#74-rinitialisation-fr6-q9)
    - [7.5 Blocage après échecs — FR10](./7-authentification-par-tlphone-et-mot-de-passe.md#75-blocage-aprs-checs-fr10)
    - [7.6 Historique de connexion — FR9](./7-authentification-par-tlphone-et-mot-de-passe.md#76-historique-de-connexion-fr9)
  - [8. RBAC et permissions serveur](./8-rbac-et-permissions-serveur.md)
    - [8.1 Modèle](./8-rbac-et-permissions-serveur.md#81-modle)
    - [8.2 Les quatre niveaux de contrôle](./8-rbac-et-permissions-serveur.md#82-les-quatre-niveaux-de-contrle)
    - [8.3 Règles structurelles](./8-rbac-et-permissions-serveur.md#83-rgles-structurelles)
    - [8.4 Campagne de tests d'accès — NFR14](./8-rbac-et-permissions-serveur.md#84-campagne-de-tests-daccs-nfr14)
  - [9. Sessions, sécurité des comptes et notifications](./9-sessions-scurit-des-comptes-et-notifications.md)
    - [9.1 Choix du mécanisme de session](./9-sessions-scurit-des-comptes-et-notifications.md#91-choix-du-mcanisme-de-session)
    - [9.2 Configuration](./9-sessions-scurit-des-comptes-et-notifications.md#92-configuration)
    - [9.3 Invalidation immédiate des sessions — FR8 / PERM-08](./9-sessions-scurit-des-comptes-et-notifications.md#93-invalidation-immdiate-des-sessions-fr8-perm-08)
    - [9.4 Notifications — A-07](./9-sessions-scurit-des-comptes-et-notifications.md#94-notifications-a-07)
    - [9.5 Durcissement HTTP](./9-sessions-scurit-des-comptes-et-notifications.md#95-durcissement-http)
  - [10. API, contrôleurs et conventions de validation](./10-api-contrleurs-et-conventions-de-validation.md)
    - [10.1 Il n'y a pas d'API publique](./10-api-contrleurs-et-conventions-de-validation.md#101-il-ny-a-pas-dapi-publique)
    - [10.2 Convention de contrôleur](./10-api-contrleurs-et-conventions-de-validation.md#102-convention-de-contrleur)
    - [10.3 Contrat Inertia](./10-api-contrleurs-et-conventions-de-validation.md#103-contrat-inertia)
    - [10.4 Validation](./10-api-contrleurs-et-conventions-de-validation.md#104-validation)
    - [10.5 Réponses d'erreur](./10-api-contrleurs-et-conventions-de-validation.md#105-rponses-derreur)
  - [11. Pièces jointes privées](./11-pices-jointes-prives.md)
    - [11.1 Stockage — A-04 / NFR15](./11-pices-jointes-prives.md#111-stockage-a-04-nfr15)
    - [11.2 Contrôle d'accès à la lecture](./11-pices-jointes-prives.md#112-contrle-daccs-la-lecture)
    - [11.3 Validation au téléversement — NFR16 / Q11](./11-pices-jointes-prives.md#113-validation-au-tlversement-nfr16-q11)
    - [11.4 Sauvegarde et volumétrie](./11-pices-jointes-prives.md#114-sauvegarde-et-volumtrie)
  - [12. Transactions financières atomiques](./12-transactions-financires-atomiques.md)
    - [12.1 Règle](./12-transactions-financires-atomiques.md#121-rgle)
    - [12.2 Ce que garantit ce motif](./12-transactions-financires-atomiques.md#122-ce-que-garantit-ce-motif)
    - [12.3 Concurrence](./12-transactions-financires-atomiques.md#123-concurrence)
    - [12.4 Idempotence](./12-transactions-financires-atomiques.md#124-idempotence)
    - [12.5 Contrôles au niveau base](./12-transactions-financires-atomiques.md#125-contrles-au-niveau-base)
  - [13. Double approbation des dépenses](./13-double-approbation-des-dpenses.md)
    - [13.1 Règles à tenir](./13-double-approbation-des-dpenses.md#131-rgles-tenir)
    - [13.2 Modèle](./13-double-approbation-des-dpenses.md#132-modle)
    - [13.3 Application](./13-double-approbation-des-dpenses.md#133-application)
    - [13.4 Approbation et paiement sont distincts — FR116](./13-double-approbation-des-dpenses.md#134-approbation-et-paiement-sont-distincts-fr116)
    - [13.5 Alerte rouge — FR164](./13-double-approbation-des-dpenses.md#135-alerte-rouge-fr164)
  - [14. Journal d'audit non modifiable](./14-journal-daudit-non-modifiable.md)
    - [14.1 Trois barrières — A-05](./14-journal-daudit-non-modifiable.md#141-trois-barrires-a-05)
    - [14.2 Écriture](./14-journal-daudit-non-modifiable.md#142-criture)
    - [14.3 Lecture — FR23 / D-04](./14-journal-daudit-non-modifiable.md#143-lecture-fr23-d-04)
    - [14.4 Rétention](./14-journal-daudit-non-modifiable.md#144-rtention)
  - [15. Immuabilité, historiques et annulations](./15-immuabilit-historiques-et-annulations.md)
    - [15.1 Règle — P2 / RM-17 / NFR20](./15-immuabilit-historiques-et-annulations.md#151-rgle-p2-rm-17-nfr20)
    - [15.2 Les trois opérations autorisées](./15-immuabilit-historiques-et-annulations.md#152-les-trois-oprations-autorises)
    - [15.3 Application](./15-immuabilit-historiques-et-annulations.md#153-application)
    - [15.4 Clôture mensuelle — FR158 / FR159](./15-immuabilit-historiques-et-annulations.md#154-clture-mensuelle-fr158-fr159)
  - [16. Réserve, parts et alerte financière](./16-rserve-parts-et-alerte-financire.md)
    - [16.1 Principe de calcul](./16-rserve-parts-et-alerte-financire.md#161-principe-de-calcul)
    - [16.2 Parts de contrat — FR128 à FR136](./16-rserve-parts-et-alerte-financire.md#162-parts-de-contrat-fr128-fr136)
    - [16.3 Réserve — FR142 à FR147](./16-rserve-parts-et-alerte-financire.md#163-rserve-fr142-fr147)
    - [16.4 Alerte — FR161 à FR165](./16-rserve-parts-et-alerte-financire.md#164-alerte-fr161-fr165)
  - [17. Fuseau horaire et devise](./17-fuseau-horaire-et-devise.md)
    - [17.1 Temps — NFR23 / DEC-01](./17-fuseau-horaire-et-devise.md#171-temps-nfr23-dec-01)
    - [17.2 Devise — RM-02 / NFR22](./17-fuseau-horaire-et-devise.md#172-devise-rm-02-nfr22)
  - [18. Performance sur connexion faible](./18-performance-sur-connexion-faible.md)
    - [18.1 Budget opposable](./18-performance-sur-connexion-faible.md#181-budget-opposable)
    - [18.2 Ce que l'architecture apporte](./18-performance-sur-connexion-faible.md#182-ce-que-larchitecture-apporte)
    - [18.3 Points de vigilance](./18-performance-sur-connexion-faible.md#183-points-de-vigilance)
    - [18.4 Vérification](./18-performance-sur-connexion-faible.md#184-vrification)
  - [19. Cache et brouillons](./19-cache-et-brouillons.md)
    - [19.1 Cache serveur](./19-cache-et-brouillons.md#191-cache-serveur)
    - [19.2 Cache applicatif](./19-cache-et-brouillons.md#192-cache-applicatif)
    - [19.3 Brouillons — NFR5 / FR63 / UX § 6.5](./19-cache-et-brouillons.md#193-brouillons-nfr5-fr63-ux-65)
  - [20. Migrations et données initiales](./20-migrations-et-donnes-initiales.md)
    - [20.1 Migrations](./20-migrations-et-donnes-initiales.md#201-migrations)
    - [20.2 Ordre de dépendance](./20-migrations-et-donnes-initiales.md#202-ordre-de-dpendance)
    - [20.3 Données de référence — exécutées en production](./20-migrations-et-donnes-initiales.md#203-donnes-de-rfrence-excutes-en-production)
    - [20.4 Données de démonstration](./20-migrations-et-donnes-initiales.md#204-donnes-de-dmonstration)
  - [21. Sauvegardes et restauration](./21-sauvegardes-et-restauration.md)
    - [21.1 Objectifs](./21-sauvegardes-et-restauration.md#211-objectifs)
    - [21.2 Dispositif](./21-sauvegardes-et-restauration.md#212-dispositif)
    - [21.3 Test de restauration — NFR25](./21-sauvegardes-et-restauration.md#213-test-de-restauration-nfr25)
    - [21.4 Ce qui n'est pas sauvegardé](./21-sauvegardes-et-restauration.md#214-ce-qui-nest-pas-sauvegard)
  - [22. Journaux, erreurs et observabilité](./22-journaux-erreurs-et-observabilit.md)
    - [22.1 Trois registres distincts](./22-journaux-erreurs-et-observabilit.md#221-trois-registres-distincts)
    - [22.2 Journaux techniques](./22-journaux-erreurs-et-observabilit.md#222-journaux-techniques)
    - [22.3 Erreurs](./22-journaux-erreurs-et-observabilit.md#223-erreurs)
    - [22.4 Observabilité](./22-journaux-erreurs-et-observabilit.md#224-observabilit)
  - [23. Stratégie de tests](./23-stratgie-de-tests.md)
    - [23.1 Pyramide](./23-stratgie-de-tests.md#231-pyramide)
    - [23.2 Règles métier bloquantes — un test dédié chacune](./23-stratgie-de-tests.md#232-rgles-mtier-bloquantes-un-test-ddi-chacune)
    - [23.3 Campagne d'autorisation — NFR14 / CA-02](./23-stratgie-de-tests.md#233-campagne-dautorisation-nfr14-ca-02)
    - [23.4 E2E — Playwright](./23-stratgie-de-tests.md#234-e2e-playwright)
    - [23.5 Base de test — DEC-02](./23-stratgie-de-tests.md#235-base-de-test-dec-02)
  - [24. Environnements](./24-environnements.md)
    - [24.1 Les quatre environnements](./24-environnements.md#241-les-quatre-environnements)
    - [24.2 Préproduction](./24-environnements.md#242-prproduction)
    - [24.3 Commande d'invariants](./24-environnements.md#243-commande-dinvariants)
  - [25. Déploiement, HTTPS, secrets et CI/CD](./25-dploiement-https-secrets-et-cicd.md)
    - [25.1 Serveur](./25-dploiement-https-secrets-et-cicd.md#251-serveur)
    - [25.2 HTTPS — NFR11](./25-dploiement-https-secrets-et-cicd.md#252-https-nfr11)
    - [25.3 Déploiement](./25-dploiement-https-secrets-et-cicd.md#253-dploiement)
    - [25.4 Deux utilisateurs MySQL](./25-dploiement-https-secrets-et-cicd.md#254-deux-utilisateurs-mysql)
    - [25.5 Secrets](./25-dploiement-https-secrets-et-cicd.md#255-secrets)
    - [25.6 CI/CD — GitHub Actions](./25-dploiement-https-secrets-et-cicd.md#256-cicd-github-actions)
  - [26. Création du premier compte administrateur](./26-cration-du-premier-compte-administrateur.md)
    - [26.1 Le problème](./26-cration-du-premier-compte-administrateur.md#261-le-problme)
    - [26.2 Solution retenue](./26-cration-du-premier-compte-administrateur.md#262-solution-retenue)
    - [26.3 Suite de l'amorçage](./26-cration-du-premier-compte-administrateur.md#263-suite-de-lamorage)
  - [27. Points requérant votre accord](./27-points-requrant-votre-accord.md)
  - [28. Suites à donner](./28-suites-donner.md)
    - [Ordre d'implémentation imposé par l'architecture](./28-suites-donner.md#ordre-dimplmentation-impos-par-larchitecture)
  - [Journal des versions](./journal-des-versions.md)

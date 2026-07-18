# Core Workflows

> Sept parcours critiques spécifiés dans `docs/front-end-spec.md` § 4, avec wireframes en § 5.

| # | Parcours | Exigence chiffrée | Story |
|---|---|---|---|
| 1 ★ | Envoyer mon rapport quotidien | **< 3 min** sur téléphone en 3G (NFR4) | 6.1 |
| 2 ★ | Approuver une dépense | **3 interactions maximum** depuis la notification (FR121) | 4.6 |
| 3 | Valider les rapports de mon équipe | — | 6.3 |
| 4 | Déclarer et faire approuver une absence | — | 4.2 |
| 5 | Enregistrer un encaissement et déclencher les parts | Calcul dans la même transaction | 8.5, 8.7 |
| 6 | Signaler un blocage et obtenir de l'aide | Notification immédiate si urgent | 6.6 |
| 7 | Rapprochement hebdomadaire à deux comptes | Préparateur ≠ contrôleur | 8.12 |

★ = parcours couverts par Playwright **et** par une recette obligatoire sur téléphone réel en réseau
dégradé avant mise en service. Ce sont les deux points où le produit peut échouer sans qu'aucun test
unitaire ne le voie.

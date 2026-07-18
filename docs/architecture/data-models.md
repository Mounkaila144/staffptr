# Data Models

> Source de vérité : `docs/architecture.md` § 6 — shard **`6-modle-de-donnes.md`**.

Règles transverses à connaître avant d'écrire un modèle :

- **Montants** : `BIGINT UNSIGNED`, entiers XOF, cast `integer`. Aucun `DECIMAL`, aucun flottant (NFR22).
- **Horodatages** : stockés en **UTC**, affichés en `Africa/Niamey` (DEC-01).
- **Aucune suppression physique** : pas de `SoftDeletes` comme substitut — correction versionnée,
  annulation motivée ou contre-écriture (P2, RM-17).
- **Séparation `people` / `users`** : la fiche personne survit au compte (A-06, CONTRA-02).
- Aucune colonne de locataire — l'application n'est pas multi-entreprise (NFR28).

Sections détaillées :

| Sujet | Shard |
|---|---|
| Règles transverses, noyau identité, noyau financier, journal d'audit | `6-modle-de-donnes.md` |
| Ordre de dépendance des migrations, seeders de référence | `20-migrations-et-donnes-initiales.md` |
| Immuabilité, historiques, annulations, clôture mensuelle | `15-immuabilit-historiques-et-annulations.md` |
| Journal d'audit et ses trois barrières | `14-journal-daudit-non-modifiable.md` |

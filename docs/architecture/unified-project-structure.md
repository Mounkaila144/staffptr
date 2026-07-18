# Unified Project Structure

Cette architecture est un **monolithe modulaire déployé en un artefact unique** : il n'y a ni API
publique, ni frontend déployé séparément, ni service auxiliaire. Backend et frontend vivent dans le
même dépôt Laravel, reliés par Inertia.

**Il n'y a donc pas de structure « unifiée » distincte à décrire.** L'arborescence complète, les cinq
modules et la règle de couplage sont dans :

→ **`source-tree.md`** (même dossier)

Détail complet : `docs/architecture.md` § 5, shard `5-structure-des-modules.md`.

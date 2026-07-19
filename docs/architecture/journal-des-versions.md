# Journal des versions

| Date | Version | Description | Auteur |
|---|---|---|---|
| 18/07/2026 | 1.0 | Architecture initiale. A-01 à A-07 tranchés ; DEC-01 à DEC-11 soumis à la direction | Winston (Architect) |
| 18/07/2026 | 1.1 | Réalignement sur le plan d'exécution en 11 epics (`docs/epics-stories.md`) : ordre d'implémentation du § 28 et échéances DEC-03, DEC-09, DEC-10 renumérotés, avec correspondance PRD conservée. Aucune décision d'architecture modifiée. | John (PM) |
| 19/07/2026 | 1.2 | `audit_logs.occurred_at` passe de `TIMESTAMP(3)` à `DATETIME(3)` : plafond 2038 et conversion par fuseau de session, tous deux disqualifiants sur une table en rétention permanente (NFR23). Corrigé avant la première mise en production. | Quinn (QA) |
| 19/07/2026 | 1.3 | DEC-05 tranché : préproduction et production sur le VPS existant, partagé avec d'autres projets. Coût nul, mais modèle de menace du § 14.1 affaibli et `log_bin_trust_function_creators` global assumé. Quatre mesures d'isolation rendues obligatoires. | John (PM) |

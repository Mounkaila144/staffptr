# External APIs

**Aucune intégration externe en MVP.** Ni banque, ni Mobile Money, ni SMS, ni WhatsApp, ni courriel
(PRD § 8.3, FR34, FR101).

Deux conséquences testables :

- Un test vérifie qu'**aucun appel externe n'est émis** depuis les écrans financiers (FR101).
- Un test vérifie qu'**aucun canal de notification externe n'est appelé** (FR34).

Les notifications utilisent le système Laravel avec le **canal `database` seul** (A-07), conçu pour
accueillir SMS ou WhatsApp en phase 2 sans refonte.

Seules exceptions, côté **exploitation** et non applicatif : stockage objet des sauvegardes (DEC-06),
surveillance externe de `/up`, suivi d'erreurs éventuel (DEC-07).

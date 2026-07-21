# External APIs

**Une seule intégration externe en MVP : WhatsApp**, via **Evolution API 2.3.7** (intégration
`WHATSAPP-BAILEYS`, DEC-15), exclusivement pour les notifications sortantes de FR34. Voir
`9-sessions-scurit-des-comptes-et-notifications.md` § 9.4bis pour la configuration, les points
d'API, le format du numéro, l'emplacement du code et le comportement en panne. Ni banque, ni Mobile
Money, ni SMS, ni courriel (PRD § 8.3, FR101).

Conséquences testables :

- Un test vérifie qu'**aucun appel externe n'est émis** depuis les écrans financiers (FR101).
- Un test vérifie qu'**aucun canal non autorisé n'est appelé** : ni SMS, ni courriel. WhatsApp est
  appelé pour toutes les notifications de FR31 (FR34, DEC-16).

Les notifications utilisent le système Laravel avec **deux canaux** : `database`, toujours actif —
et source de vérité, indépendante du sort de l'appel WhatsApp — et **WhatsApp**, conditionnel à
l'éligibilité du type d'événement. L'ajout ultérieur de SMS suivrait le même mécanisme, sans refonte
(A-07).

Seules autres exceptions, côté **exploitation** et non applicatif : stockage objet des sauvegardes
(DEC-06), surveillance externe de `/up` avec alerte SMS, suivi d'erreurs éventuel (DEC-07).

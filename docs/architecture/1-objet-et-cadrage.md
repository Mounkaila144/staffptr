# 1. Objet et cadrage

Ce document fixe l'architecture technique de PTR Staff et tranche les décisions A-01 à A-07
laissées ouvertes par le § 8.4 du PRD.

**Contexte dimensionnant.** Application interne, mono-entreprise, 5 à 100 utilisateurs (NFR27),
aucun locataire multiple (NFR28), aucune intégration externe en MVP (§ 8.3), une équipe de
développement réduite. Toute la conception découle de ce cadrage : **le facteur limitant de ce
projet n'est pas la charge technique, c'est la charge de maintenance et l'exigence d'intégrité.**

Trois exigences structurent le document plus que toutes les autres :

| Exigence | Origine | Conséquence architecturale |
|---|---|---|
| Rien ne se supprime | P2, RM-17, NFR20 | L'immuabilité est une contrainte de schéma et de privilèges base, pas une convention de code |
| L'audit est transactionnel | NFR21 | L'écriture d'audit partage la transaction métier ; son échec annule l'opération |
| Le contrôle d'accès est serveur | P4, PERM-01, NFR14 | L'autorisation est testée par campagne automatisée rôle × route, pas déduite de l'affichage |

**Principe directeur retenu :** technologie ennuyeuse, un seul artefact déployable, aucune
abstraction introduite avant son deuxième usage réel. Les microservices, le CQRS, l'event sourcing
et le découpage en paquets Composer internes sont **écartés explicitement** : ils coûteraient plus
en maintenance qu'ils n'apportent à cette échelle.

---

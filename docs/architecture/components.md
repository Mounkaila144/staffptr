# Components

> Système de design **propre, minimal, sans bibliothèque externe** — imposé par NFR2 et NFR3.
> Source de vérité : `docs/front-end-spec.md` § 6 (composants) et § 7 (charte visuelle).

Composants transverses livrés par la **story 1.7** et réutilisés partout :

| Composant | Rôle | Réf. UX |
|---|---|---|
| Pastille d'état | Composant central — état + libellé, jamais la couleur seule | § 6.2 |
| Bouton | État occupé conservant **largeur et libellé**, non cliquable | § 6.3 |
| Champ de formulaire | Erreur **sous le champ**, focus déplacé au premier champ fautif | § 6.4 |
| Formulaire long avec brouillon | Sauvegarde ≤ 10 s après la dernière frappe | § 6.5 |
| Confirmation d'opération sensible | § 6.6 |
| Carte d'action (accueil) | § 6.7 |
| File de traitement | § 6.8 |
| État vide | Ce qui est vide, pourquoi c'est normal, l'action possible. 3 tons | § 5.6 |
| Squelette de chargement | Rien < 300 ms, squelette 300 ms–3 s, texte au-delà | § 5.6 |
| Bandeau hors connexion | Non bloquant, sans promesse de synchronisation | § 5.6 |

Vocabulaire imposé : `docs/front-end-spec.md` § 6.10 — vocabulaire de **contribution**, jamais de
surveillance.

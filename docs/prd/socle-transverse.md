<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

# Socle transverse

> Ces règles font partie de la définition de terminé de **chaque** story de chaque epic.
> Une story ne les redéclare pas ; elle ne mentionne que ce qui lui est propre.

Ces onze règles font partie de la définition de terminé de **chaque** story. Une story ne les
redéclare pas ; elle ne mentionne que ce qui lui est propre. Une story qui en enfreint une n'est pas
terminée, quel que soit l'état de ses critères d'acceptation spécifiques.

| Réf. | Règle | Source |
|---|---|---|
| **SOC-01** | Toute vérification d'accès est serveur. Chaque route protégée est déclarée dans `config/authorization-matrix.php` ; une route protégée non déclarée fait échouer la CI. Un rôle non autorisé reçoit `403` — jamais `302`, jamais de contenu partiel. | PERM-01, PERM-02, NFR14, archi § 23.3 |
| **SOC-02** | Toute écriture sensible produit une entrée d'audit **dans la même transaction** que l'opération métier. L'échec de l'audit annule l'opération. | FR21, NFR21 |
| **SOC-03** | Aucune suppression physique. Correction versionnée, annulation motivée ou contre-écriture. | P2, RM-17, NFR20 |
| **SOC-04** | Une migration déployée n'est jamais modifiée : toute évolution est une nouvelle migration. Déclencheurs, contraintes et privilèges sont créés **par migration**, jamais à la main sur le serveur. Seeders idempotents (`updateOrCreate`). | Archi § 20.1, § 20.3 |
| **SOC-05** | Un test Feature par critère d'acceptation ; un test Unit par calcul pur. Suite exécutée **sur MySQL en CI** (DEC-02). `pint --dirty` propre, Larastan niveau 6 sans erreur. | PRD § 8.5, archi § 23 |
| **SOC-06** | **État vide** : ce qui est vide, pourquoi c'est normal, l'action possible. Le vide par filtre ne se confond jamais avec le vide par absence de donnée. Aucune illustration. | UX § 5.6 |
| **SOC-07** | **Chargement** : rien sous 300 ms ; squelette à la forme du contenu de 300 ms à 3 s ; au-delà, squelette + « la connexion semble lente ». Bouton occupé conserve largeur et libellé. Jamais de modale bloquante. | UX § 5.6 |
| **SOC-08** | **Erreur** : ce qui s'est passé, l'action attendue, aucun terme technique, aucun code, sous le champ concerné avec déplacement du focus. Bandeau hors connexion non bloquant, sans promesse de synchronisation. | NFR17, NFR32, UX § 5.6 |
| **SOC-09** | **Mobile** : utilisable à 320 px sans défilement horizontal, cibles tactiles ≥ 44 × 44 px, premier rendu utile < 3 s en 3G dégradée (400 kbit/s / 400 ms), page ≤ 300 Ko au premier chargement et ≤ 80 Ko ensuite, aucune ressource tierce à l'exécution. | NFR1, NFR2, NFR3, NFR7, NFR8 |
| **SOC-10** | Français simple, vocabulaire de contribution et non de surveillance. Aucune information portée par la couleur seule. WCAG 2.1 AA : contraste, libellés associés, navigation clavier. Aucun classement comparatif entre personnes. | NFR29 à NFR31, FR82 |
| **SOC-11** | Validation exclusivement par Form Request, Eloquent plutôt que `DB::`, types de retour explicites, fichiers créés par `php artisan make:*`. Montants en entiers XOF, horodatages stockés en UTC et affichés en `Africa/Niamey`. | `coding-standards.md`, NFR22, NFR23, DEC-01 |

---

<!-- Généré depuis docs/epics-stories.md — ne pas éditer à la main.
     Toute évolution se fait dans le document source puis régénération. -->

# Traçabilité PRD → epics

Les **51** stories du § 10 du PRD (13 + 8 + 13 + 17) sont intégralement couvertes. Vérification PO
du 18/07/2026 : **176 FR sur 176 couvertes, aucune orpheline.**

| PRD | Plan | PRD | Plan |
|---|---|---|---|
| 1.1 | 1.1, 1.2 | 3.1 | 6.1 |
| 1.2 | 1.4, 2.10 | 3.2 | 6.2 |
| 1.3 | 2.2, 2.7 | 3.3 | 6.3 |
| 1.4 | 2.1 | 3.4 | 6.4 |
| 1.5 | 2.4, 2.5, 2.6, 2.8 | 3.5 | 6.5 |
| 1.6 | 3.1, 3.2, 3.3, 3.6 | 3.6 | 6.6 |
| 1.7 | 3.4 | 3.7 | 7.1 |
| 1.8 | 3.7 | 3.8 | 7.2 |
| 1.9 | 4.1 | 3.9 | 7.3 *(+ 9.2)* |
| 1.10 | 4.2 | 3.10 | 7.4 |
| 1.11 | 4.4 | 3.11 | 7.5 |
| 1.12 | 4.5 | 3.12 | 7.6 |
| 1.13 | 4.6 | 3.13 | 3.8 *(avancée)* |
| 2.1 | 5.8 | 4.1 | 8.1 |
| 2.2 | 5.1 | 4.2 | 4.3 *(avancée)*, 8.2 |
| 2.3 | 5.2 | 4.3 → 4.13 | 8.3 → 8.13 |
| 2.4 | 5.3 | 4.14 | 9.1, 9.2, 9.3 |
| 2.5 | 5.4 | 4.15 | 9.4 |
| 2.6 | 5.5 | 4.16 | 9.5 |
| 2.7 | 5.6 *(+ 3.5)* | 4.17 | 10.1, 10.2, 10.3 |
| 2.8 | 5.7 | | |

### Couverture des exigences non fonctionnelles

**32 NFR sur 32 couvertes**, dont 14 par le socle transverse plutôt que par une story isolée — c'est
le rôle du socle : une exigence qui s'applique partout ne doit pas dépendre d'une story qui pourrait
être oubliée.

| NFR | Objet | Couvert par |
|---|---|---|
| NFR1 | Premier rendu utile < 3 s en 3G | SOC-09, 5.8, 9.5, **10.5** |
| NFR2 | ≤ 300 Ko / 80 Ko | SOC-09, 1.3 (budget en CI), **10.5** |
| NFR3 | Aucune ressource tierce | SOC-09, 1.7, **10.5** |
| NFR4 | Rapport quotidien < 3 min | **6.1 AC7**, 10.5 |
| NFR5 | Brouillon ≤ 10 s | 1.7 (`useDraft`), **6.1 AC5** |
| NFR6 | Aucun enregistrement partiel | **6.1 AC9**, SOC-02 |
| NFR7 / NFR8 | 320 px, cibles 44 px | SOC-09, 1.7, **10.5** |
| NFR9 | Navigateurs supportés | **10.5 AC8** |
| NFR10 | Aucun écran exigeant un ordinateur | SOC-09, 8.13 AC9, 9.5 AC6 |
| NFR11 | HTTPS, HSTS, aucun contenu mixte | **1.6** |
| NFR12 | Hachage, aucun secret journalisé | **1.6 AC6**, 2.4 AC3, 11.3 AC7 |
| NFR13 | CSRF, XSS, injection, bourrage | **1.6 AC3**, 2.6 |
| NFR14 | Autorisation serveur, URL directe | SOC-01, **2.9**, 10.4 |
| NFR15 | Pièces jointes hors racine web | **3.5** |
| NFR16 | Types et taille paramétrables | **3.5 AC3-AC5** |
| NFR17 | Erreurs sans secret ni donnée | SOC-08, **1.6 AC7**, 11.3 |
| NFR18 | Moindre privilège | SOC-01, 2.2 |
| NFR19 | Stagiaires sans donnée financière | **8.1 AC4**, 10.4 |
| NFR20 | Immuabilité au niveau du modèle | SOC-03, **1.4** |
| NFR21 | Audit dans la même transaction | SOC-02, **1.4 AC2** |
| NFR22 | Entiers XOF | SOC-11, **1.2**, 8.3 AC4 |
| NFR23 | Dates non ambiguës, Niamey | SOC-11, **1.2 AC5** |
| NFR24 | Sauvegarde quotidienne | **11.1** |
| NFR25 | Test de restauration documenté | **11.2** |
| NFR26 | Conservation 10 ans | **11.1 AC5** *(sous réserve DEC-11)* |
| **NFR27** | **5 à 100 utilisateurs sans changement d'architecture** | **10.5 AC9** *(charge simultanée à confirmer)* |
| NFR28 | Pas de multi-entreprise | **3.1 AC1**, tech-stack |
| NFR29 | Français simple, vocabulaire de contribution | SOC-10, 6.2 AC5, 7.2 AC5 |
| NFR30 | WCAG 2.1 AA | SOC-10, 1.7 AC7, **10.5 AC6** |
| NFR31 | Rien porté par la couleur seule | SOC-10, **10.5 AC7** |
| NFR32 | Messages d'erreur exploitables | SOC-08, **1.6 AC4** |

---

**Stories sans équivalent dans le PRD** — comblant les manques signalés au § 1 de ce document :
1.3 (CI), 1.5 (préproduction et secrets), 1.6 (durcissement HTTP), 1.7 (socle d'interface et
états transverses), 2.3 (**premier administrateur et données de référence**), 2.9 (campagne
d'autorisation), 9.6 (notifications métier complètes), 10.4 (invariants), 10.5 (recette NFR),
11.1 à 11.7 (**sauvegarde, restauration, supervision, staging, livraison**).

---

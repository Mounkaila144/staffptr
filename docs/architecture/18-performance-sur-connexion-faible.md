# 18. Performance sur connexion faible

## 18.1 Budget opposable

| Cible | Valeur | Source |
|---|---|---|
| Premier rendu utile, 3G dégradée (400 kbit/s, 400 ms RTT) | < 3 s | NFR1 |
| Poids transféré, premier chargement | < 300 Ko | NFR2 |
| Poids transféré, navigations suivantes | < 80 Ko | NFR2 |
| Ressources tierces à l'exécution | **zéro** | NFR3 |

## 18.2 Ce que l'architecture apporte

**Inertia est ici un avantage mesurable, pas un confort.** Après le premier chargement, une
navigation ne transporte que du JSON — typiquement 10 à 20 Ko — au lieu d'un document HTML complet
avec ses en-têtes. À 400 ms de latence, c'est aussi un aller-retour économisé par navigation.

| Levier | Mise en œuvre |
|---|---|
| Découpage de code | Un fragment par page Inertia, chargement dynamique. Le rapport quotidien ne charge pas le module financier |
| Compression | Brotli dans Nginx, repli gzip |
| HTTP/2 | Multiplexage, un seul TLS |
| Cache des ressources | Noms hachés par Vite, `Cache-Control: immutable, max-age=31536000` |
| Polices | **Pile système exclusivement**, 0 octet transféré (UX § 11.2) |
| Icônes | SVG inline, une vingtaine, aucune requête |
| Images | Vignettes serveur, `loading="lazy"`, jamais de pleine résolution en liste |
| Pagination | Explicite, jamais de défilement infini (décision UX) |
| Rechargements partiels | `Inertia.reload({ only: [...] })` sur filtres et tableaux de bord |
| Requêtes | Chargement anticipé systématique, `select()` explicite, index sur les colonnes de filtre |

**Écrans de consolidation financière.** NFR10 les autorise à être optimisés pour le grand écran,
mais ils doivent rester consultables sur téléphone. Ils sont donc chargés **par blocs différés**
(`Inertia::defer()`) : la page arrive, les agrégats lourds suivent. Le budget de 3 s porte sur le
premier rendu utile, pas sur le tableau complet.

## 18.3 Points de vigilance

- **Le tableau de bord direction (FR168) est le principal risque de N+1** : quinze indicateurs
  hétérogènes sur une seule page. Chaque bloc passe par un service dédié avec sa requête agrégée et
  son cache (§ 19). La gestion par exception décidée par l'UX (§ 11.2) réduit aussi le volume rendu.
- **Les props Inertia partagées voyagent à chaque navigation.** Toute addition à `Inertia::share()`
  se paie sur les 80 Ko, sur toutes les pages. Une revue est exigée avant tout ajout.

## 18.4 Vérification

Budget vérifié **en CI** (échec de la construction si dépassement) et **sur téléphone réel en
conditions dégradées avant chaque mise en service** (§ 8.5 du PRD, UX § 11.3). La simulation
navigateur ne suffit pas et ne constitue pas la recette.

---

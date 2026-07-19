# 3. Vue d'ensemble

## 3.1 Style architectural

**Monolithe modulaire, déployé en un artefact unique.** Laravel rend des pages Vue via Inertia ;
il n'existe ni API publique, ni frontend déployé séparément, ni service auxiliaire hors du serveur
de files d'attente qui est le même processus PHP.

```mermaid
graph TB
    subgraph Client["Navigateur — Chrome Android prioritaire"]
        V["Vue 3 + Inertia<br/>Tailwind 4<br/>Brouillons en localStorage"]
    end

    subgraph VPS["VPS unique — staff.ptrniger.com"]
        N["Apache 2.4<br/>TLS Let's Encrypt · HSTS · Brotli<br/>X-Sendfile"]
        P["PHP-FPM 8.3<br/>Laravel 13"]
        Q["Worker de file<br/>supervisor"]
        S["Ordonnanceur<br/>cron → schedule:run"]
        M[("MariaDB 10.11<br/>données + sessions")]
        R[("Redis<br/>cache + files")]
        F["storage/app/private<br/>pièces jointes"]
    end

    O["Stockage objet hors site<br/>sauvegardes chiffrées"]

    V <-->|"HTTPS · JSON Inertia"| N
    N --> P
    P --> M
    P --> R
    P --> F
    N -.->|"fichier privé<br/>après autorisation"| F
    Q --> M
    Q --> R
    S --> Q
    P -.->|"quotidien 02h00"| O
```

## 3.2 Ce que l'architecture ne fait pas

Écarté délibérément, pour que ces absences ne soient pas relues plus tard comme des oublis :

- **Pas de microservices.** Quatre domaines faiblement couplés dans un monolithe, § 8.2 du PRD.
- **Pas de SSR Inertia.** Doublerait l'exploitation (processus Node à superviser) pour un gain nul :
  les 68 écrans sont derrière authentification, aucun besoin de référencement.
- **Pas de PWA, pas de service worker, pas de mode hors ligne.** Phase 2 (§ 3.2 du PRD). Le brouillon
  local du § 18 couvre le besoin réel du MVP sans en payer la complexité.
- **Pas de bibliothèque de composants Vue.** Décision UX § 6.1, imposée par NFR2 et NFR3.
- **Pas de conteneurisation en production.** Un VPS, un déploiement scripté. Docker sert uniquement
  à l'intégration continue.
- **Pas de multi-tenant, aucune colonne `tenant_id`.** NFR28. Question Q15 en suspens : si la
  direction revient sur ce point à deux ans, la reprise sera coûteuse et assumée comme telle.

---

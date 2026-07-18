# 22. Journaux, erreurs et observabilité

## 22.1 Trois registres distincts

À ne pas confondre — ils ont trois lecteurs et trois durées de vie différents :

| Registre | Contenu | Lecteur | Rétention |
|---|---|---|---|
| **Journal d'audit** (§ 14) | Faits métier | `direction` | Permanente |
| **Journaux techniques** | Exceptions, requêtes lentes | `super_admin`, développeur | 30 jours |
| **Historiques métier** | Changements de fiche (FR18) | Selon droits | Permanente |

## 22.2 Journaux techniques

- Canal `daily`, 30 jours, **format JSON** pour être exploitable par `grep` et `jq`.
- **Nettoyage obligatoire (NFR17, NFR12).** Un processeur Monolog masque `password`,
  `password_confirmation`, `token`, `secret`, en-têtes `Authorization` et cookies. **Aucun mot de
  passe, jeton ou secret n'est jamais journalisé**, y compris dans une trace d'exception.
- Aucune donnée personnelle ni requête complète en journal (NFR17) : identifiants d'objet, pas
  contenus d'objet.
- Contexte enrichi automatiquement : identifiant de requête, `user_id`, route.

## 22.3 Erreurs

Traitées dans `bootstrap/app.php` (structure slim) :

- L'utilisateur voit un **message français, sans terme technique**, indiquant ce qui s'est passé et
  l'action attendue (NFR17, NFR32).
- `APP_DEBUG=false` en production, sans exception — vérifié par la commande d'invariants (§ 24.3).
- Pages d'erreur Inertia dédiées pour `403`, `404`, `419` (session expirée), `500`.
- `419` mérite un traitement propre : sur 3G avec un onglet resté ouvert, l'expiration de session
  est un cas **fréquent**, pas un cas limite. Le message invite à se reconnecter sans perdre le
  brouillon local.

## 22.4 Observabilité

| Besoin | Moyen |
|---|---|
| Point de santé | `/up` — base, Redis, disque (Story 1.1) ; **âge de la dernière sauvegarde ajouté en Story 11.1**, seule story qui crée une sauvegarde |
| Disponibilité | Surveillance externe (UptimeRobot ou équivalent) sur `/up`, alerte SMS |
| Erreurs applicatives | DEC-07 |
| Files d'attente | `queue:monitor` + alerte sur travaux échoués |
| Sauvegardes | `backup:monitor`, § 21.2 |
| Requêtes lentes | `DB::whenQueryingForLongerThan(500ms)` → journal d'alerte |
| Invariants métier | `ptr:check-invariants` quotidien, § 24.3 |

> **DEC-07.** Sentry accélère nettement le diagnostic pour un développeur unique. NFR3 vise les
> ressources **front** chargées à l'exécution — un client serveur ne l'enfreint pas. Mais des
> extraits d'erreur partiraient chez un tiers. Recommandation : **Sentry auto-hébergé**, ou, si
> c'est trop lourd à exploiter, journaux fichiers seuls avec alerte courriel sur exception `500`.

---

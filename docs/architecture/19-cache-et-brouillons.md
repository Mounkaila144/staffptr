# 19. Cache et brouillons

## 19.1 Cache serveur

Redis (DEC-04). **Invalidation par écriture, jamais par expiration seule** : un agrégat financier
périmé affiché pendant dix minutes est un défaut, pas une optimisation.

| Donnée | Clé | Invalidée par |
|---|---|---|
| Solde d'un compte | `account:{id}:balance` | Toute écriture imputée au compte |
| Réserve et mois couverts | `reserve:current` | Mouvement de réserve, changement de charge fixe |
| Niveau d'alerte | `alert:level:{aaaa-mm}` | Encaissement, charge fixe, clôture |
| Blocs de tableau de bord | `dash:{user}:{bloc}` | Écriture du domaine concerné, TTL plafond 5 min |
| Paramètres (FR25) | `settings:all` | Toute modification de paramètre |

Les valeurs financières affichées à l'écran portent **la date des données source** (FR145) : rendre
la fraîcheur visible plutôt que la promettre.

**Non mis en cache, jamais :** les permissions effectives (une révocation doit prendre effet
immédiatement — PERM-08), le journal d'audit, le solde utilisé **à l'intérieur** d'une transaction
d'écriture — ce dernier est toujours relu sous verrou.

## 19.2 Cache applicatif

`config:cache`, `route:cache`, `view:cache`, `event:cache` posés au déploiement (§ 25.3).
Rappel des standards : `env()` uniquement dans `config/` — un `env()` ailleurs renvoie `null` dès
que la configuration est mise en cache.

## 19.3 Brouillons — NFR5 / FR63 / UX § 6.5

**Brouillon local à l'appareil, en `localStorage`.** Aucun brouillon serveur en MVP : l'UX interdit
explicitement de promettre la reprise sur un autre appareil, promesse que le MVP ne tient pas.

Composable `useDraft(formKey, userId, entityId)` :

| Comportement | Règle |
|---|---|
| Sauvegarde | Anti-rebond **2 s** après la dernière frappe, et immédiate à la perte de focus. NFR5 exige 10 s au plus — 2 s tient la cible avec marge sur un téléphone lent |
| Clé | `draft:{userId}:{formKey}:{entityId}` — jamais de collision entre utilisateurs sur un appareil partagé |
| Restauration | Bandeau « Brouillon restauré (17 h 12) », masquable, avec « Repartir d'un formulaire vide » |
| Purge | À l'envoi réussi, et automatiquement au-delà de 7 jours |
| Portée | **Jamais de pièce jointe ni de donnée financière validée** en `localStorage` |
| Témoin | « ✓ Enregistré à 17 h 31 », discret, sans animation |

L'envoi reste **atomique côté serveur** (NFR6) : le brouillon protège la saisie, la transaction
protège la donnée. Ce sont deux mécanismes distincts et il ne faut pas confondre leurs rôles.

**Pièces jointes en arrière-plan.** Dès la sélection, le fichier part vers `/internal/v1/attachments`
pendant que la saisie continue (UX § 4.1). Le formulaire ne transporte ensuite que l'identifiant.
C'est ce qui protège les 15 dernières secondes du budget de 3 minutes de NFR4.

---

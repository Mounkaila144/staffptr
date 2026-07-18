# 10. API, contrôleurs et conventions de validation

## 10.1 Il n'y a pas d'API publique

Les contrôleurs répondent en Inertia, pas en JSON. Trois exceptions, toutes internes et
authentifiées par session :

1. **Points de terminaison de brouillon et d'autocomplétion** — préfixés `/internal/`, réponse JSON.
2. **Téléversement de pièce jointe** — multipart, réponse JSON (envoi en arrière-plan, UX § 11.2).
3. **Point de santé** — `/up`, non authentifié, sans donnée métier (Story 1.1).

Les routes sont versionnées `/internal/v1/…` conformément aux standards du dépôt, afin qu'un client
mobile de phase 2 ne force pas la réécriture des chemins existants.

## 10.2 Convention de contrôleur

Contrôleurs **fins**. Un contrôleur autorise, délègue, répond. Il ne calcule pas et n'ouvre pas de
transaction.

```php
public function approve(ApproveExpenseRequest $request, Expense $expense): RedirectResponse
{
    $this->authorize('approve', $expense);

    $this->expenseApproval->approve($expense, $request->user(), $request->validated('comment'));

    return back()->with('success', 'Votre approbation est enregistrée.');
}
```

La transaction, l'audit, la règle des deux approbateurs distincts et le changement d'état sont dans
`ExpenseApprovalService`. **Conséquence testable :** la règle métier est couverte par un test
unitaire de service, sans passer par HTTP.

## 10.3 Contrat Inertia

Props partagées à toutes les pages, tenues **délibérément minimales** — elles voyagent à chaque
navigation et pèsent sur le budget de 80 Ko (NFR2) :

```php
// AppServiceProvider
Inertia::share([
    'auth' => fn () => [
        'user' => $request->user()?->only('id', 'full_name', 'must_change_password'),
        'permissions' => fn () => $request->user()?->getPermissionNames(),  // évalué à la demande
    ],
    'notifications' => fn () => ['unread' => $request->user()?->unreadNotifications()->count()],
    'flash' => fn () => ['success' => session('success'), 'error' => session('error')],
]);
```

> **Les permissions transmises au client servent exclusivement à masquer des éléments d'interface.
> Elles ne sont jamais une autorisation.** L'autorisation est refaite côté serveur à chaque
> requête (P4). Cette phrase doit rester dans le code, en commentaire, au-dessus du partage.

Les rechargements partiels Inertia (`only: [...]`) sont utilisés sur les listes filtrées et les
tableaux de bord : ils réduisent la charge utile à ce qui change réellement.

## 10.4 Validation

**Toute validation est dans un Form Request**, jamais inline (standards du dépôt). Conventions :

- Une classe par action : `StoreExpenseRequest`, `ApproveExpenseRequest`.
- `authorize()` délègue à la Policy, il ne réimplémente pas la règle.
- Les **règles métier bloquantes** (limites de 3 objectifs, 5 priorités, 3 stagiaires, approbateurs
  distincts) sont des **règles de validation dédiées** (`App\Rules\`) réutilisables, **doublées d'une
  contrainte base** lorsque c'est exprimable. Deux barrières, pas une.
- Messages en français, orientés action (NFR32) : ce qui s'est passé, ce qui est attendu.
- Montants : entiers positifs, bornes hautes explicites, jamais de flottant accepté en entrée.

## 10.5 Réponses d'erreur

| Cas | Réponse |
|---|---|
| Validation | `422` + erreurs par champ, rendues à côté du champ |
| Non authentifié | Redirection vers connexion |
| Non autorisé | **`403` + page d'erreur Inertia** — jamais de redirection silencieuse (PERM-02) |
| Introuvable | `404` — indifférencié d'un `403` sur objet non visible, pour ne pas révéler l'existence |
| Erreur serveur | `500` + message générique français, détail technique en journal seul (NFR17, NFR32) |

---

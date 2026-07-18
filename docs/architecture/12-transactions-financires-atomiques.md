# 12. Transactions financières atomiques

## 12.1 Règle

**Toute écriture financière passe par un service, dans une transaction, avec son audit.** Aucun
contrôleur, aucune commande, aucun observateur n'écrit directement une table financière.

```php
public function record(PaymentData $data, User $actor): Payment
{
    return DB::transaction(function () use ($data, $actor) {
        $account  = Account::lockForUpdate()->findOrFail($data->accountId);
        $contract = Contract::lockForUpdate()->findOrFail($data->contractId);

        $this->monthGuard->assertOpen($data->receivedOn);      // FR114 — mois clôturé

        $payment = Payment::create([...]);                      // numéro de reçu unique, FR110

        $this->shares->computeEntitlements($payment, $contract); // FR113, FR131
        $this->reserve->allocateFrom($payment, $contract);       // FR143

        $this->audit->record($actor, $payment, 'created', null, $payment->getAttributes());

        return $payment;
    });                                                          // NFR21 : audit dans la transaction
}
```

## 12.2 Ce que garantit ce motif

| Garantie | Mécanisme |
|---|---|
| Aucun enregistrement partiel (NFR6) | Transaction unique, du reçu jusqu'au mouvement de réserve |
| Audit indissociable (NFR21) | Écrit **dans** la transaction — son échec annule l'opération métier |
| Pas de calcul concurrent faux | `lockForUpdate()` sur le compte et le contrat |
| Pas d'imputation sur mois clos (FR114, FR158) | Garde vérifiée **dans** la transaction, après verrou |

## 12.3 Concurrence

À 100 utilisateurs la contention est faible, mais deux encaissements simultanés sur le même contrat
produiraient des parts fausses sans verrou. Le verrou pessimiste est **ordonné de façon constante**
(compte, puis contrat, puis facture) dans tous les services financiers : c'est la protection contre
l'interblocage, et c'est une règle de revue de code, pas une option.

## 12.4 Idempotence

NFR6 exige qu'une action interrompue par une perte de connexion ne produise jamais d'enregistrement
partiel. La transaction couvre le cas. Reste le **doublon par renvoi** : l'utilisateur en 3G ne voit
pas la réponse et resoumet.

Chaque formulaire sensible (encaissement, dépense, approbation, paiement) porte donc une clé
d'idempotence ULID générée au **rendu** de la page. Elle est unique en base sur la table cible ; un
renvoi retourne l'enregistrement existant au lieu d'en créer un second. C'est peu coûteux et cela
supprime une classe entière d'incidents de saisie sur réseau instable.

## 12.5 Contrôles au niveau base

L'application n'est pas la seule barrière (MySQL 8.0.16+ / MariaDB 10.2+ appliquent `CHECK`) :

```sql
ALTER TABLE payments  ADD CONSTRAINT chk_payments_amount  CHECK (amount > 0);
ALTER TABLE expenses  ADD CONSTRAINT chk_expenses_amount  CHECK (amount > 0);
ALTER TABLE expense_approvals
  ADD CONSTRAINT uq_one_approval_per_approver UNIQUE (expense_id, approver_id);
```

---

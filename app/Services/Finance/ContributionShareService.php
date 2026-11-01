<?php

namespace App\Services\Finance;

use App\Enums\ContributionEntryType;
use App\Enums\ContributionOrigin;
use App\Enums\ShareType;
use App\Models\Finance\CapitalContribution;
use App\Models\Finance\Contract;
use App\Models\Finance\ContributionShare;
use App\Models\Finance\Payment;
use App\Models\Finance\ShareEntitlement;
use App\Models\Identity\User;
use App\Support\Auditing\AuditLogger;
use App\Support\Money;
use App\Support\VintageCoefficient;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Registre des parts de contribution des directeurs (story 12.1).
 *
 * Ce service **observe** le partage 10 / 60 / 30 sans jamais le réécrire : il lit la part
 * `ptr_niger` déjà calculée par {@see ShareEntitlementService} et en dérive des parts permanentes
 * pour le directeur qui a fait entrer l'argent. Les 10 % d'apporteur et les 30 % d'exécutant
 * n'émettent rien : ils ont déjà rémunéré leur bénéficiaire.
 */
final readonly class ContributionShareService
{
    public function __construct(
        private VintageCoefficient $vintage,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Émet les parts dues au directeur apporteur pour un encaissement qui vient d'être validé.
     *
     * Rien n'est émis lorsque le contrat n'a pas d'apporteur, lorsque cet apporteur n'est pas
     * directeur, ou lorsque la part entreprise est nulle. L'appel est idempotent : le droit
     * `ptr_niger` ne peut porter qu'une émission, garantie par un index unique.
     */
    public function issueForPayment(Payment $payment, Contract $contract, User $actor): ?ContributionShare
    {
        $this->assertWithinTransaction();

        $holder = $this->directorContributor($contract);

        if ($holder === null) {
            return null;
        }

        $entitlement = $payment->shareEntitlements()
            ->where('share_type', ShareType::PtrNiger->value)
            ->whereNull('reversed_at')
            ->first();

        if (! $entitlement instanceof ShareEntitlement || $entitlement->share_amount < 1) {
            return null;
        }

        $existing = ContributionShare::query()
            ->where('share_entitlement_id', $entitlement->getKey())
            ->where('entry_type', ContributionEntryType::Emission->value)
            ->first();

        if ($existing instanceof ContributionShare) {
            return $existing;
        }

        return $this->issue(
            holder: $holder,
            actor: $actor,
            origin: ContributionOrigin::Encaissement,
            occurredOn: $payment->received_on,
            baseAmount: (int) $entitlement->share_amount,
            reason: sprintf(
                'Part encaissée par l’entreprise grâce à l’apporteur sur le contrat %s, reçu %s.',
                $contract->reference,
                $payment->receipt_number,
            ),
            attributes: [
                'share_entitlement_id' => $entitlement->getKey(),
                'payment_id' => $payment->getKey(),
                'contract_id' => $contract->getKey(),
            ],
            action: 'contribution_shares_issued_from_payment',
        );
    }

    /**
     * Annule les parts d'un encaissement extourné ou corrigé, par écriture inverse datée.
     *
     * Rien n'est supprimé : l'émission d'origine reste lisible, l'annulation porte la date de la
     * contre-écriture et le coefficient figé de l'émission qu'elle neutralise.
     */
    public function reverseForPayment(Payment $original, Payment $reversal, User $actor): ?ContributionShare
    {
        $this->assertWithinTransaction();

        $emission = ContributionShare::query()
            ->where('payment_id', $original->getKey())
            ->where('entry_type', ContributionEntryType::Emission->value)
            ->lockForUpdate()
            ->first();

        if (! $emission instanceof ContributionShare) {
            return null;
        }

        $alreadyReversed = ContributionShare::query()
            ->where('reversal_of_id', $emission->getKey())
            ->first();

        if ($alreadyReversed instanceof ContributionShare) {
            return $alreadyReversed;
        }

        $entry = new ContributionShare([
            'holder_id' => $emission->holder_id,
            'origin' => $emission->origin->value,
            'entry_type' => ContributionEntryType::Annulation->value,
            'share_entitlement_id' => $emission->share_entitlement_id,
            'payment_id' => $reversal->getKey(),
            'contract_id' => $emission->contract_id,
            'reversal_of_id' => $emission->getKey(),
            'base_amount' => $emission->base_amount,
            'vintage_year' => $emission->vintage_year,
            'coefficient_basis_points' => $emission->coefficient_basis_points,
            'issued_shares' => $emission->issued_shares,
            'occurred_on' => $reversal->received_on->toDateString(),
            'reason' => 'Contre-écriture des parts liées au reçu '.$original->receipt_number.'.',
            'created_by' => $actor->getKey(),
        ]);

        $this->auditLogger->runExplicitly(
            auditable: $entry,
            operation: fn (): bool => $entry->saveOrFail(),
            actorId: (int) $actor->getKey(),
            actorLabel: $this->actorLabel($actor),
            action: 'contribution_shares_reversed',
            newValues: $entry->getAttributes(),
            reason: $entry->reason,
        );

        return $entry;
    }

    /**
     * Émet les parts d'un apport d'argent personnel au moment où le second directeur l'approuve.
     *
     * Le millésime retenu est celui de l'approbation : c'est la date à laquelle l'apport devient
     * effectif, donc le fait générateur.
     */
    public function issueForCapitalContribution(CapitalContribution $contribution, User $actor, CarbonImmutable $approvedAt): ContributionShare
    {
        $this->assertWithinTransaction();

        $existing = ContributionShare::query()
            ->where('capital_contribution_id', $contribution->getKey())
            ->where('entry_type', ContributionEntryType::Emission->value)
            ->first();

        if ($existing instanceof ContributionShare) {
            return $existing;
        }

        return $this->issue(
            holder: $contribution->contributor,
            actor: $actor,
            origin: ContributionOrigin::Apport,
            occurredOn: $approvedAt->setTimezone('Africa/Niamey')->startOfDay(),
            baseAmount: (int) $contribution->contribution_amount,
            reason: 'Apport d’argent personnel approuvé par le second directeur.',
            attributes: ['capital_contribution_id' => $contribution->getKey()],
            action: 'contribution_shares_issued_from_capital',
        );
    }

    /** Total des parts d'un directeur : émissions moins annulations. */
    public function totalFor(User $holder): int
    {
        return $this->netShares(ContributionShare::query()->where('holder_id', $holder->getKey()));
    }

    /**
     * Registre complet tel que l'écran le présente : un bloc par directeur, ses lignes du plus
     * récent au plus ancien, et la trajectoire de son pourcentage pour que la dilution se constate.
     *
     * @return array{total_shares: int, generated_at: string, method: string, directors: list<array<string, mixed>>}
     */
    public function register(): array
    {
        $entries = ContributionShare::query()
            ->with(['holder.person', 'contract.client', 'payment', 'capitalContribution'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();
        $directors = User::query()->role('direction')->with('person')->orderBy('id')->get();
        $holders = $directors->keyBy(fn (User $director): int => (int) $director->getKey());

        foreach ($entries as $entry) {
            $holderId = (int) $entry->holder_id;

            if (! $holders->has($holderId)) {
                // Un directeur parti garde ses parts : le registre continue de les porter.
                $holders->put($holderId, $entry->holder);
            }
        }

        $totalShares = (int) $entries->sum(fn (ContributionShare $entry): int => $entry->signedShares());
        $timelines = $this->timelines($entries, $holders->keys()->all());
        $basisPoints = $this->basisPoints(
            $holders->map(fn (User $holder): int => (int) $entries->where('holder_id', $holder->getKey())
                ->sum(fn (ContributionShare $entry): int => $entry->signedShares()))->all(),
            $totalShares,
        );

        return [
            'total_shares' => $totalShares,
            'generated_at' => CarbonImmutable::now('Africa/Niamey')->toDateString(),
            'method' => 'Parts émises = montant × coefficient de millésime, en division entière. '
                .'Pourcentage = parts détenues ÷ total des parts émises, calculé à la lecture : '
                .'une contribution nouvelle dilue le pourcentage des autres sans jamais réduire leurs parts.',
            'directors' => $holders->map(fn (User $holder): array => $this->directorBlock(
                holder: $holder,
                entries: $entries->where('holder_id', $holder->getKey()),
                basisPoints: $totalShares > 0 ? $basisPoints[(int) $holder->getKey()] : null,
                timeline: $timelines[(int) $holder->getKey()] ?? [],
            ))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function issue(
        User $holder,
        User $actor,
        ContributionOrigin $origin,
        CarbonImmutable $occurredOn,
        int $baseAmount,
        string $reason,
        array $attributes,
        string $action,
    ): ContributionShare {
        $vintage = $this->vintage->at($occurredOn);
        $entry = new ContributionShare([
            ...$attributes,
            'holder_id' => $holder->getKey(),
            'origin' => $origin->value,
            'entry_type' => ContributionEntryType::Emission->value,
            'base_amount' => Money::from($baseAmount)->value(),
            'vintage_year' => $vintage['year'],
            'coefficient_basis_points' => $vintage['basis_points'],
            'issued_shares' => $this->vintage->shares($baseAmount, $vintage['basis_points']),
            'occurred_on' => $occurredOn->toDateString(),
            'reason' => $reason,
            'created_by' => $actor->getKey(),
        ]);

        $this->auditLogger->runExplicitly(
            auditable: $entry,
            operation: fn (): bool => $entry->saveOrFail(),
            actorId: (int) $actor->getKey(),
            actorLabel: $this->actorLabel($actor),
            action: $action,
            newValues: $entry->getAttributes(),
            reason: $reason,
        );

        return $entry;
    }

    private function directorContributor(Contract $contract): ?User
    {
        if ($contract->contributor_id === null) {
            return null;
        }

        $contributor = $contract->contributor;

        return $contributor instanceof User && $contributor->hasRole('direction') ? $contributor : null;
    }

    /**
     * @param  Collection<int, ContributionShare>  $entries
     * @param  list<array{occurred_on: string, percentage_label: string}>  $timeline
     * @return array<string, mixed>
     */
    private function directorBlock(User $holder, Collection $entries, ?int $basisPoints, array $timeline): array
    {
        $shares = (int) $entries->sum(fn (ContributionShare $entry): int => $entry->signedShares());
        // Une annulation retranche son assiette : le montant affiché est le net réellement acquis.
        // Jamais négatif : une annulation suit toujours l'émission qu'elle extourne.
        $netBase = fn (ContributionOrigin $origin): int => max(0, (int) $entries->where('origin', $origin)->sum(
            fn (ContributionShare $entry): int => $entry->entry_type === ContributionEntryType::Annulation ? -$entry->base_amount : $entry->base_amount,
        ));

        return [
            'id' => (int) $holder->getKey(),
            'name' => $holder->person->full_name,
            'shares' => $shares,
            'shares_label' => number_format($shares, 0, '', "\u{202F}"),
            'percentage_label' => $this->percentageLabel($basisPoints),
            'percentage_basis_points' => $basisPoints ?? 0,
            'contributed_amount_label' => Money::from($netBase(ContributionOrigin::Apport))->format(),
            'brought_amount_label' => Money::from($netBase(ContributionOrigin::Encaissement))->format(),
            'entries' => $entries
                ->sortByDesc(fn (ContributionShare $entry): string => $this->chronologicalKey($entry))
                ->values()
                ->map(fn (ContributionShare $entry): array => $this->entryLine($entry))
                ->all(),
            'timeline' => $timeline,
        ];
    }

    /** Clé de tri stable : la date d'abord, l'identifiant ensuite pour départager un même jour. */
    private function chronologicalKey(ContributionShare $entry): string
    {
        return $entry->occurred_on->toDateString().'#'.str_pad((string) $entry->getKey(), 12, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed> */
    private function entryLine(ContributionShare $entry): array
    {
        return [
            'id' => (int) $entry->getKey(),
            'origin' => $entry->origin->value,
            'origin_label' => $entry->origin->label(),
            'entry_type' => $entry->entry_type->value,
            'entry_type_label' => $entry->entry_type->label(),
            // Une ligne d'encaissement porte toujours son contrat : la clé est posée à l'émission
            // et aucun contrat ne disparaît — la suppression physique est interdite.
            'source_label' => $entry->origin === ContributionOrigin::Encaissement
                ? sprintf('%s · %s', $entry->contract->reference, $entry->contract->client->name)
                : 'Apport d’argent personnel',
            'receipt_number' => $entry->payment?->receipt_number,
            'base_amount' => $entry->base_amount,
            'base_amount_label' => Money::from($entry->base_amount)->format(),
            'vintage_year' => $entry->vintage_year,
            'vintage_label' => 'Année '.$entry->vintage_year,
            'coefficient_label' => $this->vintage->label($entry->coefficient_basis_points),
            'issued_shares' => $entry->signedShares(),
            'issued_shares_label' => ($entry->signedShares() < 0 ? '− ' : '').number_format(abs($entry->signedShares()), 0, '', "\u{202F}"),
            'occurred_on' => $entry->occurred_on->toDateString(),
            'reason' => $entry->reason,
        ];
    }

    /**
     * Trajectoire du pourcentage de chaque directeur, un point par ligne du registre.
     *
     * @param  Collection<int, ContributionShare>  $entries
     * @param  list<int>  $holderIds
     * @return array<int, list<array{occurred_on: string, percentage_label: string}>>
     */
    private function timelines(Collection $entries, array $holderIds): array
    {
        /** @var array<int, int> $running */
        $running = array_fill_keys($holderIds, 0);
        $total = 0;
        /** @var array<int, list<array{occurred_on: string, percentage_label: string}>> $timelines */
        $timelines = array_fill_keys($holderIds, []);

        foreach ($entries as $entry) {
            $holderId = (int) $entry->holder_id;
            $running[$holderId] = ($running[$holderId] ?? 0) + $entry->signedShares();
            $total += $entry->signedShares();

            $basisPoints = $this->basisPoints($running, $total);

            foreach (array_keys($running) as $observedId) {
                $timelines[$observedId][] = [
                    'occurred_on' => $entry->occurred_on->toDateString(),
                    'percentage_label' => $this->percentageLabel($total > 0 ? $basisPoints[$observedId] : null),
                ];
            }
        }

        return $timelines;
    }

    /**
     * Pourcentages en points de base entiers, répartis au plus fort reste : la somme vaut toujours
     * exactement 10 000, soit 100,00 %. Arrondir chaque directeur séparément afficherait 100,01 %
     * pour 15,625 % + 84,375 %. Aucun flottant ne circule ; à reste égal, l'ordre du registre
     * départage, sans aucun classement entre personnes.
     *
     * @param  array<int, int>  $sharesByHolder
     * @return array<int, int>
     */
    private function basisPoints(array $sharesByHolder, int $totalShares): array
    {
        if ($totalShares < 1) {
            return array_map(fn (): int => 0, $sharesByHolder);
        }

        $floors = [];
        $remainders = [];

        foreach ($sharesByHolder as $holderId => $shares) {
            $scaled = max(0, $shares) * 10_000;
            $floors[$holderId] = intdiv($scaled, $totalShares);
            $remainders[$holderId] = $scaled % $totalShares;
        }

        $missing = 10_000 - array_sum($floors);
        // Tri stable : à reste égal, l'ordre d'origine est conservé.
        uasort($remainders, fn (int $left, int $right): int => $right <=> $left);

        foreach (array_keys($remainders) as $holderId) {
            if ($missing < 1) {
                break;
            }

            if ($remainders[$holderId] > 0) {
                $floors[$holderId]++;
                $missing--;
            }
        }

        return $floors;
    }

    private function percentageLabel(?int $basisPoints): string
    {
        if ($basisPoints === null) {
            return '—';
        }

        return str_replace('.', ',', number_format($basisPoints / 100, 2, '.', '')).' %';
    }

    /** @param Builder<ContributionShare> $query */
    private function netShares(Builder $query): int
    {
        return (int) (clone $query)->where('entry_type', ContributionEntryType::Emission->value)->sum('issued_shares')
            - (int) (clone $query)->where('entry_type', ContributionEntryType::Annulation->value)->sum('issued_shares');
    }

    private function assertWithinTransaction(): void
    {
        if (ContributionShare::query()->getConnection()->transactionLevel() < 1) {
            throw new RuntimeException('L’écriture du registre des parts doit appartenir à une transaction active.');
        }
    }

    private function actorLabel(User $actor): string
    {
        return $actor->person()->value('full_name') ?? "Compte #{$actor->getKey()}";
    }
}

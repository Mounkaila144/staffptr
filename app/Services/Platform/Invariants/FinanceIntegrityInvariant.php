<?php

namespace App\Services\Platform\Invariants;

use App\Enums\MonthlyReportState;
use App\Enums\ReconciliationState;
use App\Enums\ReserveMovementType;
use App\Models\Finance\MonthlyReport;
use App\Models\Finance\Reconciliation;
use App\Models\Finance\ReserveMovement;
use App\Models\Finance\ShareEntitlement;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class FinanceIntegrityInvariant implements InvariantCheck
{
    public function check(): InvariantResult
    {
        try {
            $badShares = ShareEntitlement::query()->whereNull('reversed_at')->selectRaw('payment_id, SUM(share_amount) AS total, MAX(base_amount) AS base')->groupBy('payment_id')->get()->filter(fn (ShareEntitlement $row): bool => (int) $row->getAttribute('total') !== (int) $row->getAttribute('base'))->pluck('payment_id')->all();
            $badReserve = ReserveMovement::query()->where('type', ReserveMovementType::Usage->value)->where('approval_state', 'approved')->where(function (Builder $query): void {
                $query->whereNull('reason')->orWhereNull('reconstitution_plan')->orWhereNull('first_approved_by')->orWhereNull('second_approved_by')->orWhereColumn('first_approved_by', 'second_approved_by');
            })->pluck('id')->all();
            $badReconciliations = Reconciliation::query()->where('state', ReconciliationState::Validated->value)->where(function (Builder $query): void {
                $query->whereNull('controlled_by')->orWhereColumn('prepared_by', 'controlled_by')->orWhere(function (Builder $difference): void {
                    $difference->where('difference_amount', '>', 0)->where(function (Builder $missing): void {
                        $missing->whereNull('difference_explanation')->orWhereNull('responsible_id')->orWhereNull('corrective_action');
                    });
                });
            })->pluck('id')->all();
            $badReports = MonthlyReport::query()->where('state', MonthlyReportState::Validated->value)->where(function (Builder $query): void {
                $query->whereNull('controlled_by')->orWhereColumn('prepared_by', 'controlled_by')->orWhereNull('validated_by')->orWhereDoesntHave('closure');
            })->pluck('id')->all();
        } catch (Throwable) {
            return InvariantResult::fail('Intégrité du livre financier', 'données illisibles', 'parts, réserve, rapprochements et clôtures cohérents');
        }

        $issues = [];
        if ($badShares !== []) {
            $issues[] = 'parts reçus #'.implode(', #', $badShares);
        }
        if ($badReserve !== []) {
            $issues[] = 'réserve #'.implode(', #', $badReserve);
        }
        if ($badReconciliations !== []) {
            $issues[] = 'rapprochements #'.implode(', #', $badReconciliations);
        }
        if ($badReports !== []) {
            $issues[] = 'rapports #'.implode(', #', $badReports);
        }
        $observed = $issues === [] ? 'aucun écart financier' : implode(' ; ', $issues);

        return $issues === []
            ? InvariantResult::pass('Intégrité du livre financier', $observed, 'parts, réserve, rapprochements et clôtures cohérents')
            : InvariantResult::fail('Intégrité du livre financier', $observed, 'parts, réserve, rapprochements et clôtures cohérents');
    }
}

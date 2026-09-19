<?php

namespace Tests\Unit;

use App\Enums\ExpenseState;
use App\Models\Finance\Expense;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExpensePayabilityTest extends TestCase
{
    #[Test]
    public function ac_4_only_an_approved_expense_is_payable(): void
    {
        foreach ([
            [ExpenseState::Demandee, false],
            [ExpenseState::Refusee, false],
            [ExpenseState::Annulee, false],
            [ExpenseState::Approuvee, true],
        ] as [$state, $expected]) {
            $expense = new Expense;
            $expense->forceFill(['state' => $state->value]);

            $this->assertSame($expected, $expense->isPayable(), "État testé : {$state->value}");
        }
    }
}

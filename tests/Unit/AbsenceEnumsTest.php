<?php

namespace Tests\Unit;

use App\Enums\AbsenceState;
use App\Enums\AbsenceType;
use PHPUnit\Framework\TestCase;

class AbsenceEnumsTest extends TestCase
{
    public function test_ac_1_absence_types_have_stable_values_and_french_labels(): void
    {
        $this->assertSame(['conge', 'maladie', 'autre'], array_column(AbsenceType::cases(), 'value'));
        $this->assertSame(['Congé', 'Maladie', 'Autre'], array_map(fn (AbsenceType $type): string => $type->label(), AbsenceType::cases()));
    }

    public function test_ac_2_absence_states_have_stable_values_and_french_labels(): void
    {
        $this->assertSame(['demandee', 'approuvee', 'refusee', 'annulee'], array_column(AbsenceState::cases(), 'value'));
        $this->assertSame(['Demandée', 'Approuvée', 'Refusée', 'Annulée'], array_map(fn (AbsenceState $state): string => $state->label(), AbsenceState::cases()));
    }
}

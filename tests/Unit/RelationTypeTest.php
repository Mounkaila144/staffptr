<?php

namespace Tests\Unit;

use App\Enums\RelationType;
use PHPUnit\Framework\TestCase;

class RelationTypeTest extends TestCase
{
    public function test_ac_1_relation_types_expose_the_expected_database_values_and_french_labels(): void
    {
        $this->assertSame(
            ['dirigeant', 'employe', 'contractuel', 'stagiaire'],
            array_column(RelationType::cases(), 'value'),
        );
        $this->assertSame('Dirigeant', RelationType::Dirigeant->label());
        $this->assertSame('Employé', RelationType::Employe->label());
        $this->assertSame('Contractuel', RelationType::Contractuel->label());
        $this->assertSame('Stagiaire', RelationType::Stagiaire->label());
    }
}

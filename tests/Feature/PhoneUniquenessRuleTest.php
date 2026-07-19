<?php

namespace Tests\Feature;

use App\Enums\UserState;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Services\Identity\IdentityService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

class PhoneUniquenessRuleTest extends IdentityTestCase
{
    public function test_rule_12_phone_is_unique_only_among_non_archived_accounts(): void
    {
        $this->requireMysqlProof();

        $person = Person::factory()->create();
        User::factory()->for($person)->archived()->create(['phone' => '+22790000001']);
        $reused = User::factory()->for($person)->active()->create(['phone' => '+22790000001']);

        $this->assertSame('+22790000001', $reused->phone);

        User::factory()->for($person)->active()->create(['phone' => '+22790000002']);

        try {
            User::factory()->for($person)->active()->create(['phone' => '+22790000002']);
            $this->fail("Le numéro d'un compte actif devait rester réservé.");
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1] ?? null);
        }
    }

    public function test_rule_12_invited_suspended_and_terminated_accounts_keep_the_phone_reserved(): void
    {
        $this->requireMysqlProof();

        $person = Person::factory()->create();

        foreach ([UserState::Invite, UserState::Suspendu, UserState::Termine] as $index => $state) {
            $phone = '+2279100000'.($index + 1);
            User::factory()->for($person)->create(['phone' => $phone, 'state' => $state]);

            try {
                User::factory()->for($person)->active()->create(['phone' => $phone]);
                $this->fail("L'état {$state->value} ne devait pas libérer le numéro.");
            } catch (QueryException $exception) {
                $this->assertSame(1062, $exception->errorInfo[1] ?? null);
            }
        }
    }

    public function test_rule_12_archiving_recalculates_the_generated_key_and_allows_many_archives(): void
    {
        $this->requireMysqlProof();

        $person = Person::factory()->create();
        $phone = '+22792000001';
        $first = User::factory()->for($person)->active()->create(['phone' => $phone]);

        app(IdentityService::class)->changeUserState(
            $first,
            UserState::Archive,
            null,
            'Direction test',
            'Compte remplacé',
        );
        User::factory()->for($person)->archived()->create(['phone' => $phone]);
        $active = User::factory()->for($person)->active()->create(['phone' => $phone]);

        $this->assertNull($first->fresh()->getRawOriginal('phone_unique_key'));
        $this->assertSame($phone, $active->fresh()->getRawOriginal('phone_unique_key'));
        $this->assertSame(3, User::query()->where('phone', $phone)->count());
    }

    public function test_rule_12_mysql_metadata_proves_a_stored_generated_column_and_unique_index(): void
    {
        $this->requireMysqlProof();

        $column = DB::selectOne(<<<'SQL'
            SELECT EXTRA, GENERATION_EXPRESSION
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'phone_unique_key'
            SQL);
        $index = DB::selectOne(<<<'SQL'
            SELECT NON_UNIQUE, COLUMN_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND INDEX_NAME = 'users_phone_active_unique'
            SQL);

        $this->assertNotNull($column);
        $this->assertStringContainsString('STORED GENERATED', strtoupper((string) $column->EXTRA));
        $this->assertStringContainsString('archive', mb_strtolower((string) $column->GENERATION_EXPRESSION));
        $this->assertStringContainsString('phone', mb_strtolower((string) $column->GENERATION_EXPRESSION));
        $this->assertNotNull($index);
        $this->assertSame(0, (int) $index->NON_UNIQUE);
        $this->assertSame('phone_unique_key', $index->COLUMN_NAME);
    }
}

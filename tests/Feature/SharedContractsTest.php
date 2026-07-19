<?php

namespace Tests\Feature;

use App\Support\DateTimeFormatter;
use App\Support\Money;
use App\Support\PhoneNumber;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\Support\UsesSeparatedDatabaseConnections;
use Tests\TestCase;

class SharedContractsTest extends TestCase
{
    use UsesSeparatedDatabaseConnections;

    public function test_ac_1_rejects_a_float_before_monetary_persistence(): void
    {
        $this->migrationSchema()->create('money_contract_probes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('total_amount');
        });
        $this->grantApplicationTablePrivileges(
            'money_contract_probes',
            ['SELECT', 'INSERT'],
        );

        try {
            $rejected = false;

            try {
                $amount = Money::from(1_250.50)->value();
                Schema::getConnection()->table('money_contract_probes')->insert([
                    'total_amount' => $amount,
                ]);
            } catch (InvalidArgumentException) {
                $rejected = true;
            }

            $this->assertTrue($rejected);
            $this->assertDatabaseCount('money_contract_probes', 0);
        } finally {
            $this->migrationSchema()->dropIfExists('money_contract_probes');
        }
    }

    public function test_ac_1_bis_exposes_an_integer_for_persistence_and_exports(): void
    {
        $money = Money::from(1_250_000);

        $this->assertIsInt($money->value());
        $this->assertSame(1_250_000, $money->value());
        $this->assertSame("1\u{202F}250\u{202F}000 FCFA", $money->format());
    }

    public function test_ac_3_normalizes_all_required_phone_number_forms(): void
    {
        foreach (['90123456', '+22790123456', '00227 90 12 34 56'] as $input) {
            $this->assertSame('+22790123456', PhoneNumber::normalize($input));
        }

        $this->assertSame('+22720123456', PhoneNumber::normalize('20 12 34 56'));
    }

    public function test_ac_4_returns_the_exact_french_validation_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(PhoneNumber::INVALID_MESSAGE);

        PhoneNumber::normalize('numéro invalide');
    }

    public function test_ac_5_stores_utc_and_displays_the_next_civil_day_in_niamey(): void
    {
        $utcTimestamp = new DateTimeImmutable('2026-07-18 23:30:00', new DateTimeZone('UTC'));

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Africa/Niamey', config('app.display_timezone'));
        $this->assertSame('19/07/2026 00:30', DateTimeFormatter::format($utcTimestamp));
        $this->assertSame('2026-07-18 23:30', $utcTimestamp->format('Y-m-d H:i'));
    }

    public function test_ac_6_uses_the_required_regional_configuration(): void
    {
        $this->assertSame('fr', config('app.locale'));
        $this->assertSame('fr', config('app.fallback_locale'));
        $this->assertSame('XOF', config('app.currency'));
        $this->assertSame('Africa/Niamey', config('app.display_timezone'));

        $this->assertStringStartsWith('Ce numéro', PhoneNumber::INVALID_MESSAGE);
    }
}

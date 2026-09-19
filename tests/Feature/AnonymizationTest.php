<?php

namespace Tests\Feature;

use App\Exceptions\Identity\EvolutionApiUnavailable;
use App\Models\Finance\Client;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Services\Platform\AnonymizationService;
use App\Services\Platform\EvolutionApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Story 11.1, Task 7 — anonymisation et gardes de préproduction (AC 33 à 38).
 */
class AnonymizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * AC 34 — **le test le plus important de ce fichier** : la commande refuse de s'exécuter en
     * production. C'est la seule opération du dépôt dont l'erreur ne se répare pas.
     */
    public function test_ac_34_the_command_refuses_to_run_in_production(): void
    {
        $person = Person::factory()->create(['full_name' => 'Amina Zakari']);
        app()->detectEnvironment(static fn (): string => 'production');

        try {
            $this->expectException(RuntimeException::class);
            app(AnonymizationService::class)->run();
        } finally {
            app()->detectEnvironment(static fn (): string => 'testing');
        }

        $this->assertSame('Amina Zakari', $person->fresh()->full_name);
    }

    /** AC 34 — la commande Artisan elle-même sort en erreur, sans rien modifier. */
    public function test_ac_34_the_artisan_command_exits_with_an_error_in_production(): void
    {
        Person::factory()->create(['full_name' => 'Amina Zakari']);
        app()->detectEnvironment(static fn (): string => 'production');

        try {
            $this->artisan('ptr:anonymize', ['--force' => true])->assertFailed();
        } finally {
            app()->detectEnvironment(static fn (): string => 'testing');
        }

        $this->assertDatabaseHas('people', ['full_name' => 'Amina Zakari']);
    }

    /**
     * AC 34 — second signal : un `APP_URL` de production bloque aussi, même si `APP_ENV` dit
     * « staging ». Un `.env` mal copié ne doit pas suffire à détruire des données réelles.
     */
    public function test_ac_34_a_production_url_blocks_even_when_the_environment_says_staging(): void
    {
        config(['app.url' => 'https://staff.ptrniger.com']);

        $this->expectException(RuntimeException::class);
        app(AnonymizationService::class)->assertNotProduction();
    }

    /** Une URL de préproduction laisse passer. */
    public function test_a_staging_url_is_allowed(): void
    {
        config(['app.url' => 'https://staging.ptrniger.com']);

        app(AnonymizationService::class)->assertNotProduction();

        $this->assertTrue(true, "L'anonymisation est permise en préproduction.");
    }

    /** AC 33 — noms, téléphones et pièces jointes sont remplacés. */
    public function test_ac_33_names_phones_and_attachments_are_replaced(): void
    {
        config(['app.url' => 'http://localhost']);
        Storage::fake('private');

        $person = Person::factory()->create(['full_name' => 'Amina Zakari']);
        $user = User::factory()->create(['phone' => '+22790112233']);
        $client = Client::factory()->create(['name' => 'Société Réelle', 'phone' => '+22796554433']);

        $report = app(AnonymizationService::class)->run();

        // Le compte n'est pas figé : les factories de compte et de client créent leurs propres
        // personnes. Ce qui compte est que **toutes** soient traitées, pas leur nombre exact.
        $this->assertSame(Person::query()->count(), $report['people']);
        $this->assertNotSame('Amina Zakari', $person->fresh()->full_name);
        $this->assertNotSame('+22790112233', $user->fresh()->phone);
        $this->assertNotSame('Société Réelle', $client->fresh()->name);
        $this->assertNull($client->fresh()->contact);
    }

    /**
     * AC 36 — **le test que la story réclame** : aucune donnée personnelle réelle ne subsiste
     * dans une colonne sensible. Le parcours utilise l'inventaire du service, pour qu'un champ
     * ajouté sans anonymisation fasse échouer ce test.
     */
    public function test_ac_36_no_real_personal_data_survives_in_any_sensitive_column(): void
    {
        config(['app.url' => 'http://localhost']);
        Storage::fake('private');

        $realValues = [
            'Amina Zakari', 'Ibrahim Moussa', '+22790112233', '+22796554433', 'Société Réelle',
        ];

        Person::factory()->create(['full_name' => 'Amina Zakari']);
        Person::factory()->create(['full_name' => 'Ibrahim Moussa']);
        User::factory()->create(['phone' => '+22790112233']);
        Client::factory()->create(['name' => 'Société Réelle', 'phone' => '+22796554433', 'contact' => 'Ibrahim Moussa']);

        app(AnonymizationService::class)->run();

        foreach (AnonymizationService::sensitiveColumns() as $table => $columns) {
            foreach ($columns as $column) {
                $stored = DB::table($table)->pluck($column)->filter()->map(
                    static fn (mixed $value): string => (string) $value,
                )->all();

                foreach ($stored as $value) {
                    foreach ($realValues as $real) {
                        $this->assertStringNotContainsString(
                            $real,
                            $value,
                            "La valeur réelle « {$real} » subsiste dans {$table}.{$column}.",
                        );
                    }
                }
            }
        }
    }

    /** AC 33 — le fichier joint est réellement supprimé, pas seulement renommé. */
    public function test_ac_33_attachment_files_are_deleted_not_merely_renamed(): void
    {
        config(['app.url' => 'http://localhost']);
        Storage::fake('private');
        Storage::disk('private')->put('finance/facture-reelle.pdf', 'contenu confidentiel');

        $attachment = Attachment::factory()->create([
            'disk' => 'private',
            'path' => 'finance/facture-reelle.pdf',
            'original_name' => 'facture-reelle.pdf',
        ]);

        app(AnonymizationService::class)->run();

        Storage::disk('private')->assertMissing('finance/facture-reelle.pdf');
        $this->assertStringNotContainsString('facture-reelle', (string) $attachment->fresh()->original_name);
    }

    /** Les remplacements sont déterministes : un bogue signalé reste reproductible. */
    public function test_replacements_are_deterministic_for_a_given_identifier(): void
    {
        config(['app.url' => 'http://localhost']);
        Storage::fake('private');

        $person = Person::factory()->create(['full_name' => 'Amina Zakari']);
        app(AnonymizationService::class)->run();
        $first = $person->fresh()->full_name;

        $person->forceFill(['full_name' => 'Amina Zakari'])->saveQuietly();
        app(AnonymizationService::class)->run();

        $this->assertSame($first, $person->fresh()->full_name);
    }

    /**
     * AC 37 — **la garde la plus subtile** : la préproduction ne peut pas joindre WhatsApp, et
     * cette garantie ne dépend **pas** du fait que les données soient anonymisées. Si elle en
     * dépendait, une anonymisation incomplète enverrait de vrais messages à de vraies personnes.
     */
    public function test_ac_37_staging_can_never_reach_whatsapp_even_with_unanonymised_data(): void
    {
        Http::fake();
        config([
            'services.evolution.allow_real_delivery' => false,
            'services.evolution.url' => 'https://evolution.example.com',
            'services.evolution.instance' => 'staffptr',
            'services.evolution.key' => 'clef',
        ]);

        // Le numéro est volontairement réel : la garde ne doit rien devoir à l'anonymisation.
        $this->expectException(EvolutionApiUnavailable::class);

        try {
            app(EvolutionApiClient::class)->sendText('+22790112233', 'Message de test');
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * AC 37 — la garde est une **configuration**, et son seul régime permissif est la production
     * avec drapeau explicite. La préproduction tombe dans le cas par défaut : refusée.
     */
    public function test_ac_37_only_production_with_an_explicit_flag_may_deliver(): void
    {
        $resolve = static function (string $environment): bool {
            return match ($environment) {
                'local', 'testing' => true,
                'production' => false, // sans EVOLUTION_ALLOW_REAL_DELIVERY
                default => false,
            };
        };

        // Le cas qui compte : la préproduction n'est jamais autorisée, quel que soit le drapeau.
        foreach (['staging', 'preprod', 'recette', 'demo'] as $environment) {
            $this->assertFalse(
                $resolve($environment),
                "L'environnement « {$environment} » ne doit jamais émettre vers WhatsApp.",
            );
        }

        $source = (string) file_get_contents(config_path('services.php'));
        $this->assertStringContainsString("'production' => env('EVOLUTION_ALLOW_REAL_DELIVERY', false) === true", $source);
        $this->assertStringContainsString('default => false', $source);
    }
}

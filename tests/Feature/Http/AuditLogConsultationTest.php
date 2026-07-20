<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Services\Identity\RoleAssignmentService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\IdentityTestCase;

class AuditLogConsultationTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_1_direction_filters_by_author_period_object_type_and_action_with_pagination(): void
    {
        $direction = $this->userWithRole('direction');
        $expected = AuditLog::factory()->create([
            'actor_id' => $direction->getKey(),
            'actor_label' => 'Aïcha Garba',
            'occurred_at' => CarbonImmutable::parse('2026-07-20 10:00:00', 'UTC'),
            'auditable_type' => 'tests.invoice',
            'action' => 'invoice_validated',
        ]);
        AuditLog::factory()->create([
            'actor_id' => $direction->getKey(),
            'occurred_at' => CarbonImmutable::parse('2026-06-20 10:00:00', 'UTC'),
            'auditable_type' => 'tests.invoice',
            'action' => 'invoice_validated',
        ]);
        AuditLog::factory()->create([
            'actor_id' => 99,
            'occurred_at' => CarbonImmutable::parse('2026-07-20 10:00:00', 'UTC'),
            'auditable_type' => 'tests.contract',
            'action' => 'contract_created',
        ]);

        $this->actingAs($direction)
            ->get(route('audit.index', [
                'actor_id' => (string) $direction->getKey(),
                'from' => '2026-07-01',
                'to' => '2026-07-31',
                'auditable_type' => 'tests.invoice',
                'action' => 'invoice_validated',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/AuditLogs/Index')
                ->has('entries.data', 1)
                ->where('entries.data.0.id', $expected->getKey())
                ->where('filters.actor_id', (string) $direction->getKey())
                ->where('filters.from', '2026-07-01')
                ->where('filters.to', '2026-07-31')
                ->where('filters.auditable_type', 'tests.invoice')
                ->where('filters.action', 'invoice_validated')
                ->where('filtersActive', true)
                ->has('entries.current_page')
                ->has('entries.last_page'));
    }

    public function test_ac_2_all_five_non_direction_roles_receive_403_on_both_direct_urls(): void
    {
        foreach (['super_admin', 'finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            session()->flush();
            $user = $this->userWithRole($role);

            foreach (['audit.index', 'audit.export'] as $routeName) {
                $response = $this->actingAs($user)->get(route($routeName));

                $this->assertSame(
                    403,
                    $response->getStatusCode(),
                    "Le rôle {$role} a reçu {$response->getStatusCode()} sur {$routeName}.",
                );
                $this->assertFalse($response->isRedirection());
            }
        }
    }

    public function test_ac_3_entries_render_niamey_time_french_enum_labels_reason_system_actor_and_no_secrets(): void
    {
        $direction = $this->userWithRole('direction');
        AuditLog::factory()->create([
            'actor_id' => null,
            'actor_label' => 'Amorçage système',
            'occurred_at' => CarbonImmutable::parse('2026-07-20 23:30:00', 'UTC'),
            'auditable_type' => User::class,
            'auditable_id' => 42,
            'action' => 'user_state_changed',
            'old_values' => ['state' => 'suspendu', 'password_hash' => 'secret-ancien'],
            'new_values' => [
                'state' => 'archive',
                'password_hash' => 'secret-nouveau',
                'metadata' => ['verification_token' => 'secret-imbriqué', 'source' => 'console'],
            ],
            'reason' => 'Départ confirmé par la direction.',
        ]);

        $response = $this->actingAs($direction)
            ->get(route('audit.index', ['actor_id' => 'system', 'action' => 'user_state_changed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.actor', 'Amorçage système')
                ->where('entries.data.0.occurred_at', '21/07/2026 00:30')
                ->where('entries.data.0.object', 'Compte #42')
                ->where('entries.data.0.action', "Changement d'état")
                ->where('entries.data.0.changes.0.label', 'État du compte')
                ->where('entries.data.0.changes.0.old', 'Suspendu')
                ->where('entries.data.0.changes.0.new', 'Archivé')
                ->where('entries.data.0.reason', 'Départ confirmé par la direction.'));

        $this->assertStringNotContainsString('secret-ancien', $response->getContent());
        $this->assertStringNotContainsString('secret-nouveau', $response->getContent());
        $this->assertStringNotContainsString('secret-imbriqué', $response->getContent());
        $this->assertStringNotContainsString('password_hash', $response->getContent());
    }

    public function test_ac_4_filtered_export_contains_the_same_entries_and_audits_nature_and_row_count(): void
    {
        $direction = $this->userWithRole('direction');
        $first = AuditLog::factory()->create([
            'actor_id' => $direction->getKey(),
            'actor_label' => 'Direction Export',
            'auditable_type' => 'tests.invoice',
            'action' => 'test_export_target',
            'new_values' => ['reference' => 'FACTURE-ALPHA'],
        ]);
        $second = AuditLog::factory()->create([
            'actor_id' => $direction->getKey(),
            'actor_label' => 'Direction Export',
            'auditable_type' => 'tests.invoice',
            'action' => 'test_export_target',
            'new_values' => ['reference' => '+22790000000'],
        ]);
        AuditLog::factory()->create([
            'auditable_type' => 'tests.invoice',
            'action' => 'excluded_from_export',
            'new_values' => ['reference' => 'NE-PAS-EXPORTER'],
        ]);
        $filters = [
            'actor_id' => (string) $direction->getKey(),
            'auditable_type' => 'tests.invoice',
            'action' => 'test_export_target',
        ];

        $index = $this->actingAs($direction)->get(route('audit.index', $filters));
        $indexIds = collect($index->inertiaPage()['props']['entries']['data'])
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
        $this->assertSame([$first->getKey(), $second->getKey()], $indexIds);

        $export = $this->actingAs($direction)->get(route('audit.export', $filters));
        $export->assertOk();
        $this->assertStringStartsWith('text/csv', (string) $export->headers->get('Content-Type'));
        $csv = $export->streamedContent();

        $this->assertStringContainsString('FACTURE-ALPHA', $csv);
        $this->assertStringContainsString('+22790000000', $csv);
        $this->assertStringNotContainsString('NE-PAS-EXPORTER', $csv);
        $this->assertCount(3, preg_split('/\R/u', trim($csv)) ?: []);

        $audit = AuditLog::query()->where('action', 'audit_log_exported')->latest('id')->firstOrFail();
        $this->assertSame($direction->getKey(), $audit->actor_id);
        $this->assertSame("Journal d'audit", $audit->new_values['data_nature']);
        $this->assertSame(2, $audit->new_values['row_count']);
        $this->assertSame('test_export_target', $audit->new_values['filters']['action']);
        $this->assertArrayNotHasKey('rows', $audit->new_values);
        $this->assertStringNotContainsString('FACTURE-ALPHA', json_encode($audit->new_values) ?: '');
    }

    public function test_ac_5_filtered_and_real_empty_states_are_distinct_and_mobile_components_are_reused(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Platform/AuditLogs/Index.vue'));

        $this->assertStringContainsString('Aucune entrée pour ces filtres.', $source);
        $this->assertStringContainsString('Le journal ne contient encore aucune entrée.', $source);
        $this->assertStringContainsString('Réinitialiser les filtres', $source);
        $this->assertStringContainsString('sm:grid-cols-2', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringContainsString('<EmptyState', $source);
        $this->assertStringContainsString('<StatusBadge', $source);
        $this->assertStringContainsString('<FormField', $source);
        $this->assertStringContainsString('<AppButton', $source);

        $direction = $this->userWithRole('direction');
        $this->actingAs($direction)
            ->get(route('audit.index', ['action' => 'action-absente']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 0)
                ->where('filtersActive', true)
                ->where('journalHasEntries', true));
    }

    public function test_ac_1_filters_are_validated_exclusively_by_the_form_request(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)
            ->get(route('audit.index', [
                'actor_id' => 'direction',
                'from' => '20/07/2026',
                'to' => '2026-07-01',
                'auditable_type' => str_repeat('a', 121),
                'action' => str_repeat('b', 61),
            ]))
            ->assertSessionHasErrors(['actor_id', 'from', 'to', 'auditable_type', 'action']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test story 2.10');

        return $user;
    }
}

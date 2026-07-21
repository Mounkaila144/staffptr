<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use App\Models\Platform\Setting;
use App\Services\Identity\RoleAssignmentService;
use App\Services\Platform\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia;
use Tests\Support\IdentityTestCase;

class SettingManagementTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
    }

    public function test_ac_1_index_exposes_every_parameter_to_the_management_interface(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Platform/Settings/Index')
                ->has('settings', 11)
                ->where('settings.0.key', 'working_days')
                ->has('workingDayOptions', 7)
                ->has('attachmentTypeOptions', 5));
    }

    public function test_ac_3_login_lockout_uses_modified_attempt_and_duration_settings_without_redeployment(): void
    {
        $actor = User::factory()->active()->create();
        app(SettingsService::class)->update([
            'login_max_failed_attempts' => 2,
            'login_lockout_minutes' => 7,
        ], CarbonImmutable::now('UTC'), $actor);
        config()->set('login-security.rate_limit_attempts', 10);
        $target = User::factory()->active()->create(['password' => 'MotDePasse-Correct-3.4']);
        $this->freezeTime();

        $this->post(route('login.store'), ['phone' => $target->phone, 'password' => 'Erreur-1']);
        $this->post(route('login.store'), ['phone' => $target->phone, 'password' => 'Erreur-2'])
            ->assertSessionHasErrors([
                'phone' => 'Trop de tentatives. Réessayez dans 7 minutes, ou contactez la direction.',
            ]);

        $this->assertSame(2, $target->fresh()->failed_attempts);
        $this->assertSame(now('UTC')->addMinutes(7)->timestamp, $target->fresh()->locked_until?->timestamp);
    }

    public function test_ac_4_http_update_is_audited_and_records_the_effective_date(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)
            ->patch(route('settings.update'), [
                'settings' => ['reserve_percentage' => '24'],
                'effective_at' => '2026-07-21T11:30',
            ])
            ->assertRedirect(route('settings.index'));

        $this->assertSame(24, app(SettingsService::class)->reservePercentage());
        $audit = AuditLog::query()->where('action', 'setting_changed')->sole();
        $this->assertSame(20, $audit->old_values['value']);
        $this->assertSame(24, $audit->new_values['value']);
        $this->assertSame('2026-07-21 10:30:00.000', $audit->new_values['effective_at']);
    }

    public function test_ac_5_direction_and_super_admin_manage_settings_while_four_business_roles_receive_403(): void
    {
        foreach (['direction', 'super_admin'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('settings.index'))->assertOk();
            $this->resetAuthentication();
        }

        foreach (['finance', 'tuteur', 'employe', 'stagiaire'] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
            $this->actingAs($user)->postJson(route('settings.preview'), [
                'key' => 'reserve_percentage',
                'value' => 30,
            ])->assertForbidden();
            $this->actingAs($user)->patch(route('settings.update'), [
                'settings' => ['reserve_percentage' => 30],
                'effective_at' => '2026-07-21T12:00',
            ])->assertForbidden();
            $this->resetAuthentication();
        }
    }

    public function test_ac_6_preview_calculates_the_effect_before_confirmation_without_writing(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)
            ->postJson(route('settings.preview'), [
                'key' => 'reserve_percentage',
                'value' => 27,
            ])
            ->assertOk()
            ->assertJsonPath('preview.label', 'Pourcentage de réserve')
            ->assertJsonPath('preview.current_value', 20)
            ->assertJsonPath('preview.proposed_value', 27)
            ->assertJsonPath('preview.delta', 7)
            ->assertJsonFragment(['consequence' => 'La valeur passera de 20 % à 27 % dès la confirmation. Écart calculé : +7 %.']);

        $this->assertSame(20, Setting::query()->where('key', 'reserve_percentage')->sole()->value);
        $this->assertFalse(AuditLog::query()->where('action', 'setting_changed')->exists());
    }

    public function test_ac_1_update_validation_refuses_an_invalid_value(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)
            ->patch(route('settings.update'), [
                'settings' => ['reserve_percentage' => 101],
                'effective_at' => '2026-07-21T12:00',
            ])
            ->assertSessionHasErrors('settings.reserve_percentage');
    }

    public function test_ac_6_preview_validation_refuses_an_unknown_setting(): void
    {
        $direction = $this->userWithRole('direction');

        $this->actingAs($direction)
            ->postJson(route('settings.preview'), ['key' => 'unknown', 'value' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    public function test_ac_6_page_requires_preview_and_keeps_the_320px_accessibility_contract(): void
    {
        $source = (string) file_get_contents(resource_path('js/Pages/Platform/Settings/Index.vue'));

        $this->assertStringContainsString('SensitiveConfirmation', $source);
        $this->assertStringContainsString('/parametres/apercu', $source);
        $this->assertStringContainsString('Écart calculé', $source);
        $this->assertStringContainsString('FormField', $source);
        $this->assertStringContainsString('StatusBadge', $source);
        $this->assertStringContainsString('touch-target', $source);
        $this->assertStringContainsString('min-w-0', $source);
        $this->assertStringNotContainsString('overflow-x-', $source);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test HTTP story 3.4');

        return $user;
    }

    private function resetAuthentication(): void
    {
        session()->flush();
        Auth::forgetGuards();
    }
}

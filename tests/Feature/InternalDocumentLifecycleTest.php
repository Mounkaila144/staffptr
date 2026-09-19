<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Models\Platform\Attachment;
use App\Models\Platform\AuditLog;
use App\Models\Platform\InternalDocument;
use App\Models\Platform\InternalDocumentVersion;
use App\Notifications\InternalDocumentPublishedNotification;
use App\Services\Identity\RoleAssignmentService;
use App\Services\Platform\InternalDocumentService;
use App\Services\Platform\WhatsAppChannel;
use App\Support\Auditing\ImmutableRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Support\IdentityTestCase;

class InternalDocumentLifecycleTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        Queue::fake();
        Storage::fake('private');
    }

    public function test_ac_1_document_supports_body_or_file_with_version_and_effective_date(): void
    {
        $direction = $this->userWithRole('direction');
        $service = app(InternalDocumentService::class);
        $withBody = $service->publish($this->attributes(body: 'Règle interne complète.'), null, $direction);
        $attachment = $this->stagedAttachment($direction, 'reglement.pdf');
        $withFile = $service->publish($this->attributes(title: 'Règlement en PDF', body: null), $attachment, $direction);

        $bodyVersion = $withBody->currentVersion()->sole();
        $fileVersion = $withFile->currentVersion()->with('attachment')->sole();
        $this->assertSame(1, $bodyVersion->version_number);
        $this->assertSame('Règle interne complète.', $bodyVersion->body);
        $this->assertSame(today('Africa/Niamey')->toDateString(), $bodyVersion->effective_date->toDateString());
        $this->assertNull($fileVersion->body);
        $this->assertSame($attachment->getKey(), $fileVersion->attachment?->getKey());
        $this->assertSame(InternalDocumentVersion::class, $attachment->fresh()->getAttribute('attachable_type'));
        Storage::disk('private')->assertExists($attachment->path);
    }

    public function test_ac_2_acknowledgement_is_timestamped_and_unique_per_user_and_version(): void
    {
        $direction = $this->userWithRole('direction');
        $employee = $this->userWithRole('employe');
        $service = app(InternalDocumentService::class);
        $document = $service->publish($this->attributes(), null, $direction);
        $acknowledgement = $service->acknowledge($document, $employee);

        $this->assertSame($employee->getKey(), $acknowledgement->user_id);
        $this->assertSame($document->current_version_id, $acknowledgement->internal_document_version_id);
        $this->assertNotNull($acknowledgement->acknowledged_at);
        $this->assertDatabaseCount('internal_document_acknowledgements', 1);

        try {
            $service->acknowledge($document, $employee);
            $this->fail('Une version ne doit être acceptée qu’une fois par utilisateur.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('acknowledgement', $exception->errors());
        }

        $this->assertDatabaseCount('internal_document_acknowledgements', 1);
    }

    public function test_ac_3_new_version_notifies_active_authorized_users_and_resets_acceptance(): void
    {
        $direction = $this->userWithRole('direction');
        $employee = $this->userWithRole('employe');
        $pending = $this->userWithRole('stagiaire');
        $superAdmin = $this->userWithRole('super_admin');
        $inactive = $this->userWithRole('employe', active: false);
        $service = app(InternalDocumentService::class);
        $document = $service->publish($this->attributes(), null, $direction);
        $versionOne = $document->currentVersion()->sole();
        $service->acknowledge($document, $employee);
        Notification::fake();

        $document = $service->publishVersion(
            $document,
            $this->attributes(body: 'Deuxième version du règlement.'),
            null,
            $direction,
        );
        $versionTwo = $document->currentVersion()->sole();

        $this->assertSame(2, $versionTwo->version_number);
        $this->assertSame(1, $versionOne->acknowledgements()->count());
        $this->assertSame(0, $versionTwo->acknowledgements()->count());
        Notification::assertSentTo(
            [$direction, $employee, $pending],
            InternalDocumentPublishedNotification::class,
            static fn (InternalDocumentPublishedNotification $notification): bool => $notification->versionNumber === 2
                && $notification->via($employee) === ['database', WhatsAppChannel::class],
        );
        Notification::assertNotSentTo($superAdmin, InternalDocumentPublishedNotification::class);
        Notification::assertNotSentTo($inactive, InternalDocumentPublishedNotification::class);
    }

    public function test_ac_3_publication_queues_database_and_whatsapp_as_distinct_channels(): void
    {
        $direction = $this->userWithRole('direction');
        $this->userWithRole('employe');

        app(InternalDocumentService::class)->publish($this->attributes(), null, $direction);

        Queue::assertPushed(
            SendQueuedNotifications::class,
            static fn (SendQueuedNotifications $job): bool => $job->channels === ['database'],
        );
        Queue::assertPushed(
            SendQueuedNotifications::class,
            static fn (SendQueuedNotifications $job): bool => $job->channels === [
                WhatsAppChannel::class,
            ],
        );
    }

    public function test_ac_4_versions_and_acknowledgements_are_immutable_and_history_is_never_deleted(): void
    {
        $direction = $this->userWithRole('direction');
        $employee = $this->userWithRole('employe');
        $service = app(InternalDocumentService::class);
        $document = $service->publish($this->attributes(), null, $direction);
        $versionOne = $document->currentVersion()->sole();
        $acknowledgement = $service->acknowledge($document, $employee);
        $service->publishVersion($document, $this->attributes(body: 'Version suivante.'), null, $direction);

        $this->assertSame([1, 2], $document->versions()->orderBy('version_number')->pluck('version_number')->all());
        $this->assertModelIsImmutable($versionOne, ['body' => 'Altération interdite.']);
        $this->assertModelIsImmutable($acknowledgement, ['acknowledged_at' => now('UTC')->subDay()]);
        $this->assertDatabaseCount('internal_document_versions', 2);
        $this->assertDatabaseCount('internal_document_acknowledgements', 1);

        try {
            $document->delete();
            $this->fail('La suppression physique du document devait être refusée.');
        } catch (LogicException) {
            $this->assertDatabaseCount('internal_documents', 1);
        }
    }

    public function test_ac_4_migration_declares_append_only_privileges_and_four_database_barriers(): void
    {
        $migration = (string) file_get_contents(
            database_path('migrations/2026_07_21_133450_create_internal_document_library_tables.php'),
        );

        foreach (['internal_document_versions', 'internal_document_acknowledgements'] as $table) {
            $this->assertStringContainsString("createImmutableBarriers(\$connectionName, '{$table}')", $migration);
        }

        $this->assertStringContainsString('CREATE TRIGGER {$triggerPrefix}_update', $migration);
        $this->assertStringContainsString('CREATE TRIGGER {$triggerPrefix}_delete', $migration);
        $this->assertStringContainsString('GRANT SELECT, INSERT ON', $migration);
        $this->assertStringContainsString("grantApplicationUpdate(\$connectionName, 'internal_documents')", $migration);
        $this->assertStringNotContainsString("grantApplicationUpdate(\$connectionName, 'internal_document_versions')", $migration);
        $this->assertStringNotContainsString("grantApplicationUpdate(\$connectionName, 'internal_document_acknowledgements')", $migration);
    }

    public function test_ac_4_mysql_triggers_block_direct_version_and_acknowledgement_mutations(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Les déclencheurs d’immuabilité sont vérifiés sur MySQL en CI.');
        }

        $direction = $this->userWithRole('direction');
        $employee = $this->userWithRole('employe');
        $service = app(InternalDocumentService::class);
        $document = $service->publish($this->attributes(), null, $direction);
        $version = $document->currentVersion()->sole();
        $acknowledgement = $service->acknowledge($document, $employee);

        foreach ([
            ['internal_document_versions', $version->getKey()],
            ['internal_document_acknowledgements', $acknowledgement->getKey()],
        ] as [$table, $identifier]) {
            try {
                DB::table($table)->where('id', $identifier)->update(['updated_at' => now()]);
                $this->fail("Le déclencheur UPDATE de {$table} devait refuser la modification.");
            } catch (QueryException) {
                $this->assertDatabaseHas($table, ['id' => $identifier]);
            }

            try {
                DB::table($table)->where('id', $identifier)->delete();
                $this->fail("Le déclencheur DELETE de {$table} devait refuser la suppression.");
            } catch (QueryException) {
                $this->assertDatabaseHas($table, ['id' => $identifier]);
            }
        }
    }

    public function test_ac_5_status_lists_acceptants_pending_users_and_request_age_for_direction(): void
    {
        $direction = $this->userWithRole('direction');
        $accepted = $this->userWithRole('employe');
        $pending = $this->userWithRole('tuteur');
        $superAdmin = $this->userWithRole('super_admin');
        $inactive = $this->userWithRole('stagiaire', active: false);
        $service = app(InternalDocumentService::class);
        $document = InternalDocument::factory()->create();
        $version = InternalDocumentVersion::factory()->for($document, 'document')->create([
            'published_by' => $direction->getKey(),
            'published_at' => now('UTC')->subDays(4),
        ]);
        $document->forceFill(['current_version_id' => $version->getKey()])->saveQuietly();
        $service->acknowledge($document, $accepted);

        $status = $service->acknowledgementStatus($document);
        $byId = collect($status['users'])->keyBy('id');

        $this->assertTrue($byId->get($accepted->getKey())['accepted']);
        $this->assertFalse($byId->get($pending->getKey())['accepted']);
        $this->assertSame(4, $byId->get($pending->getKey())['age_days']);
        $this->assertArrayNotHasKey($superAdmin->getKey(), $byId->all());
        $this->assertArrayNotHasKey($inactive->getKey(), $byId->all());
        $this->assertArrayHasKey($direction->getKey(), $byId->all());
    }

    public function test_ac_6_publication_new_version_and_acknowledgement_are_each_audited(): void
    {
        $direction = $this->userWithRole('direction');
        $employee = $this->userWithRole('employe');
        $service = app(InternalDocumentService::class);
        $document = $service->publish($this->attributes(), null, $direction);
        $service->publishVersion($document, $this->attributes(body: 'Nouvelle règle.'), null, $direction);
        $acknowledgement = $service->acknowledge($document->refresh(), $employee);

        foreach ([
            'internal_document_published' => $direction,
            'internal_document_version_published' => $direction,
            'internal_document_acknowledged' => $employee,
        ] as $action => $actor) {
            $audit = AuditLog::query()->where('action', $action)->sole();
            $this->assertSame($actor->getKey(), $audit->actor_id);
            $this->assertNotNull($audit->new_values);
        }

        $ackAudit = AuditLog::query()->where('action', 'internal_document_acknowledged')->sole();
        $this->assertSame($acknowledgement->getKey(), $ackAudit->auditable_id);
        $this->assertSame(2, $ackAudit->new_values['version_number']);
    }

    /** @return array{title: string, requires_acknowledgement: bool, body: string|null, effective_date: string} */
    private function attributes(
        string $title = 'Règlement intérieur',
        ?string $body = 'Contenu applicable à toute l’équipe.',
    ): array {
        return [
            'title' => $title,
            'requires_acknowledgement' => true,
            'body' => $body,
            'effective_date' => today('Africa/Niamey')->toDateString(),
        ];
    }

    private function stagedAttachment(User $actor, string $name): Attachment
    {
        $attachment = Attachment::factory()->for($actor->person, 'attachable')->create([
            'uploaded_by' => $actor->getKey(),
            'original_name' => $name,
        ]);
        Storage::disk('private')->put($attachment->path, "%PDF-1.4\n%%EOF");

        return $attachment;
    }

    /** @param array<string, mixed> $changes */
    private function assertModelIsImmutable(Model $model, array $changes): void
    {
        try {
            $model->forceFill($changes)->save();
            $this->fail($model::class.' devait refuser toute modification.');
        } catch (ImmutableRecordException) {
            $this->assertDatabaseHas($model->getTable(), ['id' => $model->getKey()]);
        }

        try {
            $model->delete();
            $this->fail($model::class.' devait refuser toute suppression.');
        } catch (ImmutableRecordException) {
            $this->assertDatabaseHas($model->getTable(), ['id' => $model->getKey()]);
        }
    }

    private function userWithRole(string $role, bool $active = true): User
    {
        $user = $active ? User::factory()->active()->create() : User::factory()->suspended()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Test bibliothèque interne');

        return $user->fresh('person');
    }
}

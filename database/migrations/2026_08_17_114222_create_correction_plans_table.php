<?php

use App\Enums\CorrectionPlanState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan correctif exigé par le niveau d'alerte orange (FR163, story 9.1 AC 11, 14 à 18).
 *
 * Le plan porte constat, actions, responsables, échéance et résultat attendu (AC 14), il est
 * rattaché au mois qui a déclenché l'orange (AC 15) et reste consultable ensuite. Après validation
 * il est immuable : une révision crée une nouvelle version liée par `previous_id` (AC 17), garantie
 * par déclencheur au même titre que le reste du module financier (SOC-03, SOC-04).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correction_plans', function (Blueprint $table): void {
            $table->id();
            // Premier jour du mois civil Niamey qui a déclenché le niveau orange.
            $table->date('month')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('previous_id')->nullable()->constrained('correction_plans')->restrictOnDelete();
            $table->text('finding');
            $table->text('actions');
            $table->text('responsibles');
            $table->date('due_on');
            $table->text('expected_result');
            $table->string('state', 20)->default(CorrectionPlanState::Brouillon->value)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('validated_at', 3)->nullable();
            $table->text('revision_reason')->nullable();
            $table->timestamps(3);
            $table->unique(['month', 'version']);
        });

        $this->createDeleteTrigger();
        $this->createValidatedImmutableTrigger();
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_delete_correction_plans');
        DB::unprepared('DROP TRIGGER IF EXISTS finance_no_update_validated_correction_plans');
        Schema::dropIfExists('correction_plans');
    }

    private function createDeleteTrigger(): void
    {
        $message = 'La suppression physique de correction_plans est interdite.';

        if ($this->isMysqlFamily()) {
            DB::unprepared(
                "CREATE TRIGGER finance_no_delete_correction_plans BEFORE DELETE ON correction_plans FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'"
            );

            return;
        }

        DB::unprepared(
            "CREATE TRIGGER finance_no_delete_correction_plans BEFORE DELETE ON correction_plans BEGIN SELECT RAISE(ABORT, '{$message}'); END"
        );
    }

    private function createValidatedImmutableTrigger(): void
    {
        $state = CorrectionPlanState::Valide->value;
        $message = 'Un plan correctif validé est immuable : une révision crée une nouvelle version.';

        if ($this->isMysqlFamily()) {
            DB::unprepared(
                "CREATE TRIGGER finance_no_update_validated_correction_plans BEFORE UPDATE ON correction_plans FOR EACH ROW BEGIN IF OLD.state = '{$state}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END"
            );

            return;
        }

        DB::unprepared(
            "CREATE TRIGGER finance_no_update_validated_correction_plans BEFORE UPDATE ON correction_plans WHEN OLD.state = '{$state}' BEGIN SELECT RAISE(ABORT, '{$message}'); END"
        );
    }

    private function isMysqlFamily(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};

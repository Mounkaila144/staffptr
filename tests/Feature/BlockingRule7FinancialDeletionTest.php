<?php

namespace Tests\Feature;

use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\Support\RefreshesSeparatedDatabase;
use Tests\TestCase;
use Throwable;

/**
 * **Règle métier bloquante n° 7** — la suppression financière est impossible : *modèle, route
 * **et** base* (RM-17, CA-12, architecture § 23.2).
 *
 * Les trois niveaux comptent, et c'est le sens du « et » de l'énoncé. Un garde applicatif seul
 * tombe dès qu'un script, une console `tinker` ou une régression contourne le modèle ; c'est
 * précisément le scénario que la story 10.1 veut détecter, celui d'une manipulation directe en
 * base. Le déclencheur SQL est donc la seule barrière qui vaille, et ce test la vérifie
 * explicitement en plus des deux autres.
 */
class BlockingRule7FinancialDeletionTest extends TestCase
{
    use RefreshesSeparatedDatabase;

    /** Niveau 1 — le modèle refuse `delete()` sur chaque objet financier. */
    public function test_blocking_rule_7_no_financial_model_can_be_deleted_through_eloquent(): void
    {
        $models = [
            Account::factory()->create(),
            Invoice::factory()->create(),
            Payment::factory()->create(),
            Expense::factory()->create(),
        ];

        foreach ($models as $model) {
            $refused = false;

            try {
                $model->delete();
            } catch (LogicException) {
                $refused = true;
            }

            $this->assertTrue(
                $refused,
                $model::class.' doit refuser la suppression physique au niveau du modèle.',
            );
        }
    }

    /**
     * Niveau 2 — aucune route de suppression n'existe sur le domaine financier.
     *
     * Le contrôle porte sur le verbe HTTP autant que sur le chemin : une route `DELETE` sur une
     * ressource financière serait une porte ouverte même si son contrôleur refusait aujourd'hui.
     */
    public function test_blocking_rule_7_no_delete_route_exists_on_financial_resources(): void
    {
        $offending = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('DELETE', $route->methods(), true)) {
                continue;
            }

            $offending[] = $route->uri();
        }

        $this->assertSame(
            [],
            $offending,
            'Aucune route DELETE ne doit exister : la correction se fait par contre-écriture. Routes trouvées : '
                .implode(', ', $offending),
        );
    }

    /**
     * Niveau 3 — **la base elle-même** refuse la suppression, déclencheur à l'appui.
     *
     * C'est le seul niveau qui résiste à une manipulation directe, donc le seul qui prouve
     * réellement RM-17. Le test contourne volontairement Eloquent pour attaquer la table.
     */
    public function test_blocking_rule_7_the_database_itself_refuses_a_direct_delete(): void
    {
        $payment = Payment::factory()->create();
        $refused = false;

        try {
            DB::table('payments')->where('id', $payment->getKey())->delete();
        } catch (Throwable) {
            $refused = true;
        }

        $this->assertTrue(
            $refused,
            'Le déclencheur de base doit refuser une suppression directe, hors de tout garde applicatif.',
        );
        $this->assertDatabaseHas('payments', ['id' => $payment->getKey()]);
    }

    /** La contre-écriture reste possible : interdire la suppression ne fige pas la comptabilité. */
    public function test_blocking_rule_7_a_correction_remains_possible_by_counter_entry(): void
    {
        $payment = Payment::factory()->create(['received_amount' => 500_000]);

        $reversal = Payment::factory()->create([
            'received_amount' => 500_000,
            'reversal_of_id' => $payment->getKey(),
            'cancellation_reason' => 'Encaissement saisi en double.',
        ]);

        $this->assertSame((int) $payment->getKey(), (int) $reversal->reversal_of_id);
        $this->assertDatabaseHas('payments', ['id' => $payment->getKey()]);
        $this->assertDatabaseHas('payments', ['id' => $reversal->getKey()]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create();
    }
}

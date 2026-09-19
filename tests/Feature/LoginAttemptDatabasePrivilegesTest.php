<?php

namespace Tests\Feature;

use App\Models\Identity\LoginAttempt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\IdentityTestCase;

class LoginAttemptDatabasePrivilegesTest extends IdentityTestCase
{
    public function test_task_1_application_account_can_update_but_cannot_delete_login_attempts(): void
    {
        $this->requireMysqlProof();

        $attempt = LoginAttempt::factory()->create(['successful' => false]);

        DB::table('login_attempts')
            ->where('id', $attempt->getKey())
            ->update(['user_agent' => 'Agent mis à jour']);

        $this->assertDatabaseHas('login_attempts', [
            'id' => $attempt->getKey(),
            'user_agent' => 'Agent mis à jour',
        ]);

        try {
            DB::table('login_attempts')->where('id', $attempt->getKey())->delete();
            $this->fail('DELETE devait rester refusé sur login_attempts.');
        } catch (QueryException $exception) {
            $this->assertSame(1142, $exception->errorInfo[1] ?? null);
        }

        $this->assertDatabaseHas('login_attempts', ['id' => $attempt->getKey()]);
    }
}

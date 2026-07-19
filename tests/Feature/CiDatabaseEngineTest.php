<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CiDatabaseEngineTest extends TestCase
{
    public function test_ac_2_ci_integration_suite_really_uses_mysql_8(): void
    {
        if (! config('app.ci')) {
            $this->markTestSkipped('Ce garde-fou s’exécute dans GitHub Actions.');
        }

        $connection = DB::connection();
        $version = $connection->selectOne('select version() as version');

        $this->assertSame('mysql', $connection->getDriverName());
        $this->assertNotNull($version);
        $this->assertMatchesRegularExpression('/^8\.0\./', (string) $version->version);
    }
}

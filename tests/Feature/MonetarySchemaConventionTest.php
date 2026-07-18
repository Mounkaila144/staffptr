<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use SplFileInfo;
use Tests\TestCase;

class MonetarySchemaConventionTest extends TestCase
{
    public function test_ac_2_migrations_respect_the_monetary_column_convention(): void
    {
        $this->assertSame([], $this->violationsIn(database_path('migrations')));
    }

    public function test_ac_2_guard_detects_an_invalid_migration_fixture(): void
    {
        $violations = $this->violationsIn(base_path('tests/Fixtures/Migrations'));

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('total_amount', $violations[0]);
        $this->assertStringContainsString('decimal', $violations[0]);
    }

    /** @return list<string> */
    private function violationsIn(string $directory): array
    {
        $violations = [];
        $forbiddenTypes = ['decimal', 'float', 'double', 'unsignedDecimal'];
        $pattern = "/\\\$table->(?<type>[A-Za-z][A-Za-z0-9_]*)\\(\\s*['\"](?<column>[A-Za-z0-9_]+)['\"](?<tail>[^;]*);/m";

        foreach (File::allFiles($directory) as $file) {
            preg_match_all($pattern, $file->getContents(), $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $type = $match['type'];
                $column = $match['column'];
                $tail = $match['tail'];

                if (in_array($type, $forbiddenTypes, true)) {
                    $violations[] = $this->violation($file, $column, $type);

                    continue;
                }

                if (! str_ends_with($column, '_amount')) {
                    continue;
                }

                $isUnsignedBigInteger = $type === 'unsignedBigInteger'
                    || ($type === 'bigInteger' && str_contains($tail, '->unsigned()'));

                if (! $isUnsignedBigInteger) {
                    $violations[] = $this->violation($file, $column, $type);
                }
            }
        }

        return $violations;
    }

    private function violation(SplFileInfo $file, string $column, string $type): string
    {
        return sprintf('%s : la colonne %s utilise %s.', $file->getFilename(), $column, $type);
    }
}

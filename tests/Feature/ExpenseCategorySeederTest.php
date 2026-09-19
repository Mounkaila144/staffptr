<?php

namespace Tests\Feature;

use App\Models\Finance\ExpenseCategory;
use Database\Seeders\ExpenseCategorySeeder;
use Tests\Support\IdentityTestCase;

class ExpenseCategorySeederTest extends IdentityTestCase
{
    public function test_ac_2_and_4_initial_categories_are_distinct_marked_and_seeded_idempotently(): void
    {
        $this->seed(ExpenseCategorySeeder::class);
        $ids = ExpenseCategory::query()->orderBy('id')->pluck('id')->all();

        $this->seed(ExpenseCategorySeeder::class);

        $this->assertSame(6, ExpenseCategory::query()->count());
        $this->assertSame($ids, ExpenseCategory::query()->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Salaires',
            'is_essential' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Gratification de stagiaire',
            'is_essential' => false,
            'is_active' => true,
        ]);
        $this->assertNotSame(
            ExpenseCategory::query()->where('name', 'Salaires')->sole()->getKey(),
            ExpenseCategory::query()->where('name', 'Gratification de stagiaire')->sole()->getKey(),
        );
    }
}

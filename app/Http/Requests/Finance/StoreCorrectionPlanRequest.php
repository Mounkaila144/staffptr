<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\CorrectionPlan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Les cinq champs du plan correctif de l'AC 14 : constat, actions, responsables, échéance et
 * résultat attendu. Tous obligatoires — un plan amputé n'est pas un plan.
 */
class StoreCorrectionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CorrectionPlan::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m-d'],
            'finding' => ['required', 'string', 'max:4000'],
            'actions' => ['required', 'string', 'max:4000'],
            'responsibles' => ['required', 'string', 'max:1000'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'expected_result' => ['required', 'string', 'max:4000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'month' => 'mois concerné',
            'finding' => 'constat',
            'actions' => 'actions',
            'responsibles' => 'responsables',
            'due_on' => 'échéance',
            'expected_result' => 'résultat attendu',
        ];
    }
}

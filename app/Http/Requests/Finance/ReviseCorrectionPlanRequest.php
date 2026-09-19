<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\CorrectionPlan;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Révision d'un plan validé : les mêmes cinq champs, plus le motif de la révision. La révision
 * crée une nouvelle version, elle ne réécrit jamais l'original (AC 17).
 */
class ReviseCorrectionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('correctionPlan');

        return $plan instanceof CorrectionPlan
            && ($this->user()?->can('revise', $plan) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'finding' => ['required', 'string', 'max:4000'],
            'actions' => ['required', 'string', 'max:4000'],
            'responsibles' => ['required', 'string', 'max:1000'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'expected_result' => ['required', 'string', 'max:4000'],
            'revision_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'finding' => 'constat',
            'actions' => 'actions',
            'responsibles' => 'responsables',
            'due_on' => 'échéance',
            'expected_result' => 'résultat attendu',
            'revision_reason' => 'motif de la révision',
        ];
    }
}

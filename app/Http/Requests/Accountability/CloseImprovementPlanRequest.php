<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\ImprovementPlan;
use Illuminate\Foundation\Http\FormRequest;

class CloseImprovementPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('improvementPlan');

        return $plan instanceof ImprovementPlan && ($this->user()?->can('close', $plan) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['observed_result' => ['required', 'string', 'max:5000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['observed_result.required' => 'Consignez le résultat constaté à la fin de l’accompagnement.'];
    }
}

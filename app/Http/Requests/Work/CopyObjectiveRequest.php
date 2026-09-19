<?php

namespace App\Http\Requests\Work;

use App\Models\Work\Objective;
use Illuminate\Foundation\Http\FormRequest;

class CopyObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $objective = $this->route('objective');

        return $objective instanceof Objective && $this->user()->can('view', $objective) && $this->user()->can('create', Objective::class);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}

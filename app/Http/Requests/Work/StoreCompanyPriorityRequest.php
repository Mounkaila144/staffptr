<?php

namespace App\Http\Requests\Work;

use App\Enums\WorkPriority;
use App\Models\Work\CompanyPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CompanyPriority::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return self::fields();
    }

    /** @return array<string, list<mixed>> */
    public static function fields(): array
    {
        return ['month' => ['required', 'date_format:Y-m-d'], 'title' => ['required', 'string', 'max:160'], 'description' => ['required', 'string', 'max:500'], 'owner_id' => ['required', 'integer', Rule::exists('users', 'id')], 'indicator' => ['required', 'string', 'max:160'], 'target' => ['required', 'string', 'max:160'], 'due_date' => ['required', 'date_format:Y-m-d'], 'priority' => ['required', Rule::enum(WorkPriority::class)]];
    }
}

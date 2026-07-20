<?php

namespace App\Http\Requests\Platform;

use App\Models\Platform\AuditLog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AuditLogIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'actor_id' => ['nullable', 'regex:/\A(?:system|[1-9][0-9]*)\z/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'auditable_type' => ['nullable', 'string', 'max:120'],
            'action' => ['nullable', 'string', 'max:60'],
        ];
    }
}

<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\ReserveMovement;
use Illuminate\Foundation\Http\FormRequest;

class RequestReserveUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ReserveMovement::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'movement_amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
            'reconstitution_plan' => ['required', 'string', 'max:4000'],
            'idempotency_key' => ['required', 'ulid'],
        ];
    }
}

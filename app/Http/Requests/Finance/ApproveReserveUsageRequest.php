<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\ReserveMovement;
use Illuminate\Foundation\Http\FormRequest;

class ApproveReserveUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $movement = $this->route('reserveMovement');

        return $movement instanceof ReserveMovement && ($this->user()?->can('approve', $movement) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}

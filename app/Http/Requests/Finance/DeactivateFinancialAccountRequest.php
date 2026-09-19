<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Account;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof Account && ($this->user()?->can('update', $account) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}

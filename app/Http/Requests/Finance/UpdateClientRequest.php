<?php

namespace App\Http\Requests\Finance;

use App\Models\Finance\Client;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends StoreClientRequest
{
    public function authorize(): bool
    {
        $client = $this->route('client');

        return $client instanceof Client && ($this->user()?->can('update', $client) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $client = $this->route('client');

        return [
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+227[0-9]{8}$/', Rule::unique('clients', 'phone')->ignore($client instanceof Client ? $client->getKey() : null)],
            'contact' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}

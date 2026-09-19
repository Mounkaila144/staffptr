<?php

namespace App\Http\Requests\Work;

use App\Models\Work\Objective;
use Illuminate\Foundation\Http\FormRequest;

class UpdateObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $objective = $this->route('objective');

        return $objective instanceof Objective && ($this->user()?->can('update', $objective) ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...StoreObjectiveRequest::fields(),
            'user_id' => StoreObjectiveRequest::ownerRule($this),
            'reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_id.in' => 'Vous ne pouvez désigner comme responsable que vous-même ou une personne de votre périmètre.',
        ];
    }
}

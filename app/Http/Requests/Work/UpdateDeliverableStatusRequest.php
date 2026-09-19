<?php

namespace App\Http\Requests\Work;

use App\Enums\DeliverableStatus;
use App\Models\Work\Deliverable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliverableStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $deliverable = $this->route('deliverable');
        if (! $deliverable instanceof Deliverable || ! ($this->user()?->can('update', $deliverable) ?? false)) {
            return false;
        } $target = $this->input('status');

        return $target !== DeliverableStatus::Valide->value || $this->user()->can('validate', $deliverable);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(DeliverableStatus::class)], 'reason' => ['required', 'string', 'min:5', 'max:1000']];
    }

    public function status(): DeliverableStatus
    {
        return DeliverableStatus::from($this->string('status')->toString());
    }
}

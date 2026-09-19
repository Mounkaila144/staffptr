<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\DailyReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DailyReport::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'planned_task' => ['required', 'string', 'max:5000'],
            'achieved_result' => ['required', 'string', 'max:10000'],
            'evidence_link' => ['nullable', 'url:http,https', 'max:2048'],
            'attachment_ulid' => ['nullable', 'string', 'size:26', Rule::exists('attachments', 'ulid')],
            'blocker_present' => ['required', 'boolean'],
            'blocker_details' => ['nullable', 'required_if:blocker_present,1', 'string', 'max:5000'],
            'next_action' => ['required', 'string', 'max:5000'],
            'help_requested' => ['required', 'boolean'],
            'help_details' => ['nullable', 'required_if:help_requested,1', 'string', 'max:5000'],
            'lateness_explanation' => ['nullable', 'string', 'max:500'],
            'correction_reason' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('evidence_link') && ! $this->filled('attachment_ulid')) {
                $validator->errors()->add('evidence_link', 'Ajoutez une preuve : un lien ou un fichier.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'planned_task.required' => 'Indiquez la tâche prévue.',
            'achieved_result.required' => 'Indiquez le résultat obtenu.',
            'blocker_present.required' => 'Indiquez si vous avez rencontré un blocage.',
            'blocker_details.required_if' => 'Décrivez brièvement le blocage.',
            'next_action.required' => 'Indiquez la prochaine action.',
            'help_requested.required' => "Indiquez si vous demandez de l'aide.",
            'help_details.required_if' => "Précisez l'aide demandée.",
        ];
    }
}

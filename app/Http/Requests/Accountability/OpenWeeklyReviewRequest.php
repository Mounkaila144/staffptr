<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\WeeklyReview;
use App\Models\Identity\User;
use Illuminate\Foundation\Http\FormRequest;

class OpenWeeklyReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = User::query()->find($this->input('subject_user_id'));

        if (! $subject instanceof User) {
            return $this->user()?->can('create', WeeklyReview::class) ?? false;
        }

        return $this->user()?->can('open', [WeeklyReview::class, $subject]) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'subject_user_id' => ['required', 'integer', 'exists:users,id'],
            'week_start_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'subject_user_id.required' => 'Choisissez la personne dont vous ouvrez la revue.',
            'subject_user_id.exists' => "Ce compte n'existe pas.",
            'week_start_date.required' => 'Choisissez la semaine concernée.',
            'week_start_date.date_format' => 'Indiquez la semaine sous la forme jour du lundi.',
        ];
    }
}

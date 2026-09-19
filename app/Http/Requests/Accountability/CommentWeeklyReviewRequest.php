<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\WeeklyReview;
use Illuminate\Foundation\Http\FormRequest;

class CommentWeeklyReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('weeklyReview');

        return $review instanceof WeeklyReview && ($this->user()?->can('comment', $review) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['body.required' => 'Écrivez votre commentaire avant de l’enregistrer.'];
    }
}

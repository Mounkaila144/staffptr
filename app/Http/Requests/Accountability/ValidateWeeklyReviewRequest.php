<?php

namespace App\Http\Requests\Accountability;

use App\Models\Accountability\WeeklyReview;
use Illuminate\Foundation\Http\FormRequest;

class ValidateWeeklyReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('weeklyReview');

        return $review instanceof WeeklyReview && ($this->user()?->can('validateReview', $review) ?? false);
    }

    /**
     * La validation électronique ne porte aucune donnée : elle vaut par son auteur et son
     * horodatage (AC 4).
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}

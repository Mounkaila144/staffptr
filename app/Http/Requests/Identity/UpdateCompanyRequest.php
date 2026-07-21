<?php

namespace App\Http\Requests\Identity;

use App\Models\Identity\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Company::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(2 * 1024)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'logo.image' => 'Choisissez une image JPG, PNG ou WebP.',
            'logo.max' => 'Le logo ne doit pas dépasser 2 Mo.',
        ];
    }
}

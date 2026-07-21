<?php

namespace App\Http\Requests\Platform;

use App\Services\Platform\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $keys = SettingsService::keys();
        $rules = [
            'settings' => ['required', 'array:'.implode(',', $keys), 'min:1'],
            'effective_at' => ['required', 'date_format:Y-m-d\TH:i'],
        ];

        foreach ($keys as $key) {
            $rules["settings.{$key}"] = ['sometimes', ...SettingsService::valueRules($key)];
            $itemRules = SettingsService::itemRules($key);

            if ($itemRules !== null) {
                $rules["settings.{$key}.*"] = $itemRules;
            }
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $settings = $this->validated('settings');

        if (! is_array($settings)) {
            return [];
        }

        foreach ($settings as $key => $value) {
            if (is_string($key) && in_array(SettingsService::type($key), ['integer', 'percentage'], true)) {
                $settings[$key] = (int) $value;
            }
        }

        return $settings;
    }

    public function effectiveAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d\TH:i',
            (string) $this->validated('effective_at'),
            (string) config('app.display_timezone'),
        )->utc();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'settings.required' => 'Modifiez au moins un paramètre.',
            'settings.*.required' => 'Cette valeur est obligatoire.',
            'settings.*.integer' => 'Saisissez un nombre entier.',
            'settings.*.min' => 'La valeur proposée est trop petite.',
            'settings.*.max' => 'La valeur proposée est trop grande.',
            'settings.*.date_format' => 'Saisissez une heure au format 17:45.',
            'effective_at.required' => "Indiquez la date d'effet.",
            'effective_at.date_format' => "La date d'effet n'est pas valide.",
        ];
    }
}

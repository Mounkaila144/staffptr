<?php

namespace App\Http\Requests\Platform;

use App\Services\Platform\SettingsService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewSettingRequest extends FormRequest
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
        $key = $this->input('key');
        $valueRules = is_string($key) && in_array($key, SettingsService::keys(), true)
            ? SettingsService::valueRules($key)
            : ['required'];
        $itemRules = is_string($key) && in_array($key, SettingsService::keys(), true)
            ? SettingsService::itemRules($key)
            : null;

        $rules = [
            'key' => ['required', 'string', Rule::in(SettingsService::keys())],
            'value' => $valueRules,
        ];

        if ($itemRules !== null) {
            $rules['value.*'] = $itemRules;
        }

        return $rules;
    }

    public function settingKey(): string
    {
        return (string) $this->validated('key');
    }

    public function proposedValue(): mixed
    {
        $value = $this->validated('value');

        return in_array(SettingsService::type($this->settingKey()), ['integer', 'percentage'], true)
            ? (int) $value
            : $value;
    }
}

<?php

namespace App\Http\Requests\Platform;

use App\Models\Platform\SavedFilter;
use App\Support\Listing\ListRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Enregistrement d'un filtre privé (AC 8, AC 9).
 *
 * Les critères acceptés sont **exactement** ceux que la liste déclare. Un filtre enregistré ne
 * peut donc pas transporter une clé inconnue qui, plus tard, serait interprétée comme autre chose
 * qu'un filtre.
 */
class StoreSavedFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('create', SavedFilter::class) ?? false)
            && app(ListRegistry::class)->has((string) $this->input('list_key'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'list_key' => ['required', 'string', Rule::in(ListRegistry::keys())],
            'name' => ['required', 'string', 'max:120'],
            'criteria' => ['present', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'list_key' => 'liste concernée',
            'name' => 'nom du filtre',
            'criteria' => 'critères',
        ];
    }

    /**
     * Critères retenus : ceux que la liste déclare, et rien d'autre.
     *
     * @return array<string, mixed>
     */
    public function criteria(): array
    {
        $registry = app(ListRegistry::class);
        $listKey = (string) $this->input('list_key');
        $allowed = array_keys($registry->get($listKey)->filterRules());
        /** @var array<string, mixed> $criteria */
        $criteria = $this->input('criteria', []);

        return array_filter(
            array_intersect_key($criteria, array_flip($allowed)),
            static fn (mixed $value): bool => $value !== null && $value !== '' && ! is_array($value),
        );
    }
}

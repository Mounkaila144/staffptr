<?php

namespace App\Http\Requests\Platform;

use App\Support\Listing\ListRegistry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation des filtres, du tri et de la pagination d'une liste principale (AC 7, 9).
 *
 * Les règles de filtre ne sont pas écrites ici : elles viennent de la `ListSource` concernée, ce
 * qui interdit qu'une liste accepte à l'écran un filtre que l'export ignorerait. Un paramètre
 * absent de ces règles n'est simplement pas lu — il ne peut donc pas élargir le périmètre (AC 9).
 */
class ListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $registry = app(ListRegistry::class);
        $key = $this->listKey();

        if (! $registry->has($key)) {
            return false;
        }

        return $this->user()?->can($registry->get($key)->permission()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $registry = app(ListRegistry::class);
        $key = $this->listKey();
        $rules = [
            'sort' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];

        if (! $registry->has($key)) {
            return $rules;
        }

        return [...$rules, ...$registry->get($key)->filterRules()];
    }

    public function listKey(): string
    {
        return (string) $this->route('liste');
    }

    /**
     * Filtres effectivement déclarés par la liste. Tout le reste de la chaîne de requête est
     * ignoré, y compris un paramètre qui ressemblerait à un filtre.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $registry = app(ListRegistry::class);
        $key = $this->listKey();

        if (! $registry->has($key)) {
            return [];
        }

        $allowed = array_keys($registry->get($key)->filterRules());

        return array_filter(
            $this->only($allowed),
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }
}

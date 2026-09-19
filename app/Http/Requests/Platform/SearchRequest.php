<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation de la recherche transverse (AC 1).
 *
 * `authorize()` ne vérifie que la permission d'utiliser la recherche. Le périmètre des résultats
 * n'est **pas** décidé ici : il l'est requête par requête, par les scopes `visibleTo()` des
 * modules propriétaires. Un paramètre d'URL ne devient jamais une autorisation (AC 9, AC 15).
 */
class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recherche.utiliser') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'in:person,project,objective'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'statut' => ['nullable', 'string', 'max:40'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'q' => 'terme recherché',
            'type' => 'type de résultat',
            'from' => 'date de début',
            'to' => 'date de fin',
            'statut' => 'statut',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'La date de fin ne peut pas précéder la date de début.',
        ];
    }

    /**
     * Forme attendue par le service. Les noms d'URL restent en français, les clés internes en
     * anglais comme le reste du code.
     *
     * @return array{term: string|null, type: string|null, from: string|null, to: string|null, status: string|null}
     */
    public function filters(): array
    {
        return [
            'term' => $this->string('q')->toString(),
            'type' => $this->query('type') === null ? null : (string) $this->query('type'),
            'from' => $this->query('from') === null ? null : (string) $this->query('from'),
            'to' => $this->query('to') === null ? null : (string) $this->query('to'),
            'status' => $this->query('statut') === null ? null : (string) $this->query('statut'),
        ];
    }
}

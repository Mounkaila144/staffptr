<?php

namespace App\Http\Requests\Work;

use App\Enums\WorkPriority;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Support\Work\AssignableOwners;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Objective::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [...self::fields(), 'user_id' => self::ownerRule($this)];
    }

    /**
     * Le responsable désigné doit faire partie du périmètre de l'acteur.
     *
     * Sans cette règle, `exists:users,id` laisserait n'importe quel compte
     * inscrire du travail au nom de n'importe qui d'autre — tous les rôles
     * portent `objectif_individuel.gerer`. La liste déroulante du formulaire
     * lit la même source, mais c'est bien ici que se joue la protection.
     *
     * @return list<mixed>
     */
    public static function ownerRule(FormRequest $request): array
    {
        $actor = $request->user();

        return [
            'required',
            'integer',
            Rule::in($actor instanceof User ? AssignableOwners::idsFor($actor) : []),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'user_id.in' => 'Vous ne pouvez désigner comme responsable que vous-même ou une personne de votre périmètre.',
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function fields(): array
    {
        return ['user_id' => ['required', 'integer', Rule::exists('users', 'id')], 'company_priority_id' => ['nullable', 'integer', Rule::exists('company_priorities', 'id')], 'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')], 'title' => ['required', 'string', 'max:160'], 'description' => ['required', 'string', 'max:500'], 'indicator' => ['required', 'string', 'max:160'], 'target_value' => ['required', 'string', 'max:160'], 'expected_evidence' => ['required', 'string', 'max:500'], 'required_means' => ['nullable', 'string', 'max:2000'], 'due_date' => ['required', 'date_format:Y-m-d'], 'priority' => ['required', Rule::enum(WorkPriority::class)], 'progress' => ['nullable', 'integer', 'between:0,100'], 'attachment_ulid' => ['nullable', 'string', 'size:26', Rule::exists('attachments', 'ulid')]];
    }
}

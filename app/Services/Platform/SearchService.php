<?php

namespace App\Services\Platform;

use App\Enums\ObjectiveState;
use App\Enums\ProjectStatus;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Work\Objective;
use App\Models\Work\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche transverse : personne, projet, objectif, période et statut (AC 1 à 6).
 *
 * **Orchestration en lecture, pas un index.** Le service interroge les scopes `visibleTo(User)`
 * des modules propriétaires. Il ne recopie aucune donnée, n'écrit nulle part et n'introduit aucun
 * moteur de recherche : l'architecture impose le monolithe Laravel/Eloquent sur MySQL, et une
 * table d'index transverse serait un composant d'infrastructure non arbitré.
 *
 * **Le point sensible est le compteur.** L'AC 3 énonce qu'un résultat interdit ne doit apparaître
 * « ni en titre, ni en extrait, ni en compteur » — un compteur qui révèle l'existence d'un objet
 * caché est une fuite. La conséquence de conception est stricte : le scope d'autorisation est
 * appliqué **avant** `count()` comme avant `get()`, et les deux partent de la même requête. Il n'y
 * a jamais de filtrage en PHP après coup, parce qu'un total calculé avant filtrage aurait déjà
 * trahi ce qu'il fallait taire.
 */
final readonly class SearchService
{
    private const TIMEZONE = 'Africa/Niamey';

    /** Longueur minimale d'un terme : en deçà, la requête ramènerait tout le corpus. */
    public const MIN_TERM_LENGTH = 2;

    /** Résultats renvoyés par type. Le compteur, lui, reste exact. */
    public const PER_TYPE_LIMIT = 10;

    /**
     * @param  array{term?: string|null, type?: string|null, from?: string|null, to?: string|null, status?: string|null}  $filters
     * @return array{term: string, type: string|null, from: string|null, to: string|null, status: string|null, has_query: bool, total: int, groups: list<array<string, mixed>>, empty_message: string}
     */
    public function search(User $viewer, array $filters): array
    {
        $term = trim((string) ($filters['term'] ?? ''));
        $type = $this->normalizeType($filters['type'] ?? null);
        $from = $this->normalizeDate($filters['from'] ?? null);
        $to = $this->normalizeDate($filters['to'] ?? null);
        $status = $this->normalizeStatus($filters['status'] ?? null);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [
                'term' => $term,
                'type' => $type,
                'from' => $from,
                'to' => $to,
                'status' => $status,
                'has_query' => false,
                'total' => 0,
                'groups' => [],
                // SOC-06 : l'état initial n'est pas un vide de recherche. Il dit quoi faire.
                'empty_message' => 'Saisissez au moins deux caractères pour lancer une recherche.',
            ];
        }

        $groups = [];
        $total = 0;

        foreach (['person', 'project', 'objective'] as $candidate) {
            if ($type !== null && $type !== $candidate) {
                continue;
            }

            $group = match ($candidate) {
                'person' => $this->people($viewer, $term, $from, $to, $status),
                'project' => $this->projects($viewer, $term, $from, $to, $status),
                default => $this->objectives($viewer, $term, $from, $to, $status),
            };

            $total += $group['count'];
            $groups[] = $group;
        }

        return [
            'term' => $term,
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'status' => $status,
            'has_query' => true,
            'total' => $total,
            'groups' => $groups,
            // AC 5 : le vide de recherche cite le terme et se distingue de l'état initial.
            'empty_message' => "Aucun résultat pour « {$term} ».",
        ];
    }

    /**
     * Personnes. La période porte sur `first_seen_at` — la date d'entrée de la personne — et le
     * statut sur son statut opérationnel.
     *
     * @return array<string, mixed>
     */
    private function people(User $viewer, string $term, ?string $from, ?string $to, ?string $status): array
    {
        $query = Person::query()
            ->visibleTo($viewer)
            ->where('full_name', 'like', $this->like($term));

        $this->applyPeriod($query, 'first_seen_at', $from, $to);

        if ($status !== null) {
            $query->where('operational_status', $status);
        }

        return $this->group('person', 'Personnes', $query, function (Person $person): array {
            return [
                'id' => (int) $person->getKey(),
                'title' => (string) $person->full_name,
                'excerpt' => $person->operational_status->label(),
                'url' => route('people.show', $person, absolute: false),
            ];
        });
    }

    /**
     * Projets. La période porte sur la fenêtre d'exécution : un projet est retenu s'il chevauche
     * l'intervalle demandé, pas seulement s'il y commence.
     *
     * @return array<string, mixed>
     */
    private function projects(User $viewer, string $term, ?string $from, ?string $to, ?string $status): array
    {
        $query = Project::query()
            ->visibleTo($viewer)
            ->where(function (Builder $scope) use ($term): void {
                $scope->where('name', 'like', $this->like($term))
                    ->orWhere('client_name', 'like', $this->like($term));
            });

        if ($from !== null) {
            $query->where(function (Builder $scope) use ($from): void {
                $scope->whereNull('end_date')->orWhereDate('end_date', '>=', $from);
            });
        }

        if ($to !== null) {
            $query->where(function (Builder $scope) use ($to): void {
                $scope->whereNull('start_date')->orWhereDate('start_date', '<=', $to);
            });
        }

        if ($status !== null && ProjectStatus::tryFrom($status) instanceof ProjectStatus) {
            $query->where('status', $status);
        }

        return $this->group('project', 'Projets', $query, function (Project $project): array {
            return [
                'id' => (int) $project->getKey(),
                'title' => (string) $project->name,
                'excerpt' => $project->status->label().($project->client_name !== null ? " · {$project->client_name}" : ''),
                'url' => route('projects.show', $project, absolute: false),
            ];
        });
    }

    /**
     * Objectifs. La période porte sur l'échéance, qui est la date sur laquelle on raisonne quand
     * on cherche un objectif.
     *
     * @return array<string, mixed>
     */
    private function objectives(User $viewer, string $term, ?string $from, ?string $to, ?string $status): array
    {
        $query = Objective::query()
            ->visibleTo($viewer)
            ->where('title', 'like', $this->like($term));

        $this->applyPeriod($query, 'due_date', $from, $to);

        if ($status !== null && ObjectiveState::tryFrom($status) instanceof ObjectiveState) {
            $query->where('state', $status);
        }

        return $this->group('objective', 'Objectifs', $query, function (Objective $objective): array {
            return [
                'id' => (int) $objective->getKey(),
                'title' => (string) $objective->title,
                'excerpt' => $objective->state->label().' · échéance '.$objective->due_date->toDateString(),
                'url' => route('objectives.show', $objective, absolute: false),
            ];
        });
    }

    /**
     * Compte puis échantillonne **la même requête déjà restreinte**. C'est ce qui garantit
     * l'AC 3 : le total ne peut pas décrire un objet que la liste n'a pas le droit de montrer.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  callable(mixed): array<string, mixed>  $present
     * @return array<string, mixed>
     */
    private function group(string $key, string $label, Builder $query, callable $present): array
    {
        $count = (clone $query)->count();
        $items = $count === 0
            ? []
            : $query->orderByDesc('id')->limit(self::PER_TYPE_LIMIT)->get()->map($present)->all();

        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'items' => $items,
            'truncated' => $count > count($items),
        ];
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyPeriod(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($from !== null) {
            $query->whereDate($column, '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate($column, '<=', $to);
        }
    }

    /**
     * Échappe les jokers SQL du terme saisi : un utilisateur qui tape `%` cherche un pourcent, il
     * ne demande pas la totalité du corpus.
     */
    private function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }

    private function normalizeType(?string $type): ?string
    {
        return in_array($type, ['person', 'project', 'objective'], true) ? $type : null;
    }

    private function normalizeStatus(?string $status): ?string
    {
        $status = is_string($status) ? trim($status) : '';

        return $status === '' ? null : $status;
    }

    private function normalizeDate(?string $date): ?string
    {
        if (! is_string($date) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date) !== 1) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $date, self::TIMEZONE)->toDateString();
    }
}

<?php

namespace App\Services\Accountability;

use App\Enums\InternshipState;
use App\Models\Accountability\Internship;
use App\Models\Identity\User;
use App\Services\Platform\SettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Limite de stagiaires actifs par tuteur — règle métier bloquante n° 3 de l'Epic 7
 * (RM-06, CA-04, FR85, FR93).
 *
 * La limite n'est jamais codée en dur : elle est relue depuis le paramétrage à **chaque** contrôle
 * (AC 22). Elle ne dépend d'aucun rôle : un employé porteur du rôle `tuteur` y est soumis
 * exactement comme un associé (AC 24). Seuls les stages actifs occupent une place ; un stage
 * terminé ou archivé en libère une (AC 23).
 */
final class TutorCapacityService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Limite en vigueur, relue du paramétrage à chaque appel (AC 22).
     */
    public function limit(): int
    {
        return $this->settings->internLimitPerTutor();
    }

    /**
     * Charge actuelle : uniquement les stages actifs (AC 23).
     */
    public function currentLoad(User $tutor): int
    {
        return Internship::query()
            ->where('tutor_id', $tutor->getKey())
            ->occupyingTutorSlot()
            ->count();
    }

    public function hasReachedLimit(User $tutor): bool
    {
        return $this->currentLoad($tutor) >= $this->limit();
    }

    public function remainingSlots(User $tutor): int
    {
        return max(0, $this->limit() - $this->currentLoad($tutor));
    }

    /**
     * Refuse côté serveur l'affectation à un tuteur qui a atteint la limite. Le message nomme le
     * tuteur et sa charge actuelle (AC 20).
     */
    public function assertCanAcceptAnotherIntern(User $tutor): void
    {
        $load = $this->currentLoad($tutor);

        if ($load < $this->limit()) {
            return;
        }

        throw ValidationException::withMessages([
            'tutor_id' => $this->limitReachedMessage($tutor, $load),
        ]);
    }

    /**
     * « Moussa encadre déjà 3 stagiaires actifs, soit la limite en vigueur. Choisissez un autre
     * tuteur. » (AC 20)
     */
    public function limitReachedMessage(User $tutor, ?int $load = null): string
    {
        $load ??= $this->currentLoad($tutor);
        $plural = $load > 1 ? 's' : '';

        return sprintf(
            '%s encadre déjà %d stagiaire%s actif%s, soit la limite en vigueur. Choisissez un autre tuteur.',
            $this->labelFor($tutor),
            $load,
            $plural,
            $plural,
        );
    }

    /**
     * Verrouille la ligne du tuteur puis vérifie sa capacité.
     *
     * Le verrou pessimiste sérialise toutes les affectations visant ce tuteur : deux affectations
     * simultanées ne peuvent pas franchir la limite ensemble (AC 26). Il doit être pris à
     * l'intérieur d'une transaction déjà ouverte, et toujours sur la ligne du tuteur — un ordre
     * de verrouillage unique évite tout interblocage.
     */
    public function lockTutorAndAssertCapacity(int $tutorId): User
    {
        $tutor = User::query()->whereKey($tutorId)->lockForUpdate()->firstOrFail();

        $this->assertCanAcceptAnotherIntern($tutor);

        return $tutor;
    }

    /**
     * Charge de chaque tuteur pour l'écran de gestion (AC 25).
     *
     * L'atteinte de la limite est portée par un libellé explicite, jamais par la seule couleur.
     *
     * @return list<array{id: int, name: string, active_interns: int, limit: int, remaining: int, at_limit: bool, load_label: string, limit_label: string|null}>
     */
    public function loadSummary(): array
    {
        $limit = $this->limit();

        $tutors = User::query()
            ->with('person')
            ->where(fn (Builder $eligible): Builder => $eligible
                ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('name', ['tuteur', 'direction']))
                ->orWhereHas('supervisedInternships'))
            ->withCount([
                'supervisedInternships as active_interns_count' => static fn (Builder $active): Builder => $active
                    ->whereIn('state', InternshipState::occupyingValues()),
            ])
            ->get();

        return $tutors
            ->sortBy(fn (User $tutor): string => $this->labelFor($tutor))
            ->map(function (User $tutor) use ($limit): array {
                $load = (int) $tutor->getAttribute('active_interns_count');
                $atLimit = $load >= $limit;

                return [
                    'id' => (int) $tutor->getKey(),
                    'name' => $this->labelFor($tutor),
                    'active_interns' => $load,
                    'limit' => $limit,
                    'remaining' => max(0, $limit - $load),
                    'at_limit' => $atLimit,
                    'load_label' => sprintf('%d stagiaire%s sur %d', $load, $load > 1 ? 's' : '', $limit),
                    'limit_label' => $atLimit ? 'Limite atteinte' : null,
                ];
            })
            ->values()
            ->all();
    }

    private function labelFor(User $user): string
    {
        return $user->person()->value('full_name') ?? "Compte #{$user->getKey()}";
    }
}

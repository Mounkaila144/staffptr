<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Services\Platform\DirectionDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord direction consolidé (AC 25 à 31, AC 42, AC 43).
 *
 * Il remplace la fixture d'autorisation `testing.authorization.dashboard-global.view`, qui tenait
 * lieu de contrat pour la permission `tableau_bord_global.consulter` en attendant cette route.
 *
 * Le niveau d'alerte est envoyé en synchrone : il est affiché en permanence et tient en quelques
 * octets (AC 4). Les blocs, eux, sont différés pour ne pas retenir le premier rendu utile.
 */
class DirectionDashboardController extends Controller
{
    public function __construct(private readonly DirectionDashboardService $dashboard) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);

        return Inertia::render('Platform/DirectionDashboard', [
            'alert' => $this->dashboard->alert(),
            'blocks' => Inertia::defer(fn (): array => $this->dashboard->blocks($viewer)),
        ]);
    }
}

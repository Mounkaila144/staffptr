<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Services\Finance\FinancialDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord financier (AC 19 à 24, AC 43).
 *
 * Le contrôleur autorise, délègue et répond ; il ne calcule rien (architecture § 10.2). L'accès à
 * la page est réservé à `finance` et `direction` par la permission `tableau_bord_financier.consulter`
 * portée par la route ; l'accès par URL directe depuis tout autre rôle reçoit `403`, jamais une
 * redirection ni un rendu partiel (AC 22, SOC-01).
 *
 * Le cloisonnement bloc par bloc reste porté par le service : ce qui n'est pas autorisé n'est pas
 * envoyé au client, même vide (AC 23).
 */
class FinancialDashboardController extends Controller
{
    public function __construct(private readonly FinancialDashboardService $dashboard) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);

        return Inertia::render('Finance/Dashboard', [
            'alert' => $this->dashboard->alert(),
            // Les agrégats sont différés : le squelette de la page arrive d'abord, ce qui protège
            // le premier rendu utile sur connexion faible (architecture § 18.2).
            'blocks' => Inertia::defer(fn (): array => $this->dashboard->blocks($viewer)),
        ]);
    }
}

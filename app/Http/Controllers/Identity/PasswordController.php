<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangePasswordRequest;
use App\Models\Identity\User;
use App\Services\Identity\PasswordChangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        // `forced` distingue la première connexion (aucune sortie possible) du changement volontaire
        // ouvert depuis « Profil », qui doit offrir un retour à l'accueil.
        return Inertia::render('Identity/ChangePassword', [
            'forced' => ! $user instanceof User || $user->must_change_password,
        ]);
    }

    public function update(
        ChangePasswordRequest $request,
        PasswordChangeService $passwordChangeService,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $passwordChangeService->change(
            $user,
            $request->validated('password'),
            $request->session(),
        );

        return redirect()->route('home')->with('success', 'Votre nouveau mot de passe est enregistré.');
    }
}

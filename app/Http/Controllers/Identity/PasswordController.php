<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ChangePasswordRequest;
use App\Models\Identity\User;
use App\Services\Identity\PasswordChangeService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Identity/ChangePassword');
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

        return redirect()->route('home');
    }
}

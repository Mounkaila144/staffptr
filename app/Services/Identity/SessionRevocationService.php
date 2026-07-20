<?php

namespace App\Services\Identity;

use App\Models\Identity\User;
use Illuminate\Support\Facades\DB;

class SessionRevocationService
{
    public function revokeFor(User $user): int
    {
        $sessionTable = config('session.table', 'sessions');

        if (! is_string($sessionTable) || $sessionTable === '') {
            $sessionTable = 'sessions';
        }

        // Laravel ne fournit aucun modèle Eloquent pour sa table de sessions. Le constructeur
        // de requêtes est donc ici le chemin prescrit pour une révocation ciblée par user_id.
        return DB::connection($user->getConnectionName())
            ->table($sessionTable)
            ->where('user_id', $user->getKey())
            ->delete();
    }
}

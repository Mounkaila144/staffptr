<?php

namespace App\Http\Controllers\Accountability;

use App\Http\Controllers\Controller;
use App\Models\Accountability\Internship;
use App\Services\Accountability\TutorCapacityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TutorCapacityController extends Controller
{
    public function __construct(private readonly TutorCapacityService $capacity) {}

    /**
     * Écran de gestion de la charge des tuteurs (AC 25, FR93).
     */
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewCapacity', Internship::class);

        return Inertia::render('Accountability/Internships/Tutors/Index', [
            'tutors' => $this->capacity->loadSummary(),
            'limit' => $this->capacity->limit(),
        ]);
    }
}

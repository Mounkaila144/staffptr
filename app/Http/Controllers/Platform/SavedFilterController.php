<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSavedFilterRequest;
use App\Models\Identity\User;
use App\Models\Platform\SavedFilter;
use App\Services\Platform\SavedFilterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Filtres enregistrés, privés à leur auteur (AC 8).
 */
class SavedFilterController extends Controller
{
    public function __construct(private readonly SavedFilterService $savedFilters) {}

    public function store(StoreSavedFilterRequest $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $filter = $this->savedFilters->save(
            $actor,
            (string) $request->validated('list_key'),
            (string) $request->validated('name'),
            $request->criteria(),
        );

        return back()->with('success', "Le filtre « {$filter->name} » est enregistré. Il n'est visible que par vous.");
    }

    public function deactivate(Request $request, SavedFilter $savedFilter): RedirectResponse
    {
        // La Policy compare les identifiants : un autre compte `direction` reçoit 403 ici.
        Gate::authorize('update', $savedFilter);
        $this->savedFilters->deactivate($savedFilter, $this->actor($request));

        return back()->with('success', 'Le filtre est retiré de votre liste.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}

<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PreviewSettingRequest;
use App\Http\Requests\Platform\UpdateSettingsRequest;
use App\Models\Identity\User;
use App\Models\Platform\Setting;
use App\Services\Platform\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function index(Request $request): Response
    {
        Gate::authorize('manage', Setting::class);
        $this->actor($request);

        return Inertia::render('Platform/Settings/Index', [
            'settings' => $this->settingsService->forManagement(),
            'workingDayOptions' => [
                ['value' => 'lun', 'label' => 'Lundi'],
                ['value' => 'mar', 'label' => 'Mardi'],
                ['value' => 'mer', 'label' => 'Mercredi'],
                ['value' => 'jeu', 'label' => 'Jeudi'],
                ['value' => 'ven', 'label' => 'Vendredi'],
                ['value' => 'sam', 'label' => 'Samedi'],
                ['value' => 'dim', 'label' => 'Dimanche'],
            ],
            'attachmentTypeOptions' => ['pdf', 'jpeg', 'png', 'webp', 'heic'],
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);
        $this->settingsService->update(
            $request->settings(),
            $request->effectiveAt(),
            $this->actor($request),
        );

        return redirect()->route('settings.index')->with('status', 'Paramètres enregistrés.');
    }

    public function preview(PreviewSettingRequest $request): JsonResponse
    {
        Gate::authorize('manage', Setting::class);
        $this->actor($request);

        return response()->json([
            'preview' => $this->settingsService->preview(
                $request->settingKey(),
                $request->proposedValue(),
            ),
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}

<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreClientRequest;
use App\Http\Requests\Finance\UpdateClientRequest;
use App\Models\Finance\Client;
use App\Models\Identity\User;
use App\Services\Finance\ClientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(private readonly ClientService $clientService) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Client::class);

        return Inertia::render('Finance/Clients/Index', [
            'clients' => $this->clientService->forManagement(),
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $this->clientService->create($request->validated(), $this->actor($request));

        return redirect()->route('clients.index')->with('success', 'Le client a été créé.');
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->clientService->update($client, $request->validated(), $this->actor($request));

        return redirect()->route('clients.index')->with('success', 'Le client a été mis à jour.');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}

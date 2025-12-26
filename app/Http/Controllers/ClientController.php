<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientStoreRequest;
use App\Http\Requests\ClientUpdateRequest;
use App\Models\Client;
use App\Services\VatIdentityResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = auth()->user()->company_id;
        $clients = Client::where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return view('client.index', [
            'clients' => $clients,
        ]);
    }

    public function create(Request $request): View
    {
        return view('client.create', [
            'identityLabels' => config('company_identity_labels', []),
        ]);
    }

    public function store(ClientStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['company_id'] = auth()->user()->company_id;

        $client = Client::create($data);

        app(VatIdentityResolver::class)->resolveForClient($client);

        $request->session()->flash('client.id', $client->id);

        return redirect()->route('clients.index');
    }

    public function show(Request $request, Client $client): View
    {
        if ((int)$client->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        $client->load('vatIdentity');

        return view('client.show', [
            'client' => $client,
        ]);
    }

    public function edit(Request $request, Client $client): View
    {
        if ((int)$client->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        return view('client.edit', [
            'client' => $client,
            'identityLabels' => config('company_identity_labels', []),
        ]);
    }

    public function update(ClientUpdateRequest $request, Client $client): RedirectResponse
    {
        if ((int)$client->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        $data = $request->validated();
        // Ensure company_id cannot be changed
        unset($data['company_id']);

        $client->update($data);
        $client->refresh();

        app(VatIdentityResolver::class)->resolveForClient($client);

        $request->session()->flash('client.id', $client->id);

        return redirect()->route('clients.index');
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        if ((int)$client->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        $client->delete();

        return redirect()->route('clients.index');
    }
}

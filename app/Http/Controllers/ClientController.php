<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientStoreRequest;
use App\Http\Requests\ClientUpdateRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = Client::all();

        return view('client.index', [
            'clients' => $clients,
        ]);
    }

    public function create(Request $request): View
    {
        return view('client.create');
    }

    public function store(ClientStoreRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated());

        $request->session()->flash('client.id', $client->id);

        return redirect()->route('clients.index');
    }

    public function show(Request $request, Client $client): View
    {
        return view('client.show', [
            'client' => $client,
        ]);
    }

    public function edit(Request $request, Client $client): View
    {
        return view('client.edit', [
            'client' => $client,
        ]);
    }

    public function update(ClientUpdateRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        $request->session()->flash('client.id', $client->id);

        return redirect()->route('clients.index');
    }

    public function destroy(Request $request, Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index');
    }
}

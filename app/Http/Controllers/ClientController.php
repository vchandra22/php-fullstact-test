<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Services\ClientService;

class ClientController extends Controller
{
    protected $clientService;

    public function __construct(ClientService $clientService)
    {
        $this->clientService = $clientService;
    }

    public function index()
    {
        $clients = $this->clientService->getAll();
        return view('clients.index', compact('clients'));
    }

    public function create()
    {
        return view('clients.create');
    }

    public function store(StoreClientRequest $request)
    {
        $client = $this->clientService->create(
            $request->validated(),
            $request->file('client_logo')
        );

        return redirect()->route('clients.index')
            ->with('success', 'Client created successfully.');
    }

    public function show($id)
    {
        $client = $this->clientService->find($id);
        return view('clients.show', compact('client'));
    }

    public function edit($id)
    {
        $client = $this->clientService->find($id);
        return view('clients.edit', compact('client'));
    }

    public function update(UpdateClientRequest $request, $id)
    {
        $client = $this->clientService->update(
            $id,
            $request->validated(),
            $request->file('client_logo')
        );

        return redirect()->route('clients.index')
            ->with('success', 'Client updated successfully.');
    }

    public function destroy($id)
    {
        $this->clientService->delete($id);

        return redirect()->route('clients.index')
            ->with('success', 'Client deleted successfully.');
    }
}

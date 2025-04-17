<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Services\ClientService;
use Illuminate\Http\Request;

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
        return response()->json(['data' => $clients]);
    }

    public function store(StoreClientRequest $request)
    {
        $client = $this->clientService->create(
            $request->validated(),
            $request->file('client_logo')
        );

        return response()->json([
            'message' => 'Client created successfully',
            'data' => $client
        ], 201);
    }

    public function show($id)
    {
        $client = $this->clientService->find($id);

        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        return response()->json(['data' => $client]);
    }

    public function update(UpdateClientRequest $request, $id)
    {
        $client = $this->clientService->update(
            $id,
            $request->validated(),
            $request->file('client_logo')
        );

        return response()->json([
            'message' => 'Client updated successfully',
            'data' => $client
        ]);
    }

    public function destroy($id)
    {
        $this->clientService->delete($id);

        return response()->json([
            'message' => 'Client deleted successfully'
        ]);
    }
}

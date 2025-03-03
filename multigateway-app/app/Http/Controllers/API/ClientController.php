<?php
namespace App\Http\Controllers\API;
use App\Models\Client;
use App\Http\Controllers\Controller as Controller;
use App\Http\Resources\ClientResource;
use App\Http\Resources\TransactionResource;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::paginate(20);
        return ClientResource::collection($clients);
    }

    public function show(Client $client)
    {
        return new ClientResource($client);
    }

    public function transactions(Client $client)
    {
        $transactions = $client->transactions()->with(['products', 'gateways'])->get();
        return TransactionResource::collection($transactions);
    }
}

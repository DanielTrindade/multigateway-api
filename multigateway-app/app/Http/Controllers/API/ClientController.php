<?php
namespace App\Http\Controllers\API;
use App\Models\Client;
use App\Http\Controllers\Controller as Controller;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::all();
        return response()->json($clients);
    }

    public function show(Client $client)
    {
        return response()->json($client);
    }

    public function transactions(Client $client)
    {
        $transactions = $client->transactions()->with('products')->get();
        return response()->json($transactions);
    }
}

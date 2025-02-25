<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller as Controller;
use App\Models\Gateway;
use Illuminate\Http\Request;

class GatewayController extends Controller
{
    public function index()
    {
        $gateways = Gateway::orderBy('priority')->get();
        return response()->json($gateways);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-gateways');

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'priority' => 'required|integer',
            'credentials' => 'nullable|array',
        ]);

        $gateway = Gateway::create($validatedData);

        return response()->json($gateway, 201);
    }

    public function show(Gateway $gateway)
    {
        return response()->json($gateway);
    }

    public function update(Request $request, Gateway $gateway)
    {
        $this->authorize('manage-gateways');

        $validatedData = $request->validate([
            'name' => 'string|max:255',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'credentials' => 'nullable|array',
        ]);

        $gateway->update($validatedData);

        return response()->json($gateway);
    }

    public function destroy(Gateway $gateway)
    {
        $this->authorize('manage-gateways');

        $gateway->delete();

        return response()->json(null, 204);
    }

    public function toggleActive(Gateway $gateway)
    {
        $this->authorize('manage-gateways');

        $gateway->is_active = !$gateway->is_active;
        $gateway->save();

        return response()->json($gateway);
    }

    public function updatePriority(Request $request, Gateway $gateway)
    {
        $this->authorize('manage-gateways');

        $validatedData = $request->validate([
            'priority' => 'required|integer',
        ]);

        $gateway->priority = $validatedData['priority'];
        $gateway->save();

        return response()->json($gateway);
    }
}

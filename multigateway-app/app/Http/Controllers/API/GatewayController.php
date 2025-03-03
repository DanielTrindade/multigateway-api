<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller as Controller;
use App\Http\Resources\GatewayResource;
use App\Models\Gateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GatewayController extends Controller
{
    public function index()
    {
        $gateways = Gateway::orderBy('priority')->paginate(20);
        return GatewayResource::collection($gateways);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-gateways');

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'is_active' => 'boolean',
            'priority' => 'required|integer',
            'credentials' => 'nullable|array',
        ]);

        $gateway = Gateway::create($validatedData);

        return new GatewayResource($gateway);
    }

    public function show(Gateway $gateway)
    {
        return new GatewayResource($gateway);
    }

    public function update(Request $request, Gateway $gateway)
    {
        $this->authorize('manage-gateways');

        $validatedData = $request->validate([
            'name' => 'string|max:255',
            'type' => 'required|string|max:50',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'credentials' => 'nullable|array',
        ]);

        $gateway->update($validatedData);

        return new GatewayResource($gateway);
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

        return new GatewayResource($gateway);
    }

    public function updatePriority(Request $request, Gateway $gateway)
    {
        $this->authorize('manage-gateways');

        $validatedData = $request->validate([
            'priority' => 'required|integer|min:1',
        ]);

        $newPriority = $validatedData['priority'];
        $oldPriority = $gateway->priority;

        // Iniciar transação para garantir consistência
        DB::beginTransaction();

        try {
            // Se estamos movendo para uma prioridade menor (maior prioridade)
            if ($newPriority < $oldPriority) {
                // Incrementar a prioridade de todos os gateways que estão entre a nova e a antiga prioridade
                Gateway::where('priority', '>=', $newPriority)
                    ->where('priority', '<', $oldPriority)
                    ->where('id', '!=', $gateway->id)
                    ->increment('priority');
            }
            // Se estamos movendo para uma prioridade maior (menor prioridade)
            else if ($newPriority > $oldPriority) {
                // Decrementar a prioridade de todos os gateways que estão entre a antiga e a nova prioridade
                Gateway::where('priority', '>', $oldPriority)
                    ->where('priority', '<=', $newPriority)
                    ->where('id', '!=', $gateway->id)
                    ->decrement('priority');
            }

            // Atualizar a prioridade do gateway atual
            $gateway->priority = $newPriority;
            $gateway->save();

            DB::commit();

            // Retornar todos os gateways em ordem para que o frontend possa atualizar
            $gateways = Gateway::orderBy('priority')->get();

            return response()->json([
                'message' => 'Prioridade atualizada com sucesso',
                'gateway' => $gateway,
                'gateways' => $gateways
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao atualizar prioridade',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reorderPriorities(Request $request)
    {
        $this->authorize('manage-gateways');

        $validatedData = $request->validate([
            'gateways' => 'required|array',
            'gateways.*.id' => 'required|exists:gateways,id',
            'gateways.*.priority' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Criar um array temporário para evitar conflitos de constraint unique
            $tempPriority = 1000; // Valor temporário alto para evitar conflitos

            foreach ($validatedData['gateways'] as $gatewayData) {
                Gateway::where('id', $gatewayData['id'])
                    ->update(['priority' => $tempPriority + $gatewayData['priority']]);
            }

            // Agora definir as prioridades finais
            foreach ($validatedData['gateways'] as $gatewayData) {
                Gateway::where('id', $gatewayData['id'])
                    ->update(['priority' => $gatewayData['priority']]);
            }

            DB::commit();

            $gateways = Gateway::orderBy('priority')->get();

            return response()->json([
                'message' => 'Prioridades reordenadas com sucesso',
                'gateways' => $gateways
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao reordenar prioridades',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function normalizePriorities()
    {
        $this->authorize('manage-gateways');

        DB::beginTransaction();

        try {
            // Buscar todos os gateways ordenados pela prioridade atual
            $gateways = Gateway::orderBy('priority')->get();

            // Normalizar as prioridades para serem consecutivas
            $priority = 1;
            foreach ($gateways as $gateway) {
                $gateway->priority = $priority++;
                $gateway->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Prioridades normalizadas com sucesso',
                'gateways' => Gateway::orderBy('priority')->get()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao normalizar prioridades',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

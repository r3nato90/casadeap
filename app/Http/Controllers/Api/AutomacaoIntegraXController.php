<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutomacaoIntegraX;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AutomacaoIntegraXController extends Controller
{
    /**
     * Obter configuração atual da automação
     */
    public function show(): JsonResponse
    {
        $setting = AutomacaoIntegraX::first();
        
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Configuração não encontrada',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuração encontrada',
            'data' => [
                'integrax_url' => $setting->integrax_url,
                'status' => $setting->status,
                'is_active' => $setting->isActive()
            ]
        ]);
    }

    /**
     * Salvar ou atualizar configuração
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'integrax_url' => 'required|url',
            'status' => 'boolean'
        ]);

        try {
            $setting = AutomacaoIntegraX::first();
            
            if ($setting) {
                $setting->update([
                    'integrax_url' => $request->integrax_url,
                    'status' => $request->status ?? true
                ]);
            } else {
                AutomacaoIntegraX::create([
                    'integrax_url' => $request->integrax_url,
                    'status' => $request->status ?? true
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Configuração salva com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar configuração: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ativar automação
     */
    public function activate(): JsonResponse
    {
        try {
            $setting = AutomacaoIntegraX::first();
            
            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuração não encontrada'
                ], 404);
            }

            $setting->activate();

            return response()->json([
                'success' => true,
                'message' => 'Automação ativada com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao ativar automação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desativar automação
     */
    public function deactivate(): JsonResponse
    {
        try {
            $setting = AutomacaoIntegraX::first();
            
            if (!$setting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuração não encontrada'
                ], 404);
            }

            $setting->deactivate();

            return response()->json([
                'success' => true,
                'message' => 'Automação desativada com sucesso'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao desativar automação: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar status da automação
     */
    public function status(): JsonResponse
    {
        $setting = AutomacaoIntegraX::first();
        
        if (!$setting) {
            return response()->json([
                'success' => false,
                'message' => 'Configuração não encontrada',
                'data' => [
                    'is_active' => false,
                    'has_url' => false
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status verificado',
            'data' => [
                'is_active' => $setting->isActive(),
                'has_url' => !empty($setting->integrax_url),
                'status' => $setting->status
            ]
        ]);
    }
} 
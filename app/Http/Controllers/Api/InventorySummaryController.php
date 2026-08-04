<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventorySummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resumen agregado de inventario para el dashboard. Toda la lógica de
 * agregación vive en `InventorySummaryService`; aquí sólo se orquesta.
 */
class InventorySummaryController extends Controller
{
    public function __construct(private InventorySummaryService $summary) {}

    /** GET /api/admin/inventory/summary */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->summary->build($request->user()),
        ]);
    }
}

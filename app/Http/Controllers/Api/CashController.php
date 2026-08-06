<?php

namespace App\Http\Controllers\Api;

use App\Enums\CashMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashMovementRequest;
use App\Http\Requests\CloseCashSessionRequest;
use App\Http\Requests\OpenCashSessionRequest;
use App\Http\Resources\CashMovementResource;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use App\Services\CashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Caja. `CashService` lleva la lógica; aquí se resuelve el turno abierto y se
 * arma la respuesta. Ruta restringida a quien puede operar caja.
 */
class CashController extends Controller
{
    public function __construct(private readonly CashService $cash) {}

    /** GET /api/admin/cash/current — el turno abierto y sus totales, o null. */
    public function current(): JsonResponse
    {
        $session = $this->cash->currentOpen();

        if ($session === null) {
            return response()->json(['data' => null]);
        }

        $session->load(['openedBy', 'movements']);

        return response()->json([
            'data' => [
                'session' => new CashSessionResource($session),
                'totals' => $this->cash->totals($session),
            ],
        ]);
    }

    /** GET /api/admin/cash/sessions — historial de turnos cerrados. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $sessions = CashSession::query()
            ->with(['openedBy', 'closedBy'])
            ->orderByDesc('openedAt')
            ->paginate($request->integer('perPage', 30));

        return CashSessionResource::collection($sessions);
    }

    /** POST /api/admin/cash/open */
    public function open(OpenCashSessionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $session = $this->cash->open(
            userId: $request->user()->id,
            openingAmount: (float) $data['openingAmount'],
            notes: $data['notes'] ?? null,
        );

        return (new CashSessionResource($session->load('openedBy')))->response()->setStatusCode(201);
    }

    /** POST /api/admin/cash/movements — ingreso/egreso en el turno abierto. */
    public function move(CashMovementRequest $request): JsonResponse
    {
        $session = $this->requireOpen();
        $data = $request->validated();

        $movement = $this->cash->addMovement(
            session: $session,
            type: CashMovementType::from($data['type']),
            amount: (float) $data['amount'],
            concept: $data['concept'],
            userId: $request->user()->id,
        );

        return response()->json([
            'message' => 'Movimiento registrado.',
            'movement' => new CashMovementResource($movement),
            'totals' => $this->cash->totals($session),
        ], 201);
    }

    /** POST /api/admin/cash/close — arqueo y cierre del turno abierto. */
    public function close(CloseCashSessionRequest $request): JsonResponse
    {
        $session = $this->requireOpen();
        $data = $request->validated();

        $session = $this->cash->close(
            session: $session,
            countedAmount: (float) $data['countedAmount'],
            userId: $request->user()->id,
            notes: $data['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Caja cerrada.',
            'session' => new CashSessionResource($session->load(['openedBy', 'closedBy'])),
        ]);
    }

    private function requireOpen(): CashSession
    {
        $session = $this->cash->currentOpen();

        if ($session === null) {
            throw new HttpException(409, 'No hay una caja abierta.');
        }

        return $session;
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashSessionRequest;
use App\Http\Requests\StoreCashSessionRequest;
use App\Http\Requests\UpdateCashSessionRequest;
use App\Http\Resources\CashSessionResource;
use App\Models\CashSession;
use App\Services\CashSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Cierre de caja diario (arqueo). La lógica vive en `CashSessionService`; aquí
 * se resuelve el modelo, se cargan las relaciones que pide cada acción y se
 * traduce la respuesta.
 */
class CashSessionController extends Controller
{
    private const WITH_CHILDREN = ['denominations', 'expenses.supplier', 'payables.supplier', 'openedBy', 'closedBy'];

    public function __construct(private readonly CashSessionService $cashSessions) {}

    /** GET /api/admin/cash-sessions — historial, orden businessDate desc. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $sessions = CashSession::query()
            ->orderByDesc('businessDate')
            ->paginate($request->integer('perPage', 30));

        return CashSessionResource::collection($sessions);
    }

    /** GET /api/admin/cash-sessions/current?date=YYYY-MM-DD — el cierre de esa fecha (o de hoy). */
    public function current(Request $request): JsonResponse
    {
        $date = $request->filled('date') ? Carbon::parse($request->string('date')->toString()) : Carbon::today();

        $session = CashSession::query()
            ->whereDate('businessDate', $date->toDateString())
            ->with(self::WITH_CHILDREN)
            ->first();

        if ($session === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => new CashSessionResource($session)]);
    }

    /** POST /api/admin/cash-sessions — abre/recupera el cierre de una fecha. */
    public function store(StoreCashSessionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $session = $this->cashSessions->openForDate(
            date: $data['businessDate'],
            base: (float) $data['baseAmount'],
            userId: $request->user()->id,
        );

        return (new CashSessionResource($session))->response()->setStatusCode(201);
    }

    /** GET /api/admin/cash-sessions/{cashSession} */
    public function show(CashSession $cashSession): CashSessionResource
    {
        return new CashSessionResource($cashSession->load(self::WITH_CHILDREN));
    }

    /** PUT /api/admin/cash-sessions/{cashSession} — guarda el borrador. */
    public function update(UpdateCashSessionRequest $request, CashSession $cashSession): CashSessionResource
    {
        $session = $this->cashSessions->saveDraft($cashSession, $request->validated());

        return new CashSessionResource($session->load(self::WITH_CHILDREN));
    }

    /** POST /api/admin/cash-sessions/{cashSession}/close */
    public function close(CloseCashSessionRequest $request, CashSession $cashSession): CashSessionResource
    {
        $session = $this->cashSessions->close($cashSession, $request->user()->id);

        return new CashSessionResource($session->load(self::WITH_CHILDREN));
    }
}

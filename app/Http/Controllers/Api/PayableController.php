<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\PayablePaymentRequest;
use App\Http\Requests\PayableRequest;
use App\Http\Resources\PayablePaymentResource;
use App\Http\Resources\PayableResource;
use App\Models\Payable;
use App\Models\Supplier;
use App\Services\PayableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cuentas por pagar. El trabajo real lo hace `PayableService`; aquí se resuelven
 * modelos, se guarda el adjunto y se traduce la respuesta. Todo el recurso es
 * de finanzas: la ruta ya exige rol de administrador.
 */
class PayableController extends Controller
{
    public function __construct(private readonly PayableService $payables) {}

    /** GET /api/admin/payables */
    public function index(Request $request): AnonymousResourceCollection
    {
        $payables = Payable::query()
            ->with('supplier')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('supplierId'), fn ($q) => $q->where('supplierId', $request->integer('supplierId')))
            ->when($request->boolean('open'), fn ($q) => $q->open())
            ->when($request->boolean('overdue'), fn ($q) => $q->open()
                ->whereNotNull('dueDate')
                ->whereDate('dueDate', '<', now()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(fn ($q) => $q
                    ->where('concept', 'LIKE', "%{$search}%")
                    ->orWhere('invoiceNumber', 'LIKE', "%{$search}%"));
            })
            // Las que tienen vencimiento primero, ordenadas por el más próximo.
            ->orderByRaw('dueDate IS NULL')
            ->orderBy('dueDate')
            ->orderByDesc('id')
            ->paginate($request->integer('perPage', 30));

        return PayableResource::collection($payables);
    }

    /** GET /api/admin/payables/summary — cuánto debo y qué vence esta semana. */
    public function summary(): JsonResponse
    {
        return response()->json(['data' => $this->payables->summary()]);
    }

    /** GET /api/admin/payables/{payable} */
    public function show(Payable $payable): PayableResource
    {
        return new PayableResource($payable->load(['supplier', 'payments']));
    }

    /** POST /api/admin/payables */
    public function store(PayableRequest $request): JsonResponse
    {
        $data = $request->validated();
        $path = $request->file('attachment')?->store('payables', 'local');

        $payable = $this->payables->register(
            supplier: Supplier::findOrFail($data['supplierId']),
            data: $data,
            issueDate: Carbon::parse($data['issueDate']),
            dueDate: isset($data['dueDate']) ? Carbon::parse($data['dueDate']) : null,
            attachmentPath: $path,
            userId: $request->user()->id,
        );

        return (new PayableResource($payable->load('supplier')))->response()->setStatusCode(201);
    }

    /** POST /api/admin/payables/{payable}/pay */
    public function pay(PayablePaymentRequest $request, Payable $payable): JsonResponse
    {
        $data = $request->validated();

        $payment = $this->payables->pay(
            payable: $payable,
            amount: (float) $data['amount'],
            method: PaymentMethod::from($data['paymentMethod']),
            paymentDate: isset($data['paymentDate']) ? Carbon::parse($data['paymentDate']) : null,
            reference: $data['reference'] ?? null,
            notes: $data['notes'] ?? null,
            userId: $request->user()->id,
        );

        return response()->json([
            'message' => 'Pago registrado.',
            'payment' => new PayablePaymentResource($payment),
            'payable' => new PayableResource($payable->refresh()->load('supplier')),
        ], 201);
    }

    /** POST /api/admin/payables/{payable}/void */
    public function void(Request $request, Payable $payable): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:300'],
        ]);

        $payable = $this->payables->void($payable, $data['reason'], $request->user()->id);

        return response()->json([
            'message' => 'Cuenta anulada.',
            'payable' => new PayableResource($payable->load('supplier')),
        ]);
    }

    /** GET /api/admin/payables/{payable}/invoice — sirve la foto de la factura. */
    public function invoice(Payable $payable): StreamedResponse|JsonResponse
    {
        if ($payable->attachmentPath === null || ! Storage::disk('local')->exists($payable->attachmentPath)) {
            return response()->json([
                'error' => 'ATTACHMENT_NOT_FOUND',
                'message' => 'Esta cuenta no tiene factura adjunta.',
            ], 404);
        }

        return Storage::disk('local')->download($payable->attachmentPath);
    }
}

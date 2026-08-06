<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gastos operativos. Todo el recurso es de finanzas (la ruta exige rol de
 * administrador).
 */
class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    /** GET /api/admin/expenses */
    public function index(Request $request): AnonymousResourceCollection
    {
        $expenses = Expense::query()
            ->with('supplier')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('expenseDate', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('expenseDate', '<=', $request->date('to')))
            ->when($request->filled('search'), fn ($q) => $q->where('description', 'LIKE', '%' . $request->string('search')->toString() . '%'))
            ->orderByDesc('expenseDate')
            ->orderByDesc('id')
            ->paginate($request->integer('perPage', 30));

        return ExpenseResource::collection($expenses);
    }

    /** GET /api/admin/expenses/summary — total del periodo y desglose por categoría. */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;

        // Query builder plano (no Eloquent) para que `category` llegue como string
        // crudo a `ExpenseCategory::from()` y sin castear propiedades inexistentes.
        $rows = DB::table('expense')
            ->when($from, fn ($q) => $q->whereDate('expenseDate', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expenseDate', '<=', $to))
            ->selectRaw('category, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $byCategory = $rows->map(fn ($row) => [
            'category' => $row->category,
            'categoryLabel' => ExpenseCategory::from($row->category)->label(),
            'count' => (int) $row->count,
            'total' => round((float) $row->total, 2),
        ]);

        return response()->json([
            'data' => [
                'total' => round((float) $byCategory->sum('total'), 2),
                'byCategory' => $byCategory,
            ],
        ]);
    }

    /** POST /api/admin/expenses */
    public function store(ExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $path = $request->file('attachment')?->store('expenses', 'local');

        $expense = $this->expenses->register(
            category: ExpenseCategory::from($data['category']),
            method: PaymentMethod::from($data['paymentMethod']),
            data: $data,
            expenseDate: Carbon::parse($data['expenseDate']),
            attachmentPath: $path,
            userId: $request->user()->id,
        );

        return (new ExpenseResource($expense->load('supplier')))->response()->setStatusCode(201);
    }

    /** GET /api/admin/expenses/{expense}/support — sirve el soporte del gasto. */
    public function support(Expense $expense): StreamedResponse|JsonResponse
    {
        if ($expense->attachmentPath === null || ! Storage::disk('local')->exists($expense->attachmentPath)) {
            return response()->json([
                'error' => 'ATTACHMENT_NOT_FOUND',
                'message' => 'Este gasto no tiene soporte adjunto.',
            ], 404);
        }

        return Storage::disk('local')->download($expense->attachmentPath);
    }
}

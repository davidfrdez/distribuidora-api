<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use Carbon\CarbonInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Gastos operativos. Registro simple: el gasto ya ocurrió y se anota con su
 * categoría, medio de pago y (opcional) soporte.
 */
class ExpenseService
{
    /**
     * @param  array{description: string, amount: float, notes?: ?string, supplierId?: ?int}  $data
     */
    public function register(
        ExpenseCategory $category,
        PaymentMethod $method,
        array $data,
        CarbonInterface $expenseDate,
        ?string $attachmentPath = null,
        ?int $userId = null,
    ): Expense {
        if ((float) $data['amount'] <= 0) {
            throw new HttpException(422, 'El monto del gasto debe ser mayor que cero.');
        }

        return Expense::create([
            'category' => $category->value,
            'description' => $data['description'],
            'amount' => round((float) $data['amount'], 2),
            'expenseDate' => $expenseDate->toDateString(),
            'paymentMethod' => $method->value,
            'supplierId' => $data['supplierId'] ?? null,
            'attachmentPath' => $attachmentPath,
            'notes' => $data['notes'] ?? null,
            'createdById' => $userId,
        ]);
    }
}

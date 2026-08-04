<?php

use App\Jobs\ReconcileStockJob;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Schedule;

// Verificación diaria de que las tres fuentes del inventario cuadran.
Schedule::job(new ReconcileStockJob)->dailyAt('03:00');

// Devuelve al disponible el stock de reservas abandonadas.
Schedule::call(fn () => app(InventoryService::class)->expireStaleReservations())
    ->everyThirtyMinutes()
    ->name('inventory:expire-reservations')
    ->withoutOverlapping();

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — migración de PRODUCCIÓN (no destructiva de datos).
 *
 * Las migraciones originales de la Fase 1 fueron editadas para producir ya el
 * esquema final (sin bodegas ni cadena de frío). Por eso:
 *
 *   - En una instalación NUEVA (local, y la suite de tests sobre SQLite) el
 *     esquema ya es el final: TODAS las guardas `hasColumn`/`hasTable` dan falso
 *     y esta migración es un NO-OP. Nada de MySQL corre sobre SQLite.
 *   - En PRODUCCIÓN, que todavía tiene el esquema viejo con datos reales, estas
 *     guardas dan verdadero y se aplican los cambios preservando los saldos.
 *
 * ⚠️ ANTES DE CORRER EN PRODUCCIÓN: respaldo completo de la base (mysqldump).
 * Esta migración consolida saldos y elimina tablas; su `down()` NO restaura
 * datos. La única vuelta atrás es el respaldo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1) stock: consolidar todas las bodegas en un saldo GLOBAL ──────────
        // Se suman los saldos de cada (producto, bodega) en una sola fila por
        // producto. `availableUnits`/`availableKg` son columnas generadas: se
        // recalculan solas al actualizar current/reserved.
        if (Schema::hasColumn('stock', 'warehouseId')) {
            DB::statement('
                UPDATE stock s
                JOIN (
                    SELECT productId, MIN(id) AS keepId,
                           SUM(currentUnits)  AS cu, SUM(reservedUnits) AS ru,
                           SUM(currentKg)     AS ck, SUM(reservedKg)    AS rk,
                           MAX(lastMovementAt) AS lm, MAX(lastCountAt)  AS lc
                    FROM stock GROUP BY productId
                ) agg ON s.id = agg.keepId
                SET s.currentUnits = agg.cu, s.reservedUnits = agg.ru,
                    s.currentKg = agg.ck, s.reservedKg = agg.rk,
                    s.lastMovementAt = agg.lm, s.lastCountAt = agg.lc
            ');

            DB::statement('
                DELETE s FROM stock s
                JOIN (SELECT productId, MIN(id) AS keepId FROM stock GROUP BY productId) agg
                    ON s.productId = agg.productId
                WHERE s.id <> agg.keepId
            ');

            Schema::table('stock', function (Blueprint $table) {
                $table->dropForeign(['warehouseId']);
                $table->dropUnique('stock_unique_product_warehouse');
                $table->dropIndex('stock_index_product');
                $table->dropColumn('warehouseId');
                $table->unique('productId', 'stock_unique_product');
            });
        }

        // ── 2) lot: soltar bodega y rehacer el índice FIFO (sin bodega) ────────
        if (Schema::hasColumn('lot', 'warehouseId')) {
            Schema::table('lot', function (Blueprint $table) {
                $table->dropForeign(['warehouseId']);
                $table->dropIndex('lot_index_fifo');
                $table->dropColumn('warehouseId');
                $table->index(['productId', 'status', 'expirationDate'], 'lot_index_fifo');
            });
        }

        // ── 3) stock_movement: soltar bodega y rehacer el índice del kardex ────
        if (Schema::hasColumn('stock_movement', 'warehouseId')) {
            Schema::table('stock_movement', function (Blueprint $table) {
                $table->dropForeign(['warehouseId']);
                $table->dropIndex('stock_movement_index_kardex');
                $table->dropColumn('warehouseId');
                $table->index(['productId', 'movementDate'], 'stock_movement_index_kardex');
            });
        }

        // ── 4) stock_reservation: soltar bodega y rehacer su índice ────────────
        if (Schema::hasColumn('stock_reservation', 'warehouseId')) {
            Schema::table('stock_reservation', function (Blueprint $table) {
                $table->dropForeign(['warehouseId']);
                $table->dropIndex('stock_reservation_index_stock');
                $table->dropColumn('warehouseId');
                $table->index(['productId', 'status'], 'stock_reservation_index_stock');
            });
        }

        // ── 5) Fuera cadena de frío ────────────────────────────────────────────
        // La bitácora primero (tiene FK a warehouse), después las columnas de
        // temperatura del producto.
        Schema::dropIfExists('temperature_log');

        if (Schema::hasColumn('product', 'storageTempMin')) {
            Schema::table('product', function (Blueprint $table) {
                $table->dropColumn(['storageTempMin', 'storageTempMax']);
            });
        }

        // ── 6) Fuera bodegas ───────────────────────────────────────────────────
        // `warehouse` antes que `warehouse_type` (aquélla tiene FK hacia ésta).
        Schema::dropIfExists('warehouse');
        Schema::dropIfExists('warehouse_type');

        // ── 7) Libra comercial = 500 g ─────────────────────────────────────────
        // En producción la unidad venía sembrada con la libra internacional
        // (0.45359237). El seeder nuevo ya usa 0.5, pero los seeders no corren en
        // producción, así que se corrige aquí. Idempotente.
        if (Schema::hasTable('unit')) {
            DB::table('unit')->where('code', 'LB')->update(['factorToBase' => 0.5]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migración irreversible: consolidó los saldos de bodegas y eliminó la cadena de frío. '
            . 'Para volver atrás, restaurar la base desde el respaldo previo.',
        );
    }
};

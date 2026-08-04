<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Genera códigos únicos y secuenciales para entidades del dominio
 * (SKU de producto, código de lote, consecutivo de pedido, etc.).
 *
 * IMPORTANTE: llamar SIEMPRE dentro de un DB::transaction(); los métodos usan
 * lockForUpdate() para evitar que dos usuarios obtengan el mismo consecutivo.
 */
class CodeGeneratorService
{
    /**
     * Código tipo "CHO-001" derivado del nombre + secuencia.
     *
     * @param  string  $name  Nombre a slugificar (ej. "Chorizo Ahumado" → CHO).
     * @param  string  $table  Tabla destino donde se busca la secuencia.
     * @param  string  $field  Columna que almacena el código (code o sku).
     * @param  int  $padding  Dígitos del sufijo (3 → "001").
     */
    public function generateBySlug(
        string $name,
        string $table,
        string $field,
        int $padding = 3,
    ): string {
        return $this->resolveNextCode($this->slugifyPrefix($name), '-', $table, $field, $padding);
    }

    /**
     * Código con prefijo fijo: "LOT-0001", "PED-000123", "CAJ-01".
     *
     * @param  string  $separator  Separador; usar '' para formatos tipo "B01".
     */
    public function generateFixed(
        string $prefix,
        string $table,
        string $field,
        int $padding = 2,
        string $separator = '-',
    ): string {
        return $this->resolveNextCode($prefix, $separator, $table, $field, $padding);
    }

    /**
     * Prefijo de 3 letras mayúsculas a partir de un nombre: quita acentos,
     * filtra a [A-Z], toma 3 y rellena con 'X'. Devuelve 'XXX' si no queda nada.
     */
    public function slugifyPrefix(string $name): string
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($name))) ?? '';

        if ($letters === '') {
            return 'XXX';
        }

        return str_pad(substr($letters, 0, 3), 3, 'X');
    }

    /**
     * Siguiente consecutivo para un prefijo.
     *
     * Incluye deliberadamente los registros soft-deleted en el cálculo del
     * máximo: el índice UNIQUE físico no distingue borrados, así que reutilizar
     * el código de un registro eliminado reventaría la restricción.
     */
    private function resolveNextCode(
        string $prefix,
        string $separator,
        string $table,
        string $field,
        int $padding,
    ): string {
        $prefixWithSep = $prefix . $separator;

        $query = DB::table($table)
            ->where($field, 'LIKE', $prefixWithSep . '%')
            ->whereNotNull($field)
            ->lockForUpdate();

        $maxSeq = 0;

        foreach ($query->pluck($field)->toArray() as $code) {
            if (str_starts_with((string) $code, $prefixWithSep)) {
                $seq = (int) substr((string) $code, strlen($prefixWithSep));
                $maxSeq = max($maxSeq, $seq);
            }
        }

        return $prefixWithSep . str_pad((string) ($maxSeq + 1), $padding, '0', STR_PAD_LEFT);
    }
}

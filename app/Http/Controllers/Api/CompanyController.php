<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;

/**
 * Datos y parámetros del negocio. Fila única: no hay `index` ni `store`,
 * sólo leer y editar la única que existe.
 */
class CompanyController extends Controller
{
    /** GET /api/admin/company */
    public function show(): CompanyResource
    {
        return new CompanyResource(Company::current());
    }

    /** PUT /api/admin/company */
    public function update(Request $request): CompanyResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'businessName' => ['nullable', 'string', 'max:200'],
            'nit' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:250'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsappPhone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'invimaRegistration' => ['nullable', 'string', 'max:60'],
            'timezone' => ['sometimes', 'string', 'max:50', 'timezone'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'brandColor' => ['nullable', 'string', 'max:20'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'minOrderAmount' => ['numeric', 'min:0'],
            'defaultWeightTolerancePercent' => ['numeric', 'min:0', 'max:100'],
            'reservationTtlMinutes' => ['integer', 'min:5', 'max:10080'],
        ]);

        $company = Company::query()->firstOrNew(['id' => 1]);
        $company->fill($data)->save();

        // El modelo invalida su propia caché al guardar; se relee explícitamente
        // para devolver el estado ya persistido.
        Company::forgetCurrent();

        return new CompanyResource(Company::current());
    }
}

<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        return $user;
    }

    // ── Unidades ─────────────────────────────────────────────────────────────

    public function test_lista_las_unidades_con_la_base_primero(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $this->seed(UnitSeeder::class);

        $response = $this->getJson('/api/admin/units?kind=WEIGHT')->assertOk();

        $codes = collect($response->json('data'))->pluck('code');
        $this->assertSame('KG', $codes->first());
        $this->assertTrue($response->json('data.0.isBase'));
    }

    public function test_no_permite_marcar_una_unidad_nueva_como_base(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        // Aunque se envíe isBase, se ignora: cambiar la base recalcularía mal
        // todas las conversiones existentes.
        $this->postJson('/api/admin/units', [
            'code' => 'TON',
            'name' => 'Tonelada',
            'kind' => 'WEIGHT',
            'factorToBase' => 1000,
            'isBase' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.isBase', false);
    }

    // ── Categorías ───────────────────────────────────────────────────────────

    public function test_lista_categorias_con_conteo_de_productos(): void
    {
        $this->actingAsRole(UserRole::VENDEDOR);
        $categoria = Category::factory()->create(['name' => 'Chorizos']);
        Product::factory()->count(2)->create(['categoryId' => $categoria->id]);

        $this->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Chorizos')
            ->assertJsonPath('data.0.productsCount', 2);
    }

    public function test_una_categoria_no_puede_ser_su_propio_padre(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $categoria = Category::factory()->create();

        $this->putJson("/api/admin/categories/{$categoria->id}", ['parentId' => $categoria->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parentId');
    }

    // ── Códigos de barras ────────────────────────────────────────────────────

    public function test_agrega_un_codigo_de_barras_y_lo_resuelve_al_escanear(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $producto = Product::factory()->create(['sku' => 'CHO-004']);

        $this->postJson("/api/admin/products/{$producto->id}/barcodes", [
            'barcode' => '7701234567890',
            'label' => 'caja x 10',
            'isPrimary' => true,
        ])->assertCreated();

        // Respuesta compuesta: los recursos anidados NO llevan envoltorio `data`,
        // que sólo lo agrega el recurso de primer nivel.
        $this->getJson('/api/admin/barcodes/lookup/7701234567890')
            ->assertOk()
            ->assertJsonPath('product.sku', 'CHO-004')
            ->assertJsonPath('barcode.label', 'caja x 10');
    }

    public function test_un_codigo_desconocido_devuelve_404_con_mensaje_claro(): void
    {
        $this->actingAsRole(UserRole::EMPACADOR);

        $this->getJson('/api/admin/barcodes/lookup/0000000000000')
            ->assertStatus(404)
            ->assertJsonPath('error', 'BARCODE_NOT_FOUND');
    }

    public function test_solo_un_codigo_queda_como_principal(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);
        $producto = Product::factory()->create();
        $primero = ProductBarcode::factory()->create([
            'productId' => $producto->id,
            'isPrimary' => true,
        ]);

        $this->postJson("/api/admin/products/{$producto->id}/barcodes", [
            'barcode' => '7709999999999',
            'isPrimary' => true,
        ])->assertCreated();

        $this->assertFalse($primero->refresh()->isPrimary);
    }

    // ── Proveedores ──────────────────────────────────────────────────────────

    public function test_crea_y_busca_proveedores(): void
    {
        $this->actingAsRole(UserRole::ALMACENISTA);

        $this->postJson('/api/admin/suppliers', [
            'code' => 'PROV-01',
            'name' => 'Cárnicos del Llano S.A.S.',
            'nit' => '900555444-1',
            'invimaRegistration' => 'INV-2211',
            'paymentTermDays' => 30,
        ])->assertCreated();

        Supplier::factory()->create(['name' => 'Otro proveedor']);

        $this->getJson('/api/admin/suppliers?search=llano')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invimaRegistration', 'INV-2211');
    }

    // ── Negocio ──────────────────────────────────────────────────────────────

    public function test_cualquiera_lee_los_datos_del_negocio_pero_solo_el_admin_los_edita(): void
    {
        Company::factory()->create(['name' => 'Salsamentaria El Progreso']);

        $this->actingAsRole(UserRole::CAJERO);
        $this->getJson('/api/admin/company')
            ->assertOk()
            ->assertJsonPath('data.name', 'Salsamentaria El Progreso');

        $this->putJson('/api/admin/company', ['minOrderAmount' => 80000])->assertStatus(403);

        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $this->putJson('/api/admin/company', ['minOrderAmount' => 80000])
            ->assertOk()
            ->assertJsonPath('data.minOrderAmount', '80000.00');

        // La caché estática debe haberse invalidado tras guardar.
        $this->assertSame('80000.00', Company::current()->minOrderAmount);
    }

    // ── Usuarios ─────────────────────────────────────────────────────────────

    public function test_solo_el_administrador_gestiona_usuarios(): void
    {
        $this->actingAsRole(UserRole::CAJERO);
        $this->getJson('/api/admin/users')->assertStatus(403);

        $this->actingAsRole(UserRole::ADMINISTRADOR);
        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'roles']);
    }

    public function test_crea_un_usuario_con_contrasena_hasheada(): void
    {
        $this->actingAsRole(UserRole::ADMINISTRADOR);

        $this->postJson('/api/admin/users', [
            'name' => 'Nuevo Empacador',
            'email' => 'nuevo@distribuidora.test',
            'password' => 'clave-segura-123',
            'role' => UserRole::EMPACADOR->value,
        ])->assertCreated()->assertJsonPath('data.roleLabel', 'Empacador');

        $creado = User::where('email', 'nuevo@distribuidora.test')->firstOrFail();
        $this->assertNotSame('clave-segura-123', $creado->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('clave-segura-123', $creado->password));
    }

    public function test_no_deja_quedarse_sin_ningun_administrador_activo(): void
    {
        $admin = $this->actingAsRole(UserRole::ADMINISTRADOR);
        $otro = User::factory()->role(UserRole::CAJERO)->create();

        // Es el único administrador: no puede degradarse ni desactivarse.
        $this->putJson("/api/admin/users/{$admin->id}", ['role' => UserRole::CAJERO->value])
            ->assertStatus(422);

        $this->deleteJson("/api/admin/users/{$admin->id}")->assertStatus(422);

        // Con un segundo administrador, ya se puede.
        $segundo = User::factory()->role(UserRole::ADMINISTRADOR)->create();
        $this->putJson("/api/admin/users/{$admin->id}", ['role' => UserRole::CAJERO->value])->assertOk();

        unset($otro, $segundo);
    }

    public function test_no_se_puede_desactivar_el_propio_usuario(): void
    {
        $admin = $this->actingAsRole(UserRole::ADMINISTRADOR);
        User::factory()->role(UserRole::ADMINISTRADOR)->create();

        $this->deleteJson("/api/admin/users/{$admin->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'CANNOT_DISABLE_SELF');
    }
}

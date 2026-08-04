# Distribuidora de Salsamentaria — contexto del proyecto

> Leer esto al iniciar cualquier sesión de trabajo.
> El tablero vivo de tareas es `docs/PLAN-DE-TRABAJO.md`.
> Última actualización: 2026-07-31.

---

## 0. NO ES MULTI-TENANT

Lo más importante del proyecto y lo que más fácil se rompe por inercia:

**Este sistema es a la medida de UN SOLO NEGOCIO.** No es un SaaS, no tiene tenants,
no tiene sedes, no hay aislamiento de datos entre clientes y **no debe introducirse
nada de eso**. Concretamente:

- **No existe** `locationId` en ninguna tabla, ni `TenantScope`, ni `BelongsToTenant`,
  ni middleware de contexto, ni header `X-Tenant-Location`, ni impersonación.
- El **login es simple**: correo y contraseña. Lo único que diferencia a un usuario
  de otro es su `role`.
- Los datos del negocio viven en la tabla **`company`, que tiene UNA SOLA FILA**.
  Se consulta con `Company::current()`. Ninguna tabla del dominio tiene FK hacia ella.
- No hay roles de plataforma multi-tenant (no existe `SOCIO` ni nada que implique
  varios negocios). Sí existe `SUPERADMIN`: es la cuenta de soporte/proveedor del
  software, un nivel de permisos por encima de `ADMINISTRADOR` dentro del MISMO
  negocio — no reintroduce sedes, tenants ni impersonación.

Si aparece la tentación de "dejarlo preparado para varias sedes": no. Añade
complejidad a cada consulta, a cada test y a cada índice, para un caso que no existe.
`AuthTest::test_el_payload_no_expone_datos_de_tenant()` es la prueba de regresión que
lo vigila.

---

## 1. Qué es

Sistema para una distribuidora de salsamentaria (embutidos, carnes frías, cárnicos
porcionados) en Bogotá. Vende por kilo a tiendas, restaurantes y consumidor final,
recibe la mayoría de los pedidos **por WhatsApp** y despacha a domicilio.

Lo que lo distingue de un POS de retail común:

1. **Peso variable (catchweight).** El cliente pide "2 kg de chorizo"; se despachan
   2,140 kg y ese es el peso que se factura. Todo el inventario lleva **doble saldo:
   unidades y kg**.
2. **Trazabilidad de lote obligatoria.** Ante un retiro de producto hay que poder decir
   a qué clientes se despachó cada lote. Ningún movimiento de stock existe sin lote.
3. **Cadena de frío.** Bodegas con rango de temperatura, bitácora de lecturas y merma
   cuando se rompe la cadena.
4. **Pedidos por WhatsApp con IA vía n8n**, que nunca tocan stock directamente: crean
   un borrador que un humano aprueba.

### Origen

El motor de inventario se **inspiró en DaliOrder** (`C:\MisProgramas\repos\DaliOrder`,
SaaS de bares/restaurantes). Se tomaron las ideas probadas —FIFO por vencimiento,
kardex inmutable, lotes, mermas con evidencia— y se corrigieron sus limitaciones para
cárnicos. **De DaliOrder NO se trajo el multi-tenancy**, que era necesario allá porque
es un SaaS y aquí sólo sería peso muerto. El detalle está en `docs/TECNICO.md`.

---

## 2. Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 12, PHP 8.3, Sanctum 4 |
| Base de datos | MySQL 8.4 vía Laragon (`C:\laragon`), BD `distribuidora` |
| Tests backend | PHPUnit sobre SQLite en memoria |
| Frontend | React 19, Vite 8, React Router 7, TanStack Query 5, **Tailwind CSS v4** |
| Tests frontend | Vitest + Testing Library |
| Calidad | Pint (formato), Larastan nivel 6, oxlint |

**Auth**: Laravel Sanctum (tokens Bearer para SPA/n8n + sesión). No es JWT.

```
C:\MisProgramas\distribuidora\
├── distribuidora-backend\   API Laravel
├── distribuidora-front\     SPA React
├── n8n\                     workflows exportados (Fase 6)
└── docs\                    esta documentación
```

---

## 3. Convenciones OBLIGATORIAS

### Tablas en singular
```
company, user, unit, category, product, lot, stock, stock_movement, waste, order
```

### Timestamps custom en camelCase
```php
const CREATED_AT = 'createdAt';
const UPDATED_AT = 'updatedAt';
const DELETED_AT = 'deletedAt';   // si usa SoftDeletes
```

### Foreign keys en camelCase
```
productId, warehouseId, lotId, customerId, orderId, supplierId
```

### Migraciones portables a SQLite
La suite corre sobre SQLite en memoria. Evitar `ALTER TABLE` que agregue FKs y
sintaxis exclusiva de MySQL: rompe los tests aunque funcione en producción. Ordenar
las migraciones para que las FKs se declaren en el `CREATE TABLE`.

### Columnas generadas para saldos derivados
`stock.availableUnits` y `stock.availableKg` las calcula la base de datos
(`storedAs`). Nunca recalcular un valor derivado a mano si la BD puede hacerlo:
es la única forma de garantizar que no se desincronice.

---

## 4. Usuarios y roles

Todos los usuarios pertenecen al mismo negocio. El control de acceso es sólo por rol,
con el middleware `role:ADMINISTRADOR,CAJERO`.

| Rol | Qué hace |
|---|---|
| `SUPERADMIN` | Cuenta de soporte/proveedor del software. Puede gestionar cualquier usuario, incluidos administradores |
| `ADMINISTRADOR` | Dueño o gerente. Acceso total, incluidos costos, márgenes y cartera |
| `VENDEDOR` | Toma pedidos y gestiona clientes |
| `CAJERO` | Caja, cobros, arqueo |
| `ALMACENISTA` | Recepción, traslados, conteos, mermas |
| `EMPACADOR` | Alistamiento y embalaje (captura el peso real) |
| `MAQUILADOR` | Órdenes de despiece y porcionado |
| `DOMICILIARIO` | Transporte y entrega |

Los permisos finos son métodos del enum `App\Enums\UserRole`
(`canManageInventory()`, `canPickAndPack()`, `canAuthorizeOverrides()`, …).
**Agregar un rol**: primero el enum, después los helpers.

---

## 5. Estructura del backend

```
app/
  Enums/            UserRole, SaleMode, QuantityDriver, LotStatus, MovementType,
                    MovementDirection, ReservationStatus, UnitKind
  Models/           Company (fila única), User, Unit, UnitConversion, Category,
                    Product, ProductBarcode, WarehouseType, Warehouse,
                    TemperatureLog, Supplier, Lot, Stock, StockMovement,
                    StockReservation
  Services/         AuthLoginService, CodeGeneratorService, UnitConversionService,
                    ColdChainService, InventoryService, StockReconciliationService
  Jobs/             ReconcileStockJob
  Support/          ConsumptionLine, StockDiscrepancy
  Http/
    Controllers/Api/    AuthController
    Middleware/         CheckRole (alias `role`)
routes/api.php · routes/console.php (tareas programadas)
```

Prefijos de ruta: `/api/auth/*` (sesión), `/api/admin/*` (back-office, requiere
`auth:sanctum`), `/api/integration/*` (n8n y WhatsApp, Fase 6).

### Reglas para código nuevo

1. La lógica de negocio va en **servicios**, no en controllers ni modelos.
2. Modelos en `app/Models/`, servicios en `app/Services/`, controllers en
   `app/Http/Controllers/Api/`.
3. Migraciones nuevas con prefijo `2026_MM_DD_XXXXXX_...`.
4. **Nunca** escribir saldos de inventario por fuera de `InventoryService`:
   `ReconcileStockJob` lo detecta y alerta.
5. Antes de dar una tarea por terminada: `php artisan test`,
   `./vendor/bin/pint` y `./vendor/bin/phpstan analyse` en verde.

---

## 6. Estructura del frontend

```
src/
  services/api.js         cliente axios (token Bearer + 401 → /login)
  context/                auth-context.js + AuthContext.jsx (AuthProvider)
  hooks/
    useAuth.js · useToast.js
    queries/              hooks TanStack Query (useCatalog, useInventory, useWarehouses, useUsers)
  lib/                    format.js (kg/$/fechas), domain.js (badges), nav.js (menú + permisos)
  components/
    ProtectedRoute.jsx · AppLayout.jsx
    ui/                   primitives.jsx, DataTable, Modal, Combobox, ToastProvider
  pages/
    Login · Dashboard · Pedidos (próximamente)
    inventory/ (Recepcion, Stock, Lotes, Kardex) · catalog/ · warehouses/ · admin/ (Usuarios)
  styles/tailwind.css     entrada única: @import tailwindcss + @theme + @layer components
```

### Estilos — Tailwind CSS v4, identidad "Frío y Brasa"
La paleta vive en `@theme` (tokens `--color-primary: #8c1c2b` vinotinto,
`--color-secondary: #1f6f8b` azul frío, sobre crema `--color-cream: #faf7f2`), lo que
habilita utilidades `bg-primary`, `text-secondary`, etc. Los componentes reutilizables
(botones, tablas, badges, modales) se definen en `@layer components`; el código nuevo
usa utilidades directamente en el JSX. **Nunca colores literales: usar los tokens.**
Las cifras de peso y dinero usan la clase `.num` (tipografía tabular).

---

## 7. Cómo correr el proyecto

```bash
# Backend — requiere Laragon con MySQL arriba y la BD `distribuidora` creada
cd distribuidora-backend
composer install
php artisan migrate --seed
php artisan serve            # http://localhost:8000

# Frontend
cd distribuidora-front
npm install
npm run dev                  # http://localhost:5173 (proxy /api → :8000)
```

### Usuarios demo (contraseña: `password`)

| Rol | Email |
|---|---|
| Administrador | `administrador@distribuidora.test` |
| Vendedor | `vendedor@distribuidora.test` |
| Cajero | `cajero@distribuidora.test` |
| Almacenista | `almacenista@distribuidora.test` |
| Empacador | `empacador@distribuidora.test` |
| Maquilador | `maquilador@distribuidora.test` |
| Domiciliario | `domiciliario@distribuidora.test` |

Patrón: `<rol-en-minúscula>@distribuidora.test`. **Son sólo para desarrollo**: antes
de instalar en la sede del cliente hay que crear los usuarios reales y borrar estos.

---

## 8. Trampas conocidas del entorno

- **`Company::current()` memoriza en una propiedad estática** que sobrevive entre
  tests del mismo proceso. `Tests\TestCase::setUp()` llama a `forgetCurrent()`;
  si se escribe un test que no extiende esa clase base, hay que hacerlo a mano.
- **Node 25 + Vitest**: Vitest pasa `--localstorage-file` sin ruta y el `localStorage`
  nativo de Node tapa el de jsdom sin implementar la Storage API.
  `src/test/setup.js` lo sustituye por una implementación en memoria, y el cliente
  usa `window.localStorage` explícito.
- **Windows es case-insensitive**: dos módulos que difieran sólo en mayúsculas
  (`authContext.js` vs `AuthContext.jsx`) se resuelven al mismo archivo y producen
  imports `undefined`. Nombrar en kebab-case los módulos que no son componentes.
- **react-router 7**: ninguna versión de la línea 7 está libre de avisos de seguridad.
  Se fija **7.18.2**: corrige el XSS por open redirect (que sí aplica a una SPA) y
  el aviso que queda es de modo RSC, que este proyecto no usa.

# Documento técnico del dominio

Decisiones de diseño y su porqué. Complementa `CLAUDE.md` (contexto general)
y `PLAN-DE-TRABAJO.md` (estado de las tareas).

---

## 1. Qué se tomó de DaliOrder y qué se cambió

DaliOrder resolvió bien: FIFO por vencimiento, kardex inmutable, lotes,
bodegas con rango de temperatura, mermas con evidencia y autorización,
caja con arqueo, facturación DIAN vía Matías. Todo eso se reutiliza.

**Lo que NO se trajo: el multi-tenancy.** DaliOrder es un SaaS y necesita
`locationId` en cada tabla, un scope global, impersonación por header y roles de
plataforma. Este sistema es a la medida de un solo negocio: ese andamiaje sólo
añadiría una condición a cada consulta, una columna a cada índice y un caso a cada
test, sin resolver ningún problema real. Los datos del negocio viven en `company`,
una tabla de **una sola fila** sin FKs entrantes. Ver `CLAUDE.md` §0.

Lo que **no servía tal cual** para una distribuidora de cárnicos:

| # | Limitación en DaliOrder | Decisión en este proyecto |
|---|---|---|
| 1 | No existe peso variable: la cantidad es un solo número. | **Doble saldo en todo el kardex**: `quantityUnits` + `quantityKg`. `product.saleMode` = `WEIGHT` / `UNIT` / `FIXED_PACK`. La línea del pedido guarda peso solicitado y peso realmente despachado. |
| 2 | `ingredient` (lo que se compra) y `product` (lo que se vende) son entidades distintas unidas por receta. | **Una sola entidad `product`**. En distribución el 90 % es comprar y revender igual. Las recetas se reemplazan por `production_order` (maquila), que sí necesita entrada → salidas. |
| 3 | Si los lotes no cubren el stock, `InventoryService` sólo escribe un `Log::warning` y descuadra. | **Lote obligatorio y falla dura.** Sin lote no hay movimiento. |
| 4 | Se sabe de qué lote salió un movimiento, pero no a qué cliente. | `order_item_lot`. Reporte de recall: dado un lote, todos los clientes que lo recibieron. |
| 5 | `lot.supplierName` / `supplierNit` son texto libre aunque existe la tabla `supplier`. | `lot.supplierId` FK **+ `lot.supplierLotCode`**: el lote del fabricante es el que aparece en un recall del INVIMA. |
| 6 | `stock.availableQuantity` se recalcula a mano en cada `save()`; `reservedQuantity` no dice quién reservó. | Tabla `stock_reservation` ligada al pedido, con expiración. Evita vender dos veces el mismo lote durante el alistamiento. |
| 7 | Triple fuente de verdad sin verificación: `stock` desnormalizado, suma de `stock_movement`, suma de `lot.currentQuantity`. | Se mantiene la desnormalización por performance, pero con `ReconcileStockJob` diario que compara las tres y alerta. |
| 8 | FIFO y costo promedio conviven sin regla de cuál manda. | **Costo de venta = costo del lote (FIFO real).** `averageCost` queda sólo como referencia para fijar precio. |
| 9 | No hay orden de compra: `receivePurchase()` crea el lote directo. | `purchase_order` → `goods_receipt` (con pesaje en recepción) → conciliación contra la factura del proveedor. |
| 10 | `warehouse.temperatureMin/Max` existe pero no hay bitácora de lecturas. | `temperature_log` + alerta de ruptura de cadena de frío, que genera merma. |
| 11 | Tres unidades rígidas por insumo (`purchase`/`storage`/`consume`) con dos factores fijos. | Apoyarse sólo en `unit_conversion`, que es más flexible. |
| 12 | **Multi-tenant** en todo el esquema: `locationId`, `TenantScope`, `BelongsToTenant`, impersonación por header. | **Eliminado.** Un solo negocio, login simple, control de acceso por rol. |
| 13 | Mermas genéricas. | Tipos propios de cárnicos: `VENCIMIENTO`, `DETERIORO`, `DESHIDRATACION`, `PURGA`, `TAJADO`, `ROTURA_EMPAQUE`, `ROTURA_CADENA_FRIO`, `DEVOLUCION_CLIENTE`. La deshidratación se calcula sola comparando peso de entrada contra peso al despachar. |

---

## 2. Conceptos clave del dominio

### 2.1 Peso variable (catchweight)

Es la decisión estructural del sistema. Tres modos de venta por producto:

- **`WEIGHT`** — precio por kg, peso variable. *Chorizo ahumado $30.300/kg.*
  El cliente pide una cantidad aproximada; el peso real se captura al alistar.
- **`UNIT`** — precio por unidad, peso irrelevante. *Queso de cabeza $19.500/un.*
- **`FIXED_PACK`** — se vende por unidad pero descuenta un peso fijo conocido
  del inventario. *Paquete de 500 g.*

Consecuencias:

1. `stock`, `lot` y `stock_movement` llevan **dos saldos**: unidades y kg.
2. `order_item` guarda `requestedQuantity` + `requestedUnit`, y después
   `pickedUnits` + `pickedKg`. **El total se recalcula sobre lo despachado.**
3. La diferencia entre pedido y despacho se valida contra
   `product.weightTolerancePercent` (por defecto el de `company`). Fuera de
   tolerancia exige autorización.

#### La cantidad conductora (`QuantityDriver`)

Los dos saldos se agotan a ritmos distintos, así que en cada operación uno manda
y el otro es consecuencia. Lo define `SaleMode::driver()`:

| Modo | Conductor | El otro saldo |
|---|---|---|
| `WEIGHT` | kg | piezas, en proporción al peso retirado |
| `UNIT` | unidades | no aplica (siempre 0) |
| `FIXED_PACK` | unidades | kg exacto = unidades × `netWeightKg` |

**Limitación conocida y aceptada:** en un producto `WEIGHT`, `currentUnits` es un
**estimado**, no un conteo de piezas. Sacar 2,140 kg de un lote de 12,5 kg y 20
piezas descuenta 3,424 unidades — un número físicamente imposible. Es correcto
como proporción contable y es lo mejor que se puede inferir cuando el sistema
reparte por su cuenta, pero no hay que leerlo como "quedan 16,576 chorizos".

Por eso el **alistamiento real no usa ese reparto**: el empacador escanea el lote
e ingresa piezas y peso medidos, vía `InventoryService::consumeFromLot()`, y
ambos saldos quedan exactos. El reparto proporcional (`consumeFifo()`) sólo actúa
en asignaciones automáticas, mermas a granel y ajustes.

Cuando el conductor de un lote llega a cero se arrastra **todo** el residuo del
otro saldo, para no dejar kilos o piezas huérfanas que nadie podría consumir.

### 2.2 Trazabilidad bidireccional

- **Hacia atrás**: dado un despacho, de qué lote y qué proveedor vino.
- **Hacia adelante**: dado un lote, a qué clientes se despachó.

La segunda es la que exige un retiro de producto y la que DaliOrder no tiene.
La sostiene `order_item_lot`, porque una línea puede salir de varios lotes.

### 2.3 Movimientos inmutables

`stock_movement` es el libro mayor: **nunca se edita ni se borra**.
Un error se corrige con un movimiento contrario, no modificando el original.

### 2.4 FIFO por vencimiento

Sale primero el lote que vence primero (`ORDER BY expirationDate`, con los
lotes sin fecha al final). En perecederos es la única política defendible.

### 2.5 Maquila y despiece

`production_order`: **1 lote de entrada → N lotes de salida**. Un pernil se
despieza en jamón, hueso y grasa. Se registra rendimiento esperado vs real;
el faltante se contabiliza como merma y el costo de entrada se reparte entre
las salidas. El análisis de varianza sobre esos rendimientos es lo que detecta
robo o mal proceso.

### 2.6 Precio final

`PricingService` resuelve en cascada y **registra por qué llegó a ese precio**:

```
lista de precios del cliente
  → descuento por volumen (escalonado por kg o por monto)
  → promoción vigente
  → descuento manual (requiere rol con canAuthorizeOverrides)
```

### 2.7 Estados del pedido

```
BORRADOR → CONFIRMADO → EN_ALISTAMIENTO → EMPACADO → DESPACHADO → ENTREGADO
                     ↘ CANCELADO        ↘ DEVUELTO_PARCIAL
```

El stock **se reserva** al confirmar (no se descuenta) y **se descuenta** al
empacar, cuando ya se conoce el peso real.

---

## 2.8 Modelo de acceso

No hay tenants, sedes ni aislamiento de datos: **todos los usuarios ven los mismos
datos**, y lo único que cambia entre ellos es qué pueden hacer.

- Autenticación: `POST /api/auth/login` con correo y contraseña. Devuelve cookie de
  sesión (SPA) o Personal Access Token de Sanctum (n8n, scripts).
- Autorización: middleware `role:ADMINISTRADOR,ALMACENISTA` en la ruta, más los
  helpers de capacidad del enum `UserRole` para decisiones dentro de un servicio.
- `company` es la fila única con los datos y parámetros del negocio. Se lee con
  `Company::current()`, memorizado por petición. Está en base de datos y no en
  `.env` porque el cliente debe poder ajustar el mínimo de pedido o la tolerancia de
  peso desde la aplicación, sin un despliegue.

---

## 3. Integración WhatsApp + IA + n8n (Fase 6)

```
Cliente → WhatsApp Cloud API → webhook → n8n
                                          ├─ GET  /api/integration/customer/lookup?phone=
                                          ├─ GET  /api/integration/catalog
                                          ├─ LLM con tool-calling arma el pedido
                                          ├─ POST /api/integration/order/draft   (Idempotency-Key)
                                          ├─ responde resumen + total por WhatsApp
                                          └─ POST /api/integration/order/{id}/confirm
```

Reglas no negociables:

- **La IA nunca descuenta stock.** Crea un borrador; el backend valida
  existencias, lista de precios del cliente, mínimo de pedido y cupo de crédito.
- **Confirmación explícita del cliente** antes de que el pedido entre a operación.
- **Doble check humano en el MVP**: los pedidos de IA caen en `PENDIENTE_REVISION`.
- Autenticación máquina-a-máquina con **token de servicio Sanctum + HMAC +
  rate limit + idempotencia**.
- Auditoría completa en `whatsapp_conversation` / `whatsapp_message`:
  hay que poder reconstruir qué dijo la IA y por qué.
- Los workflows de n8n se versionan como JSON en `/n8n`, no sólo en la instancia.

---

## 4. Estado de implementación

**Fase 0 — Cimientos: COMPLETADA.**
Login simple (correo + contraseña), roles, autenticación dual (sesión SPA / token
API), tabla `company` de fila única, seeders, PHPStan nivel 6 y Pint limpios,
SPA React con login funcionando.

**Fase 1 — Catálogo e inventario: MOTOR COMPLETADO.**
Catálogo con los tres modos de venta, unidades y conversiones, bodegas con
bitácora de cadena de frío, lotes con doble saldo, kardex inmutable, reservas
con dueño y vencimiento, `InventoryService` (recepción, FIFO, ajustes,
traslados, anulación) y `StockReconciliationService` + `ReconcileStockJob`.
**67 tests en verde**, incluido el de propiedad de cuadre.

Pendiente de la Fase 1: órdenes de compra y recepción formal, conteos físicos,
endpoints REST y pantallas. Ver `PLAN-DE-TRABAJO.md`.

El resto de fases y su alcance están en `PLAN-DE-TRABAJO.md`.

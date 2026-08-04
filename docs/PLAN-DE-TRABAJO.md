# Tablero de trabajo

Fuente de verdad del estado del proyecto. Actualizar al cerrar cada tarea.

**Estado global:** Fase 0 completada · Fase 1 con el motor de inventario terminado
y verificado; falta compras formales, conteos, API y pantallas.
**127 tests en verde**, PHPStan nivel 6 y Pint limpios.

> **El sistema NO es multi-tenant.** Un solo negocio, login simple, acceso por rol.
> Ver `CLAUDE.md` §0 antes de tocar el esquema.

| Fase | Alcance | Estimado | Estado |
|---|---|---|---|
| 0 | Cimientos: auth simple, roles, tooling | 1 sem | ✅ Completada |
| 1 | Catálogo e inventario con peso variable | 3 sem | 🔵 Motor listo |
| 2 | Clientes, listas de precios y pedidos | 3 sem | ⬜ |
| 3 | Alistamiento, embalaje y despacho | 2 sem | ⬜ |
| 4 | Caja, pagos y cartera | 2 sem | ⬜ |
| 5 | Mermas y maquila | 2 sem | ⬜ |
| 6 | Pedidos por WhatsApp con IA + n8n | 2 sem | ⬜ |
| 7 | Facturación DIAN y reportes | 2 sem | ⬜ |

---

## ✅ Fase 0 — Cimientos

- [x] Scaffold Laravel 12 + Sanctum + Pint + Larastan nivel 6
- [x] Login simple: `AuthLoginService` (sesión SPA + token API), `AuthController`, rate limit
- [x] Control de acceso por rol: middleware `CheckRole` + helpers del enum `UserRole`
- [x] Tabla `company` de **fila única** con los datos y parámetros del negocio
- [x] Enum `UserRole` con los 7 roles reales de la distribuidora
- [x] `CodeGeneratorService` para consecutivos (SKU, lote, pedido)
- [x] Seeders: negocio + un usuario por rol
- [x] **Sin multi-tenancy**: ni `locationId`, ni scope global, ni impersonación
- [x] SPA React 19 + Vite 8 + Router 7 + TanStack Query, login end-to-end verificado
- [x] Identidad visual "Frío y Brasa" en `styles/globals.css`
- [x] Documentación: `CLAUDE.md`, `TECNICO.md`, este tablero

---

## 🔵 Fase 1 — Catálogo e inventario con peso variable

El corazón del sistema. Nada de lo demás funciona si esto queda mal.

**Tablas creadas:** `unit`, `unit_conversion`, `category`, `product`,
`product_barcode`, `warehouse_type`, `warehouse`, `temperature_log`, `supplier`,
`lot`, `stock`, `stock_movement`, `stock_reservation`.

- [x] Migraciones y modelos con **doble saldo** (unidades + kg) en `stock`, `lot` y `stock_movement`
- [x] `product.saleMode` (`WEIGHT` / `UNIT` / `FIXED_PACK`) + `weightTolerancePercent`
- [x] `QuantityDriver`: qué saldo manda en cada operación (ver `TECNICO.md` §2.1)
- [x] `stock.available*` como **columnas generadas** por la BD — imposible que se desincronicen
- [x] `stock_movement` inmutable a nivel de modelo (`updating`/`deleting` lanzan excepción)
- [x] `InventoryService`: recepción con pesaje, FIFO por vencimiento, **lote obligatorio**,
      costo por lote, ajustes, traslados, anulación de lote, `lockForUpdate` con orden fijo
- [x] Reservas con dueño, vencimiento y barrido automático
- [x] `UnitConversionService` — falla en vez de adivinar factores
- [x] `temperature_log` + `ColdChainService` con detección de ruptura sostenida
- [x] `ReconcileStockJob`: compara `SUM(stock_movement)` vs `stock` vs `SUM(lot)`
- [x] **Test de propiedad**: 300 operaciones aleatorias → las tres fuentes cuadran en un y en kg
- [x] Seeder con las 18 referencias reales de salsamentaria + bodegas y unidades
- [x] **API REST bajo `/api/admin/*`** — 40 rutas: catálogo, unidades, categorías,
      códigos de barras, proveedores, bodegas, temperatura, recepción, traslados,
      ajustes, mermas, anulación de lote, stock, lotes, kardex y trazabilidad
- [x] Permisos por rol en cada ruta; los **costos sólo los ve quien puede ver finanzas**
- [x] Gestión de usuarios y datos del negocio, con guarda del último administrador
- [ ] Orden de compra → recepción → conciliación con factura del proveedor
- [ ] Conteos físicos (`physical_count`) y ajuste masivo
- [x] **Códigos automáticos**: SKU, categoría, proveedor y bodega se autogeneran
      (CodeGeneratorService); los campos de código desaparecen de los formularios
- [x] **Frontend del inventario** (React 19): shell con sidebar por rol, dashboard con
      KPIs y alertas, **recepción con formulario adaptativo al modo de venta** (kg /
      unidad / peso derivado), existencias, lotes con **trazabilidad hacia adelante**,
      kardex, productos, proveedores y bodegas con cadena de frío
- [x] Sistema de diseño "Frío y Brasa": tablas, badges, modales, toasts, combobox
- [x] Responsive verificado (colapsa a 1 columna, tablas con scroll interno) y sin
      enums crudos en la UI (todo con etiquetas en español)

---

## ⬜ Fase 2 — Clientes, listas de precios y pedidos

**Tablas:** `customer`, `price_list`, `price_list_item`, `volume_discount`,
`promotion`, `order`, `order_item`, `order_status_history`.

- [ ] `customer` con tipo, lista asignada, cupo de crédito y plazo
- [ ] `PricingService` en cascada, con log de cómo se llegó al precio
- [ ] `OrderService`: creación, validación de mínimo de pedido, reserva de stock al confirmar
- [ ] Máquina de estados del pedido
- [ ] Frontend: clientes, listas de precios, toma de pedidos

---

## ⬜ Fase 3 — Alistamiento, embalaje y despacho

**Tablas:** `picking_task`, `picking_line`, `order_item_lot`, `package`, `shipment`.

- [ ] Captura de **peso real** y lote por línea
- [ ] Recálculo del pedido con el peso despachado + validación de tolerancia
- [ ] Empaque: canastillas, hielo, peso total, precinto
- [ ] Etiquetas de lote (PDF/ZPL) y packing list
- [ ] **Reporte de recall**: dado un lote, todos los clientes que lo recibieron
- [ ] Frontend: pantalla de alistamiento pensada para tablet junto a la báscula

---

## ⬜ Fase 4 — Caja, pagos y cartera

**Tablas:** `cash_register`, `cash_session`, `cash_movement`, `payment`,
`account_receivable`, `receivable_payment`.

- [ ] Apertura, movimientos, arqueo y cierre con descuadre
- [ ] Medios de pago: efectivo, Nequi, transferencia, datáfono, crédito
- [ ] Cartera: cupo, plazo, edades, bloqueo automático por mora o sobrecupo
- [ ] Frontend: caja y estado de cartera

---

## ⬜ Fase 5 — Mermas y maquila

**Tablas:** `waste`, `production_order`, `production_output`.

- [ ] `waste` con los tipos de cárnicos, evidencia fotográfica y autorización
- [ ] `ProductionService`: 1 lote de entrada → N lotes de salida, con reparto de costo
- [ ] Merma automática por diferencia de rendimiento
- [ ] Análisis de varianza rendimiento esperado vs real

---

## ⬜ Fase 6 — Pedidos por WhatsApp con IA + n8n

**Tablas:** `whatsapp_conversation`, `whatsapp_message`, `order_draft`.

- [ ] API `/api/integration/*` con token de servicio, HMAC, rate limit e idempotencia
- [ ] Endpoints: catálogo, lookup de cliente, borrador de pedido, confirmación, chequeo de stock
- [ ] Workflows de n8n versionados en `/n8n`
- [ ] Bandeja `PENDIENTE_REVISION` en el back-office
- [ ] Verificar que reenviar el mismo mensaje **no duplica** el pedido

---

## ⬜ Fase 7 — Facturación DIAN y reportes

- [ ] Facturación electrónica vía Matías (portar `MatiasClient` + webhook HMAC)
- [ ] Reportes: ventas, rotación, próximos a vencer, mermas por tipo y responsable,
      rentabilidad real por producto, estado de cartera

---

## Punto abierto

Falta calibrar el mix real del negocio: **B2B a tiendas con crédito y ruta** vs
**B2C domicilio pagado al transportador**. El diseño soporta ambos, pero define
qué sube de prioridad: cartera y rutas (Fase 4) o canal WhatsApp (Fase 6).
Resolver con el cliente antes de la Fase 2.

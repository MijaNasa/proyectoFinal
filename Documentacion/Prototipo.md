# Prototipo del Sistema

## 1. Enfoque adoptado

Según la planificación original (ver Diagrama de Gantt, etapa 5 "Implementación y pruebas"), el prototipo constituye el paso previo a la codificación definitiva, destinado a validar tempranamente el diseño de pantallas y flujos con el cliente antes de invertir en el desarrollo completo.

Dado que el alcance y los flujos principales del sistema quedaron claramente definidos durante la etapa de diseño preliminar (Análisis de datos, Diseño preliminar del sistema), el equipo optó por **evolucionar el prototipo directamente hacia el producto final**, en lugar de construir una maqueta descartable separada del código real. Esto permitió validar cada pantalla con datos y comportamiento reales desde el inicio, reduciendo el riesgo de que el prototipo no reflejara fielmente las restricciones técnicas del sistema (stock por sucursal, roles y permisos, integración con Mercado Pago, etc.).

En consecuencia, el prototipo del sistema **es el propio sistema desplegado**, en su estado funcional actual. A continuación se documenta, pantalla por pantalla, cada funcionalidad implementada.

**URL del sistema en producción:** `[COMPLETAR: URL del deploy en Render]`

---

## 2. Tienda Online (Clientes)

### 2.1 Catálogo público

Grilla de productos con búsqueda por título/autor/ISBN y filtros por Categoría, Autor, Editorial e Idioma. Cada producto muestra su portada, precio y estado de stock (*Disponible*, *Quedan pocos*, *Sin stock* o *Preventa*).

`[CAPTURA: /catalogo — grilla de productos con filtros desplegados]`

### 2.2 Ficha de producto

Detalle de cada tomo: sinopsis, autor, formato, editorial, idioma, precio, stock por sucursal, opciones de envío disponibles y productos relacionados de la misma serie/categoría.

`[CAPTURA: /catalogo/{id} — ficha completa de un producto]`

### 2.3 Carrito de compras

Permite modificar cantidades y quitar productos, con el total actualizándose en tiempo real sin recargar la página.

`[CAPTURA: /carrito con 2 o más productos cargados]`

### 2.4 Checkout

El cliente elige comprar con cuenta o como invitado (identificado por DNI, con autocompletado si ya compró antes). Se selecciona tipo de envío (retiro en sucursal o domicilio) y método de pago (Mercado Pago, Efectivo o Transferencia).

`[CAPTURA: formulario de checkout con los datos de invitado y el método de pago]`

### 2.5 Confirmación de pedido

Pantalla posterior a la compra con el número de pedido y el estado real del pago (aprobado, o pendiente si es Efectivo/Transferencia), con opción de subir el comprobante.

`[CAPTURA: pantalla de confirmación del pedido]`

### 2.6 Mi Cuenta

Historial completo de pedidos del cliente, con el estado de cada uno, descarga/carga de comprobantes, y el historial de conversación con el asistente de IA.

`[CAPTURA: /mi-cuenta — listado de pedidos del cliente]`

### 2.7 Asistente de recomendaciones (IA)

Widget de chat flotante que recomienda títulos reales del catálogo según los gustos que indique el cliente (género, para quién es el regalo, nivel de experiencia). Disponible para visitantes (con límite de mensajes) y con más mensajes para usuarios logueados.

`[CAPTURA: widget del chatbot abierto con una recomendación de producto]`

### 2.8 Suscripción a series

Desde la ficha de una obra, el cliente puede suscribirse para recibir aviso (y un descuento) cuando ingresen nuevos tomos, a partir del tomo desde el que se suscribió.

`[CAPTURA: modal/botón de suscripción a una serie en la ficha de producto]`

### 2.9 Login y registro

Acceso con email/contraseña, con verificación reCAPTCHA. Los clientes invitados quedan con una cuenta generada automáticamente (contraseña = DNI) para poder loguearse más adelante.

`[CAPTURA: pantalla de login]`

---

## 3. Panel de Gestión (Personal)

### 3.1 Dashboard

Pantalla de inicio del panel, con contenido adaptado al rol: un administrador ve el panorama completo (ventas, stock, accesos rápidos); un repartidor ve únicamente sus rutas del día.

`[CAPTURA: Dashboard de un usuario administrador]`

### 3.2 Catálogo de Productos

Listado de obras (LibroMaster), cada una desplegable para ver sus tomos individuales. Permite dar de alta obras y tomos, gestionar precios (con historial) y aplicar aumentos masivos por proveedor, formato, serie o categoría.

`[CAPTURA: Catálogo de Productos con una obra desplegada mostrando sus tomos]`

### 3.3 Ajustes de Catálogo

Gestión de las entidades auxiliares del catálogo: categorías, autores, editoriales/proveedores, idiomas y formatos.

`[CAPTURA: pantalla de Ajustes de Catálogo]`

### 3.4 Terminal de Ventas (POS)

Punto de venta presencial: búsqueda de productos por nombre o escaneo de ISBN, carga del carrito de venta, búsqueda o alta rápida de cliente, aplicación automática de descuentos por suscripción, y cobro con múltiples métodos de pago (Efectivo, Transferencia, Tarjeta, Débito, Mercado Pago, Cuenta Corriente).

`[CAPTURA: Terminal de Ventas con productos cargados en el carrito de venta]`

### 3.5 Gestión de Ventas

Listado de todas las ventas organizado en pestañas (Activas, Finalizadas, Canceladas), con vista de detalle, cambio de estado siguiendo el flujo permitido, confirmación manual de pagos pendientes y descarga del comprobante en PDF.

`[CAPTURA: listado de Ventas con las pestañas Activas / Finalizadas / Canceladas]`

### 3.6 Clientes

Listado de clientes con su historial de compras, saldo de cuenta corriente y suscripciones activas.

`[CAPTURA: ficha de un cliente con su historial de compras]`

### 3.7 Proveedores

Administración de editoriales/distribuidoras: datos de contacto, deuda actual, historial de órdenes de compra y registro de pagos.

`[CAPTURA: ficha de un proveedor con su historial de pagos]`

### 3.8 Órdenes de Compra

Registro de pedidos de reposición a proveedores. Al marcar una orden como recibida, se actualiza el stock, se recalcula el costo promedio ponderado y se avisa al personal si hay clientes suscriptos esperando ese título.

`[CAPTURA: listado o detalle de una Orden de Compra]`

### 3.9 Empleados y Cargos

Alta de personal con asignación de sucursal y cargo (Vendedor, Despachador, Repartidor, Gerente, Administrador). Los Cargos definen qué permisos tiene cada rol (acceso a Ventas, Reportes, Repartos, gestión de Catálogo, etc.).

`[CAPTURA: pantalla de Empleados con el alta/edición de un empleado]`

### 3.10 Sucursales

Alta y edición de sucursales físicas, con desactivación lógica (no se eliminan, se preservan para no perder trazabilidad de ventas y stock históricos).

`[CAPTURA: listado de Sucursales]`

### 3.11 Stock

Consulta de existencias por sucursal, con alertas de stock bajo o agotado.

`[CAPTURA: pantalla de Stock]`

### 3.12 Caja y Gastos

Apertura/cierre de caja diario por sucursal con el detalle de ingresos por método de pago, y registro de egresos operativos.

`[CAPTURA: pantalla de Caja o Gastos]`

### 3.13 Rutas de Reparto

El despachador crea rutas, asigna un repartidor y las ventas con envío a domicilio, y puede optimizar el orden de las paradas. El repartidor ve únicamente sus propias rutas asignadas y puede iniciarlas, marcar cada parada como entregada (o registrar una incidencia) y finalizarlas.

`[CAPTURA: detalle de una Ruta de Reparto con sus paradas]`

### 3.14 Reportes

Tres vistas filtrables por fecha y sucursal: **Ventas** (evolución diaria, productos y clientes más vendidos), **Stock** (productos sin stock o con stock bajo, ranking de rotación) y **Balance** (ingresos, costos y rentabilidad).

`[CAPTURA: pantalla de Reportes, pestaña Ventas]`

### 3.15 Predicción de Demanda

Selección de un libro, tomo o categoría para ver su historial de ventas de las últimas 16 semanas junto con un pronóstico calculado por suavizado exponencial, para decidir cuánto reponer.

`[CAPTURA: gráfico de Predicción de Demanda de un producto]`

### 3.16 Notificaciones

Panel de avisos sobre eventos relevantes: nuevas ventas, comprobantes subidos por clientes, ingreso de stock esperado por suscriptores, traslados pendientes entre sucursales.

`[CAPTURA: panel de notificaciones desplegado]`

---

## 4. Anexo — Índice de capturas pendientes

Para completar el documento, tomar una captura de cada punto marcado como `[CAPTURA: ...]` (25 en total) y reemplazar el texto entre corchetes por la imagen correspondiente. Se recomienda numerar las capturas en el mismo orden en que aparecen en este documento (Figura 1, Figura 2, ...) para facilitar su referencia.

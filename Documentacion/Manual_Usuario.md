# Manual de Usuario — PuroComic

Sistema de Gestión Integral para Librería especializada en cómics, manga y novelas gráficas.

*Equipo: Paiano Krenz, Nasatsky, Glocer.*

> **Nota para quien arme el Word final:** este documento está listo para pegar en Word/Google Docs (los `#`, `##`, `###` se convierten en Título 1/2/3 automáticamente si se pega con "mantener formato" desde un visor Markdown, o abriendo este archivo con Pandoc: `pandoc Manual_Usuario.md -o Manual_Usuario.docx`). Cada bloque `[CAPTURA: ...]` indica exactamente qué pantalla capturar y dónde insertarla.

---

## 1. Introducción

**PuroComic** es un sistema de gestión integral para una librería especializada en cómics, manga y novelas gráficas, con dos sucursales físicas (Rosario y Funes) y una tienda online integrada. El sistema cubre todo el circuito del negocio: catálogo y stock, ventas (presenciales y online), proveedores y compras, empleados y permisos, logística de entregas, reportes y predicción de demanda, y un asistente de recomendaciones con inteligencia artificial para los clientes.

### 1.1 Roles del sistema

| Rol | Qué puede hacer |
|---|---|
| **Cliente / Visitante** | Navegar el catálogo, comprar online (con o sin cuenta), consultar sus pedidos, usar el chatbot de recomendaciones. |
| **Vendedor** | Operar la Terminal de Ventas (POS), gestionar ventas de su sucursal. |
| **Despachador** | Gestionar rutas de reparto y logística. |
| **Repartidor** | Ver y actualizar el estado de sus propias rutas de reparto asignadas. |
| **Gerente** | Todo lo del Vendedor/Despachador, más acceso a Reportes, Empleados y gestión ampliada de Ventas/Stock. |
| **Administrador** | Acceso total: catálogo, proveedores, empleados, cargos y permisos, configuración de sucursales, reportes y predicción de demanda. |

Los permisos de cada rol se administran desde **Administración → Cargos**, asignando permisos puntuales a cada cargo (ej. `ventas.acceder`, `reportes.acceder`, `repartos.acceder`).

---

## 2. Acceso al sistema

### 2.1 Tienda online (clientes)

No requiere inicio de sesión para navegar el catálogo ni para agregar productos al carrito. Se accede directamente en la URL principal del sitio.

`[CAPTURA: Página principal del catálogo (/catalogo), mostrando la grilla de productos y los filtros de búsqueda]`

### 2.2 Panel de gestión (personal)

El personal (vendedores, despachadores, repartidores, gerentes, administradores) ingresa desde **`/login`** con su email y contraseña.

`[CAPTURA: Pantalla de login (/login)]`

- Usuario administrador de referencia: `admin@purocomic.com`
- Tras iniciar sesión, el sistema redirige automáticamente al **Dashboard**, cuyo contenido varía según el rol (un repartidor ve sus rutas asignadas; un vendedor ve estadísticas de ventas; un administrador ve el panorama completo).

`[CAPTURA: Dashboard con las tarjetas de resumen (ventas del día, stock, accesos rápidos)]`

---

## 3. Manual para Clientes (Tienda Online)

### 3.1 Catálogo

Desde **Catálogo** el cliente puede:

- Buscar por título, autor o ISBN con la barra de búsqueda.
- Filtrar por **Categoría**, **Autor**, **Editorial** e **Idioma** (los desplegables solo muestran opciones que tienen productos reales disponibles).
- Ver el estado de stock de cada producto: *Disponible*, *Quedan pocos*, *Sin stock* o *Preventa*.

`[CAPTURA: Filtros de catálogo desplegados, con al menos un filtro de Editorial seleccionado]`

### 3.2 Ficha de producto

Al hacer clic en un producto se accede a su ficha, con:

- Sinopsis, autor, formato, editorial e idioma.
- Precio actual y stock por sucursal.
- Opciones de envío disponibles (retiro en sucursal o envío por Correo Argentino).
- Botón para agregar al carrito.
- Productos similares de la misma serie o categoría.

`[CAPTURA: Ficha de un producto (/catalogo/{id}), completa con sinopsis y opciones de compra]`

### 3.3 Carrito de compras

El carrito (**`/carrito`**) permite modificar cantidades, quitar productos y ver el total actualizado en tiempo real, sin necesidad de recargar la página.

`[CAPTURA: Carrito de compras con 2 o más productos cargados]`

### 3.4 Checkout (finalizar compra)

Al confirmar la compra, el sistema ofrece dos caminos:

- **Con cuenta**: el cliente inicia sesión o se registra.
- **Como invitado**: el cliente completa sus datos (nombre, DNI, email, teléfono). El sistema identifica al cliente por su **DNI**; si ya compró antes como invitado, sus datos se autocompletan. Se genera automáticamente una cuenta cuya contraseña inicial es el propio DNI, para que pueda consultar sus pedidos más adelante iniciando sesión.

Se elige el tipo de envío (retiro en sucursal o envío a domicilio) y el método de pago:

- **Mercado Pago** (tarjeta, dinero en cuenta): confirmación de pago automática.
- **Efectivo** o **Transferencia**: la venta queda pendiente de pago hasta que el cliente presente/suba el comprobante.

`[CAPTURA: Formulario de checkout mostrando los datos de invitado y la selección de método de pago]`

### 3.5 Confirmación y comprobante

Tras la compra se muestra una pantalla de confirmación con el número de pedido. Si el pago fue por Efectivo o Transferencia, se indica que el pedido quedó **pendiente de pago** (no "aprobado"), y se ofrece la opción de subir el comprobante de la transferencia.

`[CAPTURA: Pantalla de confirmación del pedido, con el estado del pago y la opción de subir comprobante]`

### 3.6 Mi Cuenta

Los clientes registrados acceden a **Mi Cuenta** para:

- Ver el historial completo de sus pedidos y el estado de cada uno.
- Descargar o subir el comprobante de pago de un pedido pendiente.
- Consultar el historial de conversación con el asistente de IA.

`[CAPTURA: Sección "Mis Pedidos" dentro de Mi Cuenta, con al menos un pedido listado]`

### 3.7 Asistente de recomendaciones (IA)

El botón flotante 🤖 abre un chat que recomienda títulos del catálogo real según los gustos del cliente (género, para quién es, nivel de experiencia leyendo manga/cómics). Está disponible para visitantes (con un límite de mensajes por IP) y con más mensajes disponibles para usuarios que inician sesión.

`[CAPTURA: Widget del chatbot abierto, con una recomendación de producto mostrada en la conversación]`

### 3.8 Suscripción a series

Un cliente puede suscribirse a una serie desde su ficha para recibir un aviso (y un descuento) cuando ingresen nuevos tomos a stock, a partir del tomo desde el que se suscribió.

---

## 4. Manual para Vendedores (Terminal de Ventas)

Se accede desde **Ventas → Terminal de Ventas** (requiere el permiso `ventas.acceder`).

### 4.1 Selección de sucursal

Los administradores pueden elegir en qué sucursal están operando (útil si atienden desde cualquier local); el resto del personal opera automáticamente en la sucursal donde está asignado su legajo.

`[CAPTURA: Terminal de Ventas con el selector de sucursal visible]`

### 4.2 Búsqueda y carga de productos

Se busca por título o se escanea/ingresa el **ISBN** del producto. El sistema descuenta del stock de la sucursal activa en tiempo real.

`[CAPTURA: Terminal de Ventas con la búsqueda de productos y el carrito de la venta en curso]`

### 4.3 Datos del cliente

Se puede buscar un cliente existente (por nombre, DNI o email) o darlo de alta rápidamente sin salir de la pantalla de venta. Si el cliente tiene una suscripción activa a alguna de las series que está comprando, el descuento correspondiente se aplica automáticamente.

### 4.4 Métodos de pago y confirmación

Disponibles: Efectivo, Transferencia, Tarjeta, Débito, Mercado Pago y Cuenta Corriente (para clientes habilitados). Al confirmar, la venta queda registrada con estado **Finalizado** (o **Pendiente de pago** si se marca como una excepción, por ejemplo una seña).

---

## 5. Gestión de Ventas (Panel de Administración)

En **Ventas** se listan todas las ventas, organizadas en pestañas:

- **Activas**: en preparación, esperando traslado entre sucursales, listas para retirar, o en camino.
- **Finalizadas**: ventas ya completadas.
- **Canceladas**.

`[CAPTURA: Listado de Ventas mostrando las pestañas Activas / Finalizadas / Canceladas]`

Desde el detalle de cada venta se puede:

- Cambiar su estado siguiendo el flujo permitido (por ejemplo: Pendiente de pago → En preparación → Listo para retiro → Entregado).
- Confirmar manualmente un pago pendiente.
- Descargar el comprobante/reporte en PDF.
- Cancelarla (repone automáticamente el stock descontado).

---

## 6. Manual para Administración

### 6.1 Catálogo de productos

En **Catálogo de Productos** cada fila representa una **obra** (ej. "Jujutsu Kaisen"), que se despliega para mostrar sus **tomos** individuales. Desde acá se gestiona:

- Alta de nuevas obras y tomos, con autor, categoría, editorial, formato, idioma y sinopsis.
- Precios (con historial de cambios) y aumentos masivos por proveedor, formato, serie o categoría.
- Activar/desactivar productos puntuales sin borrarlos.

`[CAPTURA: Catálogo de Productos con una obra desplegada mostrando sus tomos]`

### 6.2 Proveedores

En **Proveedores** se administran las editoriales/distribuidoras: datos de contacto, deuda actual y registro de pagos. Desde la ficha de cada proveedor se ve el historial de órdenes de compra y pagos realizados.

### 6.3 Órdenes de compra

Permiten registrar pedidos de reposición a proveedores. Al marcar una orden como **recibida**, el sistema actualiza el stock, recalcula el costo promedio ponderado del producto y avisa al personal si hay clientes suscriptos esperando ese título.

### 6.4 Clientes

Listado de clientes con su historial de compras, saldo de cuenta corriente y suscripciones activas.

### 6.5 Empleados y Cargos

- **Empleados**: alta de personal, asignación de sucursal y cargo (Vendedor, Despachador, Repartidor, Gerente, Administrador).
- **Cargos**: define qué permisos tiene cada cargo (acceso a Ventas, Reportes, Repartos, gestión de Catálogo, etc.).

`[CAPTURA: Pantalla de Empleados mostrando el alta/edición de un empleado con su cargo asignado]`

### 6.6 Sucursales

Alta y edición de sucursales físicas (dirección, datos de contacto, sucursal principal).

### 6.7 Stock y Caja

- **Stock**: consulta de existencias por sucursal, con alertas de stock bajo o agotado.
- **Caja**: apertura/cierre de caja diario por sucursal, con el detalle de ingresos por método de pago.
- **Gastos**: registro de egresos operativos por sucursal.

### 6.8 Reportes

En **Reportes** (permiso `reportes.acceder`) hay tres vistas, filtrables por rango de fechas y sucursal:

- **Ventas**: evolución diaria, productos y clientes más vendidos, resumen por tipo de venta.
- **Stock**: productos sin stock o con stock bajo, ranking de rotación (más vendidos vs. estancados).
- **Balance**: ingresos, costos y rentabilidad por mes, por sucursal y por tipo de venta.

`[CAPTURA: Pantalla de Reportes en la pestaña Ventas, con el gráfico de ventas por día]`

### 6.9 Predicción de Demanda

Desde **Reportes → Predicción** se elige un libro, un tomo o una categoría, y el sistema muestra el historial de ventas de las últimas 16 semanas junto con un pronóstico de demanda a futuro (calculado con suavizado exponencial), útil para decidir cuánto reponer.

`[CAPTURA: Pantalla de Predicción de Demanda con el gráfico de histórico + pronóstico de un producto]`

---

## 7. Manual para Logística y Repartos

En **Rutas de Reparto** (permiso `repartos.acceder`) se organiza la entrega de pedidos con envío a domicilio:

- El despachador crea una ruta, le asigna un repartidor y las ventas pendientes de entrega, y puede optimizar el orden de las paradas.
- El repartidor ve únicamente sus propias rutas asignadas, y desde cada parada puede marcarla como entregada (o registrar una incidencia).

`[CAPTURA: Detalle de una Ruta de Reparto con sus paradas y el mapa de direcciones]`

Los repartidores tienen un Dashboard simplificado, enfocado solo en sus rutas del día, sin acceso a datos de ventas o stock que no les corresponden.

---

## 8. Notificaciones

El ícono de campana en el panel avisa sobre eventos relevantes: nuevas ventas, comprobantes subidos por clientes, ingreso de stock esperado por suscriptores, traslados pendientes entre sucursales, entre otros.

`[CAPTURA: Panel de notificaciones desplegado]`

---

## 9. Anexo — Índice de capturas pendientes

Para completar el manual, tomar una captura de pantalla de cada punto marcado como `[CAPTURA: ...]` a lo largo del documento (10 en total) y reemplazar el texto entre corchetes por la imagen correspondiente.
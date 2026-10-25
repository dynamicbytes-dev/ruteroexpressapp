# GAZ — Reglas de negocio

Documento vivo. Todo lo que aquí está marcado como **configurable** se administra
desde el panel web del auditor, sin recompilar la app móvil.

## 1. Roles

| Rol | Canal | Puede registrarse solo | Estado inicial |
|---|---|---|---|
| `passenger` | App móvil | Sí | `pending` |
| `driver` | App móvil | Sí | `pending` |
| `auditor` | Panel web | Solo el auditor principal (clase A) crea otros auditores | `approved` |

Estados de cuenta: `pending` → `approved` → (`rejected` | `suspended` | `blocked`).

El **auditor principal (clase A)** es único y es el dueño del sistema (`users.is_owner`).
Solo esa cuenta puede registrar otras cuentas de auditor; los auditores que crea no
heredan ese privilegio. El listado de auditores sí es visible para todos.

- `pending`: la app muestra **"En espera de verificación"** con el checklist de documentos.
- `approved`: puede operar (pedir viajes / activarse / recargar).
- `rejected`: uno o más documentos fueron rechazados; muestra el motivo y permite reenviar.
- `suspended`: decisión manual del auditor (historial visible en la app).
- `blocked`: bloqueo temporal automático (penalización por rechazos).

> **Regla:** un usuario en `pending` o `rejected` **no puede** pedir viajes, activarse
> ni recargar billetera. Sí puede iniciar sesión y ver su estado.

> **Dos niveles de bloqueo, que no se mezclan:**
> - `users.status` + `users.blocked_until`: estado de la cuenta, lo maneja el auditor.
> - `driver_profiles.blocked_until`: penalización automática por rechazos. No cambia el
>   estado de la cuenta; solo impide recibir alertas de viaje hasta la hora indicada.

## 2. Registro

### Campos comunes
Nombre, apellido, **cédula** (única, sin puntos, 6–9 dígitos), dirección, correo, contraseña
y **ciudad**.

### Ciudades y zonas
- Ciudades habilitadas: **Puerto La Cruz, Barcelona, Lechería, Maturín, Margarita,
  Carúpano y Cumaná**. La app las obtiene de `GET /api/ciudades`; no mantiene una lista paralela.
- Cada ciudad es una **zona operativa independiente**: tiene sus propias tarifas y sus
  propios conductores.
- El pasajero elige su ciudad al registrarse. Los viajes que pide pertenecen a esa zona.
- El conductor también elige su zona al registrarse. Solo recibe alertas de viajes de su
  zona y del mismo tipo de vehículo.
- **Solo el auditor puede cambiar la zona de un conductor**, a solicitud del conductor.
  Al hacerlo, el conductor se desconecta y se cancelan sus alertas pendientes de la zona
  anterior. La ciudad del pasajero no se edita desde la app.
- Las cuentas creadas antes de esta segmentación quedan en Puerto La Cruz y el auditor
  puede reasignar la zona de los conductores desde su ficha.

### Pasajero
Además: **foto de cédula** y **foto selfie**.

### Conductor
Además: tipo de vehículo (**moto** o **carro**), marca, modelo, año, **placa** (única),
**foto de licencia de conducir**, **foto de carnet de circulación**, **foto de RCV**,
**foto de cédula**, **foto selfie** y **foto del vehículo** (con la placa visible).

### Correo
- Único a nivel global (no puede repetirse entre pasajero y conductor).
- **Solo se acepta Gmail** (`@gmail.com`). Cualquier otro dominio se rechaza con mensaje claro.
  Se valida en el backend (regla de validación) y en la app (para dar feedback inmediato).
- Verificación por enlace al correo.
- Recuperación de contraseña por correo (flujo estándar con token de un solo uso).

### Edición del perfil
- La persona puede corregir **nombre, apellido, teléfono y dirección** desde la app o el panel.
- La **cédula es de solo lectura**. Solo el auditor puede corregirla con un motivo y registro de
  auditoría; la API rechaza cualquier intento de cambiarla desde el perfil.
- El **correo** tiene un flujo aparte: exige la contraseña actual y vuelve a quedar sin verificar
  hasta que la persona abra el enlace enviado a la dirección nueva.

### Términos y privacidad
- Al registrarse hay que aceptar los términos y condiciones y la política de privacidad.
- El backend exige `accept_terms = true` y la versión vigente (`terms_version`). La cuenta guarda
  `terms_accepted_at` y `terms_version`.
- La app muestra el texto en un modal con dos pestañas y envía la versión incluida en esa
  instalación. Si el texto cambia, se actualizan la app y `config/legal.php` en el mismo lanzamiento.
- Los usuarios existentes no se bloquean por no tener la aceptación retroactiva.


## 3. Verificación (auditor)

- El auditor revisa **documento por documento**: aprobar o rechazar con motivo obligatorio.
- Queda registro inmutable: auditor, fecha, decisión, motivo (`verification_reviews` + activity log).
- **Cola del auditor:** entran las cuentas `pending` que ya subieron **todos** sus documentos
  (`users.submitted_at` con fecha). Quien va a medio registrar no ensucia la cola.
- Un documento rechazado devuelve la cuenta a `rejected` y la saca de la cola; al corregirlo y
  subirlo de nuevo, vuelve a `pending` y reingresa a la cola.
- La cuenta se aprueba **solo** cuando todos sus documentos están aprobados.
- **Reenviar un documento reemplaza el anterior** (se borra el archivo viejo) y lo devuelve a
  revisión. Un documento que vence se renueva por esta misma vía.
- Documentos con vencimiento (licencia, RCV, carnet de circulación) generan **aviso de vencimiento**
  a 30/15/5 días y pasan el vehículo a revisión si vencen.
  - El aviso queda guardado en la bandeja del usuario (`notifications`), se consulta por
    `GET /api/notificaciones` y se envía además por push.
  - Al vencer, la cuenta vuelve a `pending`, el conductor se desconecta, se cancelan sus
    alertas pendientes y no se puede aprobar la cuenta hasta que reenvíe el documento vigente.
  - El auditor ve los vencimientos en el dashboard del panel y en la ficha del conductor.
- El auditor puede **suspender** una cuenta aprobada y **reactivarla** después. La suspensión
  exige motivo y queda en `verification_reviews`; un conductor suspendido se desconecta y
  pierde las alertas pendientes. La reactivación no vuelve a revisar documentos.
- **Avisos operativos del auditor:** el panel tiene una campana y la bandeja
  `/panel/notificaciones`. Los avisos se guardan en la tabla `notifications` y **no se envían
  por correo**. Cubren cuentas nuevas o corregidas, recargas y retiros pendientes, viajes sin
  conductor, bloqueos automáticos y documentos por vencer o vencidos.

### Chat de soporte
- Pasajeros y conductores tienen un botón de soporte en la app. El chat está disponible
  incluso si la cuenta está en revisión, suspendida o bloqueada.
- Cada persona tiene **una sola conversación abierta**. La conversación se crea con el primer
  mensaje y se atiende desde el módulo **Soporte** del panel.
- Cualquier auditor puede ver las conversaciones abiertas, responder y consultar el historial
  de las finalizadas. Los mensajes del usuario quedan marcados como leídos al abrir el hilo.
- La app muestra el nombre del auditor que atiende el chat y el de cada respuesta. Solo se
  expone el nombre; si la cuenta del auditor se elimina, queda el genérico
  “Soporte GAZ”.
- Cuando el auditor da la conversación por terminada:
  - el chat deja de aparecer en la app de la persona (se limpia el hilo);
  - los mensajes y el historial **se conservan** en la base de datos para el panel;
  - si la persona vuelve a escribir, se crea una conversación nueva.
- El chat no reemplaza los avisos operativos ni el correo de recuperación de contraseña.

## 4. Billetera

Saldo **en dólares (USD)**. Libro mayor de solo-inserción: un movimiento nunca se edita,
se compensa con otro movimiento.

| Movimiento | Origen | Dirección |
|---|---|---|
| `recharge` | Recarga aprobada por el auditor | Crédito |
| `trip_charge` | Pago del viaje del pasajero | Débito |
| `trip_earning` | Ganancia del conductor (90% del viaje) | Crédito |
| `commission` | 10% de la app cuando el viaje se paga en efectivo | Débito |
| `withdrawal` | Retiro pagado por el auditor | Débito |
| `adjustment` | Corrección manual del auditor (con nota obligatoria) | Ambos |

### Recarga
1. El usuario transfiere a una cuenta nacional y sube el **comprobante** indicando monto en Bs,
   banco, referencia y fecha.
2. Queda en `pending`. La tasa de cambio **no** se congela aquí: se aplica la tasa vigente al momento
   de aprobar, y el auditor puede cambiarla en el mismo formulario de aprobación.
3. El auditor aprueba o rechaza (motivo obligatorio).
4. Al aprobar: `monto_usd = monto_bs / tasa`, se acredita y queda guardada la tasa usada.
5. Antifraude: se avisa al auditor si la referencia bancaria ya fue usada antes.

### Retiro
1. El conductor solicita indicando monto, banco, cuenta y titular.
2. Se **congela (hold)** el monto de inmediato para que no se gaste mientras se procesa.
3. El auditor paga por transferencia, sube el comprobante y marca como pagado.
4. Si se rechaza, el hold se libera.

### Reserva al pedir viaje
Al pedir un viaje, si el método es billetera se **reserva** el monto cotizado. Si al finalizar
el viaje el monto final difiere (por tráfico o cambio de destino), se ajusta del saldo y se
permite quedar en negativo hasta el tope configurado; si lo supera, no puede pedir más viajes
hasta recargar.

## 5. Tarifas

### Fórmula
```
subtotal          = tarifa_base + (km × precio_km) + (min × precio_min)
total             = (subtotal × multiplicador_dinámico × (1 + recargo_nocturno)) + recargo_vehículo
comisión_app      = total × 10%
ganancia_conductor = total − comisión_app
```

`recargo_vehículo` es **$1.00** cuando el pasajero elige carro y **$0** cuando elige moto.
El recargo se suma después de la dinámica y del nocturno, para que la diferencia entre carro y
moto sea exactamente un dólar; la comisión también se calcula sobre ese monto adicional.


Ejemplo: viaje de **$5.000**
- Al pasajero se le descuentan **$5.000**.
- Se acreditan **$4.500** al conductor ($5.000 − 10% = $500 de comisión).

### Snapshot por viaje
Cada viaje guarda en `fare_breakdown` (JSON) los valores exactos usados: tarifa base, $/km, $/min,
distancia, duración, multiplicador dinámico aplicado, porcentaje nocturno, total, comisión y
ganancia del conductor. Si el auditor cambia las tarifas mañana, los viajes de ayer no se alteran.

### Valores semilla (editables por el auditor, por ciudad)
Cada zona tiene su propia copia de estos valores. Si una zona no tiene un valor propio,
se usa el global como respaldo.

| Parámetro | Valor inicial |
|---|---|
| Tarifa base | $1.00 |
| Precio por km | $0.35 |
| Precio por minuto | $0.05 |
| Comisión de la app | 10% |
| Recargo nocturno | +20% |
| Franja nocturna | 19:00 – 06:00 |
| Tarifa dinámica | 1.0 – 1.8 según zona/franja |
| Mínimo por viaje | $1.50 |
| Recargo por carro | $1.00 |
| Velocidad promedio moto (estimación) | 25 km/h |
| Velocidad promedio carro (estimación) | 30 km/h |
| Redondeo | Al centavo (0.01) |

### La app nunca calcula el precio
El backend es la única fuente del monto cotizado; la app solo lo muestra. Así se evita manipulación
de precios desde el cliente. Cada ciudad tiene sus propios valores de tarifa; si una zona no
tiene un valor propio, se usa el global como respaldo. Los viajes cerrados conservan el
desglose con el que se cobraron.

La distancia y la duración salen de **Geoapify** cuando existe `GEOAPIFY_API_KEY`:
modo `motorcycle` para moto y `drive` para carro. Si no hay clave o el servicio falla,
el backend usa una estimación local (distancia en línea recta con factor de rodeo y
velocidad promedio configurable por tipo) y lo informa como `route_source = estimada`;
la app no cambia su comportamiento. Google Directions sigue disponible como respaldo
opcional si algún día existe `GOOGLE_MAPS_SERVER_KEY`.

### Moneda
- El viaje se cobra y se muestra **en dólares**.
- El equivalente en bolívares se muestra como referencia, usando la **tasa vigente** del día.
- La tasa la carga el auditor a mano, con fecha, autor e histórico completo.
- Todo el histórico guarda la tasa usada en el momento de cada operación.

## 6. Asignación de viajes (cascada)

1. El pasajero marca punto A (origen) y punto B (destino) con autocompletado en el mapa.
2. El backend calcula distancia, duración y precio.
3. Se buscan conductores con `is_online = true`, `approved`, sin bloqueo activo, dentro del
   **radio inicial** (por defecto 3 km, configurable), con el **mismo tipo de vehículo**
   que pidió el pasajero (moto o carro) y en la **misma ciudad/zona del viaje**.
4. **Conductor favorito:** antes del primer intento, si el pasajero tiene favoritos y
   `radio_favorito_km` es mayor que 0, se ofrece primero al favorito que esté en línea,
   con ubicación fresca y dentro de ese radio (por defecto 8 km). Si no está disponible
   o rechaza, la cascada sigue normal y no se le vuelve a ofrecer en ese viaje.
5. Al más cercano se le envía la alerta. Tiene **20 segundos** para responder.
6. Rechaza → pasa al siguiente. No responde en 20 s → se marca `expired` y pasa al siguiente.
7. Se agotan los conductores del radio → el radio se amplía en pasos (3 → 5 → 8 km, configurable).
8. Sin nadie disponible → el viaje pasa a `no_driver` y el pasajero puede reintentar.
9. Si el conductor aceptó y luego cancela, el viaje vuelve a `searching` (sin ese conductor)
   y cuenta como rechazo para la penalización.

Notas de implementación:

- La posición del conductor se guarda con PostGIS (`driver_profiles.last_location`) y solo
  cuenta si fue reportada en los últimos **2 minutos**.
- El multiplicador dinámico que se aplica es `dinamica_actual`, configurable entre
  `dinamica_min` y `dinamica_max`; el panel lo guarda junto con las demás tarifas.
- La cascada se ejecuta al crear el viaje, al rechazar/vencer una alerta y con el comando
  programado `viajes:expirar-ofertas` cada minuto, para no depender de que la app esté abierta.
- El teléfono del pasajero solo se comparte con el conductor cuando acepta el viaje.
- El **latido de ubicación** reenvía la última posición conocida al menos cada minuto,
  aunque el GPS no detecte movimiento, para que el backend no marque la ubicación como
  vencida mientras el conductor está en línea.
- El auditor recibe avisos en el panel cuando un viaje se queda sin conductor o cuando un
  conductor es bloqueado automáticamente por rechazos.

### Alerta al conductor
Muestra: **nombre del pasajero**, punto de recogida, destino, distancia hasta la recogida,
**tiempo estimado**, monto cotizado y desglose del −10% (ej. `$5.000 → recibes $4.500`),
con cuenta regresiva. Botones: **Aceptar** / **Rechazar**.

### Penalización por rechazos
- **3 rechazos dentro de una ventana de 60 minutos** → bloqueo automático de **1 hora**.
- Durante el bloqueo: no recibe alertas, ve la hora exacta de reactivación y el motivo.
- Aceptar y luego cancelar cuenta como rechazo. No responder (expirar) también cuenta.
- El contador se reinicia al aceptar un viaje o al cumplir la ventana.
- Parámetros configurables: nº de rechazos, ventana, duración del bloqueo, tiempo de respuesta.

## 7. Estado del viaje

```
searching → offered → accepted → arriving → waiting → in_progress → completed
    │           │          │                                │
    └───────────┴──────────┴────────────────────────────────┴──→ cancelled / no_driver
```

- **Activo/Inactivo:** el conductor controla su disponibilidad con un switch. Solo los activos
  reciben alertas. Al cerrar sesión se marca inactivo de inmediato; si la app se cierra sin
  avisar o pierde conexión, el comando `conductores:desconectar-inactivos` lo apaga después
  de **5 minutos** sin reportar ubicación (configurable con `MOTORUTA_DRIVER_OFFLINE_MINUTES`).
- **Pago:** billetera o efectivo.
  - Billetera: se reserva el monto al pedir y se ejecuta al finalizar.
  - Efectivo: al finalizar se **debita la comisión del 10%** del saldo del conductor. Si no tiene
    saldo, queda en negativo hasta el tope configurado (por defecto −$5); superarlo impide activarse.
- **Cancelación:** cualquiera puede cancelar antes de `in_progress`.
  - Antes de la llegada del conductor: sin penalización al pasajero en esta versión.
  - Conductor cancela: cuenta como rechazo (afecta penalización) y se registra en su historial.
- **Tracking:** el pasajero ve la posición del conductor en el mapa en vivo y el tiempo estimado
  de llegada, actualizado con WebSockets.

### Historial del conductor
- La app ofrece los filtros rápidos **Todos, Hoy, Semana y Mes**. Solo se muestran al conductor.
- El backend filtra por la fecha visible en la tarjeta: finalización, cancelación o solicitud.
- La semana empieza el lunes y los cortes se calculan en `America/Caracas`.
- El historial sigue paginado de 20 en 20; los filtros no cambian el tamaño de la página.
- La app acumula páginas con “Ver más viajes”, mantiene el período elegido y evita duplicados.

## 8. Calificación del viaje y conductores favoritos

- **Quién califica:** solo el pasajero, solo su viaje y solo cuando está `completed`.
- **Cuándo:** una sola vez por viaje, desde la tarjeta del viaje terminado o desde el
  historial («Sin calificar»).
- **Qué se pide:** de 1 a 5 estrellas (obligatorio) y un comentario opcional de hasta
  500 caracteres.
- **Dónde se ve:** el promedio y la cantidad aparecen en la tarjeta del conductor, en su
  panel de la app y en la ficha del auditor. El comentario lo ven el conductor y el auditor.
- **Sin calificaciones:** se muestra «Sin calificaciones», nunca un promedio inventado.
- **Favorito:** al calificar (o con el corazón de la tarjeta) el pasajero puede marcar al
  conductor como favorito si ya completaron un viaje juntos.
- **Prioridad:** el favorito se intenta antes de la cascada normal cuando está en línea,
  cerca (radio configurable, 8 km por defecto) y con el mismo tipo de vehículo.
- **Desactivar la prioridad:** el auditor pone `radio_favorito_km = 0` en Configuración.

## 9. Panel del auditor

Login con sesión web. Secciones: Dashboard, Usuarios (verificación, suspensión/reactivación
y ajuste de saldo), Recargas, Retiros, Viajes, Conductores (disponibilidad, bloqueos,
inhabilitados, documentos por vencer, deuda, actividad, calificaciones y favoritos),
Reportes (métricas y exportables), Configuración (tarifas y recargos **por ciudad**,
dinámica, nocturno, radios, radio del favorito y tiempos), Tasa de cambio, Auditores
(listado para todos; registro de nuevas cuentas solo para el auditor principal) y Auditoría
(log con filtros y exportación CSV).

En la ficha del conductor el auditor puede **cambiar su zona** cuando el conductor lo
solicite. El cambio lo desconecta y cancela sus alertas pendientes; al conectarse de nuevo
solo recibe viajes de la zona nueva.

También puede **inhabilitarlo** con un motivo obligatorio (reutiliza la suspensión de
cuenta: se desconecta, se cancelan sus alertas y no recibe viajes nuevos; si tiene un viaje
en curso, puede terminarlo). El botón **Habilitar** lo devuelve al estado aprobado sin pedir
documentos otra vez.

Solo el **auditor principal (clase A)** puede **registrar otro auditor** desde
`/panel/auditores`. La cuenta nueva nace aprobada y verificada, sin el privilegio de crear
más auditores; la contraseña se define en ese formulario y debe tener al menos 12
caracteres, con letras y números. Si la instalación ya tenía auditores antes de esta regla,
el más antiguo pasa a ser el principal.

El dashboard muestra los documentos de conductores que vencen en los próximos 30 días y los
ya vencidos, con enlace a la ficha para pedir la renovación.

Toda acción del auditor queda registrada con autor, fecha, IP y datos antes/después.

## 10. Decisiones cerradas

Estas reglas quedaron confirmadas por el negocio y son las que implementa el sistema:

1. **Comisión en efectivo:** se descuenta del saldo del conductor. Puede quedar en negativo
   hasta un tope de **−$5** (configurable); si lo supera, no puede activarse hasta recargar.
2. **Usuario en `pending`:** no puede pedir viajes, activarse ni recargar. Solo ve su estado
   y el detalle de sus documentos.
3. **Cancelación del pasajero:** sin penalización en esta primera versión. Se medirá el
   comportamiento real antes de evaluar cobros o límites.
4. **Alcance:** Venezuela, **siete ciudades de la zona oriental** (Puerto La Cruz,
   Barcelona, Lechería, Maturín, Margarita, Carúpano y Cumaná) y solo español. Cada ciudad
   funciona como una zona independiente, con tarifas y conductores propios.
5. **Tarifas semilla:** base $1.00 · $0.35/km · $0.05/min · mínimo $1.50 · nocturno +20%
   (19:00–06:00) · dinámica actual 1.0, dentro del rango 1.0–1.8. Todas editables por
   ciudad desde el panel; cada zona arranca con una copia independiente.
6. **Marca:** isotipo de casco frontal con líneas de velocidad. Ver `design/marca.md`.
7. **Seguridad del auditor:** verificación en dos pasos (TOTP) con códigos de recuperación
   de un solo uso, además de contraseña con mínimo de 12 caracteres. El panel recomienda
   activarla y la cuenta puede desactivarla pidiendo contraseña y un código válido.
8. **Correo:** solo se aceptan cuentas de Gmail. Hotmail, Outlook y demás proveedores
   quedan bloqueados en el registro.

### Todavía abierta

- **Clave de Geoapify:** los mapas y la estimación local ya funcionan sin cuenta. Para
  rutas y direcciones reales falta crear la clave gratuita (sin tarjeta). Ver
  [credenciales.md](credenciales.md).
- **Notificaciones por WhatsApp o SMS:** fuera de alcance; solo hay push (FCM).
- **Facturación fiscal** de la comisión de la app: por definir.


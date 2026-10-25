# GAZ

Plataforma de viajes en moto o carro con tres perfiles: **pasajero**, **conductor** y **auditor**.

- **Pasajero y conductor** operan desde la app móvil (Flutter).
- **El auditor** gestiona todo desde un panel web: verifica usuarios, atiende el chat de
  soporte, aprueba recargas y retiros, define tarifas por ciudad, asigna zonas a los
  conductores, carga la tasa de cambio del día y audita los movimientos. Solo el
  **auditor principal (clase A)** puede registrar otras cuentas de auditor.

## Documentación

| Documento | Contenido |
|---|---|
| [docs/reglas-negocio.md](docs/reglas-negocio.md) | Todas las reglas: registro, verificación, billetera, tarifas, asignación de viajes, penalizaciones |
| [docs/api-app.md](docs/api-app.md) | Contrato del API que consume la app móvil (registro, sesión, documentos, avisos, billetera, viajes y soporte) |
| [docs/setup.md](docs/setup.md) | Cómo levantar el proyecto en tu máquina |
| [docs/credenciales.md](docs/credenciales.md) | Cómo crear y colocar la clave de Geoapify y los archivos de Firebase (FCM) |
| [docs/publicacion.md](docs/publicacion.md) | Firma de release, CI, push en iOS y checklist de publicación |
| [design/marca.md](design/marca.md) | Logo, paleta y tipografía |

## Stack

| Capa | Tecnología |
|---|---|
| Backend / API / Panel | Laravel 12 (PHP 8.2+) |
| Base de datos | PostgreSQL 17 + PostGIS (Docker) |
| Autenticación API | Laravel Sanctum |
| Autenticación web | Sesión (Laravel Breeze) |
| Rol de usuario | Columna `users.role` (única fuente de verdad) |
| Permisos finos del auditor | spatie/laravel-permission (activo desde F5) |
| Auditoría | spatie/laravel-activitylog |
| Tiempo real | Laravel Reverb (WebSockets) + Firebase Cloud Messaging |
| Mapas y rutas | OpenStreetMap + Geoapify (gratis, sin tarjeta; opcional) |
| App móvil | Flutter 3.22 (provider, dio, flutter_map) |

## Estado del proyecto

| Fase | Alcance | Estado |
|---|---|---|
| F0 | Fundaciones: repositorio, base de datos, roles, panel base, marca | **Completada** |
| F1 | Registro con documentos y verificación del auditor | **Completada** |
| F1b | App móvil (Flutter): registro, documentos y espera de verificación | **Completada** |
| F2 | Billetera, recargas, retiros, tasa de cambio | **Completada** |
| F3 | Viajes, tarifas dinámicas, asignación en cascada | **Completada** |
| F4 | Tiempo real, tracking en vivo, notificaciones push | **Completada** (iOS requiere APNs en producción) |
| F5 | Reportes, conductores, auditoría avanzada y 2FA | **Completada** |
| F6 | QA, piloto y publicación en tiendas | **En curso**: CI, firma, íconos y guía listos; faltan cuentas y dispositivos |

### Qué quedó funcionando en F0

- Base de datos con PostGIS, migraciones y seeder listos (`docker compose up -d`).
- Esquema del dominio de identidad: usuarios con rol y estado, perfiles de conductor,
  documentos, revisiones del auditor y log de actividad.
- Regla de **solo Gmail** validada en el backend, con pruebas.
- **Panel web del auditor** en `/panel`: login por sesión, acceso restringido al rol
  auditor (pasajeros y conductores no pueden entrar), dashboard con colas de trabajo
  y documentos servidos con URL firmada y temporal.
- Comando `php artisan motoruta:crear-auditor` para la cuenta inicial. Después, cualquier
  auditor puede crear otro desde `/panel/auditores` (no existe registro público).
- 63 pruebas automatizadas sobre PostgreSQL real.

### Qué quedó funcionando en F1

- **Registro desde la app** (`POST /api/auth/register`): pasajero y conductor, con elección
  de **ciudad/zona**, y los documentos en la misma petición o después, uno por uno.
- **Sesión móvil** con tokens de Sanctum: entrar, salir solo del dispositivo actual y
  consultar `GET /api/perfil`, que devuelve el estado de la cuenta y la lista de
  verificación documento por documento.
- **Estados de la cuenta**: todo registro nace en *"en espera de verificación"*; solo una
  cuenta aprobada por el auditor puede operar. Los documentos pueden quedar en revisión,
  aprobados o rechazados con un motivo que la persona lee.
- **Cola de verificación del auditor** en `/panel/usuarios`: pestañas por estado
  (por verificar, deben corregir, aprobados, registro incompleto), búsqueda por nombre,
  cédula, correo o teléfono, ficha del usuario con los documentos y su historial.
- **Aprobación y rechazo** de documentos y de la cuenta, con motivo obligatorio al
  rechazar y registro inmutable de cada decisión (`verification_reviews`).
- **Suspensión y reactivación manual** de cuentas aprobadas desde la ficha del usuario,
  con motivo obligatorio y registro en el historial. Al suspender un conductor se apaga
  su disponibilidad y se cancelan las alertas pendientes.
- **Recuperación de contraseña** por correo con una página web única
  (`/recuperar`, `/restablecer/{token}`) que sirve igual para el auditor y para quien
  usa la app; al cambiarla se cierran las sesiones abiertas.
- **Verificación de correo** con enlace firmado y página de confirmación para la app.
- **Normalización de datos**: correo en minúsculas, cédula y teléfono sin puntos ni
  guiones, placa en mayúsculas.
- Documentos guardados en disco privado, servidos al auditor con URL firmada de 5 minutos.
- 112 pruebas automatizadas sobre PostgreSQL real.

### Qué quedó funcionando en F2

- **Billetera en dólares** para pasajeros y conductores, con libro mayor de solo-inserción
  (`wallet_transactions`): cada recarga, pago, comisión, retiro o ajuste explica el saldo.
- **Recargas por transferencia nacional**: la persona sube el comprobante desde la app y
  queda pendiente; el auditor lo revisa en `/panel/recargas`, ve si la referencia bancaria
  ya se usó como aviso antifraude, aplica la tasa (la vigente o una puntual) y acredita.
  El saldo no aparece hasta que el pago se aprueba.
- **Retiros de conductores**: se solicita monto, banco, cuenta y titular; el monto queda
  retenido de inmediato. El auditor paga en `/panel/retiros`, sube el comprobante y marca
  como pagado; si rechaza, la retención se libera.
- **Tasa de cambio manual** en `/panel/tasa`: cada carga guarda fecha, autor y tasa. El
  histórico no se borra y la vigente es la última con fecha de hoy o anterior.
- **Configuración operativa** en `/panel/configuracion`: **tarifas, comisión, recargos y
  multiplicador por ciudad**, franja horaria, radios y tiempos de asignación, penalización
  por rechazos, topes de saldo, retiro mínimo y datos bancarios.
- **Ajuste manual de saldo** desde la ficha del usuario: monto positivo o negativo,
  nota obligatoria y registro en el libro mayor y en el log de auditoría.
- **Comprobantes privados**: solo se abren con URL firmada de 5 minutos.
- 148 pruebas automatizadas sobre PostgreSQL real (36 nuevas de F2).

### Qué quedó funcionando en F3

- **Cotización y viajes A→B** con distancia, duración y tarifa congeladas al pedir el viaje:
  base, por kilómetro, por minuto, mínimo, recargo nocturno y multiplicador dinámico actual.
- **Rutas con Geoapify** (modo moto o carro) cuando existe `GEOAPIFY_API_KEY`; si no, estimación
  local informada como `route_source = estimada`, para poder desarrollar sin cuenta.
  Google Directions queda como respaldo opcional con `GOOGLE_MAPS_SERVER_KEY`.
- **Asignación en cascada sobre PostGIS**: conductor más cercano **de la misma zona**, alerta con cuenta regresiva,
  rechazo/vencimiento, ampliación de radios (3 → 5 → 8 km) y `no_driver` al agotarse.
- **Penalización por rechazos**: 3 en 60 minutos → 1 hora bloqueado, sin recibir alertas.
- **Disponibilidad real del conductor**: aprobado, en línea, sin bloqueo, con ubicación de los
  últimos 2 minutos y saldo dentro del tope negativo.
- **Pago con billetera** (retención al pedir, cobro y acreditación al finalizar) o **efectivo**
  (comisión del 10% debitada del conductor, con tope negativo y desconexión si lo supera).
- **Estados del viaje** `searching → offered → accepted → arriving → waiting → in_progress →
  completed`, con cancelación y liberación de la retención.
- **Panel `/panel/viajes`** de solo lectura: listado por estado, búsqueda y ficha con ruta,
  personas, tarifa, alertas enviadas y cronología.
- **Comando programado** `viajes:expirar-ofertas` cada minuto para que la cascada no dependa
  de que la app esté abierta.
- **Vencimiento de documentos**: aviso a los 30, 15 y 5 días, registro en la bandeja de
  avisos del usuario y paso automático de la cuenta a revisión cuando un documento vence.
  El conductor queda desconectado y la aprobación de la cuenta se bloquea hasta que suba
  el documento vigente.
- **Documentos por vencer en el panel**: el dashboard muestra los vencimientos de los
  próximos 30 días y los documentos ya vencidos, con acceso directo a la ficha.
- **Desconexión automática**: al cerrar sesión el conductor queda inactivo; el comando
  `conductores:desconectar-inactivos` apaga a quien dejó de reportar ubicación
  (`MOTORUTA_DRIVER_OFFLINE_MINUTES`, 5 por defecto).
- **Geocodificación** (`/api/ubicaciones/buscar` y `/reversa`) con Geoapify y respaldo
  en Nominatim (OpenStreetMap); no requiere cuenta.
- 213 pruebas automatizadas sobre PostgreSQL real.

### Qué quedó funcionando en F4

- **Tiempo real con Reverb**: ofertas, aceptación, cambios de estado y ubicación del
  conductor llegan por WebSocket; el sondeo queda como respaldo.
- **Push con Firebase Cloud Messaging** en Android e iOS, con registro de equipos,
  limpieza de tokens inválidos y avisos de la bandeja.
- **Canal privado por viaje** para pasajero y conductor, con autorización por token.
- La app funciona sin claves de FCM; simplemente no recibe notificaciones push.

### Qué quedó funcionando en F5

- **Panel `/panel/conductores`**: pestañas de todos, en línea, bloqueados, documentos
  por vencer y saldo negativo; búsqueda por nombre, cédula, correo, teléfono o placa.
- **Ficha del conductor**: vehículo, contacto, última ubicación, billetera, documentos,
  viajes recientes, alertas recibidas, rechazos y ganancias.
- **Panel `/panel/auditoria`**: log de actividad con filtros por persona, módulo, acción
  y fechas; detalle de cambios antes/después y exportación CSV.
- **Panel `/panel/reportes`**: viajes completados, facturación, comisión, ganancia de
  conductores, ticket promedio, formas de pago, recargas, retiros, ajustes, usuarios
  nuevos y top de conductores; exportables CSV de viajes y movimientos.
- **Permisos finos con spatie/laravel-permission**: el rol funcional sigue en
  `users.role`, y el rol `auditor` de Spatie agrupa los permisos de las secciones nuevas.
- **2FA TOTP** para auditores: QR, secreto manual, confirmación, ocho códigos de
  recuperación de un solo uso, regeneración y desactivación con contraseña y código.
- 255 pruebas automatizadas al cerrar F5 (20 nuevas). La suite completa suma hoy 269.

### Calificaciones, favoritos e inhabilitación

- **Calificación del viaje**: al terminar, el pasajero califica al conductor con 1–5
  estrellas y un comentario opcional (máx. 500). Se guarda una sola vez por viaje y el
  promedio se recalcula en el perfil del conductor; sin calificaciones se muestra
  «Sin calificaciones», nunca un cero.
- **Conductor favorito**: el pasajero puede marcar al conductor con el corazón de la
  tarjeta o al calificar. Antes de la cascada normal, el sistema intenta una vez al
  favorito que esté en línea y dentro de `radio_favorito_km` (8 km por defecto); si no
  acepta, sigue la búsqueda normal. El auditor puede desactivarlo con `0`.
- **Panel de conductores**: nueva pestaña **Inhabilitados**, columna de calificación y
  ficha con estrellas, comentarios y pasajeros que lo marcaron favorito.
- **Inhabilitar conductor**: desde el listado o la ficha, con motivo obligatorio. Reutiliza
  la suspensión de cuenta (se desconecta y no recibe viajes nuevos) y el botón
  **Habilitar** lo devuelve a operación sin pedir documentos otra vez.
- **En la app**: hoja de calificación al terminar el viaje, aviso «Sin calificar» en el
  historial, lista de favoritos y pantalla «Mis calificaciones» para el conductor.

### Qué quedó preparado en F6

- **CI en GitHub Actions** para backend (PostGIS real) y móvil (analyze + test).
- **Firma de release Android** con `key.properties` y respaldo de debug para desarrollo.
- **Íconos de lanzamiento** de Android e iOS generados desde el isotipo de la marca.
- **Guía de publicación** con firma, Firebase, APNs, backend de producción, ficha de
  tienda y checklist de QA en [docs/publicacion.md](docs/publicacion.md).

Todavía faltan las cuentas de Google Play/App Store, la clave APNs, la política de
privacidad pública, los respaldos/monitoreo de producción y el piloto en dispositivos
reales.

### Correcciones posteriores a la revisión

- **Cédula protegida**: la app y el perfil web la muestran bloqueada; la API rechaza
  cualquier intento de cambiarla. Solo el auditor puede corregirla desde la ficha, con
  motivo obligatorio y registro en la auditoría.
- **Moto y carro**: selector al pedir el viaje, tarifas y tiempos separados, búsqueda de
  conductores por tipo y zona, y recargo fijo de **$1.00** para carro.
- **Ciudades y zonas**: siete ciudades de la zona oriental; pasajeros y conductores eligen
  la suya al registrarse. Cada viaje queda atado a la ciudad del pasajero y solo se ofrece
  a conductores de esa zona. El auditor configura tarifas por ciudad y es el único que puede
  cambiar la zona de un conductor. El auditor principal (clase A) es el único que puede
  registrar nuevas cuentas de auditor; esas cuentas no pueden crear más auditores.
- **Perfil editable** en app y panel: nombre, apellido, teléfono y dirección; el correo
  se cambia aparte, con contraseña actual y nueva verificación.
- **Avisos del auditor** en una bandeja del panel, sin correo, para cuentas, recargas,
  retiros, viajes sin conductor, bloqueos y documentos por vencer.
- **Chat de soporte** para pasajeros y conductores desde la app, con bandeja e historial
  en `/panel/soporte`. Cuando el auditor finaliza la conversación, el chat desaparece de
  la app pero se conserva completo en el panel.
- **Latido de ubicación** del conductor cada 60 s y al volver a primer plano.
- **Sin contraseñas compartidas**: el seeder genera una contraseña aleatoria fuera de git
  o usa `AUDITOR_INITIAL_PASSWORD`; el auditor local quedó rotado.
- 326 pruebas de backend y 137 de la app (más 1 omitida) en verde.

### Qué quedó funcionando en la app móvil

- **Registro completo** de pasajero y conductor (datos, ciudad, tipo de vehículo y clave),
  con avisos en español y errores del backend pintados en su campo.
- **Subida de documentos** uno por uno con barra de progreso, fecha de vencimiento en
  licencia, carnet y RCV, y el motivo del rechazo cuando el auditor pide corregir.
- **"En espera de verificación"** con el avance documento por documento; la pantalla se
  actualiza sola cada minuto y al volver a la app, y pasa al inicio en cuanto el auditor
  aprueba.
- **Acceso, cierre de sesión, recuperación de contraseña y confirmación de correo**
  (el enlace del correo abre la página web, igual que para el auditor).
- Cuenta suspendida o bloqueada: se muestra el motivo y el paso a seguir.
- **Bandeja de avisos**: muestra en el inicio los documentos por vencer (30/15/5 días)
  y permite marcarlos como leídos. Los avisos, las ofertas y los cambios de viaje también
  llegan como notificaciones push de Firebase cuando el equipo las permite.
- **Billetera (F2)**: saldo en dólares y equivalente en bolívares con la tasa vigente,
  recarga con comprobante desde la galería y barra de progreso, solicitud de retiro con
  validación de saldo y datos de la cuenta, e historial de movimientos, recargas y retiros.
- **Viajes (F3)**: el pasajero elige **moto o carro**, fija recogida y destino, cotiza
  antes de confirmar, paga con billetera o efectivo y sigue el estado del viaje. El carro
  cuesta exactamente **$1.00 más** que la moto. El conductor se conecta con su ubicación,
  recibe alertas con cuenta regresiva, acepta/rechaza y avanza el viaje (llegada, espera,
  inicio y final), y consulta su historial. Los cambios llegan en vivo por WebSockets
  (Reverb) y el sondeo queda como respaldo.
- **Mis datos**: nombre, apellido, teléfono y dirección editables; la **cédula es de solo
  lectura** y el cambio de correo exige la contraseña actual y una nueva verificación.
- **Chat de soporte** disponible para pasajeros y conductores, también con la cuenta
  suspendida o en revisión. El auditor responde y puede finalizar el chat desde el panel;
  al finalizarlo, la app limpia el hilo y permite iniciar otro.
- **Avisos del auditor en el panel**: campana y bandeja para cuentas, recargas, retiros,
  viajes sin conductor, bloqueos y documentos por vencer; no se envían por correo.
- **Chat de soporte**: botón en el inicio y en las pantallas de documentos, espera y cuenta
  suspendida. El hilo se actualiza cada 5 segundos; si el equipo lo finaliza, se limpia y
  la persona puede iniciar una conversación nueva.
- **Mapa siempre disponible**: OpenStreetMap por defecto y Geoapify si se compila con
  `--dart-define=GEOAPIFY_API_KEY=...`. Se elige el destino tocando el mapa y se buscan
  direcciones; en desarrollo también hay coordenadas de prueba. No necesita claves
  nativas ni facturación.
- 137 pruebas automatizadas (modelos, errores de la API, sesión, ciudades, avisos,
  billetera, viajes, soporte, perfil, botones y maquetación en dos tamaños de teléfono)
  más una de integración opcional contra el backend real (1 prueba omitida por defecto).

La app se corre desde `mobile/`; instrucciones en [mobile/README.md](mobile/README.md).

El contrato del API para el equipo móvil está en [docs/api-app.md](docs/api-app.md).

Para empezar a trabajar: ver [docs/setup.md](docs/setup.md).

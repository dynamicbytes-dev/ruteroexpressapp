# GAZ — app móvil (Flutter)

App de pasajeros y conductores. En esta entrega cubre el **registro**, la **subida de
documentos**, la pantalla de **"en espera de verificación"**, la **bandeja de avisos**,
la **billetera** (saldo, recargas con comprobante, retiros e historial), los **viajes**
(cotización, pedido, alertas del conductor, seguimiento y cierre) y el **chat de soporte**,
con tiempo real (WebSocket + FCM), contra la API de F1–F4 (ver
[../docs/api-app.md](../docs/api-app.md)).

## Requisitos

- **Flutter 3.22** (Dart 3.4). En esta máquina está en
  `/Users/rosangeles/Documents/development/flutter`.
- El backend corriendo (`php artisan serve`, puerto 8000) con la base de datos arriba.
- Para web: Chrome. Para Android: un emulador o teléfono y el SDK de Android.

## Correr la app

```bash
# Terminal 1: backend
cd backend
php artisan serve

# Terminal 2: app en Chrome
cd mobile
flutter pub get
flutter run -d chrome --web-port=5000
```

La URL del backend se elige sola:

| Dónde corre la app | URL por defecto |
|---|---|
| Chrome / iOS | `http://localhost:8000` |
| Emulador de Android | `http://10.0.2.2:8000` |

Para probar desde un teléfono en la misma red (o contra otro servidor):

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000
```

El backend acepta peticiones desde cualquier origen (`api/*`), así que la app web puede
vivir en otro puerto sin tocar CORS.

## Pruebas

```bash
flutter test          # 137 pruebas + 1 omitida: modelos, errores, sesión, avisos, billetera, viajes, soporte, perfil y pantallas
flutter analyze
```

Hay una prueba **opcional contra el backend real** (registro, subida de un JPEG y
rechazo de una fecha vencida). Se salta sola salvo que se pida:

```bash
flutter test --dart-define=API_REAL=true test/integracion
```

## Cómo está organizada

```
lib/
├── main.dart                 arranque: cliente HTTP, almacén del token, sesión
├── app.dart                  tema, idioma y la compuerta que elige la pantalla
├── core/
│   ├── api/                  cliente Dio, errores traducidos, URL del backend
│   ├── storage/              token en el llavero del sistema
│   ├── theme/                colores de marca y theme de Material 3
│   └── ui/                   logo, botones, campos, avisos, etiquetas
├── data/
│   ├── models/               Usuario, documentos, billetera y viajes
│   ├── cuenta_repository.dart
│   ├── billetera_repository.dart
│   ├── soporte_repository.dart
│   └── viajes_repository.dart
└── features/
    ├── auth/                 bienvenida, rol, registro, acceso, recuperar clave
    ├── billetera/            saldo, recarga, retiro e historial de movimientos
    ├── documentos/           lista de verificación con subida y progreso
    ├── inicio/               espera de verificación, inicio y cuenta suspendida
    ├── soporte/              chat de soporte y acceso desde las pantallas
    ├── viajes/               pedir, seguir, alertas y viaje del conductor
    └── sesion/               controlador de sesión, billetera, viajes y salir
```

La app no decide el estado de la cuenta: **obedece `next_step.key`** que devuelve
`GET /api/perfil`.

| `next_step.key` | Pantalla |
|---|---|
| `upload_documents` | Lista de documentos (falta subir) |
| `fix_documents` | Lista de documentos (con el motivo del rechazo) |
| `waiting_review` | "En espera de verificación" (refresca sola cada minuto) |
| `ready` | Inicio: cuenta aprobada |
| `contact_support` | Cuenta suspendida o bloqueada |

## Decisiones que conviene conocer

- **`provider` + `Navigator`**, sin `go_router`: hay una sola "compuerta" en la raíz que
  escucha a `SesionController`. Cuando la cuenta cambia de estado, la pantalla cambia
  sola (por ejemplo, de "en espera" a "aprobada").
- **`dio`** para HTTP: permite subir multipart con **barra de progreso** real.
- **El token vive en `flutter_secure_storage`** (Keychain / Keystore), nunca en
  preferencias planas. Si el almacén falla, la sesión dura lo que dure la app abierta.
- **El isotipo se dibuja desde un PNG con transparencia** (`assets/brand/`), pintado con
  `BlendMode.srcIn`. El SVG de `design/logos/` no se usa en Flutter porque su máscara
  del visor se renderiza mal (aparecía un cuadro blanco).
- **Las fotos se comprimen al elegirlas** (`imageQuality: 88`, máx. 2200 px) y se rechazan
  las que pasen de 6 MB antes de subirlas.
- **La billetera no se calcula en la app**: `SesionController` pide `GET /api/billetera` y
  muestra saldo total, retenido y disponible. Al solicitar un retiro el backend retiene el
  monto; la app solo refleja lo que responde.
- **El comprobante de recarga** se elige de la galería y se sube con progreso real. La tasa
  no se congela al pedir: se aplica cuando el auditor aprueba, y la app lo explica.
- **Ciudad/zona**: el registro carga `GET /api/ciudades` y exige elegir una. El conductor
  solo recibe viajes de su zona; la ciudad se muestra en «Mis datos» y solo el auditor
  puede cambiarla.
- **Sólo Gmail**: el formulario avisa antes de enviar y el backend lo valida igual.
- **Los viajes viven en `SesionController`**: el viaje activo, las alertas y el estado de
  conexión del conductor se comparten entre el inicio y las pantallas de viaje. Con
  Reverb, los cambios llegan por WebSocket al instante; el sondeo queda como respaldo
  (15 s en las pantallas de viaje, 20 s en el panel y 30 s en el inicio del pasajero, o
  4/5/10 s si el canal no está conectado). El historial trae los últimos 20 viajes.
- **Selector Moto/Carro** al pedir el viaje: la cotización, el tiempo estimado y el total
  vienen del backend; el carro suma $1.00. El tipo se envía en `vehicle_type` y el
  conductor solo recibe viajes de su mismo tipo y zona; la tarifa depende de la ciudad
  del pasajero.
- **Mis datos**: la app permite editar nombre, apellido, teléfono y dirección. La cédula
  se muestra bloqueada. Cambiar el correo pide la contraseña actual y deja la cuenta
  pendiente de verificar.
- **Latido de ubicación**: mientras el conductor está en línea, la app reenvía la última
  posición cada 60 s y al volver a primer plano, para que el backend no la marque vencida.
  También hay un botón para actualizar la ubicación a mano.
- **Bandeja de avisos**: `GET /api/notificaciones` se pide al abrir el inicio, al
  deslizar para refrescar y cuando llega `notificacion.creada`. Los avisos no leídos se
  muestran en una tarjeta y se pueden marcar todos como leídos. Hoy llegan los documentos
  por vencer y los cambios de viaje. El push de FCM ya está integrado; para recibirlo
  hay que colocar las credenciales de Firebase descritas en `docs/credenciales.md`.
- **Calificaciones y favoritos**: al completar un viaje el pasajero puede calificar al
  conductor (1–5 estrellas y comentario) y marcarlo como favorito. El inicio tiene
  **Mis conductores favoritos** con su estado y reputación; el historial marca los viajes
  **Sin calificar**. El conductor ve sus estrellas y comentarios en **Mis calificaciones**.
- **El conductor no inventa disponibilidad**: para conectarse pide permiso de ubicación,
  reporta la posición y el backend decide si puede recibir viajes (aprobado, sin bloqueo,
  ubicación reciente y saldo dentro del tope).
- **El mapa siempre está disponible**: usa `flutter_map` con tiles de OpenStreetMap; si
  se compila con `--dart-define=GEOAPIFY_API_KEY=...`, usa los tiles de Geoapify. El
  backend usa su propia clave para rutas y direcciones. Se puede tocar el mapa para fijar
  el destino y buscar direcciones. En modo de desarrollo también hay coordenadas de
  prueba. La cotización siempre la calcula el backend.

## Mapas en la app

La app usa `flutter_map` + `latlong2` para el mapa y `geolocator` para la ubicación.
**No necesita claves nativas en Android ni iOS, ni facturación.** Por defecto dibuja
tiles públicos de OpenStreetMap; con una clave gratuita de Geoapify usa sus tiles (el
backend usa la misma clave para rutas y direcciones).

### OpenStreetMap (por defecto)

No hay que configurar nada. La app descarga tiles de `tile.openstreetmap.org` y muestra
la atribución *© OpenStreetMap contributors* sobre el mapa. Es la opción para desarrollo y
para el piloto.

### Geoapify (opcional, recomendado)

Crea una cuenta gratuita en <https://myprojects.geoapify.com> (no pide tarjeta) y compila
con:

```bash
flutter run --dart-define=GEOAPIFY_API_KEY=tu_clave_de_geoapify
```

La clave también puede vivir en `secrets/geoapify.json` (ignorado por git):

```bash
flutter run --dart-define-from-file=secrets/geoapify.json
```

La misma clave va en `backend/.env` (`GEOAPIFY_API_KEY`) para que el backend calcule
rutas en modo moto o carro y busque direcciones. Sin la clave del backend, la ruta se
estima localmente y las direcciones usan Nominatim; la app sigue funcionando.

### Atribución

El mapa muestra `© OpenStreetMap contributors` y, si usas Geoapify, añade `· Geoapify`.
Es obligatoria; no la ocultes.

### Web

En web `flutter_map` usa los mismos tiles; no hay que agregar ningún `<script>` de mapas
en `web/index.html`.

### iOS

`pod install` ya no instala pods de mapas. El deployment target sigue en iOS 14 y
`flutter_map` es Dart puro, así que no hay configuración nativa adicional.

## Tiempo real (WebSockets)

La app habla el protocolo de **Laravel Reverb** con `web_socket_channel`, sin
dependencias nativas. Al iniciar sesión abre un canal privado por persona
(`App.Models.User.{id}`), pide la firma a `POST /api/broadcasting/auth` con el token de
Sanctum y escucha:

| Evento | Qué hace la app |
|---|---|
| `oferta.viaje` | Pide las ofertas y abre la alerta del conductor |
| `viaje.actualizado` | Refresca el viaje y actualiza la pantalla abierta |
| `conductor.ubicacion` | Mueve el conductor en el mapa del pasajero |
| `notificacion.creada` | Refresca la bandeja de avisos |

La conexión se reconecta sola con espera progresiva (1, 2, 5, 10, 20 y 30 s) y al
volver a conectar resincroniza viaje, ofertas, saldo y avisos. Si no hay
`REVERB_APP_KEY`, el cliente queda apagado y la app usa solo el sondeo.

### Configurar Reverb en la app

La clave pública está en `backend/.env` (`REVERB_APP_KEY`). Guárdala en
`secrets/reverb.json` (ignorado por git), junto con el puerto y el esquema:

```json
{
  "REVERB_APP_KEY": "la_misma_clave_de_backend_env",
  "REVERB_PORT": "8080",
  "REVERB_SCHEME": "http"
}
```

Y corre la app con:

```bash
flutter run --dart-define-from-file=secrets/reverb.json
```

Se puede combinar con la clave de Geoapify:

```bash
flutter run \
  --dart-define-from-file=secrets/geoapify.json \
  --dart-define-from-file=secrets/reverb.json
```

En el emulador de Android el host se ajusta solo a `10.0.2.2`. En un teléfono físico,
sirve la API en la red y pasa la IP de la computadora:

```bash
# backend
php artisan serve --host=0.0.0.0 --port=8000

# mobile
flutter run \
  --dart-define=API_BASE_URL=http://192.168.1.20:8000 \
  --dart-define-from-file=secrets/reverb.json
```

La app deduce el host del WebSocket de `API_BASE_URL`. Si Reverb vive en otra
máquina, agrega `--dart-define=REVERB_HOST=192.168.1.20`.

El backend debe tener corriendo `php artisan reverb:start --debug` y
`php artisan queue:work` (los eventos se encolan en la base de datos). El
`REVERB_APP_SECRET` nunca se coloca en la app.

## Publicación

- **Push Android**: colocar `android/app/google-services.json`; **push iOS**: subir la
  clave APNs a Firebase y probar en un iPhone real (ver `docs/publicacion.md`).
- **Firma Android**: crear el keystore y `android/key.properties`; sin ese archivo el
  release usa la firma de debug y no sirve para publicar.
- **Íconos**: ya se generaron desde el isotipo de la marca para Android e iOS.
- **Tiendas**: faltan cuentas de desarrollador, capturas, textos y política de privacidad.
- **Android**: el proyecto usa Gradle 7.6, que no acepta Java 25. Para compilar hay que
  apuntar Flutter a un JDK 17 y aceptar las licencias:

  ```bash
  brew install openjdk@17
  flutter config --jdk-dir="$(brew --prefix openjdk@17)"
  flutter doctor --android-licenses
  ```

- **iOS/macOS**: Xcode 15.2 y CocoaPods 1.17 ya están instalados y `pod install` funciona.
  Falta descargar el runtime del simulador (Xcode → Settings → Platforms) para compilar y
  correr en un simulador.

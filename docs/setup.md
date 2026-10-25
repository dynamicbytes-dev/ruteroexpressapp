# GAZ — Setup de desarrollo

## Requisitos

| Herramienta | Versión | Notas |
|---|---|---|
| PHP | 8.2+ | En este equipo está vía Herd (`php` en el PATH) |
| Composer | 2.x | |
| Docker Desktop | en ejecución | Para la base de datos con PostGIS |
| Node.js | 20+ | Para compilar assets del panel web |
| Flutter SDK | 3.22+ | App móvil de pasajeros y conductores |

Verificar:

```bash
php -v && composer -V && node -v && docker info >/dev/null && echo "docker ok"
```

## Puesta en marcha

```bash
# 1. Base de datos (PostGIS en el puerto 5433, no choca con el Postgres local del 5432)
docker compose up -d
docker compose ps          # debe decir "healthy"

# 2. Backend
cd backend
cp .env.example .env       # solo si no existe
php artisan key:generate
php artisan migrate --seed

# 3. Assets del panel web
npm install
npm run dev                # deja corriendo Vite en otra terminal

# 4. Servidor
php artisan serve          # http://localhost:8000
```

El panel del auditor queda en **http://localhost:8000/panel**.

### Credenciales de desarrollo

El seeder crea un auditor para entrar al panel **sin contraseña compartida**:

- Si defines `AUDITOR_INITIAL_PASSWORD` (mínimo 12 caracteres) en `backend/.env`, usa esa.
- Si no, genera una contraseña aleatoria y la guarda en
  `backend/storage/auditor-password.txt` con permisos `600`. Ese archivo está ignorado
  por git y nunca debe pegarse en el chat ni en un issue.

Correo inicial: `auditor@motoruta.app`.

Para rotar la contraseña, usa `/recuperar` con el correo del auditor (requiere SMTP
configurado) o crea una cuenta nueva desde consola. En producción el auditor se crea
por consola, nunca por registro público:

```bash
php artisan motoruta:crear-auditor
```

La contraseña del auditor exige un mínimo de 12 caracteres. No reutilices contraseñas
entre entornos ni compartas la de producción.

### Rutas de la aplicación web

| Ruta | Para qué |
|---|---|
| `/panel` | Panel del auditor (solo rol auditor) |
| `/panel/login` | Entrada del auditor |
| `/recuperar` | Pedir el enlace de recuperación de contraseña (auditor y app) |
| `/restablecer/{token}` | Crear una contraseña nueva desde el enlace del correo |
| `/verificar-correo/{id}/{hash}` | Enlace firmado del correo de verificación |
| `/panel/conductores` | Conductores: disponibilidad, bloqueos, deuda y documentos |
| `/panel/reportes` | Métricas del período y exportables CSV |
| `/panel/auditoria` | Log de actividad con filtros y exportación CSV |
| `/panel/2fa` | Activar o administrar la verificación en dos pasos |

Las páginas de recuperación y verificación sirven para el auditor y para quienes usan
la app: el enlace del correo siempre abre en el navegador, y quien llega desde el
teléfono ve un aviso para volver a la aplicación.

Al entrar por primera vez, el panel muestra un aviso para activar el **doble factor**.
Se puede escanear el QR con Google Authenticator, Authy o 1Password y guardar los ocho
códigos de recuperación. Si se pierde el teléfono, cada código de recuperación sirve una
sola vez.

### Mapas, rutas y direcciones (Geoapify / OpenStreetMap)

> Para crear la cuenta gratuita de Geoapify y colocar la clave paso a paso, ver
> [credenciales.md](credenciales.md).

GAZ no depende de Google Cloud ni de una tarjeta de crédito. El mapa usa
**OpenStreetMap** y, si hay clave, **Geoapify** (gratis, 3.000 créditos/día) para
tiles, rutas en modo moto o carro y búsqueda de direcciones.

```ini
# backend/.env
GEOAPIFY_API_KEY=tu_clave_de_geoapify
GEOAPIFY_ROUTING_MODE=motorcycle
```

Sin `GEOAPIFY_API_KEY` todo sigue funcionando en modo degradado:

| Servicio | Sin clave | Con Geoapify |
|---|---|---|
| Mapa | Tiles públicos de OpenStreetMap | Tiles de Geoapify |
| Rutas y ETA | Estimación local (`route_source = estimada`) | Ruta real (`route_source = geoapify`) |
| Direcciones | Nominatim (OpenStreetMap) | Geocoding de Geoapify |

Para los mapas, la app móvil **no necesita claves nativas** en Android ni iOS. Para
usar los tiles de Geoapify se compila con:

```bash
flutter run --dart-define=GEOAPIFY_API_KEY=tu_clave_de_geoapify
```

También puedes guardar la clave en `mobile/secrets/geoapify.json` (esa carpeta está
ignorada por git) y ejecutar:

```bash
flutter run --dart-define-from-file=secrets/geoapify.json
```

Sin ese `--dart-define`, la app usa los tiles de OpenStreetMap. El mapa siempre está
disponible; solo cambia el proveedor. La atribución *© OpenStreetMap contributors* se
muestra sobre el mapa y, con Geoapify, se añade *· Geoapify*.

Las rutas usan el modo `motorcycle` de Geoapify. Si el servicio falla o no hay red,
el backend cae a la estimación local (distancia en línea recta × factor de rodeo y
velocidad promedio), así que cotizar y viajar nunca se bloquean.

### Comandos programados

El planificador mantiene la operación al día sin depender de que la app esté abierta:

| Comando | Frecuencia | Qué hace |
|---|---|---|
| `viajes:expirar-ofertas` | Cada minuto | Vence alertas sin respuesta y continúa la cascada |
| `conductores:desconectar-inactivos` | Cada minuto | Apaga al conductor que dejó de reportar ubicación |
| `documentos:avisar-vencimientos` | Diario, 08:00 | Avisa a 30/15/5 días y pasa a revisión al vencer |

```bash
php artisan schedule:work          # deja el planificador corriendo en otra terminal
# o ejecutarlos a mano:
php artisan viajes:expirar-ofertas
php artisan conductores:desconectar-inactivos
php artisan documentos:avisar-vencimientos
```

Sin el planificador, la cascada igual avanza cuando la app consulta las ofertas o el
viaje, pero el planificador es el que garantiza que no quede una alerta colgada ni un
conductor fantasma en línea. El tiempo de desconexión se ajusta en `backend/.env`:

```ini
# Minutos sin reportar ubicación antes de marcar al conductor desconectado
MOTORUTA_DRIVER_OFFLINE_MINUTES=5
```

### Vencimiento de documentos

El comando diario guarda un aviso en la tabla `notifications` para cada documento de
conductor que esté a 30, 15 o 5 días de vencer. El aviso se consulta con
`GET /api/notificaciones` y se ve en el dashboard del panel. Cuando el documento vence,
la cuenta vuelve a `pending`, el conductor se desconecta y el auditor debe esperar a que
suba el documento vigente antes de aprobar la cuenta.

### Tiempo real con Reverb (F4)

El backend emite los cambios de viaje en vivo por WebSockets con **Laravel Reverb**.
La instalación ya está hecha (`laravel/reverb`) y `BROADCAST_CONNECTION=reverb`; las
credenciales las generó `php artisan install:broadcasting --reverb` en `backend/.env`:

```ini
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

Para el tiempo real hacen falta dos procesos más (además de `php artisan serve`):

```bash
php artisan reverb:start --debug     # WebSockets en el puerto 8080
php artisan queue:work               # las notificaciones se encolan
```

La autorización de canales privados tiene una ruta por cliente:

| Cliente | Ruta | Guard |
|---|---|---|
| App móvil (token de Sanctum) | `/api/broadcasting/auth` | `sanctum` |
| Panel del auditor (sesión) | `/broadcasting/auth` | `web` |

Cada persona escucha su propio canal `App.Models.User.{id}` (ver `routes/channels.php`).
Las pruebas usan el broadcaster `null`; la app real usa `reverb`.

La app móvil necesita la **clave pública** de Reverb (`REVERB_APP_KEY`, no el
`REVERB_APP_SECRET`). Se puede pasar al compilar:

```bash
cd mobile
flutter run \
  --dart-define-from-file=secrets/geoapify.json \
  --dart-define-from-file=secrets/reverb.json
```

`mobile/secrets/reverb.json` (ignorado por git) contiene:

```json
{
  "REVERB_APP_KEY": "la_misma_clave_de_backend_env",
  "REVERB_PORT": "8080",
  "REVERB_SCHEME": "http"
}
```

En el emulador de Android la app usa `10.0.2.2` automáticamente. En un teléfono
físico hay que servir la API en la red (`--host=0.0.0.0`) y apuntar la app a la IP
de la computadora:

```bash
# backend
php artisan serve --host=0.0.0.0 --port=8000

# mobile
flutter run \
  --dart-define=API_BASE_URL=http://192.168.1.20:8000 \
  --dart-define-from-file=secrets/reverb.json
```

La app deduce el host del WebSocket de `API_BASE_URL`; si Reverb está en otra
máquina, agrega `--dart-define=REVERB_HOST=192.168.1.20`.

Sin `REVERB_APP_KEY` la app no abre el WebSocket y sigue con el sondeo normal: los
viajes se actualizan igual, solo que con algunos segundos de retraso. Las señales
que se emiten son:

| Evento | Va a | Para qué |
|---|---|---|
| `oferta.viaje` | Conductor | Llegó una alerta nueva; la app pide las ofertas |
| `viaje.actualizado` | Pasajero y/o conductor | Cambió el estado, se canceló o terminó |
| `conductor.ubicacion` | Pasajero | Mover el conductor en el mapa en vivo |
| `notificacion.creada` | La persona | Refrescar la bandeja de avisos |

El sondeo se mantiene como red de seguridad: 15 s en las pantallas de viaje, 20 s
en el panel del conductor y 30 s en el inicio del pasajero cuando el WebSocket está
conectado; si no lo está, vuelve a 4/5/10 s.

### Notificaciones push con FCM (F4)

> Para crear el proyecto de Firebase y colocar sus archivos paso a paso, ver
> [credenciales.md](credenciales.md).

Las notificaciones push necesitan un proyecto de **Firebase** con **Cloud Messaging**
activado. Este proyecto usa `motoruta-30c37`. En `backend/.env`:

```ini
FCM_PROJECT_ID=motoruta-30c37
FCM_CREDENTIALS_PATH=storage/app/firebase/motoruta-30c37-firebase-adminsdk-fbsvc-faa83bd80e.json
```

El JSON de la cuenta de servicio (Firebase Console → Configuración del proyecto →
Cuentas de servicio → Generar nueva clave privada) se guarda en
`backend/storage/app/firebase/`; la carpeta está ignorada por git. La app móvil usa
`android/app/google-services.json` y `ios/Runner/GoogleService-Info.plist`, que tampoco
se versionan. Sin estos archivos la app funciona, pero no recibe push.

El backend envía con la API HTTP v1 (`firebase/php-jwt` + cliente HTTP de Laravel) y la
app usa `firebase_core` y `firebase_messaging`. Al iniciar sesión se registra el equipo en
`POST /api/dispositivos`; al cerrar sesión se elimina con `DELETE /api/dispositivos`.

Los push de ofertas, cambios de viaje y avisos se envían desde **listeners en cola**.
En desarrollo deja `php artisan queue:work --tries=1` corriendo; sin el worker, el push
queda pendiente aunque el WebSocket siga funcionando. Para Android 13+ la app pide el
permiso `POST_NOTIFICATIONS`; en iOS hay que subir la clave APNs a Firebase y habilitar
Push Notifications en el perfil de firma. La guía completa está en
[credenciales.md](credenciales.md).

### Correos en desarrollo

Por defecto `MAIL_MAILER=log`: los correos no salen, se escriben en
`backend/storage/logs/laravel.log`. Para probar la recuperación de contraseña, pide el
enlace, abre el archivo y copia la URL que aparece después de `restablecer/`:

```bash
grep -o 'http://localhost:8000/restablecer/[^"]*' storage/logs/laravel.log | tail -1
```

Para probar el envío real con Gmail:

1. Activa la verificación en dos pasos en la cuenta de Gmail.
2. Crea una **contraseña de aplicación** de 16 caracteres en
   <https://myaccount.google.com/apppasswords>. No uses la contraseña normal de la cuenta.
3. Configura `backend/.env` (la contraseña de aplicación lleva espacios; va entre comillas):

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=tu-cuenta@gmail.com
MAIL_PASSWORD="xxxx xxxx xxxx xxxx"
MAIL_FROM_ADDRESS=tu-cuenta@gmail.com
MAIL_FROM_NAME="GAZ"
```

4. Ajusta `APP_URL` a la dirección desde la que se abrirán los enlaces. Para probar desde un
   teléfono en la misma red, usa la IP LAN de la computadora, por ejemplo
   `APP_URL=http://192.168.1.11:8000`. En producción debe ser el dominio HTTPS.
5. Limpia la configuración y envía una prueba:

```bash
php artisan config:clear
php artisan tinker --execute="Illuminate\\Support\\Facades\\Mail::raw('Prueba', fn (\$m) => \$m->to('tu-cuenta@gmail.com')->subject('Prueba SMTP'));"
```

Gmail sirve para desarrollo, pero tiene límites de envío y menor entregabilidad. Al tener
dominio propio, cambia a un SMTP con SPF y DKIM configurados.


### Probar el registro sin la app móvil

El contrato completo está en [api-app.md](api-app.md). Un registro de pasajero con sus
dos documentos:

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Accept: application/json" \
  -F role=passenger \
  -F first_name=María -F last_name=Pérez \
  -F national_id=18452336 \
  -F email=maria.perez@gmail.com \
  -F phone=0412-1234567 \
  -F address="Av. Bolívar, casa 12, Maracay" \
  -F password=clave12345 -F password_confirmation=clave12345 \
  -F "documentos[cedula]=@/ruta/a/cedula.jpg" \
  -F "documentos[selfie]=@/ruta/a/selfie.jpg"
```

Con el `token` que devuelve esa respuesta se consulta el estado:

```bash
curl http://localhost:8000/api/perfil -H "Authorization: Bearer <token>" -H "Accept: application/json"
```

Después, en el panel, el usuario aparece en **Usuarios → Por verificar**.

## App móvil (Flutter)

La app está en `mobile/` y consume la misma API (`/api`). Cubre registro,
documentos, espera de verificación, billetera, recargas, retiros y viajes.

```bash
# Requisitos: Flutter 3.22 (en esta máquina, ~/Documents/development/flutter)
export PATH="$PATH:$HOME/Documents/development/flutter/bin"

cd mobile
flutter pub get
flutter run -d chrome --web-port=5000       # o -d <dispositivo Android>
```

La dirección del backend se elige por plataforma (`localhost:8000` en web/iOS,
`10.0.2.2:8000` en el emulador de Android). Para apuntar a otra máquina:

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000
```

La API permite peticiones desde cualquier origen, así que la app web puede correr en
`localhost:5000` sin configurar CORS.

Pruebas de la app (no necesitan el backend):

```bash
cd mobile
flutter test
flutter analyze
```

Y la prueba de integración contra el backend real, que se salta por defecto:

```bash
flutter test --dart-define=API_REAL=true test/integracion
```

### Si vas a compilar para Android

El proyecto se creó con Gradle 7.6, que no funciona con Java 25 (el que trae Android
Studio en esta máquina). Hay que apuntar Flutter a un JDK 17 y aceptar las licencias:

```bash
brew install openjdk@17
flutter config --jdk-dir="$(brew --prefix openjdk@17)"
flutter doctor --android-licenses
```

La app fija `minSdk 23` porque `firebase_messaging` no admite versiones anteriores de
Android. El plugin de Google Services y `google-services.json` se explican en
[credenciales.md](credenciales.md).

En iOS, Xcode 15.2 y CocoaPods 1.17 ya están instalados y `pod install` funciona, pero
falta instalar la plataforma iOS 17.2 (Xcode → Settings → Platforms) para compilar y
correr en un simulador o dispositivo. El Podfile fija iOS 15 porque `firebase_core` y
`firebase_messaging` ya no admiten iOS 14.

## Comandos útiles

```bash
# Detener / borrar la base de datos
docker compose stop
docker compose down -v     # ¡borra todos los datos!

# Entrar a la base
docker compose exec db psql -U motoruta -d motoruta

# Verificar PostGIS
docker compose exec db psql -U motoruta -d motoruta -c "select postgis_version();"

# Reiniciar la base desde cero
php artisan migrate:fresh --seed

# Pruebas (usan la base motoruta_testing, dentro del mismo contenedor)
php artisan test
php artisan test --filter=GmailOnlyEmail   # una sola prueba

# Formato de código (Laravel Pint)
./vendor/bin/pint

# Colas y WebSockets (F4: tiempo real)
php artisan queue:work
php artisan reverb:start --debug
```

## Base de datos de pruebas

Las pruebas corren contra **PostgreSQL real**, no SQLite, porque el esquema usa PostGIS
(tipos geográficos para la búsqueda de conductores cercanos). La base `motoruta_testing`
se crea sola cuando el volumen de datos es nuevo (ver `docker/db/init/02-test-database.sql`).

Si el volumen ya existía antes de crear ese archivo, se crea a mano una vez:

```bash
docker compose exec -T db psql -U motoruta -d postgres -c "CREATE DATABASE motoruta_testing OWNER motoruta"
docker compose exec -T db psql -U motoruta -d motoruta_testing -c "CREATE EXTENSION postgis"
```


## Estructura del repositorio

```
motoruta/
├── backend/            Laravel 12: API móvil + panel web del auditor
├── mobile/             App Flutter (pasajero y conductor): registro, billetera y viajes
├── docker/             Configuración de la base de datos
│   └── db/init/        Scripts de inicialización (extensiones PostGIS)
├── design/             Marca: logo, tokens de color, tipografía
├── docs/               Reglas de negocio, arquitectura, guías
└── docker-compose.yml
```

## Puertos

| Servicio | Puerto | Se usa para |
|---|---|---|
| PostgreSQL (Docker) | 5433 | Base de datos de GAZ |
| Laravel | 8000 | API + panel web |
| Vite | 5173 | Assets en desarrollo |
| PostgreSQL local | 5432 | Otros proyectos del equipo (no se toca) |
| App web (Flutter) | 5000 | App móvil en el navegador (desarrollo) |
| Reverb | 8080 | WebSockets (fase F4) |

## Notas del entorno

- **PHP 8.2:** el instalador CLI de Laravel exige 8.3, pero el framework 12 funciona con 8.2.
  Si en fases futuras alguna dependencia exige 8.3+, hay que instalar esa versión desde Herd.
- **PostGIS:** no viene en el PostgreSQL 17 local del equipo (instalado con el paquete de EDB),
  por eso la base vive en Docker.
- **`docker compose down -v`** elimina los datos. Los documentos de usuarios se guardan en
  `backend/storage/app/private/documentos/`, que sí persiste en el host.

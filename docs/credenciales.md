# GAZ — Credenciales de servicios externos

Guía para crear y colocar las credenciales de **Geoapify** (mapas, rutas y
direcciones) y **Firebase Cloud Messaging** (notificaciones push). Ninguna se
versiona en git.

Sin estas credenciales GAZ sigue funcionando en modo degradado:

| Servicio | Sin credenciales | Con credenciales |
|---|---|---|
| Mapas y rutas | Tiles de OpenStreetMap, Nominatim y estimación local (`route_source = estimada`) | Tiles de Geoapify, rutas en modo moto o carro y geocoding (`route_source = geoapify`) |
| FCM | La app no recibe push; usa sondeo cada pocos segundos | Alertas y cambios de viaje en vivo |

Datos fijos de este proyecto para registrar las apps en Firebase:

| Dato | Valor |
|---|---|
| Paquete / aplicación Android | `app.motoruta` |
| Bundle ID de iOS | `app.motoruta` |

---

## Parte 1 — Geoapify (mapas, rutas y direcciones)

Geoapify es gratis, **no pide tarjeta** y permite uso comercial. El plan gratuito
incluye **3.000 créditos por día** (1 tile = 0,25 créditos; 1 ruta = 1 crédito;
1 búsqueda de dirección = 1 crédito). Para un piloto en una ciudad sobra.

### 1.1 Crear la cuenta y la clave

1. Entra a <https://myprojects.geoapify.com>.
2. Regístrate con tu correo o con GitHub. **No solicita tarjeta.**
3. Crea un proyecto llamado `GAZ`.
4. Copia la **API key** que aparece en el proyecto.

### 1.2 Restringir la clave (recomendado)

En la configuración de la API key puedes limitar:

- **APIs**: Maps, Routing y Geocoding.
- **Orígenes**: `http://localhost:*` y tu dominio de producción.
- **IP** (opcional, para el backend).

> Si usas la misma clave en el backend y en la app, no pongas restricción por IP;
> deja al menos los orígenes HTTP. Para el piloto, una sola clave sin restricción
> es aceptable.

### 1.3 Colocar la clave

```ini
# backend/.env
GEOAPIFY_API_KEY=tu_clave_de_geoapify
GEOAPIFY_ROUTING_MODE=motorcycle
```

La app móvil **no necesita archivos nativos** ni claves en Android/iOS. Se compila
con la clave para usar los tiles de Geoapify:

```bash
flutter run --dart-define=GEOAPIFY_API_KEY=tu_clave_de_geoapify
```

O guarda la clave en `mobile/secrets/geoapify.json` (ignorado por git) y usa:

```bash
flutter run --dart-define-from-file=secrets/geoapify.json
```

Sin ese `--dart-define`, la app usa los tiles públicos de OpenStreetMap y el mapa
funciona igual.

### 1.4 Verificar

```bash
php artisan config:clear

curl -s -X POST http://localhost:8000/api/viajes/cotizar \
  -H "Authorization: Bearer <token>" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"origin":{"lat":10.24,"lng":-67.59},"destination":{"lat":10.25,"lng":-67.60},"payment_method":"cash"}' \
  | grep -o '"route_source":"[a-z]*"'
```

Debe responder `"route_source":"geoapify"`. En la app, el mapa debe mostrar los
tiles de Geoapify y la búsqueda de direcciones debe devolver resultados.

### 1.5 Sin clave: OpenStreetMap

Todo el sistema funciona sin cuenta:

- **Mapa**: tiles públicos de `tile.openstreetmap.org`.
- **Direcciones**: Nominatim (`nominatim.openstreetmap.org`), con un máximo de
  1 petición por segundo y `User-Agent` identificable.
- **Rutas**: estimación local (distancia en línea recta × 1,25 y 25 km/h).

OpenStreetMap es *best-effort* y no tiene SLA. Para producción conviene poner la
clave gratuita de Geoapify. El backend ya está preparado para alternar sin tocar
código: si `GEOAPIFY_API_KEY` está vacía, usa OSM/Nominatim y la estimación local.

> **Atribución obligatoria**: el mapa muestra `© OpenStreetMap contributors` y, con
> Geoapify, añade `· Geoapify`. No la ocultes; es parte de las condiciones de ambos
> servicios.

---

## Parte 1.5 — Reverb (WebSockets)

Reverb no necesita una cuenta externa: las claves se generan con la instalación del
backend y viven en `backend/.env`. La app móvil solo usa la clave **pública**:

```ini
# backend/.env
REVERB_APP_KEY=...
REVERB_APP_SECRET=...   # solo backend, nunca en la app
```

Copia `REVERB_APP_KEY` a `mobile/secrets/reverb.json` (ignorado por git):

```json
{
  "REVERB_APP_KEY": "la_misma_clave_de_backend_env",
  "REVERB_PORT": "8080",
  "REVERB_SCHEME": "http"
}
```

Compila la app con:

```bash
cd mobile
flutter run --dart-define-from-file=secrets/reverb.json
```

Para un teléfono físico sirve la API con `php artisan serve --host=0.0.0.0 --port=8000`
y compila con `--dart-define=API_BASE_URL=http://192.168.1.20:8000` (la IP de la
computadora). La app deduce el host del WebSocket de esa URL; si Reverb está en otra
máquina, agrega `--dart-define=REVERB_HOST=192.168.1.20`. En el emulador de Android el
host se ajusta solo a `10.0.2.2`. Para verificar, deja corriendo
`php artisan reverb:start --debug` y `php artisan queue:work`: al pedir un viaje debe
aparecer la conexión y la alerta del conductor sin esperar al sondeo.

---

## Parte 2 — Firebase Cloud Messaging (FCM)

### 2.1 Crear el proyecto

1. Entra a <https://console.firebase.google.com>.
2. **Crear un proyecto** → este proyecto usa `motoruta-30c37`.
3. Google Analytics es opcional; puedes desactivarlo.
4. Espera a que se cree y entra al proyecto.

**Cloud Messaging** se habilita solo; verifica en **Compilar → Cloud Messaging**
que aparezca activo.

### 2.2 App Android

1. En la página del proyecto, pulsa el icono de **Android**.
2. **Nombre del paquete Android**: `app.motoruta`.
3. Descarga `google-services.json`.
4. Guárdalo en `mobile/android/app/google-services.json`.

El plugin `com.google.gms.google-services` ya está declarado en
`android/settings.gradle` y aplicado en `android/app/build.gradle`. El permiso
`POST_NOTIFICATIONS` ya está en el manifiesto para Android 13+.

### 2.3 App iOS

1. En la página del proyecto, pulsa **Agregar app → iOS**.
2. **Bundle ID**: `app.motoruta`.
3. Descarga `GoogleService-Info.plist`.
4. Guárdalo en `mobile/ios/Runner/GoogleService-Info.plist`.

El proyecto de Xcode ya tiene la referencia al archivo, el modo de fondo
`remote-notification` en `Info.plist` y `Runner.entitlements` con
`aps-environment`.

### 2.4 Cuenta de servicio para el backend

El backend envía los push con la API HTTP v1 y una cuenta de servicio.

1. Firebase → **Configuración del proyecto → Cuentas de servicio**.
2. **Generar nueva clave privada → Generar clave**. Descarga el JSON.
3. Guárdalo en `backend/storage/app/firebase/`. Puedes usar el nombre que
   Firebase le puso al descargarlo; en este equipo es:

```
backend/storage/app/firebase/motoruta-30c37-firebase-adminsdk-fbsvc-faa83bd80e.json
```

Esa carpeta está ignorada por git.

### 2.5 Variables en el backend

```ini
# backend/.env
FCM_PROJECT_ID=motoruta-30c37
FCM_CREDENTIALS_PATH=storage/app/firebase/motoruta-30c37-firebase-adminsdk-fbsvc-faa83bd80e.json
```

Si usas otro nombre de archivo, cambia solo `FCM_CREDENTIALS_PATH`.
`config/services.php` lee estas variables en la entrada `fcm`. Después de
editarlas ejecuta `php artisan config:clear`.

### 2.6 iOS: APNs (solo para push en iPhone)

Requiere una cuenta de Apple Developer. Genera una **APNs Authentication Key**
(*.p8, Key ID y Team ID) en el Apple Developer portal y súbela en
Firebase → **Cloud Messaging → Configuración de la app Apple**. En el target
`Runner` de Xcode debe estar habilitada la capacidad **Push Notifications** y el
perfil de firma debe incluirla. En Android no hace falta nada extra.

### 2.7 Cómo se envía y cómo probarlo

La app registra el equipo al iniciar sesión:

- `POST /api/dispositivos` con `token`, `platform` (`android`/`ios`) y
  `device_name` opcional.
- `DELETE /api/dispositivos` con `token` al cerrar sesión.

El backend manda push cuando:

- se ofrece un viaje al conductor (`oferta.viaje`);
- el viaje cambia de estado o se cancela (`viaje.actualizado`);
- se crea un aviso en la bandeja, por ejemplo un documento por vencer
  (`notificacion.creada`).

Los listeners de push usan la cola. En desarrollo deja corriendo:

```bash
cd backend
php artisan queue:work --tries=1
```

Para probar en un teléfono real, instala la app, inicia sesión, acepta el permiso
de notificaciones y cierra la app. Desde otra cuenta pide un viaje (o espera el
aviso de documento). El teléfono debe mostrar la notificación del sistema; al
tocarla, la app abre y sincroniza el viaje o la alerta.

> Sin la clave APNs configurada en Firebase, Android recibe push pero iOS no.
> Sin las credenciales de Firebase, la app sigue usando WebSocket y sondeo.

---

## Checklist final

- [ ] `backend/.env` con `GEOAPIFY_API_KEY` (opcional, pero recomendado).
- [ ] App compilada con `--dart-define=GEOAPIFY_API_KEY=...` (opcional).
- [ ] `mobile/secrets/reverb.json` con la misma `REVERB_APP_KEY` del backend.
- [ ] `php artisan reverb:start --debug` y `php artisan queue:work` corriendo.
- [ ] `backend/.env` con `FCM_PROJECT_ID` y `FCM_CREDENTIALS_PATH`.
- [ ] JSON de la cuenta de servicio en `backend/storage/app/firebase/`.
- [ ] `mobile/android/app/google-services.json` colocado.
- [ ] `mobile/ios/Runner/GoogleService-Info.plist` colocado.
- [ ] Clave APNs subida a Firebase (solo iOS) y Push Notifications habilitado.
- [ ] `php artisan config:clear` después de editar `.env`.

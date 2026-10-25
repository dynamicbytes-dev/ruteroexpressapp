# Publicación en tiendas (F6)

Esta guía deja el proyecto listo para el piloto y la publicación. Las cuentas de
Google Play, App Store y los datos legales son decisiones del negocio; aquí está
lo técnico que falta y cómo completarlo.

## Checklist rápido

| Área | Estado | Qué falta |
|---|---|---|
| CI de backend y móvil | Listo | Activar GitHub Actions en el repositorio |
| Firma de release Android | Listo | Crear keystore y `android/key.properties` |
| Íconos de lanzamiento | Listo | Revisar el diseño en dispositivos reales |
| Push Android | Listo | `google-services.json` en el equipo de release |
| Push iOS | Pendiente | Clave APNs y validación en dispositivo real |
| Ficha de tienda | Pendiente | Textos, capturas, política de privacidad |
| Piloto | Pendiente | Dispositivos reales y cuentas de prueba |

## 1. Integración continua

El flujo `.github/workflows/ci.yml` corre en cada push y pull request a `main`:

- **Backend:** PHP 8.2, PostgreSQL 17 + PostGIS 3.5 y `php artisan test`.
- **Móvil:** Flutter 3.22, `flutter analyze` y `flutter test`.

Las pruebas del backend usan la base `motoruta_testing` y no necesitan compilar
Vite (`withoutVite()` en `tests/TestCase.php`). Si el repositorio es privado,
verifica que Actions tenga minutos disponibles.

## 2. Firma de release Android

1. Crear el keystore (guárdalo fuera del repositorio y respáldalo):

   ```bash
   keytool -genkey -v \
     -keystore ~/rutero-upload.jks \
     -keyalg RSA -keysize 2048 -validity 10000 \
     -alias upload
   ```

2. Copiar `mobile/android/key.properties.example` como
   `mobile/android/key.properties` y completar:

   ```properties
   storePassword=...
   keyPassword=...
   keyAlias=upload
   storeFile=/ruta/absoluta/o/relativa/a/rutero-upload.jks
   ```

   `key.properties` y los archivos `.jks`/`.keystore` ya están en `.gitignore`.

3. Compilar el bundle:

   ```bash
   cd mobile
   flutter build appbundle --release \
     --dart-define=GEOAPIFY_API_KEY=... \
     --dart-define=REVERB_APP_KEY=...
   ```

4. Verificar la firma antes de subir:

   ```bash
   keytool -printcert -jarfile build/app/outputs/bundle/release/app-release.aab
   ```

Sin `key.properties`, el build de release sigue usando la firma de debug para
permitir pruebas locales. **No publiques un AAB firmado con debug.**

### Firebase Android

`mobile/android/app/google-services.json` no se versiona. El equipo de release
debe colocarlo y comprobar que el `package_name` coincide con `app.motoruta`.
Si se cambia el `applicationId`, hay que registrar el nuevo paquete en Firebase.

## 3. iOS

1. Abrir `mobile/ios/Runner.xcworkspace` en Xcode.
2. Seleccionar el equipo de desarrollo y un bundle identifier propio.
3. En *Signing & Capabilities*, agregar **Push Notifications** y
   **Background Modes → Remote notifications**.
4. Subir la clave APNs (`.p8`) a Firebase Console → Cloud Messaging.
5. El proyecto ya usa `APS_ENVIRONMENT=production` en Release y
   `development` en Debug (`Runner.entitlements`). Verifica que el perfil de
   distribución permita push y que la clave APNs esté cargada en Firebase.
6. Archivar y subir a TestFlight:

   ```bash
   cd mobile
   flutter build ipa --release \
     --dart-define=GEOAPIFY_API_KEY=... \
     --dart-define=REVERB_APP_KEY=...
   ```

7. Probar push en un iPhone real: los simuladores no reciben APNs.

## 4. Backend de producción

Antes del piloto:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con HTTPS y `APP_KEY` propia.
- Base PostgreSQL con PostGIS, backups automáticos y usuario con permisos mínimos.
- `QUEUE_CONNECTION=redis` o `database` con un worker supervisado
  (`php artisan queue:work --tries=3`).
- Programador de tareas (`php artisan schedule:run` cada minuto) para:
  `viajes:expirar-ofertas`, `conductores:desconectar-inactivos` y
  `documentos:avisar-vencimientos`.
- Reverb con TLS (WSS) y las claves de producción.
- `php artisan migrate --force` y `php artisan db:seed --class=PermissionSeeder`.
- `php artisan storage:link`; documentos y comprobantes siempre en disco privado.
- Correo real (SMTP o proveedor transaccional) para recuperación, verificación y
  avisos de vencimiento.
- FCM con la cuenta de servicio fuera del repositorio
  (`FCM_CREDENTIALS_PATH`).
- Monitoreo de errores y de la cola; logs sin datos sensibles.
- Exigir 2FA a todos los auditores antes de abrir el panel al público.

## 5. Ficha de tienda y legal

Preparar fuera del código:

- Nombre, descripción corta/larga, categoría y palabras clave.
- Capturas de pantalla para teléfono (y tablet si aplica), en español.
- Ícono 512×512 y gráfico destacado 1024×500 para Google Play.
- Política de privacidad pública: qué datos se recogen (identidad, documentos,
  ubicación, contactos de soporte), para qué, cuánto se conservan y cómo se
  solicitan la eliminación y la corrección.
- Términos de servicio y tratamiento de datos de Venezuela. Hay un borrador en
  `docs/terminos-y-privacidad-borrador.md`, pendiente de revisión legal.
- Declaración de seguridad de datos de Google Play y de privacidad de App Store.
- Cuenta de desarrollador de Google Play y Apple Developer Program.

## 6. QA antes del piloto

- Registro de pasajero y conductor en Android e iOS reales, aceptando términos y privacidad.
- Carga de los seis documentos del conductor y aprobación/rechazo desde el panel.
- Historial del conductor con los filtros Todos, Hoy, Semana y Mes, y carga de más páginas.
- Recarga con comprobante, aprobación y saldo reflejado en la app.
- Solicitud y pago de retiro, con comprobante visible.
- Viaje completo con billetera y con efectivo; cancelaciones y penalización.
- Tracking en vivo, WebSocket caído y recuperación por sondeo.
- Push con app cerrada, en segundo plano y en primer plano (Android e iOS).
- 2FA del auditor: activar, entrar, usar código de recuperación y desactivar.
- Conductores: documentos por vencer, bloqueo por rechazos y saldo negativo.
- Reportes y exportables CSV con acentos y montos.
- Pruebas de carga mínimas: varios viajes simultáneos y dos operadores en el panel.

## 7. Pendientes conocidos de F6

- Publicar la política de privacidad y los términos en un dominio propio.
- Subir la clave APNs y validar push en iOS de producción.
- Crear y resguardar el keystore de Android; configurar Play App Signing.
- Configurar backups, monitoreo y alertas del backend.
- Definir el piloto: ciudad, número de conductores, soporte y horarios.

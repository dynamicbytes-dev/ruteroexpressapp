# GAZ — Checklist de producción

Este documento cubre únicamente el stack de GAZ. El proyecto Supabase es
`gaz-app` (`prmgzklfmsmxobqkkpvc`). No toca el stack de `redup`.

## 1. Variables de entorno

En el servidor:

```bash
cp backend/.env.production.example backend/.env
# Edita backend/.env en el servidor y completa los valores reales.
php artisan key:generate
```

Mínimo obligatorio:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://...`
- `APP_KEY` única de producción
- `LOG_LEVEL=warning` y `LOG_STACK=daily`
- `SESSION_SECURE_COOKIE=true`
- `SESSION_ENCRYPT=true`
- `SESSION_DOMAIN` y `SANCTUM_STATEFUL_DOMAINS` según el dominio real
- `DB_PASSWORD` del rol `gaz_app`
- `MAIL_*` de un proveedor transaccional, no la contraseña personal de Gmail
- `REVERB_HOST`, `REVERB_PORT=443`, `REVERB_SCHEME=https`
- `FCM_CREDENTIALS_PATH` apuntando a un JSON existente y fuera de Git

No uses el `.env` de desarrollo en producción. Ese archivo conserva
`APP_ENV=local`, `APP_DEBUG=true` y una `APP_URL` LAN a propósito.

## 2. Supabase: seguridad y respaldos

La migración `supabase/migrations/20261112220000_harden_public_schema.sql`
revoca el acceso de `anon`, `authenticated` y `service_role` y activa RLS en
todas las tablas de `public`. `gaz_app` es owner y `postgres` tiene
`BYPASSRLS`, por lo que Laravel y el panel no se ven afectados.

Aplicar y verificar:

```bash
supabase db push --remote --dry-run
supabase db push --remote
```

Activar SSL obligatorio:

```bash
supabase ssl-enforcement update --enable-db-ssl-enforcement --experimental --yes
```

Restringir la red **solo cuando exista una CIDR de salida conocida** del
servidor de producción. El comando reemplaza la lista actual:

```bash
supabase network-restrictions update --db-allow-cidr <CIDR> --experimental --yes
```

No pongas una IP dinámica de tu equipo de desarrollo: bloquearías producción.

Backups y PITR no se activan desde el CLI. Hay que hacerlo en el plan/proyecto
de Supabase y **probar una restauración**. Para un respaldo lógico inmediato:

```bash
umask 077
supabase db dump --remote --schema public --file gaz-schema.sql
supabase db dump --remote --data-only --use-copy --schema public --file gaz-data.sql
gzip -9 gaz-schema.sql gaz-data.sql
shasum -a 256 gaz-schema.sql.gz gaz-data.sql.gz
```

Guardar esos archivos fuera del repositorio y en almacenamiento cifrado. No
sustituyen a los backups automáticos/PITR.

## 3. Desplegar el backend

```bash
cd backend
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Procesos permanentes:

- `php artisan queue:work` bajo Supervisor/systemd.
- `php artisan reverb:start --host=0.0.0.0 --port=8080` bajo Supervisor/systemd,
  detrás de un proxy TLS que publique `wss://...`.
- Cron: `* * * * * php artisan schedule:run`.

## 4. Verificación después del despliegue

```bash
php artisan migrate:status
php artisan about
curl -I https://panel.example.com/up
```

Comprobar en el panel:

- Login de auditor y 2FA.
- Carga de un documento privado con URL firmada.
- Un viaje de prueba y una notificación.
- Envío de correo de recuperación.
- Conexión WebSocket en la app móvil.
- Que el Data API de Supabase siga respondiendo 401/404 a `anon`.

## 5. Pendientes que dependen de terceros

- Backups/PITR: requieren plan de Supabase y prueba de restauración.
- Restricciones de red: requieren CIDR de salida del servidor.
- Firma Android/iOS: requieren keystore y equipo de firma.
- Correo: requiere proveedor SMTP con SPF/DKIM/DMARC.
- FCM: requiere el JSON de la cuenta de servicio.

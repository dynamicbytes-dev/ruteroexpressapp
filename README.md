# Rutero Express (GAZ)

Plataforma integral de movilidad y transporte urbano en moto y carro que conecta a pasajeros y conductores en tiempo real, respaldada por un sistema de auditoría y administración web avanzado.

El ecosistema se compone de:
- **Pasajeros y Conductores:** Operan mediante aplicaciones móviles desarrolladas en **Flutter**, disponibles como aplicaciones dedicadas (`gaz_passenger` y `gaz_driver`) o como suite unificada, compartiendo una arquitectura modular limpia (`gaz_core`).
- **Auditores y Administradores:** Gestionan la plataforma desde un **Panel Web en Laravel 12**, encargado de la verificación estricta de documentos, aprobación de recargas y retiros bancarios, definición de tarifas por ciudad/zona y tipo de vehículo, asignación de conductores, gestión de publicidad, resolución de soporte por chat en vivo y auditoría inmutable de cada acción.

---

## Índice

- [Documentación Técnica](#documentación-técnica)
- [Arquitectura del Monorepositorio](#arquitectura-del-monorepositorio)
- [Stack Tecnológico](#stack-tecnológico)
- [Módulos y Funcionalidades](#módulos-y-funcionalidades)
  - [Aplicación de Pasajeros](#aplicación-de-pasajeros)
  - [Aplicación de Conductores](#aplicación-de-conductores)
  - [Panel Web Administrativo y Auditoría](#panel-web-administrativo-y-auditoría)
  - [Motor de Tarifas y Cascada Geoespacial](#motor-de-tarifas-y-cascada-geoespacial)
- [Identidad de Marca](#identidad-de-marca)
- [Puesta en Marcha (Setup)](#puesta-en-marcha-setup)
  - [1. Requisitos](#1-requisitos)
  - [2. Base de Datos (Docker + PostGIS)](#2-base-de-datos-docker--postgis)
  - [3. Backend y Panel Web](#3-backend-y-panel-web)
  - [4. Aplicación Móvil (Flutter)](#4-aplicación-móvil-flutter)
- [Comandos de Consola y Tareas Programadas](#comandos-de-consola-y-tareas-programadas)
- [Pruebas Automatizadas (QA)](#pruebas-automatizadas-qa)
- [Variables de Entorno Clave](#variables-de-entorno-clave)

---

## Documentación Técnica

La carpeta `docs/` contiene las especificaciones detalladas del sistema:

| Documento | Descripción |
|---|---|
| [docs/reglas-negocio.md](docs/reglas-negocio.md) | Reglas de negocio completas: registro, verificación documental, billetera, libro mayor, penalizaciones y asignación |
| [docs/api-app.md](docs/api-app.md) | Contrato completo de la API REST (autenticación, viajes, conductores, billetera, soporte y avisos) |
| [docs/setup.md](docs/setup.md) | Manual detallado de instalación y configuración de desarrollo local |
| [docs/credenciales.md](docs/credenciales.md) | Guía de configuración para Geoapify (mapas/rutas) y Firebase Cloud Messaging (FCM) |
| [docs/publicacion.md](docs/publicacion.md) | Checklist de publicación, firma de APKs/AABs Android, APNs para iOS y CI/CD |
| [design/marca.md](design/marca.md) | Manual de marca: logotipo, isotipo, paleta de colores y lineamientos visuales |

---

## Arquitectura del Monorepositorio

```
ruteroexpress/
├── backend/                       # API RESTful y Panel Web Administrativo (Laravel 12)
│   ├── app/
│   │   ├── Domain/                # Lógica pura del dominio (Trips, Wallet, Verification)
│   │   ├── Http/Controllers/Api/  # Controladores de la API REST para las apps móviles
│   │   ├── Http/Controllers/Panel/# Controladores del panel web del auditor
│   │   └── Models/                # Modelos Eloquent y relaciones
│   ├── config/motoruta.php        # Configuración operativa central de la plataforma
│   ├── database/migrations/       # Migraciones PostgreSQL con extensiones PostGIS
│   └── routes/                    # Rutas de API, Web, Consola y Canales WebSocket
│
├── mobile/                        # Ecosistema móvil desarrollado en Flutter
│   ├── gaz_core/                  # Paquete compartido: modelos, repositorios, API client y temas
│   ├── gaz_passenger/             # App independiente para Pasajeros (GAZ)
│   ├── gaz_driver/                # App independiente para Conductores (GAZ Driver)
│   ├── lib/                       # Aplicación unificada original (conmutación por rol)
│   └── test/                      # Suite de pruebas unitarias y de widgets
│
├── docker/                        # Scripts de inicialización de la base de datos
├── docker-compose.yml             # Contenedor PostgreSQL 17 + PostGIS
├── design/                        # Recursos de marca (SVGs vectoriales, preview HTML y PNGs)
└── docs/                          # Documentación arquitectónica y operacional
```

---

## Stack Tecnológico

### Backend & API
- **Framework:** Laravel 12 (PHP 8.2+)
- **Base de Datos:** PostgreSQL 17 con extensión **PostGIS 3.5** para cálculo geoespacial (`ST_DWithin`, proyecciones geográficas).
- **Autenticación API:** Laravel Sanctum (tokens Bearer revocables por dispositivo).
- **Autenticación Web:** Sesiones seguras con Laravel Breeze.
- **Tiempo Real:** Laravel Reverb (servidor de WebSockets de alto rendimiento nativo).
- **Notificaciones Push:** Firebase Cloud Messaging (FCM HTTP v1).
- **Auditoría & Permisos:** `spatie/laravel-activitylog` para trazabilidad inmutable y `spatie/laravel-permission` para roles finos.
- **Seguridad 2FA:** Google2FA (TOTP) con códigos QR y códigos de recuperación uniuso para auditores.
- **Mapas y Ruteo:** OpenStreetMap (Nominatim) y Geoapify Routing API (con fallback de cálculo local).

### Aplicación Móvil
- **Framework:** Flutter 3.22+ / Dart 3.4+
- **Gestión de Estado:** Provider (arquitectura reactiva desacoplada).
- **Cliente HTTP:** Dio (con interceptores, reintentos y reporte de progreso de carga en tiempo real).
- **Almacenamiento Seguro:** `flutter_secure_storage` (Keystore en Android / Keychain en iOS).
- **Mapas:** `flutter_map` + `latlong2` (OpenStreetMap público y tiles de Geoapify; sin dependencias nativas pesadas).
- **Geolocalización:** `geolocator` con seguimiento en primer plano y latidos de posición periódicos.
- **Tiempo Real:** `web_socket_channel` conectado al protocolo Reverb con reconexión exponencial.

---

## Módulos y Funcionalidades

### Aplicación de Pasajeros
- **Registro y Perfil:** Validación obligatoria de cuentas Gmail, selección de ciudad/zona operativa y cédula de identidad protegida (inmutable por el usuario).
- **Cotización y Pedido:** Selección interactiva de punto de partida y destino en el mapa; cotización congelada al ordenar; selector de **Moto** o **Carro** (el carro aplica recargo configurable, por defecto +$1.00).
- **Seguimiento en Vivo:** Visualización del conductor aproximándose en el mapa en tiempo real vía WebSockets, estados del viaje (`arriving`, `waiting`, `in_progress`, `completed`).
- **Conductores Favoritos:** Capacidad de marcar conductores de confianza para que el algoritmo intente asignarles el viaje prioritariamente antes de la cascada general.
- **Calificaciones:** Sistema de 1 a 5 estrellas con comentarios al culminar el trayecto.
- **Billetera Digital:** Consulta de saldo disponible y retenido en USD, cálculo referencial en Bolívares (Bs) según tasa oficial del BCV, recargas mediante transferencia bancaria nacional con subida de comprobante, e historial de transacciones.
- **Publicidad:** Carrusel interactivo de banners promocionales gestionados desde el panel central.
- **Navegación Moderna:** Barra de navegación flotante integrada y soporte para viajes agendados.
- **Soporte:** Chat directo con los auditores en tiempo real.

### Aplicación de Conductores
- **Verificación Documental:** Carga guiada de fotos de cédula, licencia de conducir, carnet de circulación, RCV y foto del vehículo, con fechas de vencimiento y alertas tempranas (a los 30, 15 y 5 días antes de expirar).
- **Gestión de Disponibilidad:** Conexión/desconexión con validación de saldo positivo o dentro del límite negativo permitido, y transmisión periódica de coordenadas (latido cada 60 s).
- **Recepción de Viajes:** Alertas de ofertas con cuenta regresiva sonora y visual; visualización de distancia y ganancia neta.
- **Flujo de Viaje:** Botones de avance de fase (Llegué al origen → Esperando pasajero → Iniciar viaje → Finalizar viaje).
- **Billetera y Retiros:** Cobro automático de viajes (o cobro en efectivo con débito automático de la comisión de la plataforma), solicitud de retiros a cuentas bancarias nacionales con retención inmediata y consulta de ganancias acumuladas.
- **Mis Calificaciones:** Consulta de promedio de reputación y reseñas recibidas por parte de los usuarios.

### Panel Web Administrativo y Auditoría
- **Dashboard Operativo:** Indicadores clave en tiempo real: viajes en curso, usuarios activos, recargas por verificar, retiros solicitados y documentos por vencer.
- **Cola de Verificación:** Aprobación/rechazo de documentos con motivo explícito, inspección de archivos servidos mediante URLs firmadas de corta duración (5 min) y suspensión preventiva de cuentas.
- **Gestión de Billetera:** Aprobación de recargas con validación de referencias bancarias antifraude, procesamiento de solicitudes de retiro con comprobante de pago bancario y ajustes manuales de saldo auditados.
- **Control de Conductores:** Listado categorizado (todos, en línea, bloqueados por rechazo reiterado, con saldo negativo o inhabilitados), historial de actividad y reasignación de ciudad/zona.
- **Gestión de Banners y Publicidad:** Creación, programación y priorización de anuncios publicitarios para la app móvil.
- **Tasa de Cambio:** Carga y actualización de la tasa USD/VES, con histórico inalterable y comando de sincronización con el Banco Central de Venezuela.
- **Configuración de Tarifas:** Configuración granular de tarifa base, costo por kilómetro, costo por minuto, tarifa mínima, multiplicador dinámico y recargo nocturno por cada ciudad y vehículo.
- **Chat de Soporte Centralizado:** Atención simultánea de consultas de soporte con historial completo y cierre definitivo de conversaciones.
- **Auditoría y Reportes:** Log detallado de todas las operaciones realizadas por los auditores (cambios de estado, ajustes financieros, modificaciones de datos) con exportación a CSV.

### Motor de Tarifas y Cascada Geoespacial
1. **Cotización Congelada:** Al solicitar el viaje se calculan la distancia y duración sobre la red vial con Geoapify (o estimación geodésica con factor de curvatura de 1.25), congelando el precio antes de ofertar.
2. **Priorización de Favorito:** Si el usuario tiene conductores favoritos en línea dentro del radio de búsqueda (ej. 8 km), se les ofrece primero la carrera.
3. **Cascada Radial:** Si no hay favorito disponible, el motor PostGIS (`ST_DWithin`) busca al conductor más cercano que cumpla: misma zona, mismo tipo de vehículo, cuenta aprobada, sin suspensión ni penalización, y saldo dentro de los límites. Se expande progresivamente en radios de 3 km → 5 km → 8 km.
4. **Penalización por Rechazo:** Conductores que rechazan o dejan vencer 3 ofertas en menos de 60 minutos son bloqueados automáticamente por 1 hora.

---

## Identidad de Marca

La identidad visual **GAZ / Rutero Express** está construida bajo los siguientes parámetros:

- **Isotipo / Insignia:** Rectángulo con esquinas redondeadas en color morado oscuro (`#310F38`).
- **Wordmark:** Tipografía geométrica en minúsculas basada en *Sora Bold 700*. Las letras `ga` en color blanco puro (`#FFFFFF`) y la `z` en acento cian brillante (`#00F9FF`).
- **Paleta de Colores:**
  - `brand-800` (`#310F38`): Superficies principales, tarjetas y fondos de insignias.
  - `brand-900` (`#230A29`): Barras laterales y navegación.
  - `brand-950` (`#16051B`): Fondos profundos y modales.
  - `accent-400` (`#00F9FF`): Color de acción, botones destacados, acento dinámico y letra `z`.
  - `white` (`#FFFFFF`): Texto de alto contraste sobre fondos oscuros.

---

## Puesta en Marcha (Setup)

### 1. Requisitos
- **PHP:** 8.2 o superior con extensiones `pdo_pgsql`, `mbstring`, `bcmath`, `curl`, `openssl`.
- **Composer:** 2.x.
- **Node.js:** 20.x o superior + npm.
- **Docker Desktop:** En ejecución (para el contenedor de PostGIS).
- **Flutter SDK:** 3.22.x (Dart 3.4.x).
- **JDK:** OpenJDK 17 (requerido para compatibilidad con Gradle en compilaciones Android).

### 2. Base de Datos (Docker + PostGIS)

Levanta el contenedor de PostgreSQL con PostGIS configurado en el puerto `5433` (para evitar colisiones con instancias locales estándar en el `5432`):

```bash
docker compose up -d
docker compose ps
```

### 3. Backend y Panel Web

```bash
cd backend

# 1. Instalar dependencias PHP y Node
composer install
npm install

# 2. Configurar entorno
cp .env.example .env
php artisan key:generate

# 3. Ejecutar migraciones y datos iniciales (ciudades, tarifas, auditor base)
php artisan migrate --seed

# 4. Crear enlaces simbólicos de almacenamiento
php artisan storage:link

# 5. Iniciar servidores de desarrollo
# Terminal A: Servidor HTTP
php artisan serve --port=8000

# Terminal B: WebSockets Reverb
php artisan reverb:start --debug

# Terminal C: Procesador de colas (eventos y notificaciones)
php artisan queue:work

# Terminal D: Vite (compilación de assets del panel)
npm run dev
```

El panel de auditoría queda disponible en: **http://localhost:8000/panel**

> **Nota sobre credenciales:** El seeder genera una contraseña segura aleatoria almacenada en `backend/storage/auditor-password.txt` (ignorado por Git) para el correo `auditor@motoruta.app`. Para crear auditores adicionales:
> ```bash
> php artisan motoruta:crear-auditor
> ```

### 4. Aplicación Móvil (Flutter)

Las aplicaciones pueden ejecutarse en modo web (Chrome), emulador de Android o dispositivo físico:

#### Opción A: App de Pasajeros (`gaz_passenger`)
```bash
cd mobile/gaz_passenger
flutter pub get
flutter run -d chrome --web-port=5001
```

#### Opción B: App de Conductores (`gaz_driver`)
```bash
cd mobile/gaz_driver
flutter pub get
flutter run -d chrome --web-port=5002
```

#### Opción C: Suite Unificada (`mobile`)
```bash
cd mobile
flutter pub get
flutter run -d chrome --web-port=5000
```

#### Variables de Compilación Útiles:
```bash
# Apuntar a un servidor backend específico
flutter run --dart-define=API_BASE_URL=http://192.168.1.100:8000

# Integrar clave de Geoapify para mapas vectoriales y rutas
flutter run --dart-define=GEOAPIFY_API_KEY=tu_clave_aqui

# Usar archivo de secretos local (ignorado por git)
flutter run --dart-define-from-file=secrets/reverb.json --dart-define-from-file=secrets/geoapify.json
```

---

## Comandos de Consola y Tareas Programadas

El backend cuenta con comandos Artisan especializados para el funcionamiento autónomo de la plataforma:

| Comando | Función | Frecuencia Recomendada |
|---|---|---|
| `php artisan viajes:expirar-ofertas` | Revisa ofertas de viaje vencidas y avanza la cascada al siguiente conductor | Cada 1 minuto (cron) |
| `php artisan conductores:desconectar-inactivos` | Desconecta a conductores que no han reportado ubicación en los últimos 5 minutos | Cada 5 minutos (cron) |
| `php artisan tasa:actualizar` | Consulta la API oficial del Banco Central de Venezuela y actualiza la tasa del día | 1 vez al día (o manual) |
| `php artisan motoruta:crear-auditor` | Comando interactivo para registrar un nuevo auditor con permisos | Según necesidad |

---

## Pruebas Automatizadas (QA)

El proyecto cuenta con una cobertura integral de pruebas automatizadas que validan reglas de negocio, cálculos monetarios, asignación geoespacial e interfaces:

### Pruebas de Backend (PHPUnit)
Ejecuta más de 326 pruebas funcionales y de integración directamente sobre PostgreSQL/PostGIS:
```bash
cd backend
php artisan test
```

### Pruebas de App Móvil (Flutter Test)
Ejecuta más de 140 pruebas sobre modelos, widgets, navegaciones, billetera y segregación de roles:
```bash
cd mobile
flutter test
flutter analyze
```

Para ejecutar las pruebas del paquete base `gaz_core`:
```bash
cd mobile/gaz_core
flutter test
```

---

## Variables de Entorno Clave

Configurables en `backend/.env`:

```ini
# Base de datos PostGIS
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=motoruta
DB_USERNAME=motoruta
DB_PASSWORD=secret

# Parámetros Operativos
MOTORUTA_ALLOWED_EMAIL_DOMAINS=gmail.com
MOTORUTA_SERVICE_FEE_PERCENT=10.0
MOTORUTA_DRIVER_OFFLINE_MINUTES=5

# Integración Geoapify (Mapas, Geocodificación y Rutas)
GEOAPIFY_API_KEY=tu_api_key_geoapify
GEOAPIFY_ROUTING_MODE=motorcycle

# WebSockets Reverb
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=motoruta-app
REVERB_APP_KEY=motoruta-key
REVERB_APP_SECRET=motoruta-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Tasa de Cambio Externa
BCV_API_URL=https://www.bcv.org.ve/tasas-cambio
BCV_API_TIMEOUT=10
```

---

**Rutero Express (GAZ)** — *Plataforma de Movilidad y Transporte Urbano*

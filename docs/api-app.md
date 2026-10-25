# GAZ — API para la app móvil

Base: `/api`. Todas las respuestas son JSON.

Este documento es el contrato entre el backend y la app (Flutter). Si algo cambia
aquí, hay que avisar antes de tocarlo.

---

## 1. Convenciones

### Autenticación

Token de Sanctum. Se obtiene en `registro` o `login` y viaja en cada petición:

```
Authorization: Bearer 3|xxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

### Formato de respuesta

Éxito:

```json
{
  "message": "Texto listo para mostrar a la persona",
  "data": { }
}
```

Error de validación (`422`):

```json
{
  "message": "El correo ya tiene una cuenta en GAZ. (and 1 more error)",
  "errors": {
    "email": ["Ese correo ya tiene una cuenta en GAZ."]
  }
}
```

Otros errores:

| Código | Cuándo | Qué hacer en la app |
|---|---|---|
| `401` | Token ausente, vencido o revocado | Volver al inicio de sesión |
| `403` | La cuenta no puede hacer esa acción (suspendida, documento ajeno al rol) | Mostrar `message` |
| `404` | Recurso inexistente (tipo de documento inventado) | Error de programación: no debería pasar |
| `422` | Datos inválidos | Pintar `errors` campo por campo |
| `429` | Demasiados intentos | Esperar y reintentar (mostrar `message`) |

**Nunca** se muestra el `message` de un `422` genérico: la app debe usar el mapa
`errors` para marcar los campos.

### Límites de intentos

| Ruta | Límite |
|---|---|
| `GET /api/ciudades` | 60 por minuto por IP |
| `POST /auth/register` | 10 por minuto por IP |
| `POST /auth/login` | 10 por minuto por IP |
| `POST /auth/forgot-password` | 6 por minuto por IP |
| `POST /auth/email/verification-notification` | 6 por minuto por IP |
| `POST /api/soporte/mensajes` | 30 por minuto por cuenta |

---

## 2. Estados de la cuenta

La cuenta nace en **`pending`** y no puede operar hasta que un auditor la apruebe.

| Estado | Etiqueta para mostrar | Puede operar |
|---|---|---|
| `pending` | En espera de verificación | No |
| `approved` | Aprobado | **Sí** |
| `rejected` | Rechazado | No |
| `suspended` | Suspendido | No |
| `blocked` | Bloqueado temporalmente | No |

El campo `user.can_operate` (booleano) ya resuelve todo: la app no tiene que
reinterpretar el estado. Si es `false`, hay que mostrar la pantalla de espera con
`user.status_label` y, si existe, `user.status_reason`.

Cada documento tiene su propio estado: `pending` (en revisión), `approved` o
`rejected` (con `rejection_reason`, el motivo que escribió el auditor).

---

## 3. Registro

### `GET /api/ciudades`

Público, sin token. La app lo llama antes del registro para llenar el selector de
ciudad. Solo devuelve zonas activas.

```json
{
  "data": [
    {
      "id": 2,
      "name": "Barcelona",
      "state": "Anzoátegui",
      "display_name": "Barcelona (Anzoátegui)",
      "lat": 10.1394,
      "lng": -64.7013
    }
  ]
}
```

Ciudades iniciales: Puerto La Cruz, Barcelona, Lechería, Maturín, Margarita,
Carúpano y Cumaná.

### `POST /api/auth/register`

`multipart/form-data`. Devuelve el token de una vez: la persona entra a la app,
ve su estado y sube los documentos que falten sin volver a autenticarse.

**Campos comunes**

| Campo | Tipo | Reglas |
|---|---|---|
| `role` | texto | `passenger` o `driver`. El rol `auditor` no se registra por la app |
| `first_name` | texto | 2–80 caracteres |
| `last_name` | texto | 2–80 caracteres |
| `national_id` | texto | 6–9 dígitos, sin puntos. Único |
| `email` | texto | **Solo Gmail**. Único. Se guarda en minúsculas |
| `phone` | texto | Móvil venezolano: `0412-1234567` o `+58 412 1234567`. Se normaliza a 11 dígitos |
| `address` | texto | 10–255 caracteres |
| `city_id` | entero | Obligatorio. Debe ser una ciudad activa de `GET /api/ciudades` |
| `password` | texto | Mínimo 8, con al menos una letra y un número |
| `password_confirmation` | texto | Igual a `password` |
| `accept_terms` | booleano | Obligatorio. Debe ser `true` |
| `terms_version` | texto | Obligatorio. Debe coincidir con la versión vigente del backend |
| `device_name` | texto | Opcional. Nombre del dispositivo para identificar el token |

**Solo si `role = driver`**

| Campo | Tipo | Reglas |
|---|---|---|
| `vehicle_type` | texto | `moto` o `carro` (obligatorio) |
| `vehicle_brand` | texto | 2–60 |
| `vehicle_model` | texto | 1–60 |
| `vehicle_year` | entero | 1990 – año siguiente al actual |
| `vehicle_plate` | texto | Único. Se guarda en mayúsculas y sin guiones |

**Documentos (opcionales aquí)**

Se pueden mandar en el mismo registro o después, uno por uno:

| Campo | Tipo |
|---|---|
| `documentos[cedula]` | archivo |
| `documentos[selfie]` | archivo |
| `documentos[licencia_conducir]` | archivo (solo conductor) |
| `documentos[carnet_circulacion]` | archivo (solo conductor) |
| `documentos[rcv]` | archivo (solo conductor) |
| `documentos[foto_vehiculo]` | archivo (solo conductor) |
| `documentos_expiracion[licencia_conducir]` | fecha `YYYY-MM-DD` |

Un pasajero que intente mandar `rcv` o `foto_vehiculo` recibe `422` en
`documentos`: el documento no corresponde a su rol.

**Respuesta `201`**

```json
{
  "message": "Tu cuenta fue creada. Está en espera de verificación: ...",
  "data": {
    "token": "3|xxxxxxxx",
    "token_type": "Bearer",
    "user": { }
  }
}
```

---

## 4. Sesión

### `POST /api/auth/login`

| Campo | Tipo |
|---|---|
| `email` | texto |
| `password` | texto |
| `device_name` | opcional |

```json
{ "data": { "token": "3|xxxxxxxx", "token_type": "Bearer", "user": { } } }
```

Una cuenta en espera **sí puede entrar**: la app le muestra su estado. Un auditor
recibe `422` (su acceso es el panel web).

### `POST /api/auth/logout` (requiere token)

Cierra **solo** la sesión de ese dispositivo.

### `GET /api/perfil` (requiere token)

Es la llamada que la app hace al abrir.

```json
{
  "data": {
    "user": {
      "id": 12,
      "role": "driver",
      "role_label": "Conductor",
      "full_name": "Pedro Rojas",
      "national_id": "14988776",
      "email": "pedro.rojas@gmail.com",
      "phone": "04264567890",
      "address": "Calle Sucre, casa 45, Maracay",
      "city": {
        "id": 2,
        "name": "Barcelona",
        "state": "Anzoátegui",
        "display_name": "Barcelona (Anzoátegui)"
      },
      "status": "pending",
      "status_label": "En espera de verificación",
      "status_reason": null,
      "can_operate": false,
      "photo_url": "http://127.0.0.1:8000/api/perfil/foto/33?user=12&expires=...&signature=...",
      "email_verified": false,
      "submitted_at": "2026-10-18T23:30:12-04:00",
      "approved_at": null,
      "created_at": "2026-10-18T23:12:02-04:00",
      "verification": {
        "required": 6,
        "uploaded": 6,
        "approved": 4,
        "pending": 1,
        "rejected": 1,
        "missing_labels": [],
        "items": [
          {
            "type": "cedula",
            "label": "Foto de la cédula",
            "hint": "Ambas caras de la cédula, nítidas y sin reflejos.",
            "uploaded": true,
            "status": "approved",
            "status_label": "Aprobado",
            "rejection_reason": null,
            "uploaded_at": "2026-10-18T23:30:12-04:00",
            "expires_at": null,
            "expired": false
          },
          {
            "type": "foto_vehiculo",
            "label": "Foto del vehículo",
            "hint": "Foto completa del vehículo, con la placa visible.",
            "uploaded": true,
            "status": "approved",
            "status_label": "Aprobado",
            "rejection_reason": null,
            "uploaded_at": "2026-10-18T23:30:12-04:00",
            "expires_at": null,
            "expired": false
          }
        ]
      },
      "driver_profile": {
        "vehicle_type": "moto",
        "vehicle_type_label": "Moto",
        "vehicle_brand": "Bera",
        "vehicle_model": "BR 150",
        "vehicle_year": 2022,
        "vehicle_plate": "AB123CD",
        "vehicle_description": "Moto · Bera BR 150 2022 · AB123CD",
        "is_online": false,
        "city": {
          "id": 2,
          "name": "Barcelona",
          "state": "Anzoátegui"
        }
      }
    },
    "next_step": {
      "key": "waiting_review",
      "message": "Tus documentos están en revisión. Te avisaremos apenas sean aprobados."
    }
  }
}
```

`photo_url` es la URL firmada de la selfie vigente y sirve como foto de perfil.
Dura 60 minutos y está atada a la persona; si aún no hay selfie o el archivo es
un PDF, llega `null` y la app muestra las iniciales. Obtener esa URL no expone
ningún otro documento.

`next_step.key` decide qué pantalla mostrar:

| `key` | Cuándo | Pantalla |
|---|---|---|
| `upload_documents` | Faltan documentos | Lista de documentos con los que faltan |
| `fix_documents` | Hay un documento rechazado | Lista con el motivo a corregir |
| `waiting_review` | Todo enviado, sin decisión | Pantalla "En espera de verificación" |
| `ready` | Aprobada | La app normal (pedir viaje / activarse) |
| `contact_support` | Suspendida o bloqueada | Aviso con el motivo |

En `waiting_review` la app puede refrescar `GET /api/perfil` cada cierto tiempo
(sugerido: al volver a primer plano, y cada 60 s como máximo).

### `PATCH /api/perfil` (requiere token)

Edita los datos personales. La **cédula no se envía ni se puede cambiar**: el backend
responde `422` si llega `national_id`. Para cambiar el correo hay que enviar también la
contraseña actual; el correo nuevo queda sin verificar y se envía un enlace.

```json
{
  "first_name": "Pedro",
  "last_name": "Rojas",
  "phone": "04264567890",
  "address": "Calle Sucre, casa 45, Maracay",
  "email": "pedro.nuevo@gmail.com",
  "current_password": "clave12345"
}
```

`email` y `current_password` son opcionales si el correo no cambia. Respuesta:

```json
{
  "message": "Datos actualizados. Te enviamos un enlace para verificar el correo nuevo.",
  "data": {
    "user": { "...": "igual que GET /api/perfil" },
    "email_verification_sent": true
  }
}
```

### Bandeja de avisos

#### `GET /api/notificaciones` (requiere token)

Devuelve los últimos 50 avisos de la persona, del más reciente al más antiguo, y el
contador de no leídos. Se usa para los documentos por vencer y otros avisos de cuenta;
los viajes y las ofertas también llegan por WebSocket y push.

```json
{
  "data": [
    {
      "id": "9b1f...",
      "type": "documento_por_vencer",
      "title": "Documento por vencer",
      "message": "Tu Licencia de conducir vence en 10 días (29/10/2026). Tenla lista para volver a subirla.",
      "data": {
        "type": "documento_por_vencer",
        "title": "Documento por vencer",
        "message": "Tu Licencia de conducir vence en 10 días (29/10/2026). Tenla lista para volver a subirla.",
        "document_id": 12,
        "document_type": "licencia_conducir",
        "document_label": "Licencia de conducir",
        "days_left": 10,
        "expires_at": "2026-10-29"
      },
      "read_at": null,
      "created_at": "2026-10-19T08:00:00-04:00"
    }
  ],
  "unread_count": 1
}
```

#### `POST /api/notificaciones/{id}/leer` (requiere token)

Marca un aviso propio como leído. Un aviso de otra persona responde `404`.

#### `POST /api/notificaciones/leer-todas` (requiere token)

Marca todos los avisos pendientes como leídos.

La app pide `GET /api/notificaciones` al abrir el inicio y al deslizar para refrescar;
muestra los no leídos en una tarjeta y ofrece "Marcar leídos".

### Equipos para notificaciones push

#### `POST /api/dispositivos` (requiere token)

Registra o actualiza el token de Firebase del equipo. Si el mismo token ya estaba
asociado a otra cuenta (porque otra persona inició sesión en el teléfono), se
reasigna a la cuenta autenticada.

```json
{
  "token": "fcm-token-del-equipo",
  "platform": "android",
  "device_name": "Pixel 7"
}
```

`platform` es `android` o `ios`. Responde `201` la primera vez y `200` cuando
actualiza el registro.

#### `DELETE /api/dispositivos` (requiere token)

Elimina el token del equipo de la cuenta autenticada. La app lo llama antes de
cerrar sesión.

```json
{
  "token": "fcm-token-del-equipo"
}
```

El backend envía push de ofertas (`oferta.viaje`), cambios de viaje
(`viaje.actualizado`) y avisos de la bandeja (`notificacion.creada`). Los envíos
pasan por la cola, así que en desarrollo debe estar corriendo
`php artisan queue:work`.

### Chat de soporte (requiere token)

Disponible para pasajeros y conductores, incluso si la cuenta está en revisión,
suspendida o bloqueada. La auditoría no usa este chat desde la app: atiende las
conversaciones desde `/panel/soporte`.

#### `GET /api/soporte`

Devuelve la conversación abierta de la persona o `null` si no tiene una. No crea
nada; la conversación nace con el primer mensaje.

```json
{
  "data": {
    "id": 12,
    "status": "open",
    "status_label": "Abierta",
    "started_at": "2026-10-21T10:00:00-04:00",
    "last_message_at": "2026-10-21T10:05:00-04:00",
    "closed_at": null,
    "messages": [
      {
        "id": 1,
        "body": "Necesito ayuda con mi recarga.",
        "author": "user",
        "author_label": "Pedro Rojas",
        "from_support": false,
        "read_at": null,
        "created_at": "2026-10-21T10:00:00-04:00"
      },
      {
        "id": 2,
        "body": "Estamos revisando tu caso.",
        "author": "auditor",
        "author_label": "Soporte GAZ",
        "from_support": true,
        "read_at": "2026-10-21T10:06:00-04:00",
        "created_at": "2026-10-21T10:05:00-04:00"
      }
    ]
  }
}
```

Al consultar, los mensajes del equipo quedan marcados como leídos.

#### `POST /api/soporte/mensajes` (requiere token)

```json
{ "message": "Gracias, quedo atento." }
```

Crea la conversación si no hay una abierta y devuelve el hilo completo con la
misma forma de `GET /api/soporte`. El mensaje debe tener entre 1 y 2000
caracteres. La app hace sondeo cada 5 segundos mientras la pantalla está abierta.

El primer mensaje de cada turno avisa a los auditores en la campana del panel.
Si la persona escribe varios mensajes antes de que abran el hilo, se mantiene un
solo aviso sin leer para no llenar la bandeja.

**Cierre por el auditor:** cuando el equipo da la conversación por terminada,
`GET /api/soporte` vuelve a responder `{"data": null}`. La app limpia el hilo y
muestra que puede iniciar una nueva conversación. El historial completo queda en
el panel y no se borra de la base de datos.

---

## 5. Documentos

### `POST /api/documentos/{tipo}` (requiere token)

`multipart/form-data`.

| Parámetro | Valor |
|---|---|
| `{tipo}` | `cedula`, `selfie`, `licencia_conducir`, `carnet_circulacion`, `rcv`, `foto_vehiculo` |
| `archivo` | Archivo |
| `vence_el` | Fecha `YYYY-MM-DD`. **Obligatoria** en `licencia_conducir`, `carnet_circulacion` y `rcv`; posterior a hoy |

Requisitos del archivo:

| Regla | Valor |
|---|---|
| Formatos | `jpg`, `jpeg`, `png`, `webp`, `pdf` |
| Tamaño máximo | 6 MB |

**Reenviar un documento reemplaza el anterior** y lo devuelve a revisión: la app
puede usarlo para corregir un documento rechazado o renovar uno vencido.

Respuesta: igual que `GET /api/perfil`, con un `message` que explica qué sigue
("Te falta subir: Foto selfie.", "Ya enviaste todo: tu cuenta está en espera de
verificación.", etc.).

Notas:

- Un tipo que no corresponde al rol responde `403`.
- Un tipo inexistente responde `404`.
- Los documentos **nunca** se sirven con URL pública: solo el auditor los ve, con
  un enlace firmado que caduca en 5 minutos. La única excepción es la selfie
  vigente, que la app puede mostrar como avatar con la `photo_url` firmada de
  `GET /api/perfil` (60 minutos, atada al dueño y solo para imágenes).
- Si la cuenta está `suspended`, la subida responde `403`.
- Los documentos con vencimiento (licencia, carnet y RCV) reciben un aviso en la
  bandeja a los 30, 15 y 5 días. Cuando vencen, la cuenta vuelve a `pending` con
  `status_reason` explicando qué pasó, el conductor se desconecta y debe reenviar el
  documento para que el auditor pueda aprobar la cuenta de nuevo.
- Un documento vencido no se puede aprobar (`422`/error de validación): primero hay que
  reenviarlo.

---

## 6. Billetera, recargas y retiros

Todas las rutas de esta sección requieren token. El saldo **siempre** lo calcula el
backend; la app no suma ni convierte dinero por su cuenta (solo muestra el
equivalente en bolívares que también devuelve el servidor).

### `GET /api/billetera`

```json
{
  "data": {
    "balance": 62.50,
    "held": 5.00,
    "available": 57.50,
    "currency": "USD",
    "symbol": "$",
    "local_currency": "VES",
    "local_symbol": "Bs",
    "exchange_rate": {
      "rate": 40.0000,
      "effective_date": "2026-10-19",
      "local_equivalent": 2500.00
    },
    "limits": {
      "min_withdrawal_usd": 1.00,
      "negative_cap_usd": -5.00
    },
    "bank": {
      "bank": "Banesco",
      "account": "0134 0000 0000 0000 0000",
      "holder": "GAZ C.A.",
      "rif": "J-000000000",
      "instructions": "Haz la transferencia..."
    },
    "recent_transactions": [
      {
        "id": 31,
        "type": "recharge",
        "type_label": "Recarga",
        "direction": "credit",
        "amount": 100.00,
        "signed_amount": 100.00,
        "balance_after": 100.00,
        "description": "Recarga aprobada · Bs 4.000,00 a tasa 40",
        "created_at": "2026-10-19T12:30:00-04:00"
      }
    ]
  }
}
```

- `balance` es el saldo total; `held` es lo retenido por retiros en proceso.
- `available = balance − held` es lo que se puede usar.
- `exchange_rate` puede ser `null` si el auditor todavía no cargó la tasa.
- `bank` son los datos que la app muestra para hacer la transferencia nacional.

### `GET /api/billetera/movimientos`

Historial paginado (25 por página) del libro mayor, con la misma forma de
movimiento que `recent_transactions` y el paginador de Laravel (`data`, `links`,
`meta`). Los movimientos no se editan: una corrección aparece como `adjustment`.

### `GET /api/recargas`

Lista paginada (20) de las recargas de la persona, de la más reciente a la más
antigua:

```json
{
  "data": [
    {
      "id": 9,
      "amount_bs": 4000.00,
      "exchange_rate": null,
      "amount_usd": null,
      "bank": "Banesco",
      "reference": "123456789",
      "transferred_at": "2026-10-19",
      "status": "pending",
      "status_label": "Por revisar",
      "rejection_reason": null,
      "reviewed_at": null,
      "created_at": "2026-10-19T11:00:00-04:00"
    }
  ]
}
```

Estados: `pending` → `approved` | `rejected`.

### `POST /api/recargas` (multipart/form-data)

| Campo | Valor |
|---|---|
| `amount_bs` | Monto transferido en bolívares (mayor que 0) |
| `bank` | Banco de origen |
| `reference` | Número de referencia (se guarda en mayúsculas) |
| `transferred_at` | Fecha `YYYY-MM-DD`, hoy o antes |
| `comprobante` | JPG, PNG, WEBP o PDF · máximo 6 MB |

Respuesta `201`:

```json
{
  "message": "Recibimos tu recarga. Queda pendiente hasta que el equipo verifique el comprobante.",
  "data": { "recharge": { } }
}
```

- Una cuenta `pending`, `rejected`, `suspended` o `blocked` recibe `403`.
- La tasa **no** se congela al pedir: el auditor la aplica al aprobar. Por eso
  `amount_usd` y `exchange_rate` llegan en `null` mientras está pendiente.
- Si el comprobante falta o es inválido, `422` con el mapa `errors`.

### `GET /api/retiros`

Lista paginada (20) de los retiros del conductor:

```json
{
  "data": [
    {
      "id": 4,
      "amount_usd": 10.00,
      "bank": "Banesco",
      "account_number": "01340000000000000000",
      "account_holder": "Pedro Rojas",
      "account_id": "14988776",
      "status": "pending",
      "status_label": "En proceso",
      "rejection_reason": null,
      "reviewed_at": null,
      "created_at": "2026-10-19T11:00:00-04:00"
    }
  ]
}
```

Estados: `pending` → `paid` | `rejected`.

### `POST /api/retiros`

```json
{
  "amount_usd": 10.00,
  "bank": "Banesco",
  "account_number": "01340000000000000000",
  "account_holder": "Pedro Rojas",
  "account_id": "14988776"
}
```

- Solo conductores con cuenta aprobada.
- El monto se **retiene de inmediato** (`held`) y deja de estar disponible, pero
  no sale del saldo hasta que el auditor marca el pago.
- Si el monto supera lo disponible: `422` con el mensaje del backend.
- Si el retiro se rechaza, el monto vuelve a estar disponible.

### `GET /api/tasa-cambio`

```json
{
  "data": {
    "rate": 40.0000,
    "effective_date": "2026-10-19",
    "updated_at": "2026-10-19T08:00:00-04:00"
  }
}
```

`rate` y `effective_date` son `null` si no hay tasa cargada. La tasa vigente es la
última con fecha de hoy o anterior; el auditor la administra en `/panel/tasa`.

---

## 7. Viajes (fase F3)

Todas las rutas requieren token. El backend calcula la ruta, la tarifa y la
asignación; la app solo muestra y confirma. El conductor recibe las alertas en
vivo por WebSocket y push; `GET /api/conductor/ofertas` y el sondeo quedan como
respaldo si el tiempo real no está disponible.

### `POST /api/viajes/cotizar`

```json
{
  "origin_lat": 10.4961,
  "origin_lng": -66.8517,
  "destination_lat": 10.5003,
  "destination_lng": -66.8428,
  "origin_address": "Av. Francisco de Miranda",
  "destination_address": "Plaza Venezuela",
  "vehicle_type": "moto"
}
```

`vehicle_type` es `moto` (por defecto si se omite, para compatibilidad con versiones
anteriores de la app) o `carro`. El carro suma un recargo fijo de **$1.00** al total.

Respuesta `200`:

```json
{
  "data": {
    "city_id": 2,
    "distance_km": 5.00,
    "duration_minutes": 12,
    "base_fare": 1.00,
    "price_per_km": 0.35,
    "price_per_minute": 0.05,
    "minimum_fare": 1.50,
    "minimum_applied": false,
    "dynamic_multiplier": 1.00,
    "night_surcharge_percent": 0,
    "night": false,
    "subtotal": 3.35,
    "vehicle_type": "moto",
    "vehicle_type_label": "Moto",
    "vehicle_surcharge": 0,
    "total": 3.35,
    "commission_percent": 10,
    "commission_amount": 0.34,
    "driver_earning": 3.01,
    "route_source": "geoapify",
    "exchange_rate": 40.0000,
    "local_total": 134.00
  }
}
```

- `route_source` es `geoapify` si hay `GEOAPIFY_API_KEY`, `google` si solo hay
  `GOOGLE_MAPS_SERVER_KEY`, o `estimada` (distancia en línea recta con factor de
  rodeo y velocidad promedio). La app funciona igual con cualquiera de los tres.
- `night` indica si se aplicó el recargo nocturno; `minimum_applied` si mandó la
  tarifa mínima.
- `local_total` es solo referencia en bolívares; el cobro es en USD.
- La cotización no crea nada ni retiene saldo.
- `base_fare`, `price_per_km`, `price_per_minute`, `minimum_fare`, los recargos y la
  comisión salen de las tarifas de la **ciudad del pasajero**. Cada zona tiene sus
  propios valores; `city_id` viene en la respuesta.

### `POST /api/viajes`

Mismos campos de coordenadas y direcciones que la cotización, más:

| Campo | Valor |
|---|---|
| `payment_method` | `wallet` o `cash` |
| `vehicle_type` | `moto` (por defecto) o `carro` |

La búsqueda solo avisa a conductores del **mismo tipo de vehículo** y de la **misma
ciudad** del pasajero. El tipo y la zona quedan guardados en el viaje: un conductor
nunca recibe viajes de otra zona.

Respuesta `201`:

```json
{
  "message": "Estamos buscando un conductor para tu viaje.",
  "data": { "trip": { } }
}
```

- Si es billetera, el backend **retiene** el total al crear el viaje. Si no hay
  saldo disponible responde `422` y no crea el viaje.
- Si no hay conductores en ningún radio, el viaje queda en `no_driver`, se libera
  la retención y el mensaje invita a reintentar.
- Un pasajero en `pending`, `rejected`, `suspended` o `blocked` recibe `403`.
- Un conductor no puede pedir viajes como pasajero (`403`).

Objeto `trip`:

```json
{
  "id": 12,
  "status": "offered",
  "status_label": "Alerta enviada",
  "city": { "id": 2, "name": "Barcelona", "state": "Anzoátegui" },
  "payment_method": "wallet",
  "payment_method_label": "Billetera",
  "vehicle_type": "moto",
  "vehicle_type_label": "Moto",
  "origin": { "address": "Av. Francisco de Miranda", "lat": 10.4961, "lng": -66.8517 },
  "destination": { "address": "Plaza Venezuela", "lat": 10.5003, "lng": -66.8428 },
  "distance_km": 5.00,
  "duration_minutes": 12,
  "fare": {
    "total": 3.35,
    "commission_amount": 0.34,
    "driver_earning": 3.01,
    "vehicle_surcharge": 0,
    "exchange_rate": 40.0000,
    "local_total": 134.00,
    "breakdown": { }
  },
  "driver": null,
  "passenger": null,
  "current_offer": null,
  "can_cancel": true,
  "requested_at": "2026-10-19T12:00:00-04:00",
  "accepted_at": null,
  "started_at": null,
  "completed_at": null,
  "cancelled_at": null
}
```

- Para el pasajero, `driver` llega **null** mientras el viaje está `offered`;
  solo se muestra cuando el conductor acepta (y en viajes `completed` del historial).
- Para el conductor asignado, `passenger` incluye nombre y teléfono.
- `current_offer` solo aparece para el conductor que tiene la alerta pendiente.
- El objeto `driver` incluye `rating`, `ratings_count`, `trips_completed` e
  `is_favorite` (solo para el pasajero).
- El viaje incluye `rating` (la calificación del pasajero, o `null`) y `can_rate`
  (`true` cuando el pasajero puede calificarlo).
- `GET /api/viajes` usa el mismo objeto `trip` en cada elemento de `data`.

### `GET /api/viajes`

Historial paginado (20) del pasajero o del conductor, del más reciente al más
antiguo. Usa el paginador de Laravel.

Acepta el parámetro opcional `period`:

| Valor | Desde |
|---|---|
| `day` | Hoy a las 00:00 |
| `week` | Lunes de esta semana a las 00:00 |
| `month` | Día 1 del mes actual a las 00:00 |
| sin valor | Todo el historial |

Los límites se calculan en `America/Caracas`. El filtro usa la fecha que muestra
la tarjeta: finalización, cancelación o, si el viaje sigue activo, solicitud.
Un valor distinto devuelve `422`.

La paginación se conserva al filtrar: para pedir la siguiente página se envía
`page=2` junto con el mismo `period`. La respuesta incluye `meta.current_page` y
`meta.last_page`; la app usa esos valores para mostrar “Ver más viajes”.

### `GET /api/viajes/activo`

```json
{ "data": { "trip": null } }
```

Devuelve el viaje activo o `null`. Un viaje está activo en `searching`, `offered`,
`accepted`, `arriving`, `waiting` o `in_progress`.

### `GET /api/viajes/{trip}`

Mismo objeto `trip`. Solo el pasajero del viaje o su conductor asignado pueden
verlo (`403` para el resto).

### `POST /api/viajes/{trip}/cancelar`

```json
{ "reason": "Cambié de planes" }
```

- El pasajero puede cancelar antes de `in_progress`; se libera la retención y el
  viaje queda `cancelled`. No hay penalización en esta versión.
- Si cancela el conductor ya asignado, el viaje vuelve a `searching`, cuenta como
  rechazo y se intenta asignar a otro conductor.
- Un conductor no puede cancelar un viaje `in_progress`.

### `POST /api/viajes/{trip}/calificar` (requiere token)

Calificación del pasajero al conductor, una sola vez y solo con el viaje
`completed`.

```json
{ "rating": 5, "comment": "Muy amable y puntual.", "favorite": true }
```

| Campo | Regla |
|---|---|
| `rating` | Entero de 1 a 5 (obligatorio). |
| `comment` | Texto opcional, máximo 500 caracteres. |
| `favorite` | Opcional (`true` marca al conductor como favorito). |

Devuelve `data.trip` actualizado y `data.rating`. Si el viaje no es del pasajero,
no está completado o ya fue calificado, responde `422` con `message`.

El promedio y la cantidad se actualizan en el perfil del conductor. La app
muestra «Sin calificaciones» cuando todavía no tiene ninguna.

### Conductores favoritos (requiere token, solo pasajeros)

#### `GET /api/conductores-favoritos`

Lista los conductores favoritos del pasajero, con `rating`, `ratings_count`,
`trips_completed`, `vehicle` e `is_online`.

#### `POST /api/conductores-favoritos/{driver}`

Marca al conductor. Solo se permite si ya existe un viaje `completed` entre
ambos; de lo contrario responde `422`.

#### `DELETE /api/conductores-favoritos/{driver}`

Quita al conductor de los favoritos. Es idempotente.

#### `GET /api/conductor/calificaciones` (requiere token, solo conductores)

Últimas calificaciones recibidas (máximo 20) con `rating`, `stars`, `comment` y
`created_at`. Nunca incluye datos del pasajero.

### Prioridad del conductor favorito

Al crear un viaje, antes de la cascada normal (3 → 5 → 8 km) el sistema intenta
una sola vez al conductor favorito del pasajero que esté **en línea, con
ubicación de menos de 2 minutos, aprobado, sin viaje activo, con el mismo tipo de
vehículo y dentro del radio `radio_favorito_km`** (por defecto 8 km). Si no
acepta o rechaza, la cascada continúa normal y no se le vuelve a ofrecer.

`radio_favorito_km = 0` desactiva la prioridad.

### Conductor: conexión y ubicación

#### `GET /api/conductor/estado`

```json
{
  "data": {
    "online": true,
    "available": true,
    "blocked_until": null,
    "blocked_reason": null,
    "rejections_in_window": 0,
    "rejections_before_block": 3,
    "trips_completed": 8,
    "balance": 12.50,
    "available_balance": 12.50,
    "negative_cap": -5.00,
    "location": { "lat": 10.4961, "lng": -66.8517 },
    "location_updated_at": "2026-10-19T12:00:00-04:00"
  }
}
```

`available` es `false` si está desconectado, bloqueado por rechazos, sin ubicación
de los últimos 2 minutos o con saldo por debajo del tope negativo.

#### `PUT /api/conductor/disponibilidad`

```json
{ "online": true }
```

- Para conectarse exige cuenta aprobada, sin bloqueo, saldo dentro del tope y una
  ubicación reportada.
- Al desconectarse se cancelan sus alertas pendientes sin penalización.
- Responde `422` con un mensaje claro si no cumple alguna condición.

#### `POST /api/conductor/ubicacion`

```json
{ "lat": 10.4961, "lng": -66.8517, "bearing": 90, "speed_kmh": 25 }
```

Actualiza la posición PostGIS. La app debe reportar cada pocos segundos mientras
esté en línea; una ubicación de más de 2 minutos no sirve para asignar viajes.

### Conductor: alertas y viaje

#### `GET /api/conductor/ofertas`

Antes de responder, vence las alertas que ya pasaron su tiempo y continúa la
cascada. Devuelve las alertas pendientes del conductor:

```json
{
  "data": [
    {
      "id": 7,
      "status": "pending",
      "distance_to_pickup_km": 1.20,
      "offered_at": "2026-10-19T12:00:00-04:00",
      "expires_at": "2026-10-19T12:00:20-04:00",
      "seconds_left": 18,
      "trip": {
        "id": 12,
        "passenger_name": "Ana Pérez",
        "origin": { "address": "Av. Francisco de Miranda", "lat": 10.4961, "lng": -66.8517 },
        "destination": { "address": "Plaza Venezuela", "lat": 10.5003, "lng": -66.8428 },
        "distance_km": 5.00,
        "duration_minutes": 12,
        "payment_method": "wallet",
        "payment_method_label": "Billetera",
        "total": 3.35,
        "driver_earning": 3.01,
        "commission_amount": 0.34
      }
    }
  ]
}
```

El teléfono del pasajero **no** se incluye hasta aceptar.

#### `POST /api/ofertas/{offer}/aceptar`

Marca la alerta como aceptada, asigna el viaje y responde con el `trip` completo
(incluye los datos del pasajero). Falla con `422` si la alerta venció, ya fue
respondida o el conductor dejó de estar disponible.

#### `POST /api/ofertas/{offer}/rechazar`

Marca la alerta como rechazada, suma un rechazo al conductor y pasa al siguiente
candidato. No responder también cuenta como rechazo cuando vence.

- 3 rechazos dentro de 60 minutos → bloqueo de 1 hora (configurable).
- Aceptar un viaje reinicia el contador.
- El conductor bloqueado deja de estar en línea y no recibe alertas.

#### `GET /api/conductor/viaje-activo`

Igual que `GET /api/viajes/activo`, pero solo para el conductor.

#### Transiciones del viaje

| Ruta | Desde → hacia |
|---|---|
| `POST /api/viajes/{trip}/llegada` | `accepted` → `arriving` |
| `POST /api/viajes/{trip}/esperando` | `arriving` → `waiting` |
| `POST /api/viajes/{trip}/iniciar` | `waiting` → `in_progress` |
| `POST /api/viajes/{trip}/finalizar` | `in_progress` → `completed` |

Solo el conductor asignado puede llamarlas. Al finalizar:

- **Billetera:** se consumen la retención y el total del pasajero; el conductor
  recibe su ganancia (`total − comisión`).
- **Efectivo:** el pasajero paga fuera de la app y se debita la comisión del saldo
  del conductor. Puede quedar negativo hasta el tope; si lo supera, se desconecta
  y no puede volver a activarse hasta recargar.

### Geocodificación (Geoapify u OpenStreetMap)

#### `GET /api/ubicaciones/buscar?q=Plaza%20Venezuela`

```json
{
  "data": {
    "available": true,
    "results": [
      { "address": "Plaza Venezuela, Caracas, Venezuela", "lat": 10.5003, "lng": -66.8428 }
    ]
  }
}
```

#### `GET /api/ubicaciones/reversa?lat=10.5003&lng=-66.8428`

Devuelve `result` con la dirección formateada o `null`. El backend usa Geoapify si
hay `GEOAPIFY_API_KEY`; si no, Nominatim (OpenStreetMap). Solo devuelve resultados de
Venezuela y en español. Si los dos servicios fallan, `results`/`result` vienen vacíos;
la app debe permitir escribir la dirección a mano o tocar el mapa.

### Uso desde la app (F3)

- El pasajero cotiza antes de pedir y el backend devuelve la tarifa congelada; la app no
  calcula precios.
- El seguimiento consulta `GET /api/viajes/{trip}` cada 4 segundos mientras el viaje esté
  activo. En F4 se reemplaza por WebSockets.
- El conductor consulta `GET /api/conductor/estado` y `GET /api/conductor/ofertas` cada 5
  segundos mientras está en línea; la alerta muestra la cuenta regresiva de
  `seconds_left`.
- La ruta es `route_source = geoapify` con clave de Geoapify, `google` con clave de
  Google, o `estimada` sin ninguna. La app dibuja el mapa con OpenStreetMap (o Geoapify
  si se compila con `--dart-define=GEOAPIFY_API_KEY`) y permite fijar el destino
  tocando el mapa.
- La app obedece `can_cancel`, `seconds_left`, `status` y los mensajes del backend; no
  deduce reglas de negocio por su cuenta.

---

## 8. Correo

### `POST /api/auth/forgot-password`

```json
{ "email": "pedro.rojas@gmail.com" }
```

Responde siempre `200` con el mismo mensaje, exista o no la cuenta. El correo lleva
un enlace a una **página web** (`/restablecer/...`), no a la app: así funciona igual
si la persona abre el correo en la computadora. Después de cambiar la contraseña,
la app recibe un aviso para volver al teléfono.

Si se pide un enlace dos veces seguidas responde `429`.

### `POST /api/auth/email/verification-notification` (requiere token)

Reenvía el correo de verificación. La app lo ofrece mientras
`user.email_verified` sea `false`. La verificación del correo **no bloquea** la
revisión del auditor: es la vía de contacto para recuperar la contraseña.

---

## 9. Ejemplo del flujo completo

```
1. POST /api/auth/register        (datos del conductor)
   -> 201, token, status = pending, uploaded = 0

2. POST /api/documentos/cedula    (multipart: archivo)
3. POST /api/documentos/selfie
4. POST /api/documentos/licencia_conducir  (+ vence_el)
5. POST /api/documentos/carnet_circulacion (+ vence_el)
6. POST /api/documentos/rcv                (+ vence_el)
7. POST /api/documentos/foto_vehiculo      (foto del vehículo con la placa visible)
   -> tras el último: "Ya enviaste todo: tu cuenta está en espera de verificación."

8. GET  /api/perfil               -> next_step.key = "waiting_review"

   (el auditor revisa y aprueba; puede rechazar un documento con un motivo)

9. GET  /api/perfil               -> next_step.key = "ready", can_operate = true
```

---

## 10. Lo que todavía no existe

Las rutas de F4 ya están documentadas arriba. Las siguientes fases (reportes
avanzados, QA y publicación en tiendas) todavía no agregan rutas nuevas para la
app. Cuando lo hagan, se documentan en este mismo archivo.

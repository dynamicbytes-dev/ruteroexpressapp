# GAZ 2027 · Prototipo visual

Prototipo navegable del rediseño **Soft Spatial** de GAZ Pasajero, GAZ Driver y el panel administrativo. Sirve para aprobar jerarquía, densidad, navegación, modo oscuro e interacciones antes de traducir los cambios a Flutter y Tailwind.

La paleta de marca no cambia: se mantienen el morado `#310F38`, el cian `#00F9FF` y los neutros `ink`. El cambio está en las superficies, el espaciado, la tipografía y el uso del cian como señal.

## Cómo abrirlo

No necesita build ni dependencias. Abre cualquiera de los dos archivos en el navegador:

```bash
open design/prototipo-2027/movil.html
open design/prototipo-2027/panel.html
```

Si tu navegador bloquea fuentes locales por `file://`, sirve la carpeta:

```bash
cd design/prototipo-2027
python3 -m http.server 8080
```

Luego visita:

- `http://localhost:8080/movil.html`
- `http://localhost:8080/panel.html`

El prototipo es autocontenido: fuentes y logos viven en `assets/`.

## Enlaces directos

La navegación usa hash, así que puedes abrir una pantalla concreta:

### Móvil

| Hash | Pantalla |
|---|---|
| `#inicio` | Inicio pasajero |
| `#pedir` | Pedir viaje |
| `#viaje` | Viaje activo |
| `#historial` | Historial del pasajero |
| `#billetera` | Billetera |
| `#perfil` | Perfil del pasajero |
| `#documentos` | Documentos / verificación |
| `#driver-inicio` | Inicio conductor |
| `#oferta` | Oferta de viaje |
| `#viaje-conductor` | Viaje del conductor |
| `#driver-viajes` | Viajes del conductor |
| `#driver-ganancias` | Ganancias del conductor |
| `#driver-perfil` | Perfil del conductor |
| `#bienvenida` | Bienvenida |
| `#acceso` | Entrar |

### Panel

| Hash | Vista |
|---|---|
| `#dashboard` | Dashboard |
| `#verificacion` | Verificación de cuentas |
| `#detalle` | Detalle del conductor |
| `#recargas` | Recargas y retiros |
| `#soporte` | Soporte |
| `#configuracion` | Tarifas y comisiones |

Ejemplo: `movil.html#driver-ganancias` o `panel.html#soporte`.

## Qué incluye

### Móvil · 15 pantallas

- **Pasajero (7):** Inicio, Pedir viaje, Viaje activo, Historial, Billetera, Perfil y Documentos.
- **Conductor (6):** Inicio, Oferta, Viaje, Viajes, Ganancias y Perfil.
- **Acceso (2):** Bienvenida y Entrar.

Las tres pantallas de conductor para Viajes, Ganancias y Perfil se agregaron para que las cuatro destinaciones de la barra inferior del conductor tengan contenido propio y no reutilicen datos del pasajero.

### Panel · 6 vistas

- Dashboard con bento, gráfico de viajes, estado operativo y últimos registros.
- Verificación de cuentas con tabla, filtros y drawer de revisión.
- Detalle del conductor con documentos, vehículo, resumen y actividad.
- Recargas y retiros con 5 pendientes, filtros y acciones.
- Soporte con bandeja, conversaciones y chat.
- Tarifas y comisiones con cálculo en vivo y barra de guardado.

## Interacciones

### Móvil

- Navegación por barra inferior, panel lateral de revisión y hash.
- **Pasajero:** Inicio · Viajes · **[Pedir]** · Billetera · Perfil.
- **Conductor:** Inicio · Viajes · **[En línea]** · Ganancias · Perfil.
- Flujo de pedir viaje: destino → vehículo → precio → confirmar → viaje activo.
- El botón **Avanzar viaje** del escenario simula las fases del viaje y abre la calificación al completar.
- Recarga con montos rápidos y actualización del saldo.
- Filtros de historial del pasajero y del conductor.
- Hojas inferiores para confirmar, recargar, calificar, ver detalle y subir foto.
- Tema claro/oscuro con preferencia del sistema y toggle persistente.
- Botón **En línea** del conductor para conectarse o salir de línea.
- Rechazar una oferta vuelve al inicio del conductor con confirmación.
- Los controles de demostración del conductor se activan con **Avanzar viaje**.

### Panel

- Sidebar agrupado en Operación, Dinero, Contenido y Sistema.
- Sidebar colapsable; en modo compacto se muestra la insignia GAZ.
- Buscador global con `⌘K` / `Ctrl+K`, filtrado sin acentos y navegación por teclado.
- Tema claro/oscuro persistente.
- Drawer de verificación con aprobar/rechazar y actualización de contadores.
- Recargas con filtros, búsqueda y acciones que actualizan los pendientes.
- Soporte con cambio de conversación, envío de mensajes y respuestas rápidas.
- Tarifas con vista previa en vivo, descartar, restaurar y guardar.
- Atajos de teclado y notificaciones tipo toast.

## Sistema de diseño

### Paleta

No se agregaron tonos nuevos. Los hex existentes se reutilizan con tokens semánticos.

| Token | Hex | Uso |
|---|---|---|
| `brand-800` | `#310F38` | CTA principal claro, marca, superficies de marca |
| `brand-900` | `#230A29` | Fondos profundos, navegación |
| `brand-950` | `#16051B` | Overlays, auth, fondos máximos |
| `accent-400` | `#00F9FF` | Señal, foco, CTA en oscuro, datos activos |
| `accent-700` | `#00787C` | Texto/íconos legibles sobre claro |
| `success` | `#047857` | Estados aprobados |
| `warning` | `#B45309` | Pendientes y revisiones |
| `danger` | `#B91C1C` | Rechazos y errores |

### Semánticos claro/oscuro

| Token | Claro | Oscuro |
|---|---|---|
| `--bg` | `#F6F6F8` | `#0C0D12` |
| `--surface` | `#FFFFFF` | `#16171F` |
| `--surface-alt` | `#FAFAFC` | `#1E1F28` |
| `--border` | `#ECECF1` | `#2A2C3A` |
| `--text` | `#16171F` | `#F6F6F8` |
| `--text-2` | `#53566A` | `#B4B6C4` |
| `--text-3` | `#8B8EA1` | `#8B8EA1` |

En oscuro, el CTA principal pasa a `accent-400` con texto `brand-900`; en claro se mantiene `brand-800`.

### Tipografía

- **Sora 600/700:** títulos, cifras, marca.
- **Inter 400/500/600:** cuerpo, labels, botones.
- Se elimina Poppins de la implementación.

### Medidas

| Elemento | Valor |
|---|---|
| Espaciado | 4, 8, 12, 16, 20, 24, 32, 40, 48, 64 px |
| Radio móvil | 10, 12, 16, 20, 28, 34 px |
| Radio panel | 10, 14, 18, 24 px |
| Botón móvil | 52 px |
| Botón panel | 44 px |
| Barra inferior móvil | 68 px |
| Header panel | 64 px |
| Movimiento | 120 / 200 / 320 ms |

### Superficies y profundidad

- La sombra fuerte se reserva para elementos flotantes: barra inferior, hojas, drawer, paleta de comandos y mapa.
- El vidrio se usa con moderación en topbar, barra inferior y controles sobre mapa.
- El cian no se usa como decoración: marca foco, estado activo, datos en vivo y CTA oscuro.
- Los mapas usan overlays y opacidad porque los tiles vienen de Geoapify/OSM y no se puede asumir un estilo propio.

## Estructura

```
design/prototipo-2027/
├── movil.html     # 15 pantallas, sprite de iconos y shell de revisión
├── movil.css      # shell, teléfono, pantallas y responsive
├── panel.html     # 6 vistas, sidebar, topbar, drawer y paleta
├── panel.css      # shell, tablas, bento, drawer y responsive
├── tokens.css     # paleta, semánticos, tipografía, radios y componentes base
├── app.js         # navegación, tema, hojas y flujos del móvil
├── panel.js       # navegación, tema, ⌘K, drawer, chat y tarifas
├── assets/        # Sora + Inter, logos, insignia
└── README.md
```

## Persistencia del tema

El tema se guarda en `localStorage` con la clave compartida `gaz2027-tema`. Si no hay preferencia guardada, se usa `prefers-color-scheme`.

Esto aplica a `movil.html` y `panel.html` por separado en `file://`; al servirlos desde el mismo origen, comparten la preferencia.

## Limitaciones del prototipo

- Los datos son de demostración; no hay backend ni persistencia de negocio.
- Favoritos, agendados, soporte móvil y varias acciones secundarias muestran un toast de “siguiente iteración”.
- El temporizador de la oferta es visual; el avance se controla con el botón de demostración.
- El mapa es un mock con overlays; no hay tiles reales ni geolocalización.
- El panel no implementa autenticación ni permisos; es una revisión visual y de interacción.

## Estado de implementación

### Fase 1 · completada

- **Tokens Flutter:** `gaz_tokens.dart` con espaciado, radios, alturas, duraciones y tipografía.
- **Tema:** `MotorutaTheme.claro` / `MotorutaTheme.oscuro`, colores semánticos `context.gaz`,
  `ThemeController` persistido con `shared_preferences` (`gaz2027-tema`) y toggle en Inicio.
- **Consolidación:** toda la app vive en `gaz_core`; `mobile/lib/main.dart`,
  `gaz_passenger` y `gaz_driver` son arranques delgados.
- **Tipografía:** Sora + Inter; Poppins se retiró de Flutter, Tailwind y assets.
- **Panel:** tokens Tailwind v3, sidebar agrupado, header de 64 px, `⌘K` y tema
  claro/oscuro persistente con la misma clave.
- **Panel semántico:** las 32 vistas usan `surface`, `line` y `content`; los estados
  conservan sus variantes oscuras y se retiró el puente temporal de `app.css`.
- **Navegación móvil:** barra flotante con acción central elevada. Pasajero:
  Inicio · Viajes · **[Pedir]** · Billetera · Perfil. Conductor:
  Inicio · Viajes · **[En línea / Conectar]** · Ganancias · Perfil. La acción
  central del conductor reutiliza la lógica de conexión y GPS del panel.
- **Tests:** suite movida a `mobile/gaz_core/test`; integración opcional en
  `mobile/test/integracion`; pruebas nuevas de `ThemeController`, `BotonTema` y
  navegación móvil.
- **Panel backend:** 376 pruebas PHP pasan contra PostGIS; se restauró la cola
  de verificación en `/panel/usuarios` y se corrigieron dos aserciones
  desactualizadas.

### Fase 2 · siguiente

1. **Componentes móviles:** botones de 52 px, hojas, estados vacíos y skeletons
   con los tokens nuevos.
2. **Pantallas móviles:** migrar Inicio, Pedir, Viaje, Historial, Billetera, Perfil y
   Documentos; Inicio, Oferta, Viaje, Viajes, Ganancias y Perfil del conductor.
3. **QA visual:** revisar contraste y densidad en 360×640 y 430×932, y en panel
   escritorio/tablet.

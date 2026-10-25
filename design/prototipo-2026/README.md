# GAZ 2026 · Prototipo visual

Prototipo navegable del rediseño compacto de **GAZ Pasajero**. Sirve para aprobar densidad, jerarquía, navegación y modo oscuro antes de traducir los cambios a Flutter.

## Cómo abrirlo

No necesita build ni dependencias. Abre `index.html` en el navegador:

```bash
open design/prototipo-2026/index.html
```

O sirve la carpeta si tu navegador bloquea fuentes locales:

```bash
cd design/prototipo-2026
python3 -m http.server 8080
```

Luego visita `http://localhost:8080`.

## Qué incluye

- **9 pantallas:** Inicio, Pedir viaje, Viaje activo, Historial, Billetera, Perfil, Bienvenida, Acceso y Documentos.
- **Navegación real:** barra inferior, panel lateral y accesos contextuales.
- **Flujo de pedir viaje:** elegir destino → ver ruta y precio → confirmar en bottom sheet → viaje activo.
- **Viaje activo interactivo:** “Simular llegada”, “Iniciar viaje” y “Completar viaje” con timeline y calificación.
- **Billetera:** recarga con montos rápidos y actualización del saldo.
- **Historial:** filtros por período y detalle en bottom sheet.
- **Modo claro/oscuro:** toggle en el panel, en la cabecera del escenario y en Perfil.
- **Atajos:** flechas izquierda/derecha para recorrer pantallas, `Esc` para cerrar hojas.

## Decisiones de diseño

### Densidad

| Elemento | Antes | Ahora |
|---|---:|---:|
| Padding de página | 20 px | 16 px |
| Separación entre tarjetas | 18 px | 12–16 px |
| Padding interno de tarjeta | 20 px | 14–16 px |
| Altura de botón | 54 px | 48 px |
| Altura de barra inferior | 72 px | 64 px |
| Radio de tarjeta | 22 px | 18 px |
| Radio de botón | 16 px | 14 px |

### Tipografía

- **Sora** para títulos, cifras y marca.
- **Inter** para cuerpo, labels y botones.
- Se elimina **Poppins** para tener una sola voz tipográfica.

### Superficies

- Fondo `ink50`, tarjeta blanca con borde de 1 px y sombra mínima.
- La sombra fuerte se reserva para elementos flotantes: barra inferior, hojas, FAB y mapa.
- El morado oscuro se usa en saldo, CTA principal, cabeceras de marca y estados activos.
- El cian es acento, no color de texto sobre blanco. Para texto sobre claro se usa `accent700`.

### Navegación

**Inicio · Viajes · [Pedir] · Billetera · Perfil**

- 4 pestañas + botón central de acción.
- Historial vive dentro de “Viajes” y tiene acceso desde Inicio.
- Agendados queda como acción contextual (hoy muestra un aviso de próxima fase).
- Las pantallas de detalle (viaje, documentos, auth) ocultan la barra inferior.

### UX

- La acción principal del Inicio es pedir un viaje; el saldo y el viaje activo tienen prioridad.
- “Tus datos” sale del Inicio y vive en Perfil.
- El flujo de pedir viaje muestra el precio antes de confirmar.
- El viaje activo usa una hoja inferior con conductor, ETA, timeline y acciones.
- Los estados vacíos y los errores se resuelven con una acción clara.
- Todos los controles mantienen un objetivo táctil de al menos 44 px.

## Archivos

```
design/prototipo-2026/
├── index.html    # pantallas, iconos y estructura
├── estilos.css   # tokens, componentes y responsive
├── app.js        # navegación, tema, hojas y flujos
├── assets/       # logos y fuentes locales (Sora + Inter)
└── README.md
```

El prototipo es autocontenido: los logos y las fuentes viven en `assets/`, así que funciona tanto abriendo `index.html` directamente como sirviéndolo con un servidor local.

## Qué falta para llevarlo a Flutter

1. **Tokens:** crear `gaz_tokens.dart` con espaciado, radios, alturas, duraciones y tipografía.
2. **Tema:** separar `MotorutaTheme.claro` y `MotorutaTheme.oscuro`; persistir `ThemeMode`.
3. **Componentes:** `FilaAccion`, `Tarjeta` con variantes, `BotonPrincipal` de 48 px, `BarraNavegacionFlotante` de 64 px con CTA central, `EstadoVacio`, `Skeleton`.
4. **Pantallas:** Inicio, Pedir, Viaje, Historial, Billetera y Perfil.
5. **Auth y documentos:** hero compacto, formulario en tarjeta, stepper y progreso.
6. **Tests:** actualizar los tests que dependen de las 5 pestañas y de textos del Inicio.

## Nota

Este prototipo no usa datos reales ni se conecta con el backend. Los datos son de demostración y sirven solo para revisar la experiencia visual.

# gaz — Identidad de Marca

## Concepto de Diseño para "gaz"

**gaz** es una marca digital y tecnológica concebida para transmitir cercanía, agilidad y movimiento moderno:

- **Tipografía:** Geométrica, de trazos limpios y gruesos (estilo sans-serif moderno basado en Sora Bold 700 con proporciones circulares puras y cortes limpios). El uso de letras en minúscula (`gaz`) transmite cercanía, agilidad y un toque muy actual, ideal para plataformas tecnológicas móviles.
- **Juego de Color:** El cuerpo principal de la palabra va en un **blanco puro (`#FFFFFF`)** para garantizar legibilidad máxima y solidez, mientras que la letra **"z"** se destaca en **Cian Brillante (`#00F9FF`)** para darle un punto focal enérgico y dinámico.
- **Fondo:** Un **rectángulo con bordes redondeados en Morado Oscuro (`#310F38`)**, que aporta profundidad, contraste y una estética sumamente elegante y prémium.

---

## Archivos de Marca

| Archivo | Formato | Uso |
|---|---|---|
| `logos/gaz-badge.svg` | SVG vectorial | Insignia principal / Isotipo: rectángulo redondeado `#310F38` con "gaz" (`ga` blanco, `z` cian) |
| `logos/gaz-favicon.svg` | SVG vectorial | Favicon web y panel de auditoría (optimizado para 16px - 64px) |
| `logos/gaz-logo-horizontal.svg` | SVG vectorial | Logotipo horizontal sin fondo (texto transparente para cabeceras y barras oscuras) |
| `logos/gaz-logo-pill.svg` | SVG vectorial | Logotipo horizontal en cápsula redondeada morado oscuro (`#310F38`) |
| `logos/gaz-logo-lockup.svg` | SVG vectorial | Lockup combinado: insignia a la izquierda + texto completo |
| `logos/gaz-app-icon-1024.png` | PNG (1024x1024) | Ícono maestro para tiendas de aplicaciones (Google Play / App Store) |
| `logos/preview.html` | HTML / CSS | Hoja interactiva de visualización y verificación de contraste de marca |

> **Nota técnica:** Todos los archivos SVG tienen sus glifos convertidos a vectores precisos (`<path>`), garantizando que se rendericen de forma idéntica en cualquier plataforma, navegador o visor sin depender de la carga externa de fuentes.

---

## Paleta de Color

| Token | Hex | Muestra | Uso |
|---|---|---|---|
| `brand-800` | `#310F38` | `rgb(49, 15, 56)` | **Fondo oficial de la insignia y superficies principales de marca** |
| `brand-900` | `#230A29` | `rgb(35, 10, 41)` | Fondo de navegación, barras laterales y fondos con degradado |
| `brand-950` | `#16051B` | `rgb(22, 5, 27)` | Fondos de pantalla profundos, modales y overlays |
| `accent-400` | `#00F9FF` | `rgb(0, 249, 255)` | **Acento de la letra "z", foco visual, estados positivos y botones de acción** |
| `white` | `#FFFFFF` | `rgb(255, 255, 255)` | **Cuerpo "ga", texto principal sobre fondos oscuros** |
| `accent-100/800` | `#C4FDFF` / `#004C4F` | — | Fondo y texto de etiquetas aprobadas |
| `ink-50` … `ink-950` | Neutros fríos | — | Fondos de contenido, bordes y textos secundarios |

---

## Implementación en Código

### 1. Aplicación Móvil (Flutter)

Ubicada en `mobile/lib/core/ui/marca.dart`:

- **`WordmarkGaz`**: Dibuja el texto "gaz" con la tipografía geométrica (Sora 700), separando automáticamente las letras "ga" en blanco (`#FFFFFF`) y la letra "z" en cian (`#00F9FF`).
- **`IsotipoMarca`**: Dibuja el rectángulo con bordes redondeados en Morado Oscuro (`#310F38`) con el wordmark en su interior y sombra sutil de elevación.
- **`LogotipoMarca`**: Permite desplegar la insignia + el texto estilizado en horizontal con escala flexible.
- **Assets incluidos:**
  - `mobile/assets/brand/gaz-badge.png`
  - `mobile/assets/brand/gaz-wordmark.png`
  - `mobile/assets/brand/gaz-isotipo.png`

### 2. Panel Web (Laravel / Blade)

Ubicado en `backend/resources/views/components/logo.blade.php`:

```html
<!-- Insignia oficial con rectángulo redondeado en morado oscuro -->
<x-logo class="h-10 w-auto" />

<!-- Versión horizontal (solo texto sobre fondo oscuro) -->
<x-logo wordmark class="h-8 w-auto" />

<!-- Versión píldora / cápsula con fondo morado -->
<x-logo pill class="h-10 w-auto" />
```

Favicon web implementado en `backend/public/favicon.svg`.

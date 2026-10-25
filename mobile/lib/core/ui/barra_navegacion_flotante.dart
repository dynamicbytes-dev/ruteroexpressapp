import 'dart:ui';

import 'package:flutter/material.dart';

import '../theme/motoruta_colors.dart';

/// Elemento individual para la [BarraNavegacionFlotante].
class ElementoNavegacion {
  const ElementoNavegacion({
    required this.icono,
    required this.etiqueta,
    this.iconoActivo,
    this.badge,
  });

  /// Icono en estado inactivo.
  final IconData icono;

  /// Icono opcional para cuando la pestaña está seleccionada.
  final IconData? iconoActivo;

  /// Texto inferior con tipografía Poppins.
  final String etiqueta;

  /// Conteo opcional o indicador de notificación.
  final int? badge;
}

/// Barra de navegación inferior flotante con estilo cápsula moderna y translúcida.
///
/// Diseñada con esquinas muy redondeadas (32px), desenfoque de fondo tipo
/// glassmorphism, resalte de la pestaña activa en los colores de la marca
/// (acento cian/magenta) con un contenedor circular suave, y tipografía Poppins.
class BarraNavegacionFlotante extends StatelessWidget {
  const BarraNavegacionFlotante({
    super.key,
    required this.indiceActual,
    required this.alCambiarPestana,
    required this.elementos,
    this.fondoColor,
    this.bordeColor,
    this.colorActivo,
    this.colorInactivo,
    this.margen = const EdgeInsets.fromLTRB(16, 0, 16, 12),
  });

  /// Índice de la pestaña actualmente seleccionada.
  final int indiceActual;

  /// Notifica cuando el usuario toca un elemento.
  final ValueChanged<int> alCambiarPestana;

  /// Lista de pestañas a renderizar de forma equitativa.
  final List<ElementoNavegacion> elementos;

  /// Color de fondo translúcido (por defecto violeta oscuro de marca).
  final Color? fondoColor;

  /// Color del borde sutil luminoso.
  final Color? bordeColor;

  /// Color del icono y texto de la pestaña activa (cian de marca).
  final Color? colorActivo;

  /// Color del icono y texto inactivos.
  final Color? colorInactivo;

  /// Margen externo de la barra respecto a los bordes de la pantalla.
  final EdgeInsetsGeometry margen;

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).padding.bottom;
    final paddingFinal = margen.resolve(Directionality.of(context)).add(
          EdgeInsets.only(bottom: bottomInset > 0 ? bottomInset : 4),
        );

    final fondo = fondoColor ?? MotorutaColors.brand950.withOpacity(0.88);
    final borde = bordeColor ?? Colors.white.withOpacity(0.12);
    final activo = colorActivo ?? MotorutaColors.cian;
    final inactivo = colorInactivo ?? Colors.white.withOpacity(0.55);

    return Padding(
      padding: paddingFinal,
      child: Container(
        height: 72,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(36),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withOpacity(0.28),
              blurRadius: 22,
              offset: const Offset(0, 10),
              spreadRadius: 2,
            ),
            BoxShadow(
              color: MotorutaColors.brand800.withOpacity(0.22),
              blurRadius: 16,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(36),
          child: BackdropFilter(
            filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
            child: Container(
              decoration: BoxDecoration(
                color: fondo,
                borderRadius: BorderRadius.circular(36),
                border: Border.all(color: borde, width: 1.2),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: List<Widget>.generate(elementos.length, (index) {
                  final item = elementos[index];
                  final estaActivo = index == indiceActual;

                  return Expanded(
                    child: _BotonItemNavegacion(
                      elemento: item,
                      estaActivo: estaActivo,
                      colorActivo: activo,
                      colorInactivo: inactivo,
                      alTocar: () => alCambiarPestana(index),
                    ),
                  );
                }),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _BotonItemNavegacion extends StatelessWidget {
  const _BotonItemNavegacion({
    required this.elemento,
    required this.estaActivo,
    required this.colorActivo,
    required this.colorInactivo,
    required this.alTocar,
  });

  final ElementoNavegacion elemento;
  final bool estaActivo;
  final Color colorActivo;
  final Color colorInactivo;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    final icono = (estaActivo && elemento.iconoActivo != null)
        ? elemento.iconoActivo!
        : elemento.icono;

    return Semantics(
      label: elemento.etiqueta,
      selected: estaActivo,
      button: true,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: alTocar,
        child: SizedBox(
          height: 72,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              AnimatedContainer(
                duration: const Duration(milliseconds: 240),
                curve: Curves.easeOutCubic,
                width: 42,
                height: 38,
                decoration: estaActivo
                    ? BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: LinearGradient(
                          colors: <Color>[
                            colorActivo.withOpacity(0.24),
                            MotorutaColors.brand600.withOpacity(0.36),
                          ],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        border: Border.all(
                          color: colorActivo.withOpacity(0.65),
                          width: 1.4,
                        ),
                        boxShadow: <BoxShadow>[
                          BoxShadow(
                            color: colorActivo.withOpacity(0.25),
                            blurRadius: 10,
                            spreadRadius: 1,
                          ),
                        ],
                      )
                    : const BoxDecoration(
                        shape: BoxShape.circle,
                        color: Colors.transparent,
                      ),
                child: Stack(
                  alignment: Alignment.center,
                  clipBehavior: Clip.none,
                  children: <Widget>[
                    Icon(
                      icono,
                      size: estaActivo ? 22 : 21,
                      color: estaActivo ? colorActivo : colorInactivo,
                    ),
                    if (elemento.badge != null && elemento.badge! > 0)
                      Positioned(
                        top: 2,
                        right: 2,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 4,
                            vertical: 1.5,
                          ),
                          decoration: BoxDecoration(
                            color: MotorutaColors.rojo,
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: MotorutaColors.brand950,
                              width: 1,
                            ),
                          ),
                          constraints: const BoxConstraints(
                            minWidth: 15,
                            minHeight: 15,
                          ),
                          child: Text(
                            elemento.badge! > 9 ? '9+' : '${elemento.badge}',
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              fontFamily: 'Poppins',
                              fontSize: 9,
                              fontWeight: FontWeight.w700,
                              color: Colors.white,
                              height: 1,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 2),
              AnimatedDefaultTextStyle(
                duration: const Duration(milliseconds: 200),
                style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 10.5,
                  fontWeight: estaActivo ? FontWeight.w600 : FontWeight.w400,
                  color: estaActivo ? colorActivo : colorInactivo,
                  letterSpacing: -0.1,
                  height: 1.1,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                child: Text(elemento.etiqueta),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

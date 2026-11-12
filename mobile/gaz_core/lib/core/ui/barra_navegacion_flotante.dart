import 'dart:ui';

import 'package:flutter/material.dart';

import '../theme/gaz_colors.dart';
import '../theme/gaz_tokens.dart';

/// Elemento individual para la [BarraNavegacionFlotante].
class ElementoNavegacion {
  const ElementoNavegacion({
    required this.icono,
    required this.etiqueta,
    this.iconoActivo,
    this.badge,
    this.esAccion = false,
  });

  /// Icono en estado inactivo.
  final IconData icono;

  /// Icono opcional para cuando la pestaña está seleccionada.
  final IconData? iconoActivo;

  /// Texto inferior con tipografía Inter.
  final String etiqueta;

  /// Conteo opcional o indicador de notificación.
  final int? badge;

  /// Si es `true`, el elemento se dibuja como la acción central elevada.
  ///
  /// En el pasajero es «Pedir»; en el conductor es «En línea / Conectar».
  final bool esAccion;
}

/// Barra de navegación inferior flotante con estilo cápsula moderna y
/// translúcida.
///
/// La acción central se eleva sobre la cápsula y usa el color de marca. Las
/// pestañas laterales muestran un resaltado suave en el color de acento.
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
    this.margen = const EdgeInsets.fromLTRB(14, 0, 14, 12),
  });

  /// Índice de la pestaña actualmente seleccionada.
  final int indiceActual;

  /// Notifica cuando el usuario toca un elemento.
  final ValueChanged<int> alCambiarPestana;

  /// Lista de pestañas a renderizar de forma equitativa.
  final List<ElementoNavegacion> elementos;

  /// Color de fondo translúcido.
  final Color? fondoColor;

  /// Color del borde sutil luminoso.
  final Color? bordeColor;

  /// Color del icono y texto de la pestaña activa.
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

    final g = context.gaz;
    final oscuro = Theme.of(context).brightness == Brightness.dark;
    final activo = colorActivo ?? (oscuro ? g.accent : g.brand);
    final inactivo = colorInactivo ?? g.text3;
    final fondo = fondoColor ?? g.surface.withOpacity(oscuro ? 0.84 : 0.9);
    final borde = bordeColor ?? g.border.withOpacity(0.72);
    final fondoActivo =
        oscuro ? g.accent.withOpacity(0.12) : g.brand.withOpacity(0.10);
    final ctaFondo = oscuro ? g.accent : g.brand;
    final ctaTexto = oscuro ? g.onAccent : g.onBrand;
    const altura = GazMedidas.barraInferior + 14;

    return Padding(
      padding: paddingFinal,
      child: SizedBox(
        height: altura,
        child: Stack(
          clipBehavior: Clip.none,
          alignment: Alignment.bottomCenter,
          children: <Widget>[
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              height: GazMedidas.barraInferior,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(GazRadios.xl),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: Colors.black.withOpacity(oscuro ? 0.35 : 0.10),
                      blurRadius: 24,
                      offset: const Offset(0, 10),
                      spreadRadius: -4,
                    ),
                  ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(GazRadios.xl),
                  child: BackdropFilter(
                    filter: ImageFilter.blur(sigmaX: 22, sigmaY: 22),
                    child: Container(
                      decoration: BoxDecoration(
                        color: fondo,
                        borderRadius: BorderRadius.circular(GazRadios.xl),
                        border: Border.all(color: borde),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              height: GazMedidas.barraInferior,
              child: Row(
                children: List<Widget>.generate(elementos.length, (index) {
                  final item = elementos[index];
                  final estaActivo = index == indiceActual;
                  final contenido = item.esAccion
                      ? _BotonAccionCentral(
                          elemento: item,
                          estaActivo: estaActivo,
                          colorActivo: activo,
                          colorInactivo: inactivo,
                          colorFondo: ctaFondo,
                          colorTexto: ctaTexto,
                          alTocar: () => alCambiarPestana(index),
                        )
                      : _BotonItemNavegacion(
                          elemento: item,
                          estaActivo: estaActivo,
                          colorActivo: activo,
                          colorInactivo: inactivo,
                          fondoActivo: fondoActivo,
                          alTocar: () => alCambiarPestana(index),
                        );

                  return Expanded(
                    child: item.esAccion
                        ? Transform.translate(
                            offset: const Offset(0, -14),
                            child: contenido,
                          )
                        : contenido,
                  );
                }),
              ),
            ),
          ],
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
    required this.fondoActivo,
    required this.alTocar,
  });

  final ElementoNavegacion elemento;
  final bool estaActivo;
  final Color colorActivo;
  final Color colorInactivo;
  final Color fondoActivo;
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
          height: GazMedidas.barraInferior,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              AnimatedContainer(
                duration: GazMovimiento.rapido,
                curve: GazMovimiento.curva,
                width: 38,
                height: 30,
                decoration: BoxDecoration(
                  color: estaActivo ? fondoActivo : Colors.transparent,
                  borderRadius: BorderRadius.circular(GazRadios.xs),
                ),
                child: Stack(
                  alignment: Alignment.center,
                  clipBehavior: Clip.none,
                  children: <Widget>[
                    Icon(
                      icono,
                      size: 21,
                      color: estaActivo ? colorActivo : colorInactivo,
                    ),
                    if (elemento.badge != null && elemento.badge! > 0)
                      Positioned(
                        top: -2,
                        right: 1,
                        child: _Badge(cantidad: elemento.badge!),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: GazEspaciado.x1),
              AnimatedDefaultTextStyle(
                duration: GazMovimiento.base,
                style: TextStyle(
                  fontFamily: GazTipografia.cuerpo,
                  fontSize: 10,
                  fontWeight: estaActivo ? FontWeight.w600 : FontWeight.w500,
                  color: estaActivo ? colorActivo : colorInactivo,
                  letterSpacing: 0.1,
                  height: 1.1,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                child: Text(elemento.etiqueta),
              ),
              const SizedBox(height: 3),
              AnimatedContainer(
                duration: GazMovimiento.base,
                width: estaActivo ? 3 : 0,
                height: estaActivo ? 3 : 0,
                decoration: BoxDecoration(
                  color: colorActivo,
                  shape: BoxShape.circle,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BotonAccionCentral extends StatelessWidget {
  const _BotonAccionCentral({
    required this.elemento,
    required this.estaActivo,
    required this.colorActivo,
    required this.colorInactivo,
    required this.colorFondo,
    required this.colorTexto,
    required this.alTocar,
  });

  final ElementoNavegacion elemento;
  final bool estaActivo;
  final Color colorActivo;
  final Color colorInactivo;
  final Color colorFondo;
  final Color colorTexto;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    final oscuro = Theme.of(context).brightness == Brightness.dark;

    return Semantics(
      label: elemento.etiqueta,
      selected: estaActivo,
      button: true,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: alTocar,
        child: SizedBox(
          height: GazMedidas.barraInferior,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              AnimatedContainer(
                duration: GazMovimiento.base,
                curve: GazMovimiento.curva,
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  color: colorFondo,
                  borderRadius: BorderRadius.circular(GazRadios.panelLg),
                  border: Border.all(
                    color: Colors.white.withOpacity(oscuro ? 0.08 : 0.16),
                  ),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: colorFondo.withOpacity(oscuro ? 0.38 : 0.32),
                      blurRadius: 24,
                      offset: const Offset(0, 10),
                      spreadRadius: -6,
                    ),
                    BoxShadow(
                      color: Colors.black.withOpacity(oscuro ? 0.4 : 0.14),
                      blurRadius: 6,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Icon(
                  elemento.icono,
                  size: GazMedidas.icono,
                  color: colorTexto,
                ),
              ),
              const SizedBox(height: 2),
              AnimatedDefaultTextStyle(
                duration: GazMovimiento.base,
                style: TextStyle(
                  fontFamily: GazTipografia.cuerpo,
                  fontSize: 10,
                  fontWeight: FontWeight.w600,
                  color: estaActivo ? colorActivo : colorInactivo,
                  letterSpacing: 0.1,
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

class _Badge extends StatelessWidget {
  const _Badge({required this.cantidad});

  final int cantidad;

  @override
  Widget build(BuildContext context) {
    final g = context.gaz;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1.5),
      decoration: BoxDecoration(
        color: g.danger,
        borderRadius: BorderRadius.circular(GazRadios.xs),
        border: Border.all(color: g.surface, width: 1),
      ),
      constraints: const BoxConstraints(minWidth: 15, minHeight: 15),
      child: Text(
        cantidad > 9 ? '9+' : '$cantidad',
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontFamily: GazTipografia.cuerpo,
          fontSize: 9,
          fontWeight: FontWeight.w700,
          color: Colors.white,
          height: 1,
        ),
      ),
    );
  }
}

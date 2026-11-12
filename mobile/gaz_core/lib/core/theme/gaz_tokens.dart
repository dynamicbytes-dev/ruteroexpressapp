import 'package:flutter/material.dart';

/// Tokens de espaciado de GAZ 2027.
///
/// Escala base de 4 px. Se usan en Flutter y son los mismos valores del
/// prototipo (`design/prototipo-2027/tokens.css`).
abstract class GazEspaciado {
  static const double x1 = 4;
  static const double x2 = 8;
  static const double x3 = 12;
  static const double x4 = 16;
  static const double x5 = 20;
  static const double x6 = 24;
  static const double x8 = 32;
  static const double x10 = 40;
  static const double x12 = 48;
  static const double x16 = 64;

  static const double xs = x1;
  static const double sm = x2;
  static const double md = x3;
  static const double lg = x4;
  static const double xl = x5;
  static const double xxl = x6;
  static const double xxxl = x8;

  /// Margen lateral estándar de una pantalla móvil.
  static const EdgeInsets margenPantalla = EdgeInsets.symmetric(horizontal: x5);

  /// Área segura inferior para pantallas con barra flotante.
  static const EdgeInsets rellenoInferiorBarra =
      EdgeInsets.fromLTRB(x5, x5, x5, 110);
}

/// Radios de la app móvil y del panel.
abstract class GazRadios {
  // Móvil.
  static const double xs = 10;
  static const double sm = 12;
  static const double md = 16;
  static const double lg = 20;
  static const double xl = 28;
  static const double xxl = 34;

  // Panel web.
  static const double panelSm = 10;
  static const double panel = 14;
  static const double panelLg = 18;
  static const double panelXl = 24;

  static const BorderRadius bordeXs = BorderRadius.all(Radius.circular(xs));
  static const BorderRadius bordeSm = BorderRadius.all(Radius.circular(sm));
  static const BorderRadius bordeMd = BorderRadius.all(Radius.circular(md));
  static const BorderRadius bordeLg = BorderRadius.all(Radius.circular(lg));
  static const BorderRadius bordeXl = BorderRadius.all(Radius.circular(xl));
  static const BorderRadius bordeXxl = BorderRadius.all(Radius.circular(xxl));
}

/// Alturas y tamaños de controles.
abstract class GazMedidas {
  static const double boton = 52;
  static const double botonPanel = 44;
  static const double barraInferior = 68;
  static const double cabeceraPanel = 64;
  static const double icono = 24;
  static const double iconoSm = 18;
  static const double avatar = 54;
  static const double avatarGrande = 92;
  static const double toqueMinimo = 44;
}

/// Duraciones y curvas de movimiento.
abstract class GazMovimiento {
  static const Duration rapido = Duration(milliseconds: 120);
  static const Duration base = Duration(milliseconds: 200);
  static const Duration lento = Duration(milliseconds: 320);

  static const Curve curva = Curves.easeOutCubic;
  static const Curve curvaEntrada = Curves.easeOutCubic;
  static const Curve curvaSalida = Curves.easeInCubic;
}

/// Familias y rasgos tipográficos.
abstract class GazTipografia {
  static const String display = 'Sora';
  static const String cuerpo = 'Inter';

  static const List<FontFeature> numerosTabulares = <FontFeature>[
    FontFeature.tabularFigures(),
  ];
}

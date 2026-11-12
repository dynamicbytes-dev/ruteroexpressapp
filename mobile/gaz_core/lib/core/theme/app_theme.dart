import 'package:flutter/material.dart';

import 'gaz_colors.dart';
import 'gaz_tokens.dart';
import 'motoruta_colors.dart';

export 'gaz_colors.dart';
export 'gaz_tokens.dart';

/// Tema de GAZ 2027.
///
/// Sora para títulos/cifras e Inter para el resto. Los colores de marca no
/// cambian: `GazColors` traduce la paleta a superficies y texto de claro/oscuro.
abstract class MotorutaTheme {
  static const double radioPequeno = GazRadios.xs;
  static const double radio = GazRadios.md;
  static const double radioGrande = GazRadios.lg;

  /// Título con la tipografía de marca.
  ///
  /// Para textos que deben seguir el tema (claro/oscuro) usa [tituloDe].
  static TextStyle titulo({
    double tamano = 22,
    FontWeight peso = FontWeight.w700,
    Color color = MotorutaColors.brand900,
    double altura = 1.2,
    double letterSpacing = -0.4,
  }) {
    return TextStyle(
      fontFamily: GazTipografia.display,
      fontSize: tamano,
      fontWeight: peso,
      color: color,
      height: altura,
      letterSpacing: letterSpacing,
    );
  }

  /// Título que toma el color de texto del tema actual.
  static TextStyle tituloDe(
    BuildContext context, {
    double tamano = 22,
    FontWeight peso = FontWeight.w700,
    Color? color,
    double altura = 1.2,
    double letterSpacing = -0.4,
  }) {
    return titulo(
      tamano: tamano,
      peso: peso,
      color: color ?? Theme.of(context).colorScheme.onSurface,
      altura: altura,
      letterSpacing: letterSpacing,
    );
  }

  /// Texto de marca sobre fondo oscuro con alto contraste optimizado.
  static TextStyle tituloClaro({
    double tamano = 28,
    FontWeight peso = FontWeight.w700,
    Color color = Colors.white,
    double letterSpacing = -0.6,
  }) {
    return titulo(
      tamano: tamano,
      peso: peso,
      color: color,
      altura: 1.15,
      letterSpacing: letterSpacing,
    );
  }

  /// Subtítulo sobre fondo oscuro (87% blanco para lectura óptima sin fatiga).
  static const TextStyle subtituloClaro = TextStyle(
    fontFamily: GazTipografia.cuerpo,
    fontSize: 15,
    color: Color(0xDEFFFFFF),
    height: 1.45,
    fontWeight: FontWeight.normal,
  );

  /// Texto secundario sobre fondo oscuro (70% blanco).
  static const TextStyle cuerpoClaro = TextStyle(
    fontFamily: GazTipografia.cuerpo,
    fontSize: 14,
    color: Color(0xB3FFFFFF),
    height: 1.45,
    fontWeight: FontWeight.normal,
  );

  /// Etiqueta sutil sobre fondo oscuro (60% blanco).
  static const TextStyle etiquetaClaro = TextStyle(
    fontFamily: GazTipografia.cuerpo,
    fontSize: 12.5,
    color: Color(0x99FFFFFF),
    fontWeight: FontWeight.w500,
  );

  /// Sombra suave con tinte morado profundo.
  static const List<BoxShadow> sombraTarjeta = <BoxShadow>[
    BoxShadow(
      color: Color(0x0616051B),
      blurRadius: 8,
      offset: Offset(0, 2),
    ),
    BoxShadow(
      color: Color(0x1016051B),
      blurRadius: 24,
      offset: Offset(0, 10),
      spreadRadius: -4,
    ),
  ];

  /// Sombra elevada para elementos flotantes.
  static const List<BoxShadow> sombraFlotante = <BoxShadow>[
    BoxShadow(
      color: Color(0x0E16051B),
      blurRadius: 12,
      offset: Offset(0, 4),
    ),
    BoxShadow(
      color: Color(0x1E16051B),
      blurRadius: 32,
      offset: Offset(0, 14),
      spreadRadius: -6,
    ),
  ];

  /// Sombra para superficies en oscuro (más negra y contenida).
  static const List<BoxShadow> sombraTarjetaOscura = <BoxShadow>[
    BoxShadow(
      color: Color(0x33000000),
      blurRadius: 10,
      offset: Offset(0, 2),
    ),
    BoxShadow(
      color: Color(0x40000000),
      blurRadius: 26,
      offset: Offset(0, 12),
      spreadRadius: -6,
    ),
  ];

  static List<BoxShadow> sombraTarjetaPara(Brightness brightness) {
    return brightness == Brightness.dark ? sombraTarjetaOscura : sombraTarjeta;
  }

  static ThemeData get claro => _construir(Brightness.light);

  static ThemeData get oscuro => _construir(Brightness.dark);

  static ThemeData _construir(Brightness brightness) {
    final bool oscuro = brightness == Brightness.dark;
    final GazColors gaz = GazColors.para(brightness);

    final ColorScheme esquema = ColorScheme(
      brightness: brightness,
      primary: oscuro ? MotorutaColors.accent400 : MotorutaColors.brand800,
      onPrimary: oscuro ? MotorutaColors.brand900 : Colors.white,
      primaryContainer:
          oscuro ? MotorutaColors.brand800 : MotorutaColors.brand100,
      onPrimaryContainer:
          oscuro ? MotorutaColors.accent100 : MotorutaColors.brand900,
      secondary: oscuro ? MotorutaColors.accent300 : MotorutaColors.accent600,
      onSecondary: oscuro ? MotorutaColors.brand900 : Colors.white,
      secondaryContainer:
          oscuro ? MotorutaColors.brand800 : MotorutaColors.accent100,
      onSecondaryContainer:
          oscuro ? MotorutaColors.accent100 : MotorutaColors.accent900,
      error: gaz.danger,
      onError: Colors.white,
      errorContainer: gaz.dangerBg,
      onErrorContainer: oscuro ? const Color(0xFFFCA5A5) : MotorutaColors.rojo,
      surface: gaz.surface,
      onSurface: gaz.text,
      surfaceContainerHighest: gaz.surfaceAlt,
      onSurfaceVariant: gaz.text2,
      outline: gaz.borderStrong,
      outlineVariant: gaz.border,
      shadow: const Color(0x33310F38),
    );

    final ThemeData base = ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: esquema,
      scaffoldBackgroundColor: gaz.bg,
      canvasColor: gaz.surface,
      fontFamily: GazTipografia.cuerpo,
      splashFactory: InkSparkle.splashFactory,
      extensions: <ThemeExtension<dynamic>>[gaz],
    );

    final TextTheme textoBase = base.textTheme.apply(
      fontFamily: GazTipografia.cuerpo,
      bodyColor: gaz.text,
      displayColor: gaz.text,
    );

    final TextTheme texto = textoBase.copyWith(
      headlineLarge: titulo(
        tamano: 30,
        peso: FontWeight.w800,
        color: gaz.text,
        letterSpacing: -0.8,
      ),
      headlineMedium: titulo(
        tamano: 26,
        peso: FontWeight.w700,
        color: gaz.text,
        letterSpacing: -0.6,
      ),
      titleLarge: titulo(
        tamano: 22,
        peso: FontWeight.w700,
        color: gaz.text,
        letterSpacing: -0.4,
      ),
      titleMedium: titulo(
        tamano: 18,
        peso: FontWeight.w700,
        color: gaz.text,
        letterSpacing: -0.3,
      ),
      titleSmall: titulo(
        tamano: 15,
        peso: FontWeight.w600,
        color: gaz.text,
        letterSpacing: -0.2,
      ),
      bodyLarge: textoBase.bodyLarge?.copyWith(
        fontSize: 16,
        height: 1.5,
        color: gaz.text,
      ),
      bodyMedium: textoBase.bodyMedium?.copyWith(
        fontSize: 14.5,
        height: 1.45,
        color: gaz.text2,
      ),
      bodySmall: textoBase.bodySmall?.copyWith(
        fontSize: 13,
        height: 1.4,
        color: gaz.text2,
      ),
      labelLarge: const TextStyle(
        fontFamily: GazTipografia.cuerpo,
        fontSize: 15,
        fontWeight: FontWeight.w600,
        letterSpacing: -0.1,
      ),
      labelMedium: TextStyle(
        fontFamily: GazTipografia.cuerpo,
        fontSize: 13,
        fontWeight: FontWeight.w500,
        color: gaz.text2,
      ),
      labelSmall: TextStyle(
        fontFamily: GazTipografia.cuerpo,
        fontSize: 11.5,
        fontWeight: FontWeight.w500,
        color: gaz.text3,
      ),
    );

    return base.copyWith(
      textTheme: texto,
      appBarTheme: AppBarTheme(
        backgroundColor: gaz.surface,
        surfaceTintColor: Colors.transparent,
        foregroundColor: gaz.text,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        titleTextStyle:
            titulo(tamano: 19, peso: FontWeight.w700, color: gaz.text),
        iconTheme: IconThemeData(color: gaz.text2),
      ),
      iconTheme: IconThemeData(color: gaz.text2),
      cardTheme: CardTheme(
        color: gaz.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radioGrande),
          side: BorderSide(color: gaz.border, width: 1),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: gaz.surfaceAlt,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        hintStyle: TextStyle(
          color: gaz.text3,
          fontSize: 14.5,
          fontWeight: FontWeight.w400,
        ),
        labelStyle: TextStyle(
          color: gaz.text2,
          fontSize: 13.5,
          fontWeight: FontWeight.w500,
        ),
        floatingLabelStyle: TextStyle(
          color: esquema.primary,
          fontSize: 13.5,
          fontWeight: FontWeight.w600,
        ),
        errorStyle: TextStyle(
          color: esquema.error,
          fontSize: 12.5,
          fontWeight: FontWeight.w500,
          height: 1.3,
        ),
        border: borde(color: gaz.borderStrong),
        enabledBorder: borde(color: gaz.borderStrong),
        focusedBorder: borde(color: esquema.primary, grosor: 1.8),
        errorBorder: borde(color: esquema.error),
        focusedErrorBorder: borde(color: esquema.error, grosor: 1.8),
        disabledBorder: borde(color: gaz.border),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: esquema.primary,
          foregroundColor: esquema.onPrimary,
          disabledBackgroundColor: gaz.border,
          disabledForegroundColor: gaz.text3,
          minimumSize: const Size.fromHeight(GazMedidas.boton),
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radio),
          ),
          textStyle: const TextStyle(
            fontFamily: GazTipografia.cuerpo,
            fontSize: 16,
            fontWeight: FontWeight.w700,
            letterSpacing: -0.2,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: esquema.primary,
          minimumSize: const Size.fromHeight(GazMedidas.boton),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          side: BorderSide(color: gaz.borderStrong, width: 1.2),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radio),
          ),
          textStyle: const TextStyle(
            fontFamily: GazTipografia.cuerpo,
            fontSize: 16,
            fontWeight: FontWeight.w600,
            letterSpacing: -0.2,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: esquema.primary,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radioPequeno),
          ),
          textStyle: const TextStyle(
            fontFamily: GazTipografia.cuerpo,
            fontSize: 15,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: oscuro ? gaz.surfaceAlt : MotorutaColors.ink900,
        contentTextStyle: TextStyle(
          fontFamily: GazTipografia.cuerpo,
          fontSize: 14.5,
          color: oscuro ? gaz.text : Colors.white,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radio),
        ),
      ),
      dividerTheme: DividerThemeData(
        color: gaz.border,
        thickness: 1,
        space: 1,
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: esquema.primary,
        linearTrackColor: gaz.border,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: gaz.surface,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
      ),
      dialogTheme: DialogTheme(
        backgroundColor: gaz.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radioGrande),
        ),
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: esquema.primary,
        foregroundColor: esquema.onPrimary,
      ),
      listTileTheme: ListTileThemeData(
        textColor: gaz.text,
        iconColor: gaz.text2,
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith<Color?>((estados) {
          return estados.contains(WidgetState.selected)
              ? esquema.onPrimary
              : gaz.text3;
        }),
        trackColor: WidgetStateProperty.resolveWith<Color?>((estados) {
          return estados.contains(WidgetState.selected)
              ? esquema.primary
              : gaz.border;
        }),
      ),
    );
  }

  static OutlineInputBorder borde({
    Color color = MotorutaColors.ink200,
    double grosor = 1,
  }) {
    return OutlineInputBorder(
      borderRadius: BorderRadius.circular(radio),
      borderSide: BorderSide(color: color, width: grosor),
    );
  }
}

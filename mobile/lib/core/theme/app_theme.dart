import 'package:flutter/material.dart';

import 'motoruta_colors.dart';

/// Tema de la app: mismo lenguaje visual que el panel del auditor.
///
/// Tipografía: Sora para títulos, Inter para el resto. Radios de 14/18 y
/// bordes suaves, igual que las tarjetas del panel.
abstract class MotorutaTheme {
  static const double radioPequeno = 10;
  static const double radio = 16;
  static const double radioGrande = 22;

  /// Título con la tipografía de marca.
  /// Título con la tipografía de marca y peso visual jerárquico.
  static TextStyle titulo({
    double tamano = 22,
    FontWeight peso = FontWeight.w700,
    Color color = MotorutaColors.brand900,
    double altura = 1.2,
    double letterSpacing = -0.4,
  }) {
    return TextStyle(
      fontFamily: 'Sora',
      fontSize: tamano,
      fontWeight: peso,
      color: color,
      height: altura,
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
    fontFamily: 'Inter',
    fontSize: 15,
    color: Color(0xDEFFFFFF),
    height: 1.45,
    fontWeight: FontWeight.normal,
  );

  /// Texto secundario sobre fondo oscuro (70% blanco).
  static const TextStyle cuerpoClaro = TextStyle(
    fontFamily: 'Inter',
    fontSize: 14,
    color: Color(0xB3FFFFFF),
    height: 1.45,
    fontWeight: FontWeight.normal,
  );

  /// Etiqueta sutil sobre fondo oscuro (60% blanco).
  static const TextStyle etiquetaClaro = TextStyle(
    fontFamily: 'Inter',
    fontSize: 12.5,
    color: Color(0x99FFFFFF),
    fontWeight: FontWeight.w500,
  );

  /// Sombra suave con tinte morado profundo, difusa y elegante.
  static const List<BoxShadow> sombraTarjeta = [
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

  /// Sombra elevada para elementos flotantes o destacados.
  static const List<BoxShadow> sombraFlotante = [
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

  static ThemeData get claro {
    const esquema = ColorScheme(
      brightness: Brightness.light,
      primary: MotorutaColors.brand800,
      onPrimary: Colors.white,
      primaryContainer: MotorutaColors.brand100,
      onPrimaryContainer: MotorutaColors.brand900,
      secondary: MotorutaColors.accent600,
      onSecondary: Colors.white,
      secondaryContainer: MotorutaColors.accent100,
      onSecondaryContainer: MotorutaColors.accent900,
      error: MotorutaColors.rojo,
      onError: Colors.white,
      errorContainer: MotorutaColors.rojoFondo,
      onErrorContainer: MotorutaColors.rojo,
      surface: Colors.white,
      onSurface: MotorutaColors.ink800,
      surfaceContainerHighest: MotorutaColors.ink100,
      onSurfaceVariant: MotorutaColors.ink600,
      outline: MotorutaColors.ink200,
      outlineVariant: MotorutaColors.ink100,
      shadow: Color(0x33310F38),
    );

    final base = ThemeData(
      useMaterial3: true,
      colorScheme: esquema,
      scaffoldBackgroundColor: MotorutaColors.ink50,
      fontFamily: 'Inter',
      splashFactory: InkSparkle.splashFactory,
    );

    final texto = base.textTheme.apply(
      fontFamily: 'Inter',
      bodyColor: MotorutaColors.ink800,
      displayColor: MotorutaColors.brand900,
    );

    return base.copyWith(
      textTheme: texto.copyWith(
        headlineLarge: titulo(tamano: 30, peso: FontWeight.w800, letterSpacing: -0.8),
        headlineMedium: titulo(tamano: 26, peso: FontWeight.w700, letterSpacing: -0.6),
        titleLarge: titulo(tamano: 22, peso: FontWeight.w700, letterSpacing: -0.4),
        titleMedium: titulo(tamano: 18, peso: FontWeight.w700, letterSpacing: -0.3),
        titleSmall: titulo(tamano: 15, peso: FontWeight.w600, letterSpacing: -0.2),
        bodyLarge: texto.bodyLarge?.copyWith(fontSize: 16, height: 1.5, color: MotorutaColors.ink800),
        bodyMedium: texto.bodyMedium?.copyWith(fontSize: 14.5, height: 1.45, color: MotorutaColors.ink700),
        bodySmall: texto.bodySmall?.copyWith(fontSize: 13, height: 1.4, color: MotorutaColors.ink500),
        labelLarge: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 15,
          fontWeight: FontWeight.w600,
          letterSpacing: -0.1,
        ),
        labelMedium: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 13,
          fontWeight: FontWeight.w500,
          color: MotorutaColors.ink500,
        ),
        labelSmall: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 11.5,
          fontWeight: FontWeight.w500,
          color: MotorutaColors.ink400,
        ),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        foregroundColor: MotorutaColors.brand900,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        titleTextStyle: titulo(tamano: 19, peso: FontWeight.w700),
        iconTheme: const IconThemeData(color: MotorutaColors.ink700),
      ),
      cardTheme: CardTheme(
        color: Colors.white,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radioGrande),
          side: const BorderSide(color: MotorutaColors.ink100, width: 1),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
        hintStyle: const TextStyle(
          color: MotorutaColors.ink400,
          fontSize: 14.5,
          fontWeight: FontWeight.w400,
        ),
        labelStyle: const TextStyle(
          color: MotorutaColors.ink500,
          fontSize: 13.5,
          fontWeight: FontWeight.w500,
        ),
        floatingLabelStyle: const TextStyle(
          color: MotorutaColors.brand800,
          fontSize: 13.5,
          fontWeight: FontWeight.w600,
        ),
        errorStyle: const TextStyle(
          color: MotorutaColors.rojo,
          fontSize: 12.5,
          fontWeight: FontWeight.w500,
          height: 1.3,
        ),
        border: borde(),
        enabledBorder: borde(),
        focusedBorder: borde(color: MotorutaColors.brand800, grosor: 1.8),
        errorBorder: borde(color: MotorutaColors.rojo),
        focusedErrorBorder: borde(color: MotorutaColors.rojo, grosor: 1.8),
        disabledBorder: borde(color: MotorutaColors.ink200),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: MotorutaColors.brand800,
          foregroundColor: Colors.white,
          disabledBackgroundColor: MotorutaColors.ink200,
          disabledForegroundColor: MotorutaColors.ink400,
          minimumSize: const Size.fromHeight(54),
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radio),
          ),
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 16,
            fontWeight: FontWeight.w700,
            letterSpacing: -0.2,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: MotorutaColors.brand800,
          minimumSize: const Size.fromHeight(54),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          side: const BorderSide(color: MotorutaColors.ink200, width: 1.2),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radio),
          ),
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 16,
            fontWeight: FontWeight.w600,
            letterSpacing: -0.2,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: MotorutaColors.brand700,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radioPequeno),
          ),
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 15,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: MotorutaColors.ink900,
        contentTextStyle: const TextStyle(fontFamily: 'Inter', fontSize: 14.5),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radio),
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: MotorutaColors.ink100,
        thickness: 1,
        space: 1,
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: MotorutaColors.brand800,
        linearTrackColor: MotorutaColors.ink100,
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
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

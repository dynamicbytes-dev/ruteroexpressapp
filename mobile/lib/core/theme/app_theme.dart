import 'package:flutter/material.dart';

import 'motoruta_colors.dart';

/// Tema de la app: mismo lenguaje visual que el panel del auditor.
///
/// Tipografía: Sora para títulos, Inter para el resto. Radios de 14/18 y
/// bordes suaves, igual que las tarjetas del panel.
abstract class MotorutaTheme {
  static const double radio = 14;
  static const double radioGrande = 18;

  /// Título con la tipografía de marca.
  static TextStyle titulo({
    double tamano = 22,
    FontWeight peso = FontWeight.w600,
    Color color = MotorutaColors.brand900,
    double altura = 1.2,
  }) {
    return TextStyle(
      fontFamily: 'Sora',
      fontSize: tamano,
      fontWeight: peso,
      color: color,
      height: altura,
    );
  }

  /// Texto de marca sobre fondo oscuro.
  static TextStyle tituloClaro({
    double tamano = 28,
    FontWeight peso = FontWeight.w600,
    Color color = Colors.white,
  }) {
    return titulo(tamano: tamano, peso: peso, color: color, altura: 1.15);
  }

  /// Sombra suave con tinte morado, como en el panel.
  static const List<BoxShadow> sombraTarjeta = [
    BoxShadow(
      color: Color(0x0F310F38),
      blurRadius: 2,
      offset: Offset(0, 1),
    ),
    BoxShadow(
      color: Color(0x3D310F38),
      blurRadius: 24,
      offset: Offset(0, 12),
      spreadRadius: -12,
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
        titleLarge: titulo(tamano: 22),
        titleMedium: titulo(tamano: 18),
        titleSmall: titulo(tamano: 16),
        bodyLarge: texto.bodyLarge?.copyWith(height: 1.45),
        bodyMedium: texto.bodyMedium?.copyWith(height: 1.45),
        labelLarge: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 15,
          fontWeight: FontWeight.w600,
        ),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        foregroundColor: MotorutaColors.brand900,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        titleTextStyle: titulo(tamano: 19),
        iconTheme: const IconThemeData(color: MotorutaColors.ink700),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        hintStyle: const TextStyle(color: MotorutaColors.ink400, fontSize: 15),
        labelStyle: const TextStyle(
          color: MotorutaColors.ink600,
          fontSize: 14,
          fontWeight: FontWeight.w500,
        ),
        floatingLabelStyle: const TextStyle(
          color: MotorutaColors.brand700,
          fontSize: 14,
          fontWeight: FontWeight.w500,
        ),
        errorStyle: const TextStyle(
          color: MotorutaColors.rojo,
          fontSize: 12.5,
          height: 1.3,
        ),
        border: borde(),
        enabledBorder: borde(),
        focusedBorder: borde(color: MotorutaColors.brand800, grosor: 1.6),
        errorBorder: borde(color: MotorutaColors.rojo),
        focusedErrorBorder: borde(color: MotorutaColors.rojo, grosor: 1.6),
        disabledBorder: borde(color: MotorutaColors.ink200),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: MotorutaColors.brand800,
          foregroundColor: Colors.white,
          disabledBackgroundColor: MotorutaColors.ink200,
          disabledForegroundColor: MotorutaColors.ink400,
          minimumSize: const Size.fromHeight(54),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radio),
          ),
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: MotorutaColors.brand800,
          minimumSize: const Size.fromHeight(54),
          side: const BorderSide(color: MotorutaColors.ink200),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radio),
          ),
          textStyle: const TextStyle(
            fontFamily: 'Inter',
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: MotorutaColors.brand700,
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
          borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
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

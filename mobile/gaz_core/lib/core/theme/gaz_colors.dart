import 'package:flutter/material.dart';

import 'motoruta_colors.dart';

/// Colores semánticos de GAZ 2027.
///
/// La paleta de marca no cambia (`MotorutaColors`); estos tokens traducen esa
/// paleta a superficies, texto y estados para claro y oscuro.
@immutable
class GazColors extends ThemeExtension<GazColors> {
  const GazColors({
    required this.bg,
    required this.surface,
    required this.surfaceAlt,
    required this.surfaceElevated,
    required this.border,
    required this.borderStrong,
    required this.text,
    required this.text2,
    required this.text3,
    required this.brand,
    required this.onBrand,
    required this.accent,
    required this.onAccent,
    required this.success,
    required this.successBg,
    required this.warning,
    required this.warningBg,
    required this.danger,
    required this.dangerBg,
    required this.overlay,
  });

  final Color bg;
  final Color surface;
  final Color surfaceAlt;
  final Color surfaceElevated;
  final Color border;
  final Color borderStrong;
  final Color text;
  final Color text2;
  final Color text3;
  final Color brand;
  final Color onBrand;
  final Color accent;
  final Color onAccent;
  final Color success;
  final Color successBg;
  final Color warning;
  final Color warningBg;
  final Color danger;
  final Color dangerBg;
  final Color overlay;

  static const GazColors claro = GazColors(
    bg: MotorutaColors.ink50,
    surface: Colors.white,
    surfaceAlt: Color(0xFFFAFAFC),
    surfaceElevated: Colors.white,
    border: MotorutaColors.ink100,
    borderStrong: MotorutaColors.ink200,
    text: MotorutaColors.ink900,
    text2: MotorutaColors.ink600,
    text3: MotorutaColors.ink400,
    brand: MotorutaColors.brand800,
    onBrand: Colors.white,
    accent: MotorutaColors.accent400,
    onAccent: MotorutaColors.brand900,
    success: MotorutaColors.verde,
    successBg: MotorutaColors.verdeFondo,
    warning: MotorutaColors.amarillo,
    warningBg: MotorutaColors.amarilloFondo,
    danger: MotorutaColors.rojo,
    dangerBg: MotorutaColors.rojoFondo,
    overlay: Color(0x99000000),
  );

  static const GazColors oscuro = GazColors(
    bg: MotorutaColors.ink950,
    surface: Color(0xFF16171F),
    surfaceAlt: Color(0xFF1E1F28),
    surfaceElevated: Color(0xFF22232D),
    border: Color(0xFF2A2C3A),
    borderStrong: Color(0xFF3E4052),
    text: MotorutaColors.ink50,
    text2: Color(0xFFB4B6C4),
    text3: MotorutaColors.ink400,
    brand: MotorutaColors.brand100,
    onBrand: MotorutaColors.brand900,
    accent: MotorutaColors.accent400,
    onAccent: MotorutaColors.brand900,
    success: Color(0xFF34D399),
    successBg: Color(0xFF052E22),
    warning: Color(0xFFFBBF24),
    warningBg: Color(0xFF3B2606),
    danger: Color(0xFFF87171),
    dangerBg: Color(0xFF3B0A0A),
    overlay: Color(0xB3000000),
  );

  static GazColors of(BuildContext context) {
    return Theme.of(context).extension<GazColors>() ?? claro;
  }

  static GazColors para(Brightness brightness) {
    return brightness == Brightness.dark ? oscuro : claro;
  }

  @override
  GazColors copyWith({
    Color? bg,
    Color? surface,
    Color? surfaceAlt,
    Color? surfaceElevated,
    Color? border,
    Color? borderStrong,
    Color? text,
    Color? text2,
    Color? text3,
    Color? brand,
    Color? onBrand,
    Color? accent,
    Color? onAccent,
    Color? success,
    Color? successBg,
    Color? warning,
    Color? warningBg,
    Color? danger,
    Color? dangerBg,
    Color? overlay,
  }) {
    return GazColors(
      bg: bg ?? this.bg,
      surface: surface ?? this.surface,
      surfaceAlt: surfaceAlt ?? this.surfaceAlt,
      surfaceElevated: surfaceElevated ?? this.surfaceElevated,
      border: border ?? this.border,
      borderStrong: borderStrong ?? this.borderStrong,
      text: text ?? this.text,
      text2: text2 ?? this.text2,
      text3: text3 ?? this.text3,
      brand: brand ?? this.brand,
      onBrand: onBrand ?? this.onBrand,
      accent: accent ?? this.accent,
      onAccent: onAccent ?? this.onAccent,
      success: success ?? this.success,
      successBg: successBg ?? this.successBg,
      warning: warning ?? this.warning,
      warningBg: warningBg ?? this.warningBg,
      danger: danger ?? this.danger,
      dangerBg: dangerBg ?? this.dangerBg,
      overlay: overlay ?? this.overlay,
    );
  }

  @override
  GazColors lerp(ThemeExtension<GazColors>? other, double t) {
    if (other is! GazColors) return this;

    Color mezcla(Color a, Color b) => Color.lerp(a, b, t) ?? a;

    return GazColors(
      bg: mezcla(bg, other.bg),
      surface: mezcla(surface, other.surface),
      surfaceAlt: mezcla(surfaceAlt, other.surfaceAlt),
      surfaceElevated: mezcla(surfaceElevated, other.surfaceElevated),
      border: mezcla(border, other.border),
      borderStrong: mezcla(borderStrong, other.borderStrong),
      text: mezcla(text, other.text),
      text2: mezcla(text2, other.text2),
      text3: mezcla(text3, other.text3),
      brand: mezcla(brand, other.brand),
      onBrand: mezcla(onBrand, other.onBrand),
      accent: mezcla(accent, other.accent),
      onAccent: mezcla(onAccent, other.onAccent),
      success: mezcla(success, other.success),
      successBg: mezcla(successBg, other.successBg),
      warning: mezcla(warning, other.warning),
      warningBg: mezcla(warningBg, other.warningBg),
      danger: mezcla(danger, other.danger),
      dangerBg: mezcla(dangerBg, other.dangerBg),
      overlay: mezcla(overlay, other.overlay),
    );
  }
}

/// Acceso corto a los colores semánticos desde cualquier `build`.
extension ColoresGazContext on BuildContext {
  GazColors get gaz => GazColors.of(this);
}

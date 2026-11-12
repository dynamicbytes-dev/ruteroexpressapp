import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/core/theme/app_theme.dart';
import 'package:gaz_core/core/theme/motoruta_colors.dart';

void main() {
  group('Tema 2027', () {
    test('claro y oscuro usan los tokens semánticos', () {
      final claro = MotorutaTheme.claro;
      final oscuro = MotorutaTheme.oscuro;

      expect(claro.brightness, Brightness.light);
      expect(oscuro.brightness, Brightness.dark);
      expect(claro.scaffoldBackgroundColor, GazColors.claro.bg);
      expect(oscuro.scaffoldBackgroundColor, GazColors.oscuro.bg);
      expect(oscuro.extension<GazColors>(), GazColors.oscuro);
    });

    test('el CTA oscuro usa cian con texto morado', () {
      final estilo = MotorutaTheme.oscuro.filledButtonTheme.style!;
      final fondo = estilo.backgroundColor?.resolve(<WidgetState>{});
      final texto = estilo.foregroundColor?.resolve(<WidgetState>{});

      expect(fondo, MotorutaColors.accent400);
      expect(texto, MotorutaColors.brand900);
    });
  });
}

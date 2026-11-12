import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/core/theme/theme_controller.dart';

void main() {
  group('ThemeController', () {
    test('sin preferencia arranca en el tema del sistema', () async {
      final almacen = AlmacenTemaMemoria();
      final controlador = await ThemeController.cargar(almacen: almacen);

      expect(controlador.modo, ThemeMode.system);
      expect(controlador.esSistema, isTrue);
      expect(controlador.esOscuro(Brightness.dark), isTrue);
      expect(controlador.esOscuro(Brightness.light), isFalse);
    });

    test('lee una preferencia guardada de claro u oscuro', () async {
      final claro = await ThemeController.cargar(
        almacen: AlmacenTemaMemoria('light'),
      );
      final oscuro = await ThemeController.cargar(
        almacen: AlmacenTemaMemoria('dark'),
      );

      expect(claro.modo, ThemeMode.light);
      expect(oscuro.modo, ThemeMode.dark);
      expect(oscuro.esOscuro(Brightness.light), isTrue);
    });

    test('alternar guarda el modo contrario al brillo actual', () async {
      final almacen = AlmacenTemaMemoria('light');
      final controlador = await ThemeController.cargar(almacen: almacen);

      await controlador.alternar(Brightness.light);
      expect(controlador.modo, ThemeMode.dark);
      expect(almacen.valor, 'dark');

      await controlador.alternar(Brightness.dark);
      expect(controlador.modo, ThemeMode.light);
      expect(almacen.valor, 'light');
    });

    test('usarSistema borra la preferencia persistida', () async {
      final almacen = AlmacenTemaMemoria('dark');
      final controlador = await ThemeController.cargar(almacen: almacen);

      await controlador.usarSistema();

      expect(controlador.modo, ThemeMode.system);
      expect(almacen.valor, isNull);
    });
  });
}

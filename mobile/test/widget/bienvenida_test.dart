import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/theme/app_theme.dart';
import 'package:motoruta/core/ui/marca.dart';
import 'package:motoruta/features/auth/screens/bienvenida_screen.dart';

void main() {
  testWidgets('la bienvenida muestra la marca y las dos puertas',
      (tester) async {
    await tester.pumpWidget(
      MaterialApp(theme: MotorutaTheme.claro, home: const BienvenidaScreen()),
    );
    await tester.pumpAndSettle();

    expect(find.byType(IsotipoMarca), findsOneWidget);
    expect(find.byType(WordmarkGaz), findsWidgets);
    expect(find.text('Crear mi cuenta'), findsOneWidget);
    expect(find.text('Ya tengo cuenta'), findsOneWidget);
  });

  testWidgets('"Crear mi cuenta" lleva a elegir el rol', (tester) async {
    await tester.pumpWidget(
      MaterialApp(theme: MotorutaTheme.claro, home: const BienvenidaScreen()),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Crear mi cuenta'));
    await tester.pumpAndSettle();

    expect(find.text('¿Cómo vas a usar GAZ?'), findsOneWidget);
    expect(find.text('Pasajero'), findsOneWidget);
    expect(find.text('Conductor'), findsOneWidget);
  });

  testWidgets('"Ya tengo cuenta" lleva al acceso', (tester) async {
    await tester.pumpWidget(
      MaterialApp(theme: MotorutaTheme.claro, home: const BienvenidaScreen()),
    );
    await tester.pumpAndSettle();

    // El botón queda abajo del pliegue: primero se desplaza hasta él.
    await tester.ensureVisible(find.text('Ya tengo cuenta'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Ya tengo cuenta'));
    await tester.pumpAndSettle();

    expect(find.text('Entrar'), findsWidgets);
    expect(find.text('¿Olvidaste tu contraseña?'), findsOneWidget);
  });
}

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/core/theme/app_theme.dart';
import 'package:gaz_core/core/theme/theme_controller.dart';
import 'package:gaz_core/core/ui/boton_tema.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('BotonTema encuentra el controlador y alterna el modo', (
    tester,
  ) async {
    final controlador = ThemeController.memoria();

    await tester.pumpWidget(
      ChangeNotifierProvider<ThemeController>.value(
        value: controlador,
        child: AnimatedBuilder(
          animation: controlador,
          builder: (context, _) {
            return MaterialApp(
              theme: MotorutaTheme.claro,
              darkTheme: MotorutaTheme.oscuro,
              themeMode: controlador.modo,
              home: Scaffold(
                appBar: AppBar(actions: const <Widget>[BotonTema()]),
              ),
            );
          },
        ),
      ),
    );

    expect(find.byIcon(Icons.dark_mode_rounded), findsOneWidget);

    await tester.tap(find.byType(IconButton));
    await tester.pumpAndSettle();

    expect(controlador.modo, ThemeMode.dark);
    expect(find.byIcon(Icons.light_mode_rounded), findsOneWidget);
  });

  testWidgets('BotonTema no rompe pantallas sin raíz de tema', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(appBar: AppBar(actions: const <Widget>[BotonTema()])),
      ),
    );

    expect(find.byType(IconButton), findsNothing);
  });
}

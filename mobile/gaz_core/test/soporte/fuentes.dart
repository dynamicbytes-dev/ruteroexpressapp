import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

/// Carga las tipografías de la app en las pruebas.
///
/// Sin esto, los tests dibujan todo con la tipografía de prueba (Ahem), que es
/// mucho más ancha que Inter: aparecen "desbordes" que en el teléfono no
/// existen. Con las fuentes reales, la revisión de maquetación es fiel.
Future<void> cargarFuentesDeMarca() async {
  for (final familia in <String, List<String>>{
    'Inter': <String>['inter-400.ttf', 'inter-500.ttf', 'inter-600.ttf'],
    'Sora': <String>['sora-400.ttf', 'sora-500.ttf', 'sora-600.ttf'],
  }.entries) {
    final cargador = FontLoader(familia.key);
    for (final archivo in familia.value) {
      cargador.addFont(rootBundle.load('assets/fonts/$archivo'));
    }
    await cargador.load();
  }
}

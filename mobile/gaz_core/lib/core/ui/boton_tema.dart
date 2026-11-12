import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../theme/theme_controller.dart';

/// Botón de la barra superior para alternar claro/oscuro.
///
/// El `ThemeController` vive en la raíz de `GazApp`, así que cualquier pantalla
/// puede cambiar el tema. La preferencia se persiste con `shared_preferences`.
class BotonTema extends StatelessWidget {
  const BotonTema({super.key});

  @override
  Widget build(BuildContext context) {
    // En tests y previews que montan una pantalla suelta no hay raíz de tema.
    final controlador = context.watch<ThemeController?>();
    if (controlador == null) return const SizedBox.shrink();

    final oscuro = Theme.of(context).brightness == Brightness.dark;
    final siguiente = oscuro ? 'claro' : 'oscuro';

    return IconButton(
      tooltip: 'Cambiar a modo $siguiente',
      onPressed: () => controlador.alternar(Theme.of(context).brightness),
      icon: Icon(
        oscuro ? Icons.light_mode_rounded : Icons.dark_mode_rounded,
      ),
    );
  }
}

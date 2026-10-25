import 'package:flutter/material.dart';

import '../theme/motoruta_colors.dart';

/// Muestra un mensaje corto abajo de la pantalla.
///
/// Con [error] se pinta en rojo, para los rechazos del backend.
void mostrarMensaje(BuildContext context, String texto, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Text(texto),
        backgroundColor: error ? MotorutaColors.rojo : MotorutaColors.ink900,
      ),
    );
}

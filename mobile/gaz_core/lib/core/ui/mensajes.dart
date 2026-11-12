import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../theme/motoruta_colors.dart';

/// Muestra un mensaje corto abajo de la pantalla.
///
/// Con [error] se pinta en rojo, para los rechazos del backend. Sin error usa
/// el `SnackBarTheme` del tema (claro u oscuro).
void mostrarMensaje(BuildContext context, String texto, {bool error = false}) {
  final oscuro = Theme.of(context).brightness == Brightness.dark;
  final gaz = context.gaz;

  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Text(
          texto,
          style: TextStyle(
            color: error
                ? (oscuro ? gaz.danger : Colors.white)
                : Theme.of(context).snackBarTheme.contentTextStyle?.color,
          ),
        ),
        backgroundColor:
            error ? (oscuro ? gaz.dangerBg : MotorutaColors.rojo) : null,
      ),
    );
}

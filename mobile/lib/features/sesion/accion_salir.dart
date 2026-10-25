import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'sesion_controller.dart';

/// Botón de cerrar sesión con confirmación.
///
/// Se usa en las pantallas que ya están dentro de la app (documentos, espera,
/// inicio), para que salir nunca sea un toque accidental.
class AccionSalir extends StatelessWidget {
  const AccionSalir({super.key, this.claro = false});

  final bool claro;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: 'Cerrar sesión',
      color: claro ? Colors.white : null,
      icon: const Icon(Icons.logout_rounded),
      onPressed: () => _confirmar(context),
    );
  }

  Future<void> _confirmar(BuildContext context) async {
    final sesion = context.read<SesionController>();

    final confirmado = await showDialog<bool>(
      context: context,
      builder: (contexto) => AlertDialog(
        title: const Text('Cerrar sesión'),
        content: const Text('¿Quieres salir de GAZ en este dispositivo?'),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(contexto).pop(false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(contexto).pop(true),
            child: const Text('Cerrar sesión'),
          ),
        ],
      ),
    );

    if (confirmado == true) {
      await sesion.salir();
    }
  }
}

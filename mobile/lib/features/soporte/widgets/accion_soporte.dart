import 'package:flutter/material.dart';

import '../screens/soporte_chat_screen.dart';

/// Acceso al chat de soporte desde la barra superior de una pantalla.
///
/// Se usa en las etapas de documentos y espera para que cualquier pasajero o
/// conductor pueda pedir ayuda sin tener que buscar un correo de contacto.
class AccionSoporte extends StatelessWidget {
  const AccionSoporte({super.key});

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: 'Soporte',
      onPressed: () => Navigator.of(context).push<void>(
        MaterialPageRoute<void>(
          builder: (_) => const SoporteChatScreen(),
        ),
      ),
      icon: const Icon(Icons.support_agent_rounded),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../sesion/accion_salir.dart';
import '../../sesion/sesion_controller.dart';
import '../../soporte/screens/soporte_chat_screen.dart';

/// Pantalla para cuentas suspendidas o bloqueadas (`contact_support`).
///
/// No se ofrece nada más que el motivo y la salida: la persona tiene que
/// hablar con el equipo para revisar su caso.
class SoporteScreen extends StatelessWidget {
  const SoporteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final usuario = context.watch<SesionController>().usuario;

    if (usuario == null) {
      return const SizedBox.shrink();
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tu cuenta'),
        actions: const <Widget>[AccionSalir()],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
        children: <Widget>[
          Tarjeta(
            child: Column(
              children: <Widget>[
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: const BoxDecoration(
                    color: MotorutaColors.rojoFondo,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.gpp_maybe_outlined,
                    size: 34,
                    color: MotorutaColors.rojo,
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  usuario.estadoEtiqueta.isEmpty
                      ? 'Tu cuenta no está operativa'
                      : usuario.estadoEtiqueta,
                  textAlign: TextAlign.center,
                  style: MotorutaTheme.titulo(tamano: 22),
                ),
                const SizedBox(height: 10),
                Text(
                  usuario.motivoEstado ??
                      'Tu cuenta no puede operar en este momento. Escríbenos para revisar tu caso.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 14.5,
                    color: MotorutaColors.ink600,
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          const Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Aviso(
                  tono: Tono.neutro,
                  icono: Icons.support_agent_rounded,
                  titulo: 'Cómo seguir',
                  texto:
                      'Usa el chat de soporte e indica tu cédula. El equipo de GAZ revisará tu caso y te responderá por aquí.',
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          BotonPrincipal(
            texto: 'Abrir chat de soporte',
            icono: Icons.chat_bubble_outline_rounded,
            alPresionar: () => Navigator.of(context).push<void>(
              MaterialPageRoute<void>(
                builder: (_) => const SoporteChatScreen(),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

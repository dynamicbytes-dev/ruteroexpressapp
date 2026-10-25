import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/app/gaz_app.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/marca.dart';
import 'registro_screen.dart';

/// Elección de rol: pasajero o conductor.
///
/// En las apps divididas (GAZ y GAZ Driver) el rol viene fijo desde
/// `GazRolScope` y esta pantalla pasa directo al formulario correspondiente.
class ElegirRolScreen extends StatelessWidget {
  const ElegirRolScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final rolFijo = GazRolScope.maybeOf(context);

    if (rolFijo != null) {
      return RegistroScreen(rol: rolFijo);
    }

    return PantallaMarca(
      mostrarRetroceso: true,
      hijos: <Widget>[
        const SizedBox(height: 8),
        Text(
          '¿Cómo vas a usar GAZ?',
          style: MotorutaTheme.tituloClaro(tamano: 30),
        ),
        const SizedBox(height: 10),
        const Text(
          'Elige una opción. Después podrás crear otra cuenta con el otro rol si lo necesitas.',
          style: MotorutaTheme.subtituloClaro,
        ),
        const SizedBox(height: 28),
        _OpcionRol(
          icono: Icons.person_outline_rounded,
          titulo: 'Pasajero',
          descripcion: 'Pides viajes y pagas con saldo o en efectivo.',
          etiqueta: '2 documentos',
          alTocar: () => _abrir(context, 'passenger'),
        ),
        const SizedBox(height: 16),
        _OpcionRol(
          icono: Icons.garage_outlined,
          titulo: 'Conductor',
          descripcion: 'Recibes viajes cerca de ti y cobras por tus carreras.',
          etiqueta: '6 documentos + tu vehículo',
          alTocar: () => _abrir(context, 'driver'),
        ),
      ],
    );
  }

  void _abrir(BuildContext context, String rol) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => RegistroScreen(rol: rol)),
    );
  }
}

class _OpcionRol extends StatelessWidget {
  const _OpcionRol({
    required this.icono,
    required this.titulo,
    required this.descripcion,
    required this.etiqueta,
    required this.alTocar,
  });

  final IconData icono;
  final String titulo;
  final String descripcion;
  final String etiqueta;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(MotorutaTheme.radioGrande),
        border: Border.all(color: MotorutaColors.ink100, width: 1.2),
        boxShadow: MotorutaTheme.sombraTarjeta,
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(MotorutaTheme.radioGrande),
        child: InkWell(
          borderRadius: BorderRadius.circular(MotorutaTheme.radioGrande),
          onTap: () {
            HapticFeedback.lightImpact();
            alTocar();
          },
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Row(
              children: <Widget>[
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: MotorutaColors.brand50,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(icono, size: 28, color: MotorutaColors.brand800),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(titulo,
                          style: MotorutaTheme.titulo(
                              tamano: 19, peso: FontWeight.w700)),
                      const SizedBox(height: 4),
                      Text(
                        descripcion,
                        style: const TextStyle(
                          fontSize: 14,
                          color: MotorutaColors.ink600,
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        etiqueta,
                        style: const TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w600,
                          color: MotorutaColors.accent700,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded,
                    color: MotorutaColors.ink400),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

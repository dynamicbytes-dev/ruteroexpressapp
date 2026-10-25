import 'package:flutter/material.dart';

import '../../../core/app/gaz_app.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/botones.dart';
import '../../../core/ui/marca.dart';
import 'acceso_screen.dart';
import 'elegir_rol_screen.dart';

/// Primera pantalla de la app: qué es GAZ y las dos puertas de entrada.
class BienvenidaScreen extends StatelessWidget {
  const BienvenidaScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final esConductor = GazRolScope.maybeOf(context) == 'driver';

    return PantallaMarca(
      hijos: <Widget>[
        const SizedBox(height: 14),
        const Center(child: IsotipoMarca(tamano: 88)),
        const SizedBox(height: 14),
        const Center(
          child: WordmarkGaz(
            tamano: 36,
            letterSpacing: -1.2,
          ),
        ),
        const SizedBox(height: 8),
        Center(
          child: Text(
            esConductor
                ? 'Conecta, conduce y cobra tus viajes.'
                : 'Tu viaje, tu ruta, tu ingreso.',
            style: MotorutaTheme.subtituloClaro,
          ),
        ),
        const SizedBox(height: 22),
        const _Punto(
          icono: Icons.verified_user_outlined,
          titulo: 'Conductores verificados',
          texto:
              'Revisamos cédula, licencia, carnet y seguro antes de activar una cuenta.',
        ),
        const SizedBox(height: 12),
        const _Punto(
          icono: Icons.route_outlined,
          titulo: 'Ruta y precio claros',
          texto: 'Ves el recorrido y el monto antes de pedir el viaje.',
        ),
        const SizedBox(height: 12),
        const _Punto(
          icono: Icons.payments_outlined,
          titulo: 'Pago como te quede mejor',
          texto: 'Saldo en la billetera o efectivo al bajarte.',
        ),
        const SizedBox(height: 24),
        BotonPrincipal(
          texto: 'Crear mi cuenta',
          icono: Icons.person_add_alt_1_rounded,
          claro: true,
          alPresionar: () => Navigator.of(context).push(
            MaterialPageRoute<void>(builder: (_) => const ElegirRolScreen()),
          ),
        ),
        const SizedBox(height: 12),
        BotonSecundario(
          texto: 'Ya tengo cuenta',
          icono: Icons.login_rounded,
          claro: true,
          alPresionar: () => Navigator.of(context).push(
            MaterialPageRoute<void>(builder: (_) => const AccesoScreen()),
          ),
        ),
      ],
    );
  }
}

class _Punto extends StatelessWidget {
  const _Punto(
      {required this.icono, required this.titulo, required this.texto});

  final IconData icono;
  final String titulo;
  final String texto;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.05),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withOpacity(0.09), width: 1),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            padding: const EdgeInsets.all(9),
            decoration: BoxDecoration(
              color: MotorutaColors.cian.withOpacity(0.14),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: MotorutaColors.cian.withOpacity(0.25),
                width: 1,
              ),
            ),
            child: Icon(icono, size: 20, color: MotorutaColors.cian),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  titulo,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                    letterSpacing: -0.2,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  texto,
                  style: MotorutaTheme.cuerpoClaro,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

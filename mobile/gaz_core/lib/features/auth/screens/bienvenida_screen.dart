import 'package:flutter/material.dart';

import '../../../core/app/gaz_app.dart';
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
        const SizedBox(height: 28),
        const Center(child: IsotipoMarca(tamano: 104)),
        const SizedBox(height: 20),
        const Center(
          child: WordmarkGaz(
            tamano: 42,
            letterSpacing: -1.4,
          ),
        ),
        const SizedBox(height: 10),
        Center(
          child: Text(
            esConductor
                ? 'Conecta, conduce y cobra tus viajes.'
                : 'Tu viaje, tu ruta, tu ingreso.',
            style: const TextStyle(
              fontSize: 16,
              color: Colors.white70,
              height: 1.4,
            ),
          ),
        ),
        const SizedBox(height: 36),
        const _Punto(
          icono: Icons.verified_user_outlined,
          titulo: 'Conductores verificados',
          texto:
              'Revisamos cédula, licencia, carnet y seguro antes de activar una cuenta.',
        ),
        const SizedBox(height: 18),
        const _Punto(
          icono: Icons.route_outlined,
          titulo: 'Ruta y precio claros',
          texto: 'Ves el recorrido y el monto antes de pedir el viaje.',
        ),
        const SizedBox(height: 18),
        const _Punto(
          icono: Icons.payments_outlined,
          titulo: 'Pago como te quede mejor',
          texto: 'Saldo en la billetera o efectivo al bajarte.',
        ),
        const SizedBox(height: 40),
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

/// Renglón con icono, título y explicación.
class _Punto extends StatelessWidget {
  const _Punto(
      {required this.icono, required this.titulo, required this.texto});

  final IconData icono;
  final String titulo;
  final String texto;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: MotorutaColors.cian.withOpacity(0.14),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icono, size: 22, color: MotorutaColors.cian),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                titulo,
                style: const TextStyle(
                  fontSize: 15.5,
                  fontWeight: FontWeight.w600,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                texto,
                style: const TextStyle(
                  fontSize: 14,
                  color: Colors.white70,
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

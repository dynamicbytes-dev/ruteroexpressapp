import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import 'pedir_viaje_screen.dart';

/// Pantalla o vista de pestaña para viajes programados/agendados del pasajero.
///
/// Permite al pasajero consultar reservas a futuro, ver detalles de la hora
/// programada y agendar un nuevo traslado con antelación.
class ViajesAgendadosScreen extends StatelessWidget {
  const ViajesAgendadosScreen({
    super.key,
    this.mostrarAppBar = false,
    this.alAgendar,
  });

  /// Si muestra la barra superior de Scaffold (true al abrir con Navigator.push).
  final bool mostrarAppBar;

  /// Callback opcional para cambiar a la pestaña de pedir viaje o abrirla.
  final VoidCallback? alAgendar;

  void _abrirPedirViaje(BuildContext context) {
    if (alAgendar != null) {
      alAgendar!();
      return;
    }

    Navigator.of(context).push<void>(
      MaterialPageRoute<void>(builder: (_) => const PedirViajeScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final contenido = ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
      children: <Widget>[
        Tarjeta(
          color: MotorutaColors.brand950,
          gradiente: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[
              MotorutaColors.brand950,
              MotorutaColors.brand900,
              MotorutaColors.brand800,
            ],
          ),
          borde: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Container(
                    width: 44,
                    height: 44,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: MotorutaColors.cian.withOpacity(0.18),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: MotorutaColors.cian.withOpacity(0.4),
                      ),
                    ),
                    child: const Icon(
                      Icons.calendar_month_rounded,
                      color: MotorutaColors.cian,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 14),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Viajes agendados',
                          style: TextStyle(
                            fontFamily: 'Poppins',
                            fontSize: 18,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'Planifica tus traslados con anticipación',
                          style: TextStyle(
                            fontFamily: 'Poppins',
                            fontSize: 12.5,
                            color: Colors.white70,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text(
                'Reserva tu viaje para hoy más tarde o para próximos días. Un conductor calificado estará listo a la hora pactada.',
                style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 13.5,
                  color: Colors.white70,
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 24),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 32),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: MotorutaColors.ink100),
            boxShadow: MotorutaTheme.sombraTarjeta,
          ),
          child: Column(
            children: <Widget>[
              Container(
                width: 68,
                height: 68,
                decoration: BoxDecoration(
                  color: MotorutaColors.accent50,
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: MotorutaColors.accent200,
                    width: 1.5,
                  ),
                ),
                child: const Icon(
                  Icons.event_available_rounded,
                  size: 34,
                  color: MotorutaColors.brand800,
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'No tienes viajes agendados',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 17,
                  fontWeight: FontWeight.w700,
                  color: MotorutaColors.ink900,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Cuando programes un viaje aparecerá aquí con los datos del conductor y el recordatorio de salida.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 13,
                  color: MotorutaColors.ink500,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 22),
              BotonPrincipal(
                texto: 'Agendar un viaje',
                icono: Icons.add_circle_outline_rounded,
                alPresionar: () => _abrirPedirViaje(context),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        const Text(
          'Ventajas de agendar tu viaje',
          style: TextStyle(
            fontFamily: 'Poppins',
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: MotorutaColors.ink800,
          ),
        ),
        const SizedBox(height: 12),
        const _ItemVentaja(
          icono: Icons.notifications_active_rounded,
          titulo: 'Recordatorios a tiempo',
          detalle: 'Te notificamos 15 minutos antes de la hora acordada.',
        ),
        const SizedBox(height: 10),
        const _ItemVentaja(
          icono: Icons.favorite_rounded,
          titulo: 'Conductores favoritos',
          detalle: 'Prioridad de asignación a conductores de tu lista.',
        ),
        const SizedBox(height: 10),
        const _ItemVentaja(
          icono: Icons.price_check_rounded,
          titulo: 'Tarifa fijada sin sorpresas',
          detalle: 'El precio se respeta según la estimación inicial.',
        ),
      ],
    );

    if (mostrarAppBar) {
      return Scaffold(
        appBar: AppBar(title: const Text('Viajes agendados')),
        body: contenido,
      );
    }

    return contenido;
  }
}

class _ItemVentaja extends StatelessWidget {
  const _ItemVentaja({
    required this.icono,
    required this.titulo,
    required this.detalle,
  });

  final IconData icono;
  final String titulo;
  final String detalle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: MotorutaColors.ink100),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 36,
            height: 36,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: MotorutaColors.brand50,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icono, color: MotorutaColors.brand800, size: 20),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  titulo,
                  style: const TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: MotorutaColors.ink900,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  detalle,
                  style: const TextStyle(
                    fontFamily: 'Poppins',
                    fontSize: 12,
                    color: MotorutaColors.ink500,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

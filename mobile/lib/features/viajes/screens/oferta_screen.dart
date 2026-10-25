import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/realtime/evento_tiempo_real.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../data/models/viaje.dart';
import '../../billetera/billetera_ui.dart';
import '../../sesion/sesion_controller.dart';
import '../viajes_ui.dart';

/// Alerta de viaje con cuenta regresiva. El conductor acepta o rechaza.
class OfertaScreen extends StatefulWidget {
  const OfertaScreen({super.key, required this.oferta});

  final OfertaViaje oferta;

  /// Duración configurada de 20 segundos para responder a una oferta entrante.
  static const int duracionSegundos = 20;
  static const Duration duracionTotal = Duration(seconds: duracionSegundos);
  static const Duration intervaloReloj = Duration(seconds: 1);

  @override
  State<OfertaScreen> createState() => _OfertaScreenState();
}

class _OfertaScreenState extends State<OfertaScreen> {
  late int _restantes;
  late int _total;
  Timer? _reloj;
  StreamSubscription<EventoTiempoReal>? _eventos;
  bool _respondiendo = false;
  bool _resuelta = false;
  String? _error;

  @override
  void initState() {
    super.initState();

    final segundosIniciales = widget.oferta.segundosRestantes;
    _total = OfertaScreen.duracionSegundos;
    _restantes = (segundosIniciales <= 0 ||
            segundosIniciales > OfertaScreen.duracionSegundos)
        ? OfertaScreen.duracionSegundos
        : segundosIniciales;

    _eventos =
        context.read<SesionController>().eventosTiempoReal.listen(_alEvento);

    _reloj = Timer.periodic(OfertaScreen.intervaloReloj, (_) {
      if (!mounted || _resuelta) return;

      if (_restantes <= 1) {
        _reloj?.cancel();
        setState(() => _restantes = 0);
        _expirar();
      } else {
        setState(() => _restantes--);
      }
    });
  }

  @override
  void dispose() {
    _eventos?.cancel();
    _reloj?.cancel();
    super.dispose();
  }

  /// Si el pasajero cancela, la alerta vence o se reasigna, esta pantalla se
  /// cierra sin esperar a que termine la cuenta regresiva.
  void _alEvento(EventoTiempoReal evento) {
    if (!mounted || _resuelta || _respondiendo) return;

    if (evento.tipo != TipoEventoTiempoReal.viaje ||
        evento.viajeId != widget.oferta.viaje.id) {
      return;
    }

    final viaje = evento.viaje;

    // Sigue siendo una oferta viva para este conductor: no hay nada que hacer.
    if (viaje != null && viaje.estado == 'offered') return;

    _reloj?.cancel();
    setState(() => _resuelta = true);
    Navigator.of(context).pop();
  }

  Future<void> _expirar() async {
    if (_resuelta) return;

    _resuelta = true;

    try {
      await context
          .read<SesionController>()
          .responderOferta(widget.oferta, aceptar: false);
    } on ApiException {
      // Si ya venció en el backend, no hay nada más que hacer.
    }

    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _responder({required bool aceptar}) async {
    if (_respondiendo || _resuelta) return;

    _reloj?.cancel();
    setState(() {
      _respondiendo = true;
      _error = null;
    });

    try {
      final resultado = await context
          .read<SesionController>()
          .responderOferta(widget.oferta, aceptar: aceptar);

      if (!mounted) return;

      _resuelta = true;

      if (aceptar) {
        final viaje = resultado?.viaje ?? widget.oferta.aViajeEnCurso();
        Navigator.of(context).pop(viaje);
      } else {
        Navigator.of(context).pop();
      }
    } on ApiException catch (error) {
      if (!mounted) return;
      if (aceptar) {
        _resuelta = true;
        Navigator.of(context).pop(widget.oferta.aViajeEnCurso());
        return;
      }
      setState(() {
        _error = error.mensaje;
        _respondiendo = false;
      });
    } catch (error) {
      if (!mounted) return;
      if (aceptar) {
        _resuelta = true;
        Navigator.of(context).pop(widget.oferta.aViajeEnCurso());
        return;
      }
      setState(() {
        _error = '$error';
        _respondiendo = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final oferta = widget.oferta;
    final viaje = oferta.viaje;
    final avance = _total == 0 ? 0.0 : _restantes / _total;

    return Scaffold(
      backgroundColor: MotorutaColors.brand950,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        foregroundColor: Colors.white,
        title: const Text('Nuevo viaje'),
        automaticallyImplyLeading: false,
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 6, 20, 24),
          children: <Widget>[
            Row(
              children: <Widget>[
                SizedBox(
                  width: 74,
                  height: 74,
                  child: Stack(
                    alignment: Alignment.center,
                    children: <Widget>[
                      SizedBox(
                        width: 74,
                        height: 74,
                        child: TweenAnimationBuilder<double>(
                          tween: Tween<double>(
                            begin: avance.clamp(0.0, 1.0),
                            end: avance.clamp(0.0, 1.0),
                          ),
                          duration: OfertaScreen.intervaloReloj,
                          curve: Curves.linear,
                          builder: (context, valor, _) {
                            return CircularProgressIndicator(
                              value: valor.clamp(0.0, 1.0),
                              strokeWidth: 6,
                              backgroundColor: Colors.white12,
                              color: MotorutaColors.cian,
                            );
                          },
                        ),
                      ),
                      Text(
                        '$_restantes',
                        style: MotorutaTheme.titulo(
                          tamano: 25,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Viaje cerca de ti',
                        style: MotorutaTheme.titulo(
                          tamano: 22,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Recogida a ${distanciaViaje(oferta.distanciaRecogidaKm)}',
                        style: const TextStyle(
                          fontSize: 13.5,
                          color: Colors.white70,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          viaje.pasajero,
                          style: MotorutaTheme.titulo(tamano: 19),
                        ),
                      ),
                      EtiquetaEstado(
                        texto: viaje.metodoPagoEtiqueta,
                        tono: viaje.metodoPago == 'wallet'
                            ? Tono.info
                            : Tono.neutro,
                        icono: viaje.metodoPago == 'wallet'
                            ? Icons.account_balance_wallet_outlined
                            : Icons.payments_outlined,
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  LineaRuta(origen: viaje.origen, destino: viaje.destino),
                  const SizedBox(height: 16),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: _Dato(
                          etiqueta: 'Distancia',
                          valor:
                              '${distanciaViaje(viaje.distanciaKm)} · ${viaje.duracionMinutos} min',
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _Dato(
                          etiqueta: 'Ganas',
                          valor: montoUsd(viaje.ganancia),
                          destacado: true,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: MotorutaColors.ink50,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Row(
                      children: <Widget>[
                        const Icon(
                          Icons.info_outline_rounded,
                          size: 18,
                          color: MotorutaColors.ink500,
                        ),
                        const SizedBox(width: 9),
                        Expanded(
                          child: Text(
                            'El pasajero paga ${montoUsd(viaje.total)}. '
                            'La comisión de GAZ es ${montoUsd(viaje.comision)}.',
                            style: const TextStyle(
                              fontSize: 12,
                              height: 1.35,
                              color: MotorutaColors.ink600,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (_error != null) ...<Widget>[
                    const SizedBox(height: 12),
                    Aviso(tono: Tono.error, texto: _error!),
                  ],
                  const SizedBox(height: 18),
                  BotonPrincipal(
                    texto: 'Aceptar viaje',
                    icono: Icons.two_wheeler_rounded,
                    cargando: _respondiendo,
                    alPresionar:
                        _respondiendo ? null : () => _responder(aceptar: true),
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed:
                        _respondiendo ? null : () => _responder(aceptar: false),
                    child: const Text('Rechazar'),
                  ),
                  if (_error != null)
                    TextButton(
                      onPressed: () => Navigator.of(context).pop(),
                      child: const Text('Cerrar'),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Dato extends StatelessWidget {
  const _Dato({
    required this.etiqueta,
    required this.valor,
    this.destacado = false,
  });

  final String etiqueta;
  final String valor;
  final bool destacado;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          etiqueta,
          style: const TextStyle(fontSize: 12, color: MotorutaColors.ink500),
        ),
        const SizedBox(height: 3),
        Text(
          valor,
          style: destacado
              ? MotorutaTheme.titulo(tamano: 23)
              : const TextStyle(
                  fontSize: 14.5,
                  fontWeight: FontWeight.w600,
                  color: MotorutaColors.ink900,
                ),
        ),
      ],
    );
  }
}

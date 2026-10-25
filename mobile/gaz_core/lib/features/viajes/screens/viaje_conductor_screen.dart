import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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

/// Viaje activo del conductor, con el siguiente paso según el estado.
class ViajeConductorScreen extends StatefulWidget {
  const ViajeConductorScreen({super.key, required this.inicial});

  final Viaje inicial;

  @override
  State<ViajeConductorScreen> createState() => _ViajeConductorScreenState();
}

class _ViajeConductorScreenState extends State<ViajeConductorScreen> {
  late Viaje _viaje;
  Timer? _reloj;
  StreamSubscription<EventoTiempoReal>? _eventos;
  bool _consultando = false;
  bool _accionando = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _viaje = widget.inicial;
    _eventos =
        context.read<SesionController>().eventosTiempoReal.listen(_alEvento);
    _programar();
  }

  @override
  void dispose() {
    _eventos?.cancel();
    _reloj?.cancel();
    super.dispose();
  }

  void _programar() {
    _reloj?.cancel();

    if (!mounted || !_viaje.activo) return;

    final segundos =
        context.read<SesionController>().tiempoRealConectado ? 15 : 4;

    _reloj = Timer.periodic(
      Duration(seconds: segundos),
      (_) => _actualizar(),
    );
  }

  void _alEvento(EventoTiempoReal evento) {
    if (!mounted) return;

    if (evento.tipo == TipoEventoTiempoReal.conectado) {
      _programar();
      return;
    }

    if (evento.viajeId != _viaje.id) return;

    final viaje = evento.viaje;

    if (viaje != null) {
      setState(() {
        _viaje = viaje;
        _error = null;
      });

      if (!viaje.activo) _reloj?.cancel();

      return;
    }

    if (evento.tipo == TipoEventoTiempoReal.viaje &&
        (evento.motivo == 'conductor_cancelo' ||
            evento.motivo == 'conductor_desconectado')) {
      _volverAlInicio();
    }
  }

  Future<void> _actualizar() async {
    if (_consultando || !mounted) return;

    _consultando = true;

    try {
      final viaje =
          await context.read<SesionController>().actualizarViaje(_viaje.id);

      if (!mounted) return;

      setState(() {
        _viaje = viaje;
        _error = null;
      });

      if (!viaje.activo) _reloj?.cancel();
    } on ApiException {
      // Se conserva lo último que se vio.
    } finally {
      _consultando = false;
    }
  }

  Future<void> _avanzar(String accion) async {
    setState(() {
      _accionando = true;
      _error = null;
    });

    try {
      final resultado = await context
          .read<SesionController>()
          .avanzarViaje(_viaje.id, accion);

      if (!mounted) return;

      setState(() {
        _viaje = resultado.viaje;
        _error = null;
      });
      _programar();

      if (resultado.mensaje != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(resultado.mensaje!)),
        );
      }
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.mensaje);
    } finally {
      if (mounted) setState(() => _accionando = false);
    }
  }

  Future<void> _cancelar() async {
    final motivo = await showDialog<String>(
      context: context,
      builder: (_) => const DialogoCancelarViaje(esConductor: true),
    );

    if (motivo == null || !mounted) return;

    setState(() {
      _accionando = true;
      _error = null;
    });

    try {
      final sesion = context.read<SesionController>();
      final resultado = await sesion.cancelarViaje(_viaje.id, motivo: motivo);

      if (!mounted) return;

      // Si el viaje volvió a búsqueda (o pasó a otro conductor), esta pantalla
      // ya no tiene nada que mostrar.
      if (resultado.viaje.conductor?.id != sesion.usuario?.id) {
        _volverAlInicio();
        return;
      }

      setState(() => _viaje = resultado.viaje);

      if (resultado.mensaje != null) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(resultado.mensaje!)),
        );
      }
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.mensaje);
    } finally {
      if (mounted) setState(() => _accionando = false);
    }
  }

  Future<void> _copiarTelefono(String telefono) async {
    await Clipboard.setData(ClipboardData(text: telefono));

    if (!mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Teléfono copiado.')),
    );
  }

  void _volverAlInicio() {
    Navigator.of(context).popUntil((ruta) => ruta.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    final pasajero = _viaje.pasajero;
    final (String? accion, String etiqueta) = switch (_viaje.estado) {
      'accepted' => ('llegada', 'Llegué al punto de recogida'),
      'arriving' => ('esperando', 'Estoy esperando al pasajero'),
      'waiting' => ('iniciar', 'Iniciar viaje'),
      'in_progress' => ('finalizar', 'Finalizar viaje'),
      _ => (null, ''),
    };

    return Scaffold(
      appBar: AppBar(
        title: const Text('Viaje en curso'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Actualizar',
            onPressed: _actualizar,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _actualizar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 36),
          children: <Widget>[
            Tarjeta(
              color: _viaje.completado || _viaje.cancelado
                  ? Colors.white
                  : MotorutaColors.brand900,
              borde: false,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          _viaje.instruccionConductor,
                          style: MotorutaTheme.titulo(
                            tamano: 20,
                            color: _viaje.completado || _viaje.cancelado
                                ? MotorutaColors.brand900
                                : Colors.white,
                          ),
                        ),
                      ),
                      etiquetaEstadoViaje(_viaje),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _viaje.completado
                        ? 'El viaje terminó. Revisa tu ganancia y sigue conectado.'
                        : _viaje.cancelado
                            ? 'El viaje no continuó.'
                            : 'Sigue las indicaciones y mantén informado al pasajero.',
                    style: TextStyle(
                      fontSize: 13.5,
                      height: 1.4,
                      color: _viaje.completado || _viaje.cancelado
                          ? MotorutaColors.ink600
                          : Colors.white70,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            MapaViaje(
              origen: _viaje.origen,
              destino: _viaje.destino,
              altura: 220,
            ),
            if (pasajero != null) ...<Widget>[
              const SizedBox(height: 16),
              Tarjeta(
                child: Row(
                  children: <Widget>[
                    CircleAvatar(
                      radius: 25,
                      backgroundColor: MotorutaColors.brand800,
                      backgroundImage: pasajero.fotoUrl != null &&
                              pasajero.fotoUrl!.trim().isNotEmpty
                          ? NetworkImage(pasajero.fotoUrl!.trim())
                          : null,
                      onBackgroundImageError: pasajero.fotoUrl != null &&
                              pasajero.fotoUrl!.trim().isNotEmpty
                          ? (_, __) {}
                          : null,
                      child: pasajero.fotoUrl == null ||
                              pasajero.fotoUrl!.trim().isEmpty
                          ? const Icon(
                              Icons.person_outline_rounded,
                              color: Colors.white,
                            )
                          : null,
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            pasajero.nombre,
                            style: MotorutaTheme.titulo(tamano: 17),
                          ),
                          Text(
                            pasajero.telefono.isEmpty
                                ? 'Teléfono no disponible'
                                : pasajero.telefono,
                            style: const TextStyle(
                              fontSize: 13,
                              color: MotorutaColors.ink600,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (pasajero.telefono.isNotEmpty)
                      IconButton(
                        tooltip: 'Copiar teléfono',
                        onPressed: () => _copiarTelefono(pasajero.telefono),
                        icon: const Icon(Icons.phone_outlined),
                      ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 16),
            Tarjeta(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Text('Ruta', style: MotorutaTheme.titulo(tamano: 17)),
                  const SizedBox(height: 12),
                  LineaRuta(origen: _viaje.origen, destino: _viaje.destino),
                  const SizedBox(height: 10),
                  Text(
                    '${distanciaViaje(_viaje.distanciaKm)} · '
                    '${_viaje.duracionMinutos} min aproximados',
                    style: const TextStyle(
                      fontSize: 13,
                      color: MotorutaColors.ink500,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Tarjeta(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Text('Cobro', style: MotorutaTheme.titulo(tamano: 17)),
                  const SizedBox(height: 10),
                  DatoFila(
                    etiqueta: 'Método',
                    valor: _viaje.metodoPagoEtiqueta.isEmpty
                        ? (_viaje.enEfectivo ? 'Efectivo' : 'Billetera')
                        : _viaje.metodoPagoEtiqueta,
                  ),
                  DatoFila(
                    etiqueta: 'Total pasajero',
                    valor: montoUsd(_viaje.tarifa.total),
                  ),
                  DatoFila(
                    etiqueta: 'Comisión',
                    valor: '-${montoUsd(_viaje.tarifa.comision)}',
                  ),
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: <Widget>[
                      const Text(
                        'Tu ganancia',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                          color: MotorutaColors.ink700,
                        ),
                      ),
                      Text(
                        montoUsd(_viaje.tarifa.gananciaConductor),
                        style: MotorutaTheme.titulo(tamano: 26),
                      ),
                    ],
                  ),
                  if (_viaje.enEfectivo && _viaje.completado) ...<Widget>[
                    const SizedBox(height: 10),
                    Aviso(
                      tono: Tono.aviso,
                      texto:
                          'Cobra ${montoUsd(_viaje.tarifa.total)} en efectivo al pasajero. '
                          'La comisión ya se descontó de tu billetera.',
                    ),
                  ],
                ],
              ),
            ),
            if (_error != null) ...<Widget>[
              const SizedBox(height: 16),
              Aviso(tono: Tono.error, texto: _error!),
            ],
            const SizedBox(height: 20),
            if (accion != null)
              BotonPrincipal(
                texto: etiqueta,
                icono: switch (accion) {
                  'finalizar' => Icons.flag_rounded,
                  'iniciar' => Icons.play_arrow_rounded,
                  'esperando' => Icons.access_time_rounded,
                  _ => Icons.place_rounded,
                },
                cargando: _accionando,
                alPresionar: _accionando ? null : () => _avanzar(accion),
              ),
            if (_viaje.activo && _viaje.puedeCancelar) ...<Widget>[
              const SizedBox(height: 8),
              BotonSecundario(
                texto: _accionando ? 'Un momento…' : 'Cancelar viaje',
                icono: Icons.close_rounded,
                alPresionar: _accionando ? null : _cancelar,
              ),
            ],
            if (!_viaje.activo) ...<Widget>[
              BotonPrincipal(
                texto: 'Volver al inicio',
                icono: Icons.home_outlined,
                alPresionar: _volverAlInicio,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

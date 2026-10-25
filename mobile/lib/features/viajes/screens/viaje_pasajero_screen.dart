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
import '../widgets/calificar_viaje_sheet.dart';

/// Seguimiento del viaje para el pasajero.
///
/// F3 consulta el estado cada pocos segundos. En F4 este sondeo se reemplaza
/// por WebSockets, pero el contrato de datos es el mismo.
class ViajePasajeroScreen extends StatefulWidget {
  const ViajePasajeroScreen({super.key, required this.inicial});

  final Viaje inicial;

  @override
  State<ViajePasajeroScreen> createState() => _ViajePasajeroScreenState();
}

class _ViajePasajeroScreenState extends State<ViajePasajeroScreen> {
  late Viaje _viaje;
  Timer? _reloj;
  StreamSubscription<EventoTiempoReal>? _eventos;
  bool _consultando = false;
  bool _cancelando = false;
  bool _guardandoFavorito = false;
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

    // El WebSocket entrega los cambios al instante. El sondeo queda como red
    // de seguridad y baja de frecuencia cuando el canal está conectado.
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
        evento.motivo == 'conductor_cancelo') {
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
      // Un fallo de red no borra lo que ya se ve; el próximo ciclo reintenta.
    } finally {
      _consultando = false;
    }
  }

  Future<void> _cancelar() async {
    final motivo = await showDialog<String>(
      context: context,
      builder: (_) => const DialogoCancelarViaje(esConductor: false),
    );

    if (motivo == null || !mounted) return;

    setState(() {
      _cancelando = true;
      _error = null;
    });

    try {
      final resultado = await context
          .read<SesionController>()
          .cancelarViaje(_viaje.id, motivo: motivo);

      if (!mounted) return;

      setState(() {
        _viaje = resultado.viaje;
        _error =
            resultado.viaje.estado == 'cancelled' ? null : resultado.mensaje;
      });
      _programar();

      if (resultado.mensaje != null && mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(resultado.mensaje!)));
      }
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.mensaje);
    } finally {
      if (mounted) setState(() => _cancelando = false);
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

  Future<void> _calificar() async {
    final actualizado = await mostrarCalificarViaje(context, viaje: _viaje);

    if (actualizado == null || !mounted) return;

    setState(() {
      _viaje = actualizado;
      _error = null;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Gracias por calificar tu viaje.')),
    );
  }

  Future<void> _alternarFavorito() async {
    final conductor = _viaje.conductor;

    if (conductor == null || _guardandoFavorito) return;

    setState(() => _guardandoFavorito = true);

    try {
      final favorito = await context
          .read<SesionController>()
          .alternarFavorito(conductor.id, !conductor.esFavorito);

      if (!mounted) return;

      setState(() => _viaje = _viaje.conFavorito(favorito));

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            favorito
                ? '${conductor.nombre} quedó en tus conductores favoritos.'
                : '${conductor.nombre} ya no está en tus favoritos.',
          ),
        ),
      );
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.mensaje)));
      }
    } finally {
      if (mounted) setState(() => _guardandoFavorito = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final conductor = _viaje.conductor;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tu viaje'),
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
                          _viaje.resumenEstado,
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
                    switch (_viaje.estado) {
                      'searching' ||
                      'offered' =>
                        'Estamos avisando a los conductores más cercanos. No cierres la app.',
                      'accepted' ||
                      'arriving' =>
                        'Tu conductor va en camino al punto de recogida.',
                      'waiting' =>
                        'Tu conductor ya está en el punto de recogida.',
                      'in_progress' => 'Vas en camino a tu destino.',
                      'completed' =>
                        'Llegaste a tu destino. Gracias por viajar con GAZ.',
                      'no_driver' =>
                        'No hubo un conductor disponible. Puedes intentarlo de nuevo.',
                      _ => 'Este viaje no continuó.',
                    },
                    style: TextStyle(
                      fontSize: 13.5,
                      height: 1.4,
                      color: _viaje.completado || _viaje.cancelado
                          ? MotorutaColors.ink600
                          : Colors.white70,
                    ),
                  ),
                  if (_viaje.buscando) ...<Widget>[
                    const SizedBox(height: 14),
                    const LinearProgressIndicator(
                      minHeight: 3,
                      backgroundColor: Colors.white12,
                      color: MotorutaColors.cian,
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
            MapaViaje(
              origen: _viaje.origen,
              destino: _viaje.destino,
              conductor: conductor?.lat != null && conductor?.lng != null
                  ? PuntoViaje(
                      direccion: conductor!.nombre,
                      lat: conductor.lat!,
                      lng: conductor.lng!,
                    )
                  : null,
              altura: 220,
            ),
            if (conductor != null) ...<Widget>[
              const SizedBox(height: 16),
              Tarjeta(
                child: Row(
                  children: <Widget>[
                    CircleAvatar(
                      radius: 25,
                      backgroundColor: MotorutaColors.brand800,
                      backgroundImage: conductor.fotoUrl != null &&
                              conductor.fotoUrl!.trim().isNotEmpty
                          ? NetworkImage(conductor.fotoUrl!.trim())
                          : null,
                      onBackgroundImageError: conductor.fotoUrl != null &&
                              conductor.fotoUrl!.trim().isNotEmpty
                          ? (_, __) {}
                          : null,
                      child: conductor.fotoUrl == null ||
                              conductor.fotoUrl!.trim().isEmpty
                          ? const Icon(
                              Icons.two_wheeler_rounded,
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
                            conductor.nombre,
                            style: MotorutaTheme.titulo(tamano: 17),
                          ),
                          if (conductor.vehiculo != null)
                            Text(
                              conductor.vehiculo!,
                              style: const TextStyle(
                                fontSize: 13,
                                color: MotorutaColors.ink600,
                              ),
                            ),
                          if (conductor.calificacion != null)
                            Text(
                              '★ ${conductor.calificacion!.toStringAsFixed(1)}'
                              '${conductor.viajesCompletados != null ? ' · ${conductor.viajesCompletados} viajes' : ''}',
                              style: const TextStyle(
                                fontSize: 12,
                                color: MotorutaColors.ink500,
                              ),
                            ),
                        ],
                      ),
                    ),
                    if (conductor.telefono.isNotEmpty)
                      IconButton(
                        tooltip: 'Copiar teléfono',
                        onPressed: () => _copiarTelefono(conductor.telefono),
                        icon: const Icon(Icons.phone_outlined),
                      ),
                    if (_viaje.completado)
                      IconButton(
                        tooltip: conductor.esFavorito
                            ? 'Quitar de favoritos'
                            : 'Marcar como conductor favorito',
                        onPressed:
                            _guardandoFavorito ? null : _alternarFavorito,
                        icon: _guardandoFavorito
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : Icon(
                                conductor.esFavorito
                                    ? Icons.favorite_rounded
                                    : Icons.favorite_border_rounded,
                                color: conductor.esFavorito
                                    ? MotorutaColors.rojo
                                    : MotorutaColors.ink400,
                              ),
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
                  Text('Pago', style: MotorutaTheme.titulo(tamano: 17)),
                  const SizedBox(height: 10),
                  DatoFila(
                    etiqueta: 'Método',
                    valor: _viaje.metodoPagoEtiqueta.isEmpty
                        ? (_viaje.enEfectivo ? 'Efectivo' : 'Billetera')
                        : _viaje.metodoPagoEtiqueta,
                  ),
                  if (_viaje.tarifa.tasaCambio != null)
                    DatoFila(
                      etiqueta: 'Tasa',
                      valor: '${numeroViaje(_viaje.tarifa.tasaCambio!)} Bs/USD',
                    ),
                  const Divider(height: 24),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: <Widget>[
                      const Text(
                        'Total',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w600,
                          color: MotorutaColors.ink700,
                        ),
                      ),
                      Text(
                        montoUsd(_viaje.tarifa.total),
                        style: MotorutaTheme.titulo(tamano: 26),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            if (_viaje.completado) ...<Widget>[
              const SizedBox(height: 16),
              _TarjetaCalificacion(
                calificacion: _viaje.calificacion,
                puedeCalificar: _viaje.puedoCalificar,
                alCalificar: _calificar,
              ),
            ],
            if (_error != null) ...<Widget>[
              const SizedBox(height: 16),
              Aviso(tono: Tono.error, texto: _error!),
            ],
            const SizedBox(height: 20),
            if (_viaje.activo && _viaje.puedeCancelar)
              BotonSecundario(
                texto: _cancelando ? 'Cancelando…' : 'Cancelar viaje',
                icono: Icons.close_rounded,
                alPresionar: _cancelando ? null : _cancelar,
              ),
            if (!_viaje.activo) ...<Widget>[
              BotonPrincipal(
                texto: _viaje.completado
                    ? 'Volver al inicio'
                    : 'Volver e intentar de nuevo',
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

/// Calificación del viaje: invita a calificar o muestra lo que ya se envió.
class _TarjetaCalificacion extends StatelessWidget {
  const _TarjetaCalificacion({
    required this.calificacion,
    required this.puedeCalificar,
    required this.alCalificar,
  });

  final CalificacionViaje? calificacion;
  final bool puedeCalificar;
  final VoidCallback alCalificar;

  @override
  Widget build(BuildContext context) {
    final enviada = calificacion;

    return Tarjeta(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text('Tu calificación', style: MotorutaTheme.titulo(tamano: 17)),
          const SizedBox(height: 10),
          if (enviada != null) ...<Widget>[
            Text(
              enviada.textoEstrellas,
              style: const TextStyle(
                fontSize: 26,
                letterSpacing: 2,
                color: MotorutaColors.amarillo,
              ),
            ),
            if (enviada.comentario != null) ...<Widget>[
              const SizedBox(height: 6),
              Text(
                enviada.comentario!,
                style: const TextStyle(
                  fontSize: 13.5,
                  height: 1.4,
                  color: MotorutaColors.ink700,
                ),
              ),
            ],
          ] else if (puedeCalificar) ...<Widget>[
            const Text(
              'Cuéntanos cómo condujo tu conductor. Solo toma un momento.',
              style: TextStyle(
                fontSize: 13.5,
                height: 1.4,
                color: MotorutaColors.ink600,
              ),
            ),
            const SizedBox(height: 12),
            BotonPrincipal(
              texto: 'Calificar el viaje',
              icono: Icons.star_rounded,
              alPresionar: alCalificar,
            ),
          ] else
            const Text(
              'Todavía no hay una calificación para este viaje.',
              style: TextStyle(fontSize: 13.5, color: MotorutaColors.ink500),
            ),
        ],
      ),
    );
  }
}

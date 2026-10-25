import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
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
import '../screens/oferta_screen.dart';
import '../screens/calificaciones_conductor_screen.dart';
import '../screens/viaje_conductor_screen.dart';
import '../ubicacion_service.dart';
import '../viajes_ui.dart';

/// Tarjeta de conexión del conductor en el inicio.
///
/// Mientras está en línea reporta su ubicación y consulta alertas/viaje activo.
/// En F4 las alertas llegarán también por push y WebSockets; el sondeo queda
/// como respaldo.
class PanelConductor extends StatefulWidget {
  const PanelConductor({super.key});

  @override
  State<PanelConductor> createState() => _PanelConductorState();
}

class _PanelConductorState extends State<PanelConductor>
    with WidgetsBindingObserver {
  Timer? _reloj;
  StreamSubscription<Position>? _ubicacion;
  StreamSubscription<EventoTiempoReal>? _eventos;
  DateTime _ultimoReporte = DateTime.fromMillisecondsSinceEpoch(0);
  Position? _ultimaPosicion;
  bool _cambiando = false;
  bool _mostrandoOferta = false;

  /// Sin movimiento el stream no emite, así que además del GPS se reenvía la
  /// última posición cada minuto. El backend vence la ubicación a los 2 minutos.
  static const Duration _intervaloLatido = Duration(seconds: 60);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    _eventos =
        context.read<SesionController>().eventosTiempoReal.listen(_alEvento);

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _cargarInicial();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _eventos?.cancel();
    _reloj?.cancel();
    _ubicacion?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed || !mounted) return;

    final sesion = context.read<SesionController>();

    if (sesion.estadoConductor?.enLinea == true) {
      unawaited(_reportarAhora(usarCache: true));
      _activarSeguimiento();
      _iniciarSondeo();
    }
  }

  void _alEvento(EventoTiempoReal evento) {
    if (!mounted) return;

    if (evento.tipo == TipoEventoTiempoReal.oferta) {
      _revisarOfertas();
      return;
    }

    if (evento.tipo == TipoEventoTiempoReal.viaje) {
      if (evento.viaje == null || !evento.viaje!.activo) {
        unawaited(_refrescar());
      }

      _revisarOfertas();
      return;
    }

    if (evento.tipo == TipoEventoTiempoReal.conectado) {
      if (context.read<SesionController>().estadoConductor?.enLinea == true) {
        _iniciarSondeo();
      }
    }
  }

  Future<void> _cargarInicial() async {
    final sesion = context.read<SesionController>();

    await sesion.cargarEstadoConductor();
    if (!mounted) return;

    await sesion.cargarViajeActivo(silencioso: true);
    if (!mounted) return;

    await sesion.cargarOfertas(silencioso: true);
    if (!mounted) return;

    if (sesion.estadoConductor?.enLinea == true) {
      unawaited(_reportarAhora());
      _activarSeguimiento();
      _iniciarSondeo();
    }

    setState(() {});
    _revisarOfertas();
  }

  void _iniciarSondeo() {
    _reloj?.cancel();

    if (!mounted) return;

    final segundos =
        context.read<SesionController>().tiempoRealConectado ? 20 : 5;

    _reloj = Timer.periodic(
      Duration(seconds: segundos),
      (_) => _refrescar(),
    );
  }

  Future<void> _refrescar() async {
    if (!mounted) return;

    final sesion = context.read<SesionController>();

    await sesion.cargarEstadoConductor(silencioso: true);
    if (!mounted) return;

    await sesion.cargarViajeActivo(silencioso: true);
    if (!mounted) return;

    await sesion.cargarOfertas(silencioso: true);
    if (!mounted) return;

    final enLinea = sesion.estadoConductor?.enLinea == true;

    if (enLinea) {
      _activarSeguimiento();

      final vencido = _ultimaPosicion == null ||
          DateTime.now().difference(_ultimoReporte) >= _intervaloLatido;

      if (vencido) unawaited(_reportarAhora(usarCache: true));
    } else {
      _detenerSeguimiento();
    }

    setState(() {});
    _revisarOfertas();
  }

  /// Reporta una posición aunque el GPS no se haya movido: al abrir la app con
  /// la sesión ya conectada, el backend necesita una ubicación reciente.
  Future<void> _reportarAhora({bool usarCache = false}) async {
    if (!mounted) return;

    final sesion = context.read<SesionController>();

    try {
      final posicion = (usarCache ? _ultimaPosicion : null) ??
          await const UbicacionService().actual();

      _ultimaPosicion = posicion;
      await sesion.reportarUbicacion(posicion.latitude, posicion.longitude);
      _ultimoReporte = DateTime.now();
    } on UbicacionException {
      // Se mantiene el sondeo; si el GPS se arregla, el stream lo reportará.
    } on ApiException {
      // Un fallo de red no debe tumbar la pantalla.
    }
  }

  void _activarSeguimiento() {
    if (_ubicacion != null) return;

    _ubicacion = const UbicacionService().seguir().listen(
      (posicion) {
        _ultimaPosicion = posicion;

        final ahora = DateTime.now();

        if (ahora.difference(_ultimoReporte) < const Duration(seconds: 12)) {
          return;
        }

        _ultimoReporte = ahora;

        if (!mounted) return;

        unawaited(
          context
              .read<SesionController>()
              .reportarUbicacion(posicion.latitude, posicion.longitude)
              .catchError((Object _) {}),
        );
      },
      onError: (Object _) {
        // Si el sistema corta el GPS, el latido sigue enviando la última
        // posición conocida hasta que el sistema lo recupere.
      },
    );
  }

  void _detenerSeguimiento() {
    _ubicacion?.cancel();
    _ubicacion = null;
    _ultimaPosicion = null;
    _reloj?.cancel();
    _reloj = null;
  }

  Future<void> _alternar(bool conectar) async {
    if (_cambiando) return;

    setState(() => _cambiando = true);

    final sesion = context.read<SesionController>();

    try {
      if (conectar) {
        final posicion = await const UbicacionService().actual();

        _ultimaPosicion = posicion;
        await sesion.reportarUbicacion(
          posicion.latitude,
          posicion.longitude,
        );
        _ultimoReporte = DateTime.now();
        await sesion.cambiarDisponibilidad(true);

        if (!mounted) return;

        _activarSeguimiento();
        _iniciarSondeo();
      } else {
        await sesion.cambiarDisponibilidad(false);
        _detenerSeguimiento();
      }
    } on UbicacionException catch (error) {
      if (mounted) _avisar(error.mensaje);
    } on ApiException catch (error) {
      if (mounted) _avisar(error.mensaje);
    } finally {
      if (mounted) setState(() => _cambiando = false);
    }
  }

  Future<void> _revisarOfertas() async {
    if (!mounted || _mostrandoOferta) return;

    final sesion = context.read<SesionController>();

    if (sesion.ofertas.isEmpty) return;

    // No abrir una alerta encima de otra pantalla que el conductor esté usando.
    if (ModalRoute.of(context)?.isCurrent != true) return;

    _mostrandoOferta = true;

    final viaje = await Navigator.of(context).push<Viaje>(
      MaterialPageRoute<Viaje>(
        builder: (_) => OfertaScreen(oferta: sesion.ofertas.first),
      ),
    );

    _mostrandoOferta = false;

    if (!mounted) return;

    await _refrescar();

    final viajeParaAbrir = viaje ?? sesion.viajeActivo;

    if (viajeParaAbrir != null && viajeParaAbrir.enCurso && mounted) {
      await Navigator.of(context).push<void>(
        MaterialPageRoute<void>(
          builder: (_) => ViajeConductorScreen(inicial: viajeParaAbrir),
        ),
      );

      if (mounted) await _refrescar();
    }
  }

  void _avisar(String mensaje) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(mensaje)),
    );
  }

  Future<void> _abrirViaje(Viaje viaje) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => ViajeConductorScreen(inicial: viaje),
      ),
    );

    if (mounted) await _refrescar();
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionController>();
    final estado = sesion.estadoConductor;
    final viaje = sesion.viajeActivo;

    if (estado == null) {
      return Tarjeta(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Text('Modo conductor', style: MotorutaTheme.titulo(tamano: 18)),
            const SizedBox(height: 12),
            if (sesion.cargandoEstadoConductor)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(8),
                  child: CircularProgressIndicator(),
                ),
              )
            else ...<Widget>[
              Text(
                sesion.errorViajes ??
                    'No pudimos leer tu estado de conexión. Inténtalo de nuevo.',
                style: const TextStyle(
                  fontSize: 13.5,
                  color: MotorutaColors.ink600,
                ),
              ),
              const SizedBox(height: 10),
              BotonSecundario(
                texto: 'Reintentar',
                icono: Icons.refresh_rounded,
                alPresionar: _cargarInicial,
              ),
            ],
          ],
        ),
      );
    }

    final advertencia = _advertencia(estado);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Tarjeta(
          color: MotorutaColors.brand900,
          borde: false,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        const Text(
                          'MODO CONDUCTOR',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 0.9,
                            color: MotorutaColors.cian,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          estado.enLinea
                              ? 'Estás en línea'
                              : 'Estás desconectado',
                          style: MotorutaTheme.titulo(
                            tamano: 20,
                            color: Colors.white,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (_cambiando)
                    const SizedBox(
                      height: 24,
                      width: 24,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.4,
                        color: MotorutaColors.cian,
                      ),
                    )
                  else
                    Switch(
                      value: estado.enLinea,
                      onChanged: _alternar,
                      activeColor: MotorutaColors.cian,
                      activeTrackColor: MotorutaColors.cian.withOpacity(0.35),
                      inactiveThumbColor: Colors.white70,
                      inactiveTrackColor: Colors.white12,
                    ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                estado.enLinea
                    ? 'Recibirás alertas de viajes cercanos. Mantén la ubicación activa.'
                    : 'Conéctate para recibir viajes. Necesitamos tu ubicación.',
                style: const TextStyle(
                  fontSize: 13,
                  height: 1.4,
                  color: Colors.white70,
                ),
              ),
              if (advertencia != null) ...<Widget>[
                const SizedBox(height: 12),
                Aviso(tono: advertencia.$1, texto: advertencia.$2),
                if (estado.enLinea && !estado.disponible) ...<Widget>[
                  const SizedBox(height: 10),
                  BotonSecundario(
                    texto: 'Actualizar ubicación',
                    icono: Icons.my_location_rounded,
                    claro: true,
                    alPresionar: () => _reportarAhora(usarCache: false),
                  ),
                ],
              ],
              const SizedBox(height: 16),
              Row(
                children: <Widget>[
                  _Estadistica(
                    valor: '${estado.viajesCompletados}',
                    etiqueta: 'Viajes',
                  ),
                  _Estadistica(
                    valor:
                        '${estado.rechazosVentana}/${estado.rechazosParaBloqueo}',
                    etiqueta: 'Rechazos',
                  ),
                  _Estadistica(
                    valor: montoUsd(estado.saldoDisponible),
                    etiqueta: 'Saldo',
                  ),
                  _Estadistica(
                    valor: estado.calificacion == null
                        ? '—'
                        : '★ ${estado.calificacion!.toStringAsFixed(1)}',
                    etiqueta: 'Calificación',
                  ),
                ],
              ),
              if (estado.bloqueado) ...<Widget>[
                const SizedBox(height: 12),
                Text(
                  'Bloqueado hasta ${horaViaje(estado.bloqueadoHasta)}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: MotorutaColors.cian,
                  ),
                ),
              ],
              if (sesion.ofertas.isNotEmpty) ...<Widget>[
                const SizedBox(height: 12),
                BotonPrincipal(
                  texto: 'Tienes una alerta de viaje',
                  icono: Icons.notifications_active_outlined,
                  claro: true,
                  alPresionar: _revisarOfertas,
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 14),
        Tarjeta(
          child: ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(
              Icons.star_rounded,
              color: MotorutaColors.amarillo,
            ),
            title: const Text(
              'Mis calificaciones',
              style: TextStyle(fontWeight: FontWeight.w600),
            ),
            subtitle: Text(
              estado.calificacion == null
                  ? 'Todavía no tienes calificaciones'
                  : '★ ${estado.calificacion!.toStringAsFixed(1)} · '
                      '${estado.calificaciones} calificación'
                      '${estado.calificaciones == 1 ? '' : 'es'}',
              style: const TextStyle(fontSize: 12.5),
            ),
            trailing: const Icon(Icons.chevron_right_rounded),
            onTap: () => Navigator.of(context).push<void>(
              MaterialPageRoute<void>(
                builder: (_) => const CalificacionesConductorScreen(),
              ),
            ),
          ),
        ),
        if (viaje != null && viaje.activo) ...<Widget>[
          const SizedBox(height: 14),
          Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        'Viaje en curso',
                        style: MotorutaTheme.titulo(tamano: 17),
                      ),
                    ),
                    etiquetaEstadoViaje(viaje),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  '${viaje.origen.direccion} → ${viaje.destino.direccion}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13,
                    color: MotorutaColors.ink600,
                  ),
                ),
                const SizedBox(height: 12),
                BotonPrincipal(
                  texto: 'Continuar viaje',
                  icono: Icons.two_wheeler_rounded,
                  alPresionar: () => _abrirViaje(viaje),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  /// Mensaje accionable si el conductor no puede recibir viajes.
  (Tono, String)? _advertencia(EstadoConductor estado) {
    if (estado.bloqueado) {
      return (
        Tono.error,
        'Tienes un bloqueo por rechazos. Termina antes de ${horaViaje(estado.bloqueadoHasta)}.',
      );
    }

    if (estado.saldoBajo) {
      return (
        Tono.error,
        'Tu saldo llegó al tope negativo (${montoUsd(estado.topeNegativo)}). Recarga para conectarte.',
      );
    }

    if (estado.enLinea && !estado.disponible) {
      return (
        Tono.aviso,
        'Todavía no recibimos una ubicación reciente. Revisa que el GPS esté activo y actualiza tu posición.',
      );
    }

    if (!estado.enLinea && estado.rechazosVentana > 0) {
      return (
        Tono.aviso,
        'Tienes ${estado.rechazosVentana} rechazo(s) recientes. Al llegar a ${estado.rechazosParaBloqueo} se bloquea la cuenta por una hora.',
      );
    }

    return null;
  }
}

class _Estadistica extends StatelessWidget {
  const _Estadistica({required this.valor, required this.etiqueta});

  final String valor;
  final String etiqueta;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            valor,
            style: MotorutaTheme.titulo(tamano: 19, color: Colors.white),
          ),
          const SizedBox(height: 2),
          Text(
            etiqueta,
            style: const TextStyle(fontSize: 11.5, color: Colors.white54),
          ),
        ],
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../core/ui/mensajes.dart';
import '../../../data/models/periodo_historial.dart';
import '../../../data/models/viaje.dart';
import '../../billetera/billetera_ui.dart';
import '../../sesion/sesion_controller.dart';
import '../viajes_ui.dart';
import 'viaje_conductor_screen.dart';
import 'viaje_pasajero_screen.dart';

/// Viajes anteriores del pasajero o del conductor.
///
/// La API devuelve páginas de 20 viajes, del más reciente al más antiguo. El
/// botón "Ver más viajes" acumula las páginas siguientes sin cambiar el filtro.
/// El detalle reutiliza las mismas pantallas del viaje activo.
class HistorialViajesScreen extends StatefulWidget {
  const HistorialViajesScreen({super.key, this.mostrarAppBar = true});

  final bool mostrarAppBar;

  @override
  State<HistorialViajesScreen> createState() => _HistorialViajesScreenState();
}

class _HistorialViajesScreenState extends State<HistorialViajesScreen> {
  List<Viaje> _viajes = const <Viaje>[];
  PeriodoHistorial _periodo = PeriodoHistorial.todos;
  bool _cargando = true;
  bool _cargandoMas = false;
  String? _error;
  int _pagina = 1;
  int _ultimaPagina = 1;

  bool get _hayMas => _pagina < _ultimaPagina;

  /// Evita que una respuesta vieja pise a un filtro elegido después.
  int _cargaActual = 0;

  @override
  void initState() {
    super.initState();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _cargar();
    });
  }

  Future<void> _cargar() async {
    final carga = ++_cargaActual;

    setState(() {
      _cargando = true;
      _cargandoMas = false;
      _error = null;
    });

    try {
      final pagina = await context
          .read<SesionController>()
          .historialViajesPaginado(periodo: _periodo);

      if (!mounted || carga != _cargaActual) return;

      setState(() {
        _viajes = pagina.viajes;
        _pagina = pagina.pagina;
        _ultimaPagina = pagina.ultimaPagina;
      });
    } on ApiException catch (error) {
      if (!mounted || carga != _cargaActual) return;
      setState(() => _error = error.mensaje);
    } finally {
      if (mounted && carga == _cargaActual) {
        setState(() => _cargando = false);
      }
    }
  }

  Future<void> _cargarMas() async {
    if (_cargando || _cargandoMas || !_hayMas) return;

    final carga = _cargaActual;

    setState(() => _cargandoMas = true);

    try {
      final pagina = await context
          .read<SesionController>()
          .historialViajesPaginado(periodo: _periodo, pagina: _pagina + 1);

      if (!mounted || carga != _cargaActual) return;

      final existentes = _viajes.map((viaje) => viaje.id).toSet();

      setState(() {
        _viajes = <Viaje>[
          ..._viajes,
          ...pagina.viajes.where((viaje) => !existentes.contains(viaje.id)),
        ];
        _pagina = pagina.pagina;
        _ultimaPagina = pagina.ultimaPagina;
      });
    } on ApiException catch (error) {
      if (!mounted || carga != _cargaActual) return;
      mostrarMensaje(context, error.mensaje, error: true);
    } finally {
      if (mounted && carga == _cargaActual) {
        setState(() => _cargandoMas = false);
      }
    }
  }

  void _cambiarPeriodo(PeriodoHistorial periodo) {
    if (periodo == _periodo) return;

    setState(() => _periodo = periodo);
    _cargar();
  }

  Future<void> _abrir(Viaje viaje) async {
    final sesion = context.read<SesionController>();

    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => sesion.esConductor
            ? ViajeConductorScreen(inicial: viaje)
            : ViajePasajeroScreen(inicial: viaje),
      ),
    );

    if (mounted) await _cargar();
  }

  @override
  Widget build(BuildContext context) {
    final esConductor = context.watch<SesionController>().esConductor;
    final bottomInset = MediaQuery.of(context).padding.bottom;
    final paddingInferior = 110.0 + bottomInset;

    return Scaffold(
      appBar: widget.mostrarAppBar
          ? AppBar(title: const Text('Historial de viajes'))
          : null,
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: EdgeInsets.fromLTRB(20, 16, 20, paddingInferior),
          children: <Widget>[
            if (esConductor) ...<Widget>[
              _FiltroPeriodo(
                seleccionado: _periodo,
                alCambiar: _cambiarPeriodo,
              ),
              const SizedBox(height: 16),
            ],
            if (_cargando)
              const Padding(
                padding: EdgeInsets.only(top: 40),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null) ...<Widget>[
              Aviso(tono: Tono.error, texto: _error!),
              const SizedBox(height: 14),
              BotonSecundario(
                texto: 'Reintentar',
                icono: Icons.refresh_rounded,
                alPresionar: _cargar,
              ),
            ] else if (_viajes.isEmpty)
              Tarjeta(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    const Icon(
                      Icons.history_rounded,
                      size: 42,
                      color: MotorutaColors.brand800,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      _periodo.filtra
                          ? 'No hay viajes en este período'
                          : 'Todavía no hay viajes',
                      textAlign: TextAlign.center,
                      style: MotorutaTheme.titulo(tamano: 18),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      _periodo.filtra
                          ? 'Prueba con otro período o vuelve a revisar más tarde.'
                          : esConductor
                              ? 'Cuando completes tu primer viaje aparecerá aquí.'
                              : 'Cuando pidas tu primer viaje aparecerá aquí.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 13.5,
                        height: 1.4,
                        color: MotorutaColors.ink600,
                      ),
                    ),
                  ],
                ),
              )
            else
              ..._viajes.map(
                (viaje) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _TarjetaHistorial(
                    viaje: viaje,
                    alTocar: () => _abrir(viaje),
                  ),
                ),
              ),
            if (!_cargando && _error == null && _viajes.isNotEmpty && _hayMas)
              Padding(
                padding: const EdgeInsets.only(top: 2, bottom: 8),
                child: BotonSecundario(
                  texto: _cargandoMas ? 'Cargando más...' : 'Ver más viajes',
                  icono: _cargandoMas ? null : Icons.expand_more_rounded,
                  alPresionar: _cargandoMas ? null : _cargarMas,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Filtros rápidos del historial del conductor.
class _FiltroPeriodo extends StatelessWidget {
  const _FiltroPeriodo({
    required this.seleccionado,
    required this.alCambiar,
  });

  final PeriodoHistorial seleccionado;
  final ValueChanged<PeriodoHistorial> alCambiar;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: PeriodoHistorial.values.map((periodo) {
        return ChoiceChip(
          label: Text(periodo.etiqueta),
          selected: seleccionado == periodo,
          showCheckmark: false,
          selectedColor: MotorutaColors.brand100,
          side: BorderSide(
            color: seleccionado == periodo
                ? MotorutaColors.brand300
                : MotorutaColors.ink200,
          ),
          labelStyle: TextStyle(
            fontSize: 13.5,
            fontWeight: FontWeight.w600,
            color: seleccionado == periodo
                ? MotorutaColors.brand800
                : MotorutaColors.ink600,
          ),
          onSelected: (_) => alCambiar(periodo),
        );
      }).toList(),
    );
  }
}

class _TarjetaHistorial extends StatelessWidget {
  const _TarjetaHistorial({required this.viaje, required this.alTocar});

  final Viaje viaje;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    return Tarjeta(
      onTap: alTocar,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  horaViaje(
                    viaje.completadoEn ?? viaje.canceladoEn ?? viaje.pedidoEn,
                  ),
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: MotorutaColors.ink500,
                  ),
                ),
              ),
              etiquetaEstadoViaje(viaje),
            ],
          ),
          const SizedBox(height: 12),
          LineaRuta(
            origen: viaje.origen,
            destino: viaje.destino,
            compacta: true,
          ),
          const SizedBox(height: 12),
          Row(
            children: <Widget>[
              Text(
                distanciaViaje(viaje.distanciaKm),
                style: const TextStyle(
                  fontSize: 12.5,
                  color: MotorutaColors.ink500,
                ),
              ),
              const SizedBox(width: 10),
              Text(
                '· ${viaje.duracionMinutos} min',
                style: const TextStyle(
                  fontSize: 12.5,
                  color: MotorutaColors.ink500,
                ),
              ),
              const Spacer(),
              Text(
                montoUsd(viaje.tarifa.total),
                style: MotorutaTheme.titulo(tamano: 17),
              ),
              const SizedBox(width: 4),
              const Icon(
                Icons.chevron_right_rounded,
                size: 20,
                color: MotorutaColors.ink400,
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            viaje.metodoPagoEtiqueta.isEmpty
                ? (viaje.enEfectivo ? 'Efectivo' : 'Billetera')
                : viaje.metodoPagoEtiqueta,
            style: const TextStyle(
              fontSize: 12,
              color: MotorutaColors.ink500,
            ),
          ),
            if (viaje.completado && viaje.calificacion != null) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                '${viaje.calificacion!.textoEstrellas} Tu calificación',
                style: const TextStyle(
                  fontSize: 12.5,
                  color: MotorutaColors.amarillo,
                ),
              ),
            ] else if (viaje.completado && viaje.puedoCalificar) ...<Widget>[
              const SizedBox(height: 8),
              const Text(
                'Sin calificar · toca para calificar el viaje',
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                  color: MotorutaColors.brand700,
                ),
              ),
            ],
          ],
        ),
      );
    }
  }

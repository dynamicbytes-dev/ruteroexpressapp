import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../core/ui/campos.dart';
import '../../../data/models/viaje.dart';
import '../../billetera/billetera_ui.dart';
import '../../sesion/sesion_controller.dart';
import '../ubicacion_service.dart';
import '../viajes_ui.dart';
import 'viaje_pasajero_screen.dart';

/// Elegir origen y destino, ver el precio y pedir el viaje.
class PedirViajeScreen extends StatefulWidget {
  const PedirViajeScreen({super.key});

  @override
  State<PedirViajeScreen> createState() => _PedirViajeScreenState();
}

class _PedirViajeScreenState extends State<PedirViajeScreen> {
  final TextEditingController _buscarOrigen = TextEditingController();
  final TextEditingController _buscarDestino = TextEditingController();
  final TextEditingController _latPrueba = TextEditingController(
    text: '10.5003',
  );
  final TextEditingController _lngPrueba = TextEditingController(
    text: '-66.8428',
  );

  Timer? _debounceOrigen;
  Timer? _debounceDestino;

  PuntoViaje? _origen;
  PuntoViaje? _destino;
  List<LugarGeocodificado> _resultadosOrigen = const <LugarGeocodificado>[];
  List<LugarGeocodificado> _resultadosDestino = const <LugarGeocodificado>[];
  bool _buscandoOrigen = false;
  bool _buscandoDestino = false;
  bool _ubicando = false;
  bool _cotizando = false;
  bool _geocodingDisponible = true;
  String _tipoVehiculo = 'moto';
  String? _error;

  bool get _esCarro => _tipoVehiculo == 'carro';

  @override
  void dispose() {
    _debounceOrigen?.cancel();
    _debounceDestino?.cancel();
    _buscarOrigen.dispose();
    _buscarDestino.dispose();
    _latPrueba.dispose();
    _lngPrueba.dispose();
    super.dispose();
  }

  Future<void> _ubicarme({bool silencioso = false}) async {
    final sesion = context.read<SesionController>();

    setState(() {
      _ubicando = true;
      if (!silencioso) _error = null;
    });

    try {
      final posicion = await const UbicacionService().actual();
      var direccion = 'Mi ubicación actual';

      try {
        final busqueda = await sesion.direccionDe(
          posicion.latitude,
          posicion.longitude,
        );
        if (busqueda.direccion != null) direccion = busqueda.direccion!;
      } on ApiException {
        // Si falla la dirección, la coordenada sigue sirviendo.
      }

      if (!mounted) return;

      setState(() {
        _origen = PuntoViaje(
          direccion: direccion,
          lat: posicion.latitude,
          lng: posicion.longitude,
        );
        _buscarOrigen.text = direccion;
        _resultadosOrigen = const <LugarGeocodificado>[];
        _error = null;
      });
    } on UbicacionException catch (error) {
      if (!mounted) return;
      if (!silencioso) {
        setState(() => _error = error.mensaje);
      }
    } finally {
      if (mounted) setState(() => _ubicando = false);
    }
  }

  Future<void> _buscarDireccionOrigen({bool silencioso = false}) async {
    final consulta = _buscarOrigen.text.trim();

    if (consulta.length < 3) {
      if (!silencioso) {
        setState(() =>
            _error = 'Escribe al menos tres caracteres para buscar el origen.');
      }
      return;
    }

    setState(() {
      _buscandoOrigen = true;
      if (!silencioso) _error = null;
    });

    try {
      final resultado =
          await context.read<SesionController>().buscarLugares(consulta);

      if (!mounted) return;

      setState(() {
        _resultadosOrigen = resultado.lugares;
        _geocodingDisponible = resultado.disponible;
        if (!silencioso) {
          _error = resultado.disponible && resultado.lugares.isEmpty
              ? 'No encontramos ese punto de recogida. Prueba con otro nombre.'
              : null;
        }
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      if (!silencioso) setState(() => _error = error.mensaje);
    } finally {
      if (mounted) setState(() => _buscandoOrigen = false);
    }
  }

  void _alCambiarOrigen(String texto) {
    _debounceOrigen?.cancel();
    final consulta = texto.trim();
    if (consulta.isEmpty) {
      setState(() {
        _resultadosOrigen = const <LugarGeocodificado>[];
        _origen = null;
      });
      return;
    }
    if (consulta.length >= 3) {
      _debounceOrigen = Timer(const Duration(milliseconds: 400), () {
        if (mounted) _buscarDireccionOrigen(silencioso: true);
      });
    } else {
      setState(() => _resultadosOrigen = const <LugarGeocodificado>[]);
    }
  }

  void _elegirOrigen(LugarGeocodificado lugar) {
    setState(() {
      _origen = PuntoViaje(
        direccion: lugar.direccion,
        lat: lugar.lat,
        lng: lugar.lng,
      );
      _buscarOrigen.text = lugar.direccion;
      _resultadosOrigen = const <LugarGeocodificado>[];
      _error = null;
    });
  }

  Future<void> _buscarDireccionDestino({bool silencioso = false}) async {
    final consulta = _buscarDestino.text.trim();

    if (consulta.length < 3) {
      if (!silencioso) {
        setState(() => _error = 'Escribe al menos tres caracteres.');
      }
      return;
    }

    setState(() {
      _buscandoDestino = true;
      if (!silencioso) _error = null;
    });

    try {
      final resultado =
          await context.read<SesionController>().buscarLugares(consulta);

      if (!mounted) return;

      setState(() {
        _resultadosDestino = resultado.lugares;
        _geocodingDisponible = resultado.disponible;
        if (!silencioso) {
          _error = resultado.disponible && resultado.lugares.isEmpty
              ? 'No encontramos esa dirección. Prueba con otro nombre.'
              : null;
        }
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      if (!silencioso) setState(() => _error = error.mensaje);
    } finally {
      if (mounted) setState(() => _buscandoDestino = false);
    }
  }

  void _alCambiarDestino(String texto) {
    _debounceDestino?.cancel();
    final consulta = texto.trim();
    if (consulta.isEmpty) {
      setState(() {
        _resultadosDestino = const <LugarGeocodificado>[];
        _destino = null;
      });
      return;
    }
    if (consulta.length >= 3) {
      _debounceDestino = Timer(const Duration(milliseconds: 400), () {
        if (mounted) _buscarDireccionDestino(silencioso: true);
      });
    } else {
      setState(() => _resultadosDestino = const <LugarGeocodificado>[]);
    }
  }

  void _elegirDestino(LugarGeocodificado lugar) {
    setState(() {
      _destino = PuntoViaje(
        direccion: lugar.direccion,
        lat: lugar.lat,
        lng: lugar.lng,
      );
      _buscarDestino.text = lugar.direccion;
      _resultadosDestino = const <LugarGeocodificado>[];
      _error = null;
    });
  }

  Future<void> _tocarMapa(LatLng punto) async {
    final esOrigen = _origen == null;

    if (esOrigen) {
      final origen = PuntoViaje(
        direccion: _buscarOrigen.text.trim().isEmpty
            ? 'Punto de recogida en el mapa'
            : _buscarOrigen.text.trim(),
        lat: punto.latitude,
        lng: punto.longitude,
      );

      setState(() {
        _origen = origen;
        _resultadosOrigen = const <LugarGeocodificado>[];
        _error = null;
      });

      try {
        final busqueda = await context
            .read<SesionController>()
            .direccionDe(punto.latitude, punto.longitude);

        if (mounted && busqueda.direccion != null) {
          setState(() {
            _origen = PuntoViaje(
              direccion: busqueda.direccion!,
              lat: punto.latitude,
              lng: punto.longitude,
            );
            _buscarOrigen.text = busqueda.direccion!;
          });
        }
      } on ApiException {
        // Se queda con "Punto de recogida en el mapa".
      }
    } else {
      final destino = PuntoViaje(
        direccion: _buscarDestino.text.trim().isEmpty
            ? 'Punto en el mapa'
            : _buscarDestino.text.trim(),
        lat: punto.latitude,
        lng: punto.longitude,
      );

      setState(() {
        _destino = destino;
        _resultadosDestino = const <LugarGeocodificado>[];
        _error = null;
      });

      try {
        final busqueda = await context
            .read<SesionController>()
            .direccionDe(punto.latitude, punto.longitude);

        if (mounted && busqueda.direccion != null) {
          setState(() {
            _destino = PuntoViaje(
              direccion: busqueda.direccion!,
              lat: punto.latitude,
              lng: punto.longitude,
            );
            _buscarDestino.text = busqueda.direccion!;
          });
        }
      } on ApiException {
        // Se queda con "Punto en el mapa"; las coordenadas son las correctas.
      }
    }
  }

  void _usarPrueba() {
    setState(() {
      _origen = const PuntoViaje(
        direccion: 'Av. Francisco de Miranda (prueba)',
        lat: 10.4961,
        lng: -66.8517,
      );
      _destino = PuntoViaje(
        direccion: 'Destino de prueba',
        lat: double.tryParse(_latPrueba.text) ?? 10.5003,
        lng: double.tryParse(_lngPrueba.text) ?? -66.8428,
      );
      _buscarOrigen.text = _origen!.direccion;
      _buscarDestino.text = _destino!.direccion;
      _resultadosOrigen = const <LugarGeocodificado>[];
      _resultadosDestino = const <LugarGeocodificado>[];
      _error = null;
    });
  }

  Future<void> _cotizar() async {
    final origen = _origen;
    final destino = _destino;

    if (origen == null || destino == null) {
      setState(() => _error = 'Elige el punto de recogida y el destino.');
      return;
    }

    setState(() {
      _cotizando = true;
      _error = null;
    });

    try {
      final cotizacion = await context.read<SesionController>().cotizarViaje(
            origen: origen,
            destino: destino,
            tipoVehiculo: _tipoVehiculo,
          );

      if (!mounted) return;

      // La cotización ya está lista: se apaga el giro del botón antes de abrir
      // la hoja para que no siga animando detrás del modal.
      setState(() => _cotizando = false);

      final viaje = await showModalBottomSheet<Viaje>(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => _HojaCotizacion(
          origen: origen,
          destino: destino,
          cotizacion: cotizacion,
          tipoVehiculo: _tipoVehiculo,
        ),
      );

      if (!mounted || viaje == null) return;

      await Navigator.of(context).pushReplacement(
        MaterialPageRoute<void>(
          builder: (_) => ViajePasajeroScreen(inicial: viaje),
        ),
      );
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.mensaje);
    } finally {
      if (mounted) setState(() => _cotizando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final listo = _origen != null && _destino != null;

    return Scaffold(
      appBar: AppBar(title: const Text('Pedir un viaje')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
        children: <Widget>[
          MapaViaje(
            origen: _origen,
            destino: _destino,
            altura: 220,
            alTocar: _tocarMapa,
          ),
          const SizedBox(height: 14),
          Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                const Text(
                  '¿En qué quieres viajar?',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: MotorutaColors.ink800,
                  ),
                ),
                const SizedBox(height: 10),
                SegmentedButton<String>(
                  showSelectedIcon: false,
                  segments: const <ButtonSegment<String>>[
                    ButtonSegment<String>(
                      value: 'moto',
                      label: Text('Moto'),
                      icon: Icon(Icons.two_wheeler_rounded),
                    ),
                    ButtonSegment<String>(
                      value: 'carro',
                      label: Text('Carro'),
                      icon: Icon(Icons.directions_car_rounded),
                    ),
                  ],
                  selected: <String>{_tipoVehiculo},
                  onSelectionChanged: _cotizando
                      ? null
                      : (seleccion) => setState(() {
                            _tipoVehiculo = seleccion.first;
                          }),
                ),
                const SizedBox(height: 8),
                Text(
                  _esCarro
                      ? 'El carro cuesta \$1.00 más que la moto.'
                      : 'La opción más rápida y económica para moverte.',
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: MotorutaColors.ink500,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                CampoTexto(
                  controlador: _buscarOrigen,
                  etiqueta: 'Punto de recogida (origen)',
                  pista: '¿Dónde te recogemos?',
                  icono: Icons.radio_button_checked_rounded,
                  accionTexto: TextInputAction.search,
                  alCambiar: _alCambiarOrigen,
                  alEnviar: (_) => _buscarDireccionOrigen(),
                  sufijo: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      if (_ubicando)
                        const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 8),
                          child: SizedBox(
                            height: 18,
                            width: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                        )
                      else
                        IconButton(
                          tooltip: 'Usar mi ubicación actual',
                          icon: const Icon(
                            Icons.my_location_rounded,
                            size: 20,
                            color: MotorutaColors.brand800,
                          ),
                          onPressed: _ubicarme,
                        ),
                      IconButton(
                        tooltip: 'Buscar punto de recogida',
                        onPressed:
                            _buscandoOrigen ? null : _buscarDireccionOrigen,
                        icon: _buscandoOrigen
                            ? const SizedBox(
                                height: 18,
                                width: 18,
                                child:
                                    CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Icon(Icons.arrow_forward_rounded),
                      ),
                    ],
                  ),
                ),
                if (_origen != null) ...<Widget>[
                  const SizedBox(height: 8),
                  Row(
                    children: <Widget>[
                      const Icon(
                        Icons.place_rounded,
                        size: 19,
                        color: MotorutaColors.verde,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _origen!.direccion,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w600,
                            color: MotorutaColors.ink900,
                          ),
                        ),
                      ),
                      IconButton(
                        tooltip: 'Quitar recogida',
                        onPressed: () => setState(() {
                          _origen = null;
                          _buscarOrigen.clear();
                          _resultadosOrigen = const <LugarGeocodificado>[];
                        }),
                        icon: const Icon(Icons.close_rounded, size: 19),
                      ),
                    ],
                  ),
                ],
                if (_resultadosOrigen.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 6),
                  ..._resultadosOrigen.map(
                    (lugar) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      dense: true,
                      leading: const Icon(
                        Icons.place_outlined,
                        size: 20,
                        color: MotorutaColors.verde,
                      ),
                      title: Text(
                        lugar.direccion,
                        style: const TextStyle(fontSize: 14),
                      ),
                      onTap: () => _elegirOrigen(lugar),
                    ),
                  ),
                ],
                const Divider(height: 24),
                CampoTexto(
                  controlador: _buscarDestino,
                  etiqueta: '¿A dónde vas? (destino)',
                  pista: 'Dirección o lugar',
                  icono: Icons.flag_rounded,
                  accionTexto: TextInputAction.search,
                  alCambiar: _alCambiarDestino,
                  alEnviar: (_) => _buscarDireccionDestino(),
                  sufijo: IconButton(
                    tooltip: 'Buscar destino',
                    onPressed:
                        _buscandoDestino ? null : _buscarDireccionDestino,
                    icon: _buscandoDestino
                        ? const SizedBox(
                            height: 18,
                            width: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.arrow_forward_rounded),
                  ),
                ),
                if (_destino != null) ...<Widget>[
                  const SizedBox(height: 8),
                  Row(
                    children: <Widget>[
                      const Icon(
                        Icons.flag_rounded,
                        size: 19,
                        color: MotorutaColors.cian,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _destino!.direccion,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w600,
                            color: MotorutaColors.ink900,
                          ),
                        ),
                      ),
                      IconButton(
                        tooltip: 'Quitar destino',
                        onPressed: () => setState(() {
                          _destino = null;
                          _buscarDestino.clear();
                          _resultadosDestino = const <LugarGeocodificado>[];
                        }),
                        icon: const Icon(Icons.close_rounded, size: 19),
                      ),
                    ],
                  ),
                ],
                if (_resultadosDestino.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 6),
                  ..._resultadosDestino.map(
                    (lugar) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      dense: true,
                      leading: const Icon(Icons.place_outlined, size: 20),
                      title: Text(
                        lugar.direccion,
                        style: const TextStyle(fontSize: 14),
                      ),
                      onTap: () => _elegirDestino(lugar),
                    ),
                  ),
                ],
                if (!_geocodingDisponible) ...<Widget>[
                  const SizedBox(height: 8),
                  const Text(
                    'La búsqueda de direcciones no está disponible en este momento. Puedes fijar puntos tocando el mapa.',
                    style:
                        TextStyle(fontSize: 12, color: MotorutaColors.ink500),
                  ),
                ],
                if (kDebugMode) ...<Widget>[
                  const SizedBox(height: 8),
                  ExpansionTile(
                    tilePadding: EdgeInsets.zero,
                    childrenPadding: const EdgeInsets.only(top: 4),
                    title: const Text(
                      'Coordenadas de prueba (desarrollo)',
                      style: TextStyle(fontSize: 13),
                    ),
                    children: <Widget>[
                      Row(
                        children: <Widget>[
                          Expanded(
                            child: CampoTexto(
                              controlador: _latPrueba,
                              etiqueta: 'Latitud destino',
                              tipoTeclado:
                                  const TextInputType.numberWithOptions(
                                decimal: true,
                                signed: true,
                              ),
                              maxLongitud: 12,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: CampoTexto(
                              controlador: _lngPrueba,
                              etiqueta: 'Longitud destino',
                              tipoTeclado:
                                  const TextInputType.numberWithOptions(
                                decimal: true,
                                signed: true,
                              ),
                              maxLongitud: 12,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      BotonSecundario(
                        texto: 'Usar datos de prueba',
                        icono: Icons.science_outlined,
                        alPresionar: _usarPrueba,
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          if (_error != null) ...<Widget>[
            const SizedBox(height: 14),
            Aviso(tono: Tono.error, texto: _error!),
          ],
          if (listo) ...<Widget>[
            const SizedBox(height: 14),
            const Tarjeta(
              child: Row(
                children: <Widget>[
                  Icon(
                    Icons.route_rounded,
                    color: MotorutaColors.brand800,
                  ),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Recogida y destino listos. Calcula el precio para confirmar.',
                      style: TextStyle(
                        fontSize: 13.5,
                        height: 1.35,
                        color: MotorutaColors.ink600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 18),
          BotonPrincipal(
            texto: _cotizando ? 'Calculando…' : 'Cotizar viaje',
            icono: Icons.calculate_outlined,
            cargando: _cotizando,
            alPresionar: listo && !_cotizando ? _cotizar : null,
          ),
        ],
      ),
    );
  }
}

/// Hoja con el desglose, el método de pago y la confirmación.
class _HojaCotizacion extends StatefulWidget {
  const _HojaCotizacion({
    required this.origen,
    required this.destino,
    required this.cotizacion,
    required this.tipoVehiculo,
  });

  final PuntoViaje origen;
  final PuntoViaje destino;
  final CotizacionViaje cotizacion;
  final String tipoVehiculo;

  @override
  State<_HojaCotizacion> createState() => _HojaCotizacionState();
}

class _HojaCotizacionState extends State<_HojaCotizacion> {
  String _pago = 'wallet';
  bool _enviando = false;
  String? _error;

  Future<void> _pedir() async {
    setState(() {
      _enviando = true;
      _error = null;
    });

    try {
      final resultado = await context.read<SesionController>().solicitarViaje(
            origen: widget.origen,
            destino: widget.destino,
            metodoPago: _pago,
            tipoVehiculo: widget.tipoVehiculo,
          );

      if (!mounted) return;

      if (resultado.viaje.estado == 'no_driver') {
        setState(() {
          _error = resultado.mensaje ??
              'No encontramos conductores cerca. Inténtalo de nuevo.';
          _enviando = false;
        });
        return;
      }

      Navigator.of(context).pop(resultado.viaje);
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.mensaje;
        _enviando = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final cotizacion = widget.cotizacion;

    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
      ),
      padding: EdgeInsets.fromLTRB(
        22,
        14,
        22,
        22 + MediaQuery.of(context).viewInsets.bottom,
      ),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: MotorutaColors.ink200,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text('Tu viaje', style: MotorutaTheme.titulo(tamano: 22)),
              const SizedBox(height: 14),
              LineaRuta(origen: widget.origen, destino: widget.destino),
              const SizedBox(height: 12),
              Row(
                children: <Widget>[
                  Icon(
                    cotizacion.tipoVehiculo == 'carro'
                        ? Icons.directions_car_rounded
                        : Icons.two_wheeler_rounded,
                    size: 19,
                    color: MotorutaColors.brand800,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    cotizacion.tipoVehiculoEtiqueta,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: MotorutaColors.ink700,
                    ),
                  ),
                  if (cotizacion.recargoVehiculo > 0) ...<Widget>[
                    const SizedBox(width: 6),
                    Text(
                      '+${montoUsd(cotizacion.recargoVehiculo)} por carro',
                      style: const TextStyle(
                        fontSize: 12,
                        color: MotorutaColors.ink500,
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 16),
              Row(
                children: <Widget>[
                  _Dato(
                    etiqueta: 'Distancia',
                    valor: distanciaViaje(cotizacion.distanciaKm),
                  ),
                  _Dato(
                    etiqueta: 'Duración',
                    valor: '${cotizacion.duracionMinutos} min',
                  ),
                  _Dato(
                    etiqueta: 'Precio',
                    valor: montoUsd(cotizacion.total),
                    destacado: true,
                  ),
                ],
              ),
              const SizedBox(height: 16),
              if (cotizacion.minimoAplicado)
                const Padding(
                  padding: EdgeInsets.only(bottom: 8),
                  child: Text(
                    'Se aplicó la tarifa mínima.',
                    style:
                        TextStyle(fontSize: 12, color: MotorutaColors.ink500),
                  ),
                ),
              if (cotizacion.recargoNocturno > 0)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    'Incluye recargo nocturno de ${numeroViaje(cotizacion.recargoNocturno, decimales: 0)}%.',
                    style: const TextStyle(
                      fontSize: 12,
                      color: MotorutaColors.ink500,
                    ),
                  ),
                ),
              if (cotizacion.multiplicador > 1)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    'Tarifa dinámica ×${numeroViaje(cotizacion.multiplicador)}.',
                    style: const TextStyle(
                      fontSize: 12,
                      color: MotorutaColors.ink500,
                    ),
                  ),
                ),
              if (cotizacion.esEstimada)
                const Padding(
                  padding: EdgeInsets.only(bottom: 8),
                  child: Text(
                    'La distancia es una estimación mientras no haya un servicio de rutas disponible.',
                    style:
                        TextStyle(fontSize: 12, color: MotorutaColors.ink500),
                  ),
                ),
              if (cotizacion.totalLocal != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    '≈ ${montoBs(cotizacion.totalLocal!)} a la tasa del día.',
                    style: const TextStyle(
                      fontSize: 12,
                      color: MotorutaColors.ink500,
                    ),
                  ),
                ),
              const SizedBox(height: 4),
              const Divider(),
              const SizedBox(height: 4),
              const Text(
                '¿Cómo pagas?',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: MotorutaColors.ink700,
                ),
              ),
              RadioListTile<String>(
                contentPadding: EdgeInsets.zero,
                value: 'wallet',
                groupValue: _pago,
                onChanged: _enviando
                    ? null
                    : (valor) => setState(() => _pago = valor ?? 'wallet'),
                title: const Text('Billetera GAZ'),
                subtitle: const Text(
                  'Se descuenta del saldo al terminar el viaje.',
                  style: TextStyle(fontSize: 12),
                ),
              ),
              RadioListTile<String>(
                contentPadding: EdgeInsets.zero,
                value: 'cash',
                groupValue: _pago,
                onChanged: _enviando
                    ? null
                    : (valor) => setState(() => _pago = valor ?? 'wallet'),
                title: const Text('Efectivo'),
                subtitle: const Text(
                  'Le pagas al conductor al terminar.',
                  style: TextStyle(fontSize: 12),
                ),
              ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 8),
                Aviso(tono: Tono.error, texto: _error!),
              ],
              const SizedBox(height: 14),
              BotonPrincipal(
                texto: 'Pedir viaje',
                icono: widget.tipoVehiculo == 'carro'
                    ? Icons.directions_car_rounded
                    : Icons.two_wheeler_rounded,
                cargando: _enviando,
                alPresionar: _enviando ? null : _pedir,
              ),
            ],
          ),
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
    return Expanded(
      child: Column(
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
                ? MotorutaTheme.titulo(tamano: 22)
                : const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: MotorutaColors.ink900,
                  ),
          ),
        ],
      ),
    );
  }
}

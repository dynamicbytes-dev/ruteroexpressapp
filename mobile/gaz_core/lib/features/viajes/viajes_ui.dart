import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../../core/theme/motoruta_colors.dart';
import '../../core/ui/avisos.dart';
import '../../data/models/viaje.dart';
import 'ubicacion_service.dart';

final DateFormat _fechaHora = DateFormat('dd/MM · HH:mm', 'es');
final NumberFormat _unaDecimal = NumberFormat('0.0', 'es');
final NumberFormat _dosDecimales = NumberFormat('#,##0.00', 'es');

/// Número con coma decimal, para tasas y distancias.
String numeroViaje(double valor, {int decimales = 2}) =>
    (decimales == 1 ? _unaDecimal : _dosDecimales).format(valor);

String distanciaViaje(double km) => '${_unaDecimal.format(km)} km';

String horaViaje(DateTime? valor) =>
    valor == null ? '—' : _fechaHora.format(valor.toLocal());

/// Color y tono del estado del viaje (los mismos del panel).
Widget etiquetaEstadoViaje(Viaje viaje) {
  final (Tono tono, IconData icono) = switch (viaje.estado) {
    'searching' || 'offered' => (Tono.aviso, Icons.radar_rounded),
    'accepted' || 'arriving' => (Tono.info, Icons.two_wheeler_rounded),
    'waiting' => (Tono.aviso, Icons.access_time_rounded),
    'in_progress' => (Tono.info, Icons.route_rounded),
    'completed' => (Tono.exito, Icons.check_rounded),
    'cancelled' || 'no_driver' => (Tono.error, Icons.close_rounded),
    _ => (Tono.neutro, Icons.circle_outlined),
  };

  return EtiquetaEstado(
    texto: viaje.estadoEtiqueta.isEmpty
        ? viaje.resumenEstado
        : viaje.estadoEtiqueta,
    tono: tono,
    icono: icono,
  );
}

/// Origen y destino con la línea vertical de la marca.
class LineaRuta extends StatelessWidget {
  const LineaRuta({
    super.key,
    required this.origen,
    required this.destino,
    this.compacta = false,
  });

  final PuntoViaje origen;
  final PuntoViaje destino;
  final bool compacta;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SizedBox(
          width: 22,
          child: Column(
            children: <Widget>[
              const SizedBox(height: 4),
              Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  color: MotorutaColors.cian,
                  shape: BoxShape.circle,
                  border: Border.all(color: MotorutaColors.brand800, width: 2),
                ),
              ),
              Container(
                width: 2,
                height: compacta ? 24 : 34,
                color: MotorutaColors.ink200,
              ),
              Container(
                width: 10,
                height: 10,
                decoration: const BoxDecoration(
                  color: MotorutaColors.brand800,
                  shape: BoxShape.circle,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                origen.direccion.isEmpty ? 'Punto de recogida' : origen.direccion,
                maxLines: compacta ? 1 : 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: MotorutaColors.ink900,
                ),
              ),
              SizedBox(height: compacta ? 20 : 30),
              Text(
                destino.direccion.isEmpty ? 'Destino' : destino.direccion,
                maxLines: compacta ? 1 : 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: MotorutaColors.ink900,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Mapa de la ruta con `flutter_map`.
///
/// Usa tiles de Geoapify si hay `GEOAPIFY_API_KEY`; si no, los tiles públicos
/// de OpenStreetMap. No necesita claves nativas ni facturación.
class MapaViaje extends StatefulWidget {
  const MapaViaje({
    super.key,
    this.origen,
    this.destino,
    this.conductor,
    this.altura = 230,
    this.alTocar,
    this.centroInicial,
  });

  final PuntoViaje? origen;
  final PuntoViaje? destino;
  final PuntoViaje? conductor;
  final double altura;
  final ValueChanged<LatLng>? alTocar;
  final LatLng? centroInicial;

  /// Proveedor de tiles alternativo para las pruebas.
  ///
  /// En producción es `null` y `flutter_map` usa su proveedor de red. En
  /// `flutter test` no hay red, así que las pruebas inyectan uno que devuelve
  /// un tile transparente.
  static TileProvider? proveedorTilesPrueba;

  @override
  State<MapaViaje> createState() => _MapaViajeState();
}

class _MapaViajeState extends State<MapaViaje> {
  final MapController _controlador = MapController();

  List<LatLng> get _puntos => <LatLng>[
        if (widget.origen != null)
          LatLng(widget.origen!.lat, widget.origen!.lng),
        if (widget.destino != null)
          LatLng(widget.destino!.lat, widget.destino!.lng),
        if (widget.conductor != null)
          LatLng(widget.conductor!.lat, widget.conductor!.lng),
      ];

  LatLng get _centro {
    if (widget.centroInicial != null) return widget.centroInicial!;

    final puntos = _puntos;

    if (puntos.isNotEmpty) return puntos.first;

    return const LatLng(10.4961, -66.8517); // Caracas
  }

  List<Marker> get _marcadores => <Marker>[
        if (widget.origen != null)
          Marker(
            point: LatLng(widget.origen!.lat, widget.origen!.lng),
            width: 38,
            height: 38,
            child: const _PinMapa(
              icono: Icons.trip_origin,
              color: MotorutaColors.cian,
              etiqueta: 'Recogida',
            ),
          ),
        if (widget.destino != null)
          Marker(
            point: LatLng(widget.destino!.lat, widget.destino!.lng),
            width: 38,
            height: 38,
            child: const _PinMapa(
              icono: Icons.location_on,
              color: MotorutaColors.brand800,
              etiqueta: 'Destino',
            ),
          ),
        if (widget.conductor != null)
          Marker(
            point: LatLng(widget.conductor!.lat, widget.conductor!.lng),
            width: 42,
            height: 42,
            child: const _PinMapa(
              icono: Icons.two_wheeler,
              color: MotorutaColors.brand900,
              etiqueta: 'Conductor',
            ),
          ),
      ];

  List<Polyline> get _lineas => <Polyline>[
        if (widget.origen != null && widget.destino != null)
          Polyline(
            points: <LatLng>[
              LatLng(widget.origen!.lat, widget.origen!.lng),
              LatLng(widget.destino!.lat, widget.destino!.lng),
            ],
            color: MotorutaColors.brand800,
            strokeWidth: 4,
          ),
      ];

  @override
  void initState() {
    super.initState();
    _ajustarVista();
  }

  @override
  void didUpdateWidget(covariant MapaViaje oldWidget) {
    super.didUpdateWidget(oldWidget);

    final cambioOrigen = oldWidget.origen?.lat != widget.origen?.lat ||
        oldWidget.origen?.lng != widget.origen?.lng;
    final cambioDestino = oldWidget.destino?.lat != widget.destino?.lat ||
        oldWidget.destino?.lng != widget.destino?.lng;
    final cambioConductor = oldWidget.conductor?.lat != widget.conductor?.lat ||
        oldWidget.conductor?.lng != widget.conductor?.lng;

    if (cambioOrigen || cambioDestino || cambioConductor) {
      _ajustarVista();
    }
  }

  void _ajustarVista() {
    final puntos = _puntos;

    if (puntos.isEmpty) return;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;

      _controlador.fitCamera(
        CameraFit.coordinates(
          coordinates: puntos,
          padding: const EdgeInsets.all(42),
          maxZoom: 16,
        ),
      );
    });
  }

  @override
  void dispose() {
    _controlador.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(18),
      child: SizedBox(
        height: widget.altura,
        child: Stack(
          children: <Widget>[
            FlutterMap(
              mapController: _controlador,
              options: MapOptions(
                initialCenter: _centro,
                initialZoom: 13,
                onTap: (_, punto) => widget.alTocar?.call(punto),
                interactionOptions: const InteractionOptions(
                  // Dentro de un ListView: el arrastre de un dedo desplaza la
                  // pantalla, no el mapa. El pellizco sigue haciendo zoom y el
                  // toque sigue fijando el destino.
                  flags: InteractiveFlag.all &
                      ~InteractiveFlag.drag &
                      ~InteractiveFlag.rotate,
                ),
              ),
              children: <Widget>[
                TileLayer(
                  urlTemplate: MapaConfig.urlTiles,
                  userAgentPackageName: 'app.motoruta',
                  maxNativeZoom: 19,
                  tileProvider: MapaViaje.proveedorTilesPrueba,
                ),
                if (_lineas.isNotEmpty) PolylineLayer(polylines: _lineas),
                MarkerLayer(markers: _marcadores),
              ],
            ),
            Positioned(
              right: 6,
              bottom: 4,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.82),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  '© ${MapaConfig.atribucion}',
                  style: const TextStyle(
                    fontSize: 9,
                    color: MotorutaColors.ink600,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Pin con la paleta de la marca para los puntos del mapa.
class _PinMapa extends StatelessWidget {
  const _PinMapa({
    required this.icono,
    required this.color,
    required this.etiqueta,
  });

  final IconData icono;
  final Color color;
  final String etiqueta;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: etiqueta,
      child: Container(
        decoration: BoxDecoration(
          color: color,
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white, width: 2.5),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withOpacity(0.22),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Icon(icono, color: Colors.white, size: 21),
      ),
    );
  }
}

/// Distancia en línea recta entre dos puntos (solo para el resumen local).
double distanciaAproximadaKm(PuntoViaje a, PuntoViaje b) {
  const radioTierra = 6371.0;
  final dLat = _rad(b.lat - a.lat);
  final dLng = _rad(b.lng - a.lng);
  final h = math.sin(dLat / 2) * math.sin(dLat / 2) +
      math.cos(_rad(a.lat)) *
          math.cos(_rad(b.lat)) *
          math.sin(dLng / 2) *
          math.sin(dLng / 2);
  return 2 * radioTierra * math.asin(math.sqrt(h));
}

double _rad(double grados) => grados * math.pi / 180;


/// Diálogo de cancelación usado por pasajero y conductor.
class DialogoCancelarViaje extends StatefulWidget {
  const DialogoCancelarViaje({super.key, required this.esConductor});

  final bool esConductor;

  @override
  State<DialogoCancelarViaje> createState() => _DialogoCancelarViajeState();
}

class _DialogoCancelarViajeState extends State<DialogoCancelarViaje> {
  final TextEditingController _motivo = TextEditingController();

  @override
  void dispose() {
    _motivo.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('¿Cancelar el viaje?'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            widget.esConductor
                ? 'El viaje volverá a búsqueda y contará como rechazo para tu cuenta.'
                : 'Si ya pediste el viaje, avisaremos al conductor.',
            style: const TextStyle(fontSize: 14, height: 1.4),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _motivo,
            textCapitalization: TextCapitalization.sentences,
            maxLength: 200,
            decoration: const InputDecoration(
              labelText: 'Motivo (opcional)',
              counterText: '',
            ),
          ),
        ],
      ),
      actions: <Widget>[
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Seguir con el viaje'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(_motivo.text.trim()),
          child: const Text('Sí, cancelar'),
        ),
      ],
    );
  }
}

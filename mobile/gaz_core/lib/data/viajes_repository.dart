import '../core/api/api_client.dart';
import 'models/favorito.dart';
import 'models/pagina_viajes.dart';
import 'models/periodo_historial.dart';
import 'models/viaje.dart';

/// Llamadas de viajes a la API. Las pantallas nunca arman URLs.
class ViajesRepository {
  ViajesRepository(this._api);

  final ApiClient _api;

  Future<CotizacionViaje> cotizar({
    required PuntoViaje origen,
    required PuntoViaje destino,
    String tipoVehiculo = 'moto',
  }) async {
    final respuesta = await _api.post(
      '/api/viajes/cotizar',
      datos: <String, dynamic>{
        'origin_lat': origen.lat,
        'origin_lng': origen.lng,
        'origin_address': origen.direccion,
        'destination_lat': destino.lat,
        'destination_lng': destino.lng,
        'destination_address': destino.direccion,
        'vehicle_type': tipoVehiculo,
      },
    );

    return CotizacionViaje.desdeJson(respuesta['data']);
  }

  Future<ResultadoViaje> solicitar({
    required PuntoViaje origen,
    required PuntoViaje destino,
    required String metodoPago,
    String tipoVehiculo = 'moto',
  }) async {
    final respuesta = await _api.post(
      '/api/viajes',
      datos: <String, dynamic>{
        'origin_lat': origen.lat,
        'origin_lng': origen.lng,
        'origin_address': origen.direccion,
        'destination_lat': destino.lat,
        'destination_lng': destino.lng,
        'destination_address': destino.direccion,
        'payment_method': metodoPago,
        'vehicle_type': tipoVehiculo,
      },
    );

    return ResultadoViaje(
      viaje: _viajeDeRespuesta(respuesta),
      mensaje: _texto(respuesta['message']),
    );
  }

  Future<Viaje> detalle(int id) async {
    final respuesta = await _api.get('/api/viajes/$id');
    return _viajeDeRespuesta(respuesta);
  }

  Future<Viaje?> activo() async {
    final respuesta = await _api.get('/api/viajes/activo');
    return _viajeONull(respuesta);
  }

  Future<List<Viaje>> historial({
    PeriodoHistorial periodo = PeriodoHistorial.todos,
  }) async {
    final pagina = await historialPaginado(periodo: periodo);
    return pagina.viajes;
  }

  /// Lee una página del historial. La primera página se pide con [pagina] 1.
  Future<PaginaViajes> historialPaginado({
    PeriodoHistorial periodo = PeriodoHistorial.todos,
    int pagina = 1,
  }) async {
    final parametros = <String, String>{
      if (periodo.apiValue != null) 'period': periodo.apiValue!,
      if (pagina > 1) 'page': '$pagina',
    };

    final ruta = parametros.isEmpty
        ? '/api/viajes'
        : Uri(path: '/api/viajes', queryParameters: parametros).toString();

    final respuesta = await _api.get(ruta);
    final meta = _mapa(respuesta['meta']);
    final paginaActual = _entero(meta['current_page'], porDefecto: pagina);
    final ultimaPagina = _entero(meta['last_page'], porDefecto: paginaActual);

    return PaginaViajes(
      viajes: _lista(respuesta).map(Viaje.desdeJson).toList(),
      pagina: paginaActual,
      ultimaPagina: ultimaPagina,
    );
  }

  Future<ResultadoViaje> cancelar(int id, {String? motivo}) async {
    final respuesta = await _api.post(
      '/api/viajes/$id/cancelar',
      datos: <String, dynamic>{
        if (motivo != null && motivo.trim().isNotEmpty) 'reason': motivo.trim(),
      },
    );

    return ResultadoViaje(
      viaje: _viajeDeRespuesta(respuesta),
      mensaje: _texto(respuesta['message']),
    );
  }

  /// Califica el viaje completado. `favorito` marca al conductor de una vez.
  Future<Viaje> calificar(
    int id, {
    required int estrellas,
    String? comentario,
    bool favorito = false,
  }) async {
    final respuesta = await _api.post(
      '/api/viajes/$id/calificar',
      datos: <String, dynamic>{
        'rating': estrellas,
        if (comentario != null && comentario.trim().isNotEmpty)
          'comment': comentario.trim(),
        'favorite': favorito,
      },
    );

    return _viajeDeRespuesta(respuesta);
  }

  Future<List<ConductorFavorito>> favoritos() async {
    final respuesta = await _api.get('/api/conductores-favoritos');
    return _lista(respuesta).map(ConductorFavorito.desdeJson).toList();
  }

  /// Marca o quita un conductor favorito. Devuelve el estado final.
  Future<bool> marcarFavorito(int conductorId, bool favorito) async {
    final ruta = '/api/conductores-favoritos/$conductorId';

    if (favorito) {
      await _api.post(ruta);
    } else {
      await _api.delete(ruta);
    }

    return favorito;
  }

  /// Calificaciones recibidas por el conductor autenticado.
  Future<List<CalificacionRecibida>> calificacionesConductor() async {
    final respuesta = await _api.get('/api/conductor/calificaciones');
    return _lista(respuesta).map(CalificacionRecibida.desdeJson).toList();
  }

  Future<EstadoConductor> estadoConductor() async {
    final respuesta = await _api.get('/api/conductor/estado');
    return EstadoConductor.desdeJson(respuesta['data']);
  }

  Future<EstadoConductor> disponibilidad(bool enLinea) async {
    final respuesta = await _api.put(
      '/api/conductor/disponibilidad',
      datos: <String, dynamic>{'online': enLinea},
    );

    return EstadoConductor.desdeJson(respuesta['data']);
  }

  Future<void> reportarUbicacion({
    required double lat,
    required double lng,
    int? rumbo,
    double? velocidad,
  }) async {
    await _api.post(
      '/api/conductor/ubicacion',
      datos: <String, dynamic>{
        'lat': lat,
        'lng': lng,
        if (rumbo != null) 'bearing': rumbo,
        if (velocidad != null) 'speed_kmh': velocidad,
      },
    );
  }

  Future<List<OfertaViaje>> ofertas() async {
    final respuesta = await _api.get('/api/conductor/ofertas');
    return _lista(respuesta).map(OfertaViaje.desdeJson).toList();
  }

  Future<ResultadoAccionViaje> aceptarOferta(int id) async {
    final respuesta = await _api.post('/api/ofertas/$id/aceptar');
    return ResultadoAccionViaje(
      viaje: _viajeDeRespuesta(respuesta),
      mensaje: _texto(respuesta['message']),
    );
  }

  Future<String?> rechazarOferta(int id) async {
    final respuesta = await _api.post('/api/ofertas/$id/rechazar');
    return _texto(respuesta['message']);
  }

  Future<Viaje?> viajeActivoConductor() async {
    final respuesta = await _api.get('/api/conductor/viaje-activo');
    return _viajeONull(respuesta);
  }

  Future<ResultadoAccionViaje> avanzar(int id, String accion) async {
    final respuesta = await _api.post('/api/viajes/$id/$accion');
    return ResultadoAccionViaje(
      viaje: _viajeDeRespuesta(respuesta),
      mensaje: _texto(respuesta['message']),
    );
  }

  Future<BusquedaLugares> buscarLugares(String consulta) async {
    final respuesta = await _api.get(
      '/api/ubicaciones/buscar?q=${Uri.encodeQueryComponent(consulta.trim())}',
    );
    final datos = _mapa(respuesta['data']);

    return BusquedaLugares(
      disponible: datos['available'] == true,
      lugares: datos['results'] is List
          ? (datos['results'] as List)
              .map(LugarGeocodificado.desdeJson)
              .toList()
          : const <LugarGeocodificado>[],
    );
  }

  Future<BusquedaLugares> direccionDe(double lat, double lng) async {
    final respuesta = await _api.get(
      '/api/ubicaciones/reversa?lat=$lat&lng=$lng',
    );
    final datos = _mapa(respuesta['data']);
    final resultado = datos['result'];

    return BusquedaLugares(
      disponible: datos['available'] == true,
      direccion: resultado is Map ? _texto(resultado['address']) : null,
      lugares: const <LugarGeocodificado>[],
    );
  }

  Viaje _viajeDeRespuesta(Map<String, dynamic> respuesta) {
    final datos = _mapa(respuesta['data']);
    final viaje = datos['trip'];

    if (viaje == null) {
      throw StateError('La API no devolvió el viaje.');
    }

    return Viaje.desdeJson(viaje);
  }

  Viaje? _viajeONull(Map<String, dynamic> respuesta) {
    final datos = _mapa(respuesta['data']);
    final viaje = datos['trip'];
    return viaje == null ? null : Viaje.desdeJson(viaje);
  }

  static List<Map<String, dynamic>> _lista(Map<String, dynamic> respuesta) {
    final datos = respuesta['data'];

    if (datos is List) {
      return datos.whereType<Map>().map(Map<String, dynamic>.from).toList();
    }

    if (datos is Map && datos['data'] is List) {
      return (datos['data'] as List)
          .whereType<Map>()
          .map(Map<String, dynamic>.from)
          .toList();
    }

    return const <Map<String, dynamic>>[];
  }

  static Map<String, dynamic> _mapa(Object? valor) {
    if (valor is Map<String, dynamic>) return valor;
    if (valor is Map) return Map<String, dynamic>.from(valor);
    return const <String, dynamic>{};
  }

  static String? _texto(Object? valor) {
    if (valor == null) return null;
    final texto = '$valor'.trim();
    return texto.isEmpty ? null : texto;
  }

  static int _entero(Object? valor, {required int porDefecto}) {
    if (valor is int) return valor;
    return int.tryParse('$valor') ?? porDefecto;
  }
}

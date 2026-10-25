/// Modelos de viajes tal como los devuelve el backend (fase F3).
///
/// Igual que el resto de modelos, no inventan datos: si un campo no viene,
/// queda en `null` o en un valor neutro y la pantalla lo muestra como "—".
library;

/// Punto de recogida o destino.
class PuntoViaje {
  const PuntoViaje({
    required this.direccion,
    required this.lat,
    required this.lng,
  });

  final String direccion;
  final double lat;
  final double lng;

  factory PuntoViaje.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    return PuntoViaje(
      direccion: _texto(json['address']) ?? '',
      lat: _decimal(json['lat']),
      lng: _decimal(json['lng']),
    );
  }

  Map<String, dynamic> aJson() => <String, dynamic>{
        'lat': lat,
        'lng': lng,
        'address': direccion,
      };
}

/// Desglose del precio que calculó el backend.
class TarifaViaje {
  const TarifaViaje({
    required this.base,
    required this.precioKm,
    required this.precioMinuto,
    required this.minimo,
    required this.minimoAplicado,
    required this.multiplicador,
    required this.recargoNocturno,
    required this.subtotal,
    required this.total,
    required this.comisionPorcentaje,
    required this.comision,
    required this.gananciaConductor,
    this.tasaCambio,
    this.totalLocal,
    this.recargoVehiculo = 0,
  });

  final double base;
  final double precioKm;
  final double precioMinuto;
  final double minimo;
  final bool minimoAplicado;
  final double multiplicador;
  final double recargoNocturno;
  final double subtotal;
  final double total;
  final double comisionPorcentaje;
  final double comision;
  final double gananciaConductor;
  final double? tasaCambio;
  final double? totalLocal;
  final double recargoVehiculo;

  factory TarifaViaje.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    final total = _decimal(json['total']);

    return TarifaViaje(
      base: _decimal(json['base']),
      precioKm: _decimal(json['price_per_km']),
      precioMinuto: _decimal(json['price_per_minute']),
      minimo: _decimal(json['minimum']),
      minimoAplicado: json['minimum_applied'] == true,
      multiplicador: _decimal(json['dynamic_multiplier'], 1),
      recargoNocturno: _decimal(json['night_surcharge_percent']),
      subtotal: _decimal(json['subtotal']),
      total: total,
      comisionPorcentaje: _decimal(json['commission_percent'], 10),
      comision: _decimal(json['commission_amount']),
      gananciaConductor: _decimal(json['driver_earning']),
      tasaCambio: _decimalONull(json['exchange_rate']),
      totalLocal: _decimalONull(json['local_total']),
      recargoVehiculo: _decimal(json['vehicle_surcharge']),
    );
  }
}

/// Conductor asignado. Solo llega con nombre y teléfono cuando el viaje ya fue
/// aceptado; antes de eso la API lo devuelve en `null`.
class ConductorViaje {
  const ConductorViaje({
    required this.id,
    required this.nombre,
    required this.telefono,
    this.calificacion,
    this.viajesCompletados,
    this.vehiculo,
    this.esFavorito = false,
    this.lat,
    this.lng,
    this.ubicacionActualizada,
    this.fotoUrl,
  });

  final int id;
  final String nombre;
  final String telefono;
  final double? calificacion;
  final int? viajesCompletados;
  final String? vehiculo;
  final bool esFavorito;
  final double? lat;
  final double? lng;
  final DateTime? ubicacionActualizada;
  final String? fotoUrl;

  factory ConductorViaje.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};
    final ubicacion = json['location'] is Map
        ? Map<String, dynamic>.from(json['location'] as Map)
        : const <String, dynamic>{};

    return ConductorViaje(
      id: _entero(json['id']),
      nombre: _texto(json['name']) ?? 'Conductor',
      telefono: _texto(json['phone']) ?? '',
      calificacion: _decimalONull(json['rating']),
      viajesCompletados: json['trips_completed'] is num
          ? (json['trips_completed'] as num).toInt()
          : null,
      vehiculo: _texto(json['vehicle']),
      esFavorito: json['is_favorite'] == true,
      lat: _decimalONull(ubicacion['lat']),
      lng: _decimalONull(ubicacion['lng']),
      ubicacionActualizada: _fecha(json['location_updated_at']),
      fotoUrl: _texto(json['photo_url']) ?? _texto(json['avatar']),
    );
  }

  /// Copia con la posición que acaba de reportar el conductor.
  ConductorViaje conUbicacion(
    double nuevaLat,
    double nuevaLng,
    DateTime? actualizada,
  ) {
    return ConductorViaje(
      id: id,
      nombre: nombre,
      telefono: telefono,
      calificacion: calificacion,
      viajesCompletados: viajesCompletados,
      vehiculo: vehiculo,
      esFavorito: esFavorito,
      lat: nuevaLat,
      lng: nuevaLng,
      ubicacionActualizada: actualizada,
      fotoUrl: fotoUrl,
    );
  }

  /// Copia con la marca de favorito actualizada.
  ConductorViaje conFavorito(bool favorito) {
    return ConductorViaje(
      id: id,
      nombre: nombre,
      telefono: telefono,
      calificacion: calificacion,
      viajesCompletados: viajesCompletados,
      vehiculo: vehiculo,
      esFavorito: favorito,
      lat: lat,
      lng: lng,
      ubicacionActualizada: ubicacionActualizada,
      fotoUrl: fotoUrl,
    );
  }
}

/// Calificación que el pasajero dejó al conductor.
class CalificacionViaje {
  const CalificacionViaje({
    required this.estrellas,
    this.comentario,
    this.fecha,
  });

  final int estrellas;
  final String? comentario;
  final DateTime? fecha;

  String get textoEstrellas =>
      '★' * estrellas + '☆' * (5 - estrellas);

  factory CalificacionViaje.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    return CalificacionViaje(
      estrellas: _entero(json['rating']),
      comentario: _texto(json['comment']),
      fecha: _fecha(json['created_at']),
    );
  }
}

/// Pasajero visto por el conductor (incluye teléfono al aceptar).
class PasajeroViaje {
  const PasajeroViaje({
    required this.id,
    required this.nombre,
    required this.telefono,
    this.fotoUrl,
  });

  final int id;
  final String nombre;
  final String telefono;
  final String? fotoUrl;

  factory PasajeroViaje.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    return PasajeroViaje(
      id: _entero(json['id']),
      nombre: _texto(json['name']) ?? 'Pasajero',
      telefono: _texto(json['phone']) ?? '',
      fotoUrl: _texto(json['photo_url']) ?? _texto(json['avatar']),
    );
  }
}

/// Viaje completo.
class Viaje {
  const Viaje({
    required this.id,
    required this.estado,
    required this.estadoEtiqueta,
    required this.metodoPago,
    required this.metodoPagoEtiqueta,
    this.tipoVehiculo = 'moto',
    this.tipoVehiculoEtiqueta = 'Moto',
    required this.origen,
    required this.destino,
    required this.distanciaKm,
    required this.duracionMinutos,
    required this.tarifa,
    this.conductor,
    this.pasajero,
    this.ofertaActual,
    this.calificacion,
    this.puedoCalificar = false,
    this.puedeCancelar = false,
    this.pedidoEn,
    this.aceptadoEn,
    this.llegadaEn,
    this.iniciadoEn,
    this.completadoEn,
    this.canceladoEn,
    this.canceladoPor,
    this.motivoCancelacion,
  });

  final int id;
  final String estado;
  final String estadoEtiqueta;
  final String metodoPago;
  final String metodoPagoEtiqueta;
  final String tipoVehiculo;
  final String tipoVehiculoEtiqueta;
  final PuntoViaje origen;
  final PuntoViaje destino;
  final double distanciaKm;
  final int duracionMinutos;
  final TarifaViaje tarifa;
  final ConductorViaje? conductor;
  final PasajeroViaje? pasajero;
  final OfertaViaje? ofertaActual;
  final CalificacionViaje? calificacion;
  final bool puedoCalificar;
  final bool puedeCancelar;
  final DateTime? pedidoEn;
  final DateTime? aceptadoEn;
  final DateTime? llegadaEn;
  final DateTime? iniciadoEn;
  final DateTime? completadoEn;
  final DateTime? canceladoEn;
  final String? canceladoPor;
  final String? motivoCancelacion;

  static const Set<String> _activos = <String>{
    'searching',
    'offered',
    'accepted',
    'arriving',
    'waiting',
    'in_progress',
  };

  static const Set<String> _enCurso = <String>{
    'accepted',
    'arriving',
    'waiting',
    'in_progress',
  };

  bool get activo => _activos.contains(estado);
  bool get enCurso => _enCurso.contains(estado);
  bool get buscando => estado == 'searching' || estado == 'offered';
  bool get completado => estado == 'completed';
  bool get cancelado => estado == 'cancelled' || estado == 'no_driver';
  bool get enEfectivo => metodoPago == 'cash';

  /// Título corto para la tarjeta del viaje.
  String get resumenEstado => switch (estado) {
        'searching' => 'Buscando conductor',
        'offered' => 'Esperando respuesta del conductor',
        'accepted' => 'Conductor en camino',
        'arriving' => 'El conductor va a tu encuentro',
        'waiting' => 'El conductor te espera en el punto',
        'in_progress' => 'Viaje en curso',
        'completed' => 'Viaje completado',
        'cancelled' => 'Viaje cancelado',
        'no_driver' => 'No hubo conductor disponible',
        _ => estadoEtiqueta,
      };

  /// Instrucción principal para el conductor.
  String get instruccionConductor => switch (estado) {
        'accepted' => 'Ve al punto de recogida',
        'arriving' => 'Avisa cuando llegues',
        'waiting' => 'Espera al pasajero e inicia el viaje',
        'in_progress' => 'En camino al destino',
        'completed' => 'Viaje completado',
        _ => resumenEstado,
      };

  factory Viaje.desdeJson(Object? crudo) {
    final json = _mapa(crudo);
    final oferta = json['current_offer'];

    return Viaje(
      id: _entero(json['id']),
      estado: _texto(json['status']) ?? 'searching',
      estadoEtiqueta: _texto(json['status_label']) ?? '',
      metodoPago: _texto(json['payment_method']) ?? 'cash',
      metodoPagoEtiqueta: _texto(json['payment_method_label']) ?? '',
      tipoVehiculo: _texto(json['vehicle_type']) ?? 'moto',
      tipoVehiculoEtiqueta: _texto(json['vehicle_type_label']) ?? 'Moto',
      origen: PuntoViaje.desdeJson(json['origin']),
      destino: PuntoViaje.desdeJson(json['destination']),
      distanciaKm: _decimal(json['distance_km']),
      duracionMinutos: _entero(json['duration_minutes']),
      tarifa: TarifaViaje.desdeJson(json['fare']),
      conductor: json['driver'] == null
          ? null
          : _intentar(() => ConductorViaje.desdeJson(json['driver'])),
      pasajero: json['passenger'] == null
          ? null
          : _intentar(() => PasajeroViaje.desdeJson(json['passenger'])),
      ofertaActual:
          oferta == null ? null : OfertaViaje.desdeJson(_mapa(oferta)),
      calificacion: json['rating'] == null
          ? null
          : CalificacionViaje.desdeJson(json['rating']),
      puedoCalificar: json['can_rate'] == true,
      puedeCancelar: json['can_cancel'] == true,
      pedidoEn: _fecha(json['requested_at']),
      aceptadoEn: _fecha(json['accepted_at']),
      llegadaEn: _fecha(json['arrived_at']),
      iniciadoEn: _fecha(json['started_at']),
      completadoEn: _fecha(json['completed_at']),
      canceladoEn: _fecha(json['cancelled_at']),
      canceladoPor: _texto(json['cancelled_by']),
      motivoCancelacion: _texto(json['cancellation_reason']),
    );
  }

  /// Copia el viaje con la nueva ubicación del conductor asignado.
  Viaje conUbicacionConductor(
    double lat,
    double lng,
    DateTime? actualizada,
  ) {
    final actual = conductor;

    if (actual == null) return this;

    return Viaje(
      id: id,
      estado: estado,
      estadoEtiqueta: estadoEtiqueta,
      metodoPago: metodoPago,
      metodoPagoEtiqueta: metodoPagoEtiqueta,
      tipoVehiculo: tipoVehiculo,
      tipoVehiculoEtiqueta: tipoVehiculoEtiqueta,
      origen: origen,
      destino: destino,
      distanciaKm: distanciaKm,
      duracionMinutos: duracionMinutos,
      tarifa: tarifa,
      conductor: actual.conUbicacion(lat, lng, actualizada),
      pasajero: pasajero,
      ofertaActual: ofertaActual,
      calificacion: calificacion,
      puedoCalificar: puedoCalificar,
      puedeCancelar: puedeCancelar,
      pedidoEn: pedidoEn,
      aceptadoEn: aceptadoEn,
      llegadaEn: llegadaEn,
      iniciadoEn: iniciadoEn,
      completadoEn: completadoEn,
      canceladoEn: canceladoEn,
      canceladoPor: canceladoPor,
      motivoCancelacion: motivoCancelacion,
    );
  }

  /// Copia con la marca de favorito del conductor actualizada.
  Viaje conFavorito(bool favorito) {
    final actual = conductor;

    return Viaje(
      id: id,
      estado: estado,
      estadoEtiqueta: estadoEtiqueta,
      metodoPago: metodoPago,
      metodoPagoEtiqueta: metodoPagoEtiqueta,
      tipoVehiculo: tipoVehiculo,
      tipoVehiculoEtiqueta: tipoVehiculoEtiqueta,
      origen: origen,
      destino: destino,
      distanciaKm: distanciaKm,
      duracionMinutos: duracionMinutos,
      tarifa: tarifa,
      conductor: actual?.conFavorito(favorito),
      pasajero: pasajero,
      ofertaActual: ofertaActual,
      calificacion: calificacion,
      puedoCalificar: puedoCalificar,
      puedeCancelar: puedeCancelar,
      pedidoEn: pedidoEn,
      aceptadoEn: aceptadoEn,
      llegadaEn: llegadaEn,
      iniciadoEn: iniciadoEn,
      completadoEn: completadoEn,
      canceladoEn: canceladoEn,
      canceladoPor: canceladoPor,
      motivoCancelacion: motivoCancelacion,
    );
  }
}

/// Alerta de viaje que recibe el conductor.
class OfertaViaje {
  const OfertaViaje({
    required this.id,
    required this.estado,
    required this.distanciaRecogidaKm,
    required this.segundosRestantes,
    required this.viaje,
    this.enviadaEn,
    this.venceEn,
  });

  final int id;
  final String estado;
  final double distanciaRecogidaKm;
  final int segundosRestantes;
  final ResumenOferta viaje;
  final DateTime? enviadaEn;
  final DateTime? venceEn;

  bool get pendiente => estado == 'pending';

  factory OfertaViaje.desdeJson(Object? crudo) {
    final json = _mapa(crudo);

    return OfertaViaje(
      id: _entero(json['id']),
      estado: _texto(json['status']) ?? 'pending',
      distanciaRecogidaKm: _decimal(json['distance_to_pickup_km']),
      segundosRestantes: _entero(json['seconds_left']),
      viaje: ResumenOferta.desdeJson(json['trip']),
      enviadaEn: _fecha(json['offered_at']),
      venceEn: _fecha(json['expires_at']),
    );
  }

  /// Construye un viaje en curso de respaldo si la respuesta del servidor
  /// falla por errores secundarios (ej. serialización del perfil/foto).
  Viaje aViajeEnCurso() => viaje.aViajeEnCurso();
}

/// Lo que ve el conductor antes de aceptar: ruta, pago y ganancia.
class ResumenOferta {
  const ResumenOferta({
    required this.id,
    required this.pasajero,
    required this.origen,
    required this.destino,
    required this.distanciaKm,
    required this.duracionMinutos,
    required this.metodoPago,
    required this.metodoPagoEtiqueta,
    required this.total,
    required this.ganancia,
    required this.comision,
  });

  final int id;
  final String pasajero;
  final PuntoViaje origen;
  final PuntoViaje destino;
  final double distanciaKm;
  final int duracionMinutos;
  final String metodoPago;
  final String metodoPagoEtiqueta;
  final double total;
  final double ganancia;
  final double comision;

  factory ResumenOferta.desdeJson(Object? crudo) {
    final json = _mapa(crudo);

    return ResumenOferta(
      id: _entero(json['id']),
      pasajero: _texto(json['passenger_name']) ?? 'Pasajero',
      origen: PuntoViaje.desdeJson(json['origin']),
      destino: PuntoViaje.desdeJson(json['destination']),
      distanciaKm: _decimal(json['distance_km']),
      duracionMinutos: _entero(json['duration_minutes']),
      metodoPago: _texto(json['payment_method']) ?? 'cash',
      metodoPagoEtiqueta: _texto(json['payment_method_label']) ?? 'Efectivo',
      total: _decimal(json['total']),
      ganancia: _decimal(json['driver_earning']),
      comision: _decimal(json['commission_amount']),
    );
  }

  /// Convierte el resumen de la oferta en un viaje activo para permitir
  /// avanzar a la pantalla de viaje en curso sin depender de la carga del perfil.
  Viaje aViajeEnCurso({
    String estado = 'accepted',
    String estadoEtiqueta = 'Aceptado',
  }) {
    return Viaje(
      id: id,
      estado: estado,
      estadoEtiqueta: estadoEtiqueta,
      metodoPago: metodoPago,
      metodoPagoEtiqueta: metodoPagoEtiqueta,
      tipoVehiculo: 'moto',
      tipoVehiculoEtiqueta: 'Moto',
      origen: origen,
      destino: destino,
      distanciaKm: distanciaKm,
      duracionMinutos: duracionMinutos,
      tarifa: TarifaViaje(
        base: 0,
        precioKm: 0,
        precioMinuto: 0,
        minimo: 0,
        minimoAplicado: false,
        multiplicador: 1,
        recargoNocturno: 0,
        subtotal: total,
        total: total,
        comisionPorcentaje: 0,
        comision: comision,
        gananciaConductor: ganancia,
      ),
      pasajero: PasajeroViaje(
        id: 0,
        nombre: pasajero,
        telefono: '',
      ),
      aceptadoEn: DateTime.now(),
    );
  }
}

/// Cotización antes de pedir el viaje.
class CotizacionViaje {
  const CotizacionViaje({
    required this.distanciaKm,
    required this.duracionMinutos,
    required this.total,
    required this.comision,
    required this.ganancia,
    required this.multiplicador,
    required this.recargoNocturno,
    required this.minimoAplicado,
    required this.fuenteRuta,
    this.tipoVehiculo = 'moto',
    this.tipoVehiculoEtiqueta = 'Moto',
    this.recargoVehiculo = 0,
    this.tasaCambio,
    this.totalLocal,
    this.desglose = const <String, dynamic>{},
  });

  final double distanciaKm;
  final int duracionMinutos;
  final double total;
  final double comision;
  final double ganancia;
  final double multiplicador;
  final double recargoNocturno;
  final bool minimoAplicado;
  final String fuenteRuta;
  final String tipoVehiculo;
  final String tipoVehiculoEtiqueta;
  final double recargoVehiculo;
  final double? tasaCambio;
  final double? totalLocal;
  final Map<String, dynamic> desglose;

  bool get esEstimada => fuenteRuta == 'estimada';

  factory CotizacionViaje.desdeJson(Object? crudo) {
    final json = _mapa(crudo);
    final desglose = json['breakdown'];

    return CotizacionViaje(
      distanciaKm: _decimal(json['distance_km']),
      duracionMinutos: _entero(json['duration_minutes']),
      total: _decimal(json['total']),
      comision: _decimal(json['commission_amount']),
      ganancia: _decimal(json['driver_earning']),
      multiplicador: _decimal(json['dynamic_multiplier'], 1),
      recargoNocturno: _decimal(json['night_surcharge_percent']),
      minimoAplicado: json['minimum_applied'] == true,
      fuenteRuta: _texto(json['route_source']) ?? 'estimada',
      tipoVehiculo: _texto(json['vehicle_type']) ?? 'moto',
      tipoVehiculoEtiqueta: _texto(json['vehicle_type_label']) ?? 'Moto',
      recargoVehiculo: _decimal(json['vehicle_surcharge']),
      tasaCambio: _decimalONull(json['exchange_rate']),
      totalLocal: _decimalONull(json['local_total']),
      desglose: desglose is Map
          ? Map<String, dynamic>.from(desglose)
          : const <String, dynamic>{},
    );
  }
}

/// Estado de conexión y saldo del conductor.
class EstadoConductor {
  const EstadoConductor({
    required this.enLinea,
    required this.disponible,
    required this.rechazosVentana,
    required this.rechazosParaBloqueo,
    required this.viajesCompletados,
    required this.saldo,
    required this.saldoDisponible,
    required this.topeNegativo,
    this.calificacion,
    this.calificaciones = 0,
    this.bloqueadoHasta,
    this.motivoBloqueo,
    this.lat,
    this.lng,
    this.ubicacionActualizada,
  });

  final bool enLinea;
  final bool disponible;
  final int rechazosVentana;
  final int rechazosParaBloqueo;
  final int viajesCompletados;
  final double saldo;
  final double saldoDisponible;
  final double topeNegativo;
  final double? calificacion;
  final int calificaciones;
  final DateTime? bloqueadoHasta;
  final String? motivoBloqueo;
  final double? lat;
  final double? lng;
  final DateTime? ubicacionActualizada;

  bool get bloqueado =>
      bloqueadoHasta != null && bloqueadoHasta!.isAfter(DateTime.now());

  bool get saldoBajo => saldo <= topeNegativo;

  /// Copia con la ubicación recién reportada (sin volver a pedir el resumen).
  EstadoConductor conUbicacion(double nuevaLat, double nuevaLng) {
    return EstadoConductor(
      enLinea: enLinea,
      disponible: disponible,
      rechazosVentana: rechazosVentana,
      rechazosParaBloqueo: rechazosParaBloqueo,
      viajesCompletados: viajesCompletados,
      saldo: saldo,
      saldoDisponible: saldoDisponible,
      topeNegativo: topeNegativo,
      calificacion: calificacion,
      calificaciones: calificaciones,
      bloqueadoHasta: bloqueadoHasta,
      motivoBloqueo: motivoBloqueo,
      lat: nuevaLat,
      lng: nuevaLng,
      ubicacionActualizada: DateTime.now(),
    );
  }

  factory EstadoConductor.desdeJson(Object? crudo) {
    final json = _mapa(crudo);
    final ubicacion = _mapa(json['location']);

    return EstadoConductor(
      enLinea: json['online'] == true,
      disponible: json['available'] == true,
      rechazosVentana: _entero(json['rejections_in_window']),
      rechazosParaBloqueo: _entero(json['rejections_before_block'], 3),
      viajesCompletados: _entero(json['trips_completed']),
      saldo: _decimal(json['balance']),
      saldoDisponible: _decimal(json['available_balance']),
      topeNegativo: _decimal(json['negative_cap'], -5),
      calificacion: _decimalONull(json['rating']),
      calificaciones: _entero(json['ratings_count']),
      bloqueadoHasta: _fecha(json['blocked_until']),
      motivoBloqueo: _texto(json['blocked_reason']),
      lat: _decimalONull(ubicacion['lat']),
      lng: _decimalONull(ubicacion['lng']),
      ubicacionActualizada: _fecha(json['location_updated_at']),
    );
  }
}

/// Resultado de pedir un viaje.
class ResultadoViaje {
  const ResultadoViaje({required this.viaje, this.mensaje});

  final Viaje viaje;
  final String? mensaje;
}

/// Resultado de una acción del conductor sobre el viaje.
class ResultadoAccionViaje {
  const ResultadoAccionViaje({required this.viaje, this.mensaje});

  final Viaje viaje;
  final String? mensaje;
}

/// Un resultado de búsqueda de direcciones.
class LugarGeocodificado {
  const LugarGeocodificado({
    required this.direccion,
    required this.lat,
    required this.lng,
  });

  final String direccion;
  final double lat;
  final double lng;

  factory LugarGeocodificado.desdeJson(Object? crudo) {
    final json = _mapa(crudo);

    return LugarGeocodificado(
      direccion: _texto(json['address']) ?? '',
      lat: _decimal(json['lat']),
      lng: _decimal(json['lng']),
    );
  }
}

/// Resultado de buscar en Google Geocoding.
class BusquedaLugares {
  const BusquedaLugares({
    required this.disponible,
    required this.lugares,
    this.direccion,
  });

  final bool disponible;
  final List<LugarGeocodificado> lugares;
  final String? direccion;
}

// ---------------------------------------------------------------------------
// Lectura tolerante del JSON.
// ---------------------------------------------------------------------------

Map<String, dynamic> _mapa(Object? valor) {
  if (valor is Map<String, dynamic>) return valor;
  if (valor is Map) return Map<String, dynamic>.from(valor);
  return const <String, dynamic>{};
}

String? _texto(Object? valor) {
  if (valor == null) return null;
  final texto = '$valor'.trim();
  return texto.isEmpty ? null : texto;
}

int _entero(Object? valor, [int defecto = 0]) {
  if (valor is num) return valor.toInt();
  return int.tryParse('$valor') ?? defecto;
}

double _decimal(Object? valor, [double defecto = 0]) {
  if (valor is num) return valor.toDouble();
  return double.tryParse('$valor') ?? defecto;
}

double? _decimalONull(Object? valor) {
  if (valor is num) return valor.toDouble();
  if (valor == null) return null;
  return double.tryParse('$valor');
}

DateTime? _fecha(Object? valor) {
  if (valor is! String || valor.isEmpty) return null;
  return DateTime.tryParse(valor);
}

T? _intentar<T>(T Function() ejecutor) {
  try {
    return ejecutor();
  } catch (_) {
    return null;
  }
}


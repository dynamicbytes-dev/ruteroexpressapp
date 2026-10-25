import 'dart:convert';

import '../../data/models/viaje.dart';

/// Tipo de señal que llega por el WebSocket.
enum TipoEventoTiempoReal {
  /// El canal privado quedó suscrito: la app puede sincronizar lo que se
  /// perdió mientras estaba desconectada.
  conectado,

  /// Hay una alerta de viaje nueva para el conductor.
  oferta,

  /// Un viaje cambió de estado o fue cancelado.
  viaje,

  /// El conductor movió su posición durante un viaje asignado.
  ubicacion,

  /// La bandeja de avisos tiene una notificación nueva.
  notificacion,
}

/// Señal ya interpretada, lista para que la sesión y las pantallas reaccionen.
///
/// Los eventos de Reverb solo traen identificadores y datos mínimos. El viaje
/// completo se pide a la API con el token de la persona, así el backend sigue
/// controlando qué información ve cada rol.
class EventoTiempoReal {
  const EventoTiempoReal({
    required this.tipo,
    this.viajeId,
    this.ofertaId,
    this.estado,
    this.motivo,
    this.lat,
    this.lng,
    this.rumbo,
    this.velocidad,
    this.actualizadoEn,
    this.notificacionId,
    this.viaje,
  });

  final TipoEventoTiempoReal tipo;
  final int? viajeId;
  final int? ofertaId;
  final String? estado;
  final String? motivo;
  final double? lat;
  final double? lng;
  final int? rumbo;
  final double? velocidad;
  final DateTime? actualizadoEn;
  final String? notificacionId;

  /// Viaje ya consultado a la API, cuando la sesión pudo obtenerlo.
  final Viaje? viaje;

  const EventoTiempoReal.conectado()
      : this(tipo: TipoEventoTiempoReal.conectado);

  /// Convierte un evento crudo del protocolo Pusher/Reverb.
  ///
  /// `data` puede venir como texto JSON (lo normal) o como mapa.
  static EventoTiempoReal? desdePusher(String evento, Object? data) {
    final json = _mapa(data);

    return switch (evento) {
      'oferta.viaje' => EventoTiempoReal(
          tipo: TipoEventoTiempoReal.oferta,
          ofertaId: _enteroONull(json['offer_id']),
          viajeId: _enteroONull(json['trip_id']),
          estado: 'pending',
        ),
      'viaje.actualizado' => EventoTiempoReal(
          tipo: TipoEventoTiempoReal.viaje,
          viajeId: _enteroONull(json['trip_id']),
          estado: _texto(json['status']),
          motivo: _texto(json['motivo']),
        ),
      'conductor.ubicacion' => EventoTiempoReal(
          tipo: TipoEventoTiempoReal.ubicacion,
          viajeId: _enteroONull(json['trip_id']),
          lat: _decimalONull(json['lat']),
          lng: _decimalONull(json['lng']),
          rumbo: _enteroONull(json['bearing']),
          velocidad: _decimalONull(json['speed_kmh']),
          actualizadoEn: _fecha(json['updated_at']),
        ),
      'notificacion.creada' => EventoTiempoReal(
          tipo: TipoEventoTiempoReal.notificacion,
          notificacionId: _texto(json['notification_id']),
        ),
      _ => null,
    };
  }

  /// Copia el evento con el viaje que se acaba de consultar (o `null`).
  EventoTiempoReal conViaje(Viaje? viaje) {
    return EventoTiempoReal(
      tipo: tipo,
      viajeId: viajeId,
      ofertaId: ofertaId,
      estado: viaje?.estado ?? estado,
      motivo: motivo,
      lat: lat,
      lng: lng,
      rumbo: rumbo,
      velocidad: velocidad,
      actualizadoEn: actualizadoEn,
      notificacionId: notificacionId,
      viaje: viaje,
    );
  }
}

Map<String, dynamic> _mapa(Object? valor) {
  if (valor is Map<String, dynamic>) return valor;
  if (valor is Map) return Map<String, dynamic>.from(valor);
  if (valor is String && valor.trim().isNotEmpty) {
    try {
      final decodificado = jsonDecode(valor);
      if (decodificado is Map) return Map<String, dynamic>.from(decodificado);
    } catch (_) {
      // El evento no era JSON: se ignora.
    }
  }

  return const <String, dynamic>{};
}

String? _texto(Object? valor) {
  if (valor == null) return null;
  final texto = '$valor'.trim();
  return texto.isEmpty ? null : texto;
}

int? _enteroONull(Object? valor) {
  if (valor is num) return valor.toInt();
  return int.tryParse('$valor');
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

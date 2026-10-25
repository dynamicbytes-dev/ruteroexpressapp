import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/realtime/evento_tiempo_real.dart';

void main() {
  test('interpreta la alerta de una oferta', () {
    final evento = EventoTiempoReal.desdePusher(
      'oferta.viaje',
      '{"offer_id":7,"trip_id":12,"driver_id":3,'
          '"distance_to_pickup_km":1.4,"expires_at":"2026-10-19T12:00:30Z"}',
    );

    expect(evento, isNotNull);
    expect(evento!.tipo, TipoEventoTiempoReal.oferta);
    expect(evento.ofertaId, 7);
    expect(evento.viajeId, 12);
  });

  test('interpreta un cambio de estado del viaje', () {
    final evento = EventoTiempoReal.desdePusher(
      'viaje.actualizado',
      <String, dynamic>{
        'trip_id': 12,
        'status': 'accepted',
        'motivo': 'aceptado',
      },
    );

    expect(evento, isNotNull);
    expect(evento!.tipo, TipoEventoTiempoReal.viaje);
    expect(evento.viajeId, 12);
    expect(evento.estado, 'accepted');
    expect(evento.motivo, 'aceptado');
  });

  test('interpreta la ubicación del conductor', () {
    final evento = EventoTiempoReal.desdePusher(
      'conductor.ubicacion',
      '{"trip_id":12,"lat":10.4962,"lng":-66.8518,'
          '"bearing":90,"speed_kmh":30,'
          '"updated_at":"2026-10-19T12:01:00Z"}',
    );

    expect(evento, isNotNull);
    expect(evento!.tipo, TipoEventoTiempoReal.ubicacion);
    expect(evento.viajeId, 12);
    expect(evento.lat, 10.4962);
    expect(evento.lng, -66.8518);
    expect(evento.rumbo, 90);
    expect(evento.velocidad, 30);
    expect(evento.actualizadoEn?.toUtc().hour, 12);
  });

  test('interpreta una notificación nueva', () {
    final evento = EventoTiempoReal.desdePusher(
      'notificacion.creada',
      '{"notification_id":"abc-123"}',
    );

    expect(evento, isNotNull);
    expect(evento!.tipo, TipoEventoTiempoReal.notificacion);
    expect(evento.notificacionId, 'abc-123');
  });

  test('ignora eventos que no son de la app', () {
    expect(EventoTiempoReal.desdePusher('otro.evento', '{}'), isNull);
  });

  test('el evento conectado sirve para resincronizar la sesión', () {
    const evento = EventoTiempoReal.conectado();

    expect(evento.tipo, TipoEventoTiempoReal.conectado);
    expect(evento.viaje, isNull);
  });
}

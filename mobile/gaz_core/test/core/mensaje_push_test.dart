import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/core/push/mensaje_push.dart';
import 'package:gaz_core/core/realtime/evento_tiempo_real.dart';

void main() {
  group('MensajePush', () {
    test('interpreta una oferta y la convierte en evento de tiempo real', () {
      final mensaje = MensajePush.desdeDatos(<String, dynamic>{
        'tipo': 'oferta',
        'trip_id': '12',
        'offer_id': 34,
      });

      expect(mensaje, isNotNull);
      expect(mensaje!.tipo, TipoMensajePush.oferta);
      expect(mensaje.viajeId, 12);
      expect(mensaje.ofertaId, 34);

      final evento = mensaje.aEvento();

      expect(evento, isNotNull);
      expect(evento!.tipo, TipoEventoTiempoReal.oferta);
      expect(evento.viajeId, 12);
      expect(evento.ofertaId, 34);
      expect(evento.estado, 'pending');
    });

    test('interpreta un cambio de viaje con estado y motivo', () {
      final mensaje = MensajePush.desdeDatos(<String, dynamic>{
        'tipo': 'viaje',
        'trip_id': '99',
        'status': 'accepted',
        'motivo': 'aceptado',
      });

      final evento = mensaje!.aEvento()!;

      expect(evento.tipo, TipoEventoTiempoReal.viaje);
      expect(evento.viajeId, 99);
      expect(evento.estado, 'accepted');
      expect(evento.motivo, 'aceptado');
    });

    test('interpreta un aviso de la bandeja', () {
      final mensaje = MensajePush.desdeDatos(<String, dynamic>{
        'tipo': 'aviso',
        'notification_id': 'aviso-1',
      });

      final evento = mensaje!.aEvento()!;

      expect(evento.tipo, TipoEventoTiempoReal.notificacion);
      expect(evento.notificacionId, 'aviso-1');
    });

    test('ignora tipos desconocidos y viajes sin identificador', () {
      expect(
        MensajePush.desdeDatos(<String, dynamic>{'tipo': 'otro'}),
        isNull,
      );

      final sinViaje = MensajePush.desdeDatos(<String, dynamic>{
        'tipo': 'viaje',
      });

      expect(sinViaje, isNotNull);
      expect(sinViaje!.aEvento(), isNull);
    });
  });
}

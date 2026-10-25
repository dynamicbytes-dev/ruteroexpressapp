import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/data/models/soporte.dart';

void main() {
  test('lee una conversación con mensajes del usuario y del equipo', () {
    final conversacion = ConversacionSoporte.desdeJson(<String, dynamic>{
      'id': 12,
      'status': 'open',
      'status_label': 'Abierta',
      'started_at': '2026-10-21T10:00:00-04:00',
      'last_message_at': '2026-10-21T10:05:00-04:00',
      'closed_at': null,
      'auditor_name': 'Ana Pérez',
      'messages': <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 1,
          'body': 'Hola, necesito ayuda.',
          'author': 'user',
          'author_label': 'Pedro Rojas',
          'from_support': false,
          'read_at': null,
          'created_at': '2026-10-21T10:00:00-04:00',
        },
        <String, dynamic>{
          'id': 2,
          'body': 'Estamos revisando tu caso.',
          'author': 'auditor',
          'author_label': 'Ana Pérez',
          'from_support': true,
          'read_at': '2026-10-21T10:06:00-04:00',
          'created_at': '2026-10-21T10:05:00-04:00',
        },
      ],
    });

    expect(conversacion.id, 12);
    expect(conversacion.abierta, isTrue);
    expect(conversacion.etiquetaEstado, 'Abierta');
    expect(conversacion.auditorNombre, 'Ana Pérez');
    expect(conversacion.mensajes, hasLength(2));
    expect(conversacion.mensajes.first.deSoporte, isFalse);
    expect(conversacion.mensajes.last.deSoporte, isTrue);
    expect(conversacion.mensajes.last.autorEtiqueta, 'Ana Pérez');
    expect(conversacion.mensajes.last.leidoEn, isNotNull);
    expect(conversacion.iniciadaEn, isNotNull);
  });

  test('una conversación finalizada se reconoce por su estado', () {
    final conversacion = ConversacionSoporte.desdeJson(<String, dynamic>{
      'id': 5,
      'status': 'closed',
      'status_label': 'Finalizada',
      'closed_at': '2026-10-21T11:00:00-04:00',
      'messages': <Map<String, dynamic>>[],
    });

    expect(conversacion.abierta, isFalse);
    expect(conversacion.mensajes, isEmpty);
    expect(conversacion.auditorNombre, isNull);
    expect(conversacion.cerradaEn, isNotNull);
  });
}

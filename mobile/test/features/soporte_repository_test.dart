import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/api/api_client.dart';
import 'package:motoruta/data/soporte_repository.dart';

import '../soporte/servidor_falso.dart';

void main() {
  late ServidorFalso servidor;
  late SoporteRepository soporte;

  setUp(() {
    servidor = ServidorFalso();
    final api = ApiClient(
      dio: Dio()..httpClientAdapter = servidor,
      baseUrl: 'http://localhost:8000',
    );
    soporte = SoporteRepository(api);
  });

  test('sin conversación abierta devuelve null', () async {
    servidor.responder('GET', '/api/soporte', <String, dynamic>{'data': null});

    expect(await soporte.activa(), isNull);
  });

  test('envía un mensaje como JSON y devuelve el hilo actualizado', () async {
    servidor.responder('POST', '/api/soporte/mensajes', <String, dynamic>{
      'message': 'Tu mensaje fue enviado al equipo de soporte.',
      'data': <String, dynamic>{
        'id': 3,
        'status': 'open',
        'status_label': 'Abierta',
        'messages': <Map<String, dynamic>>[
          <String, dynamic>{
            'id': 8,
            'body': 'Necesito ayuda con mi recarga.',
            'author': 'user',
            'author_label': 'Pedro Rojas',
            'from_support': false,
            'read_at': null,
            'created_at': '2026-10-21T10:00:00-04:00',
          },
        ],
      },
    });

    final conversacion =
        await soporte.enviar('  Necesito ayuda con mi recarga.  ');

    expect(conversacion.id, 3);
    expect(
        conversacion.mensajes.single.texto, 'Necesito ayuda con mi recarga.');
    expect(conversacion.mensajes.single.deSoporte, isFalse);

    final peticion = servidor.peticiones.single;
    final cuerpo = peticion.data as Map<String, dynamic>;

    expect(peticion.uri.path, '/api/soporte/mensajes');
    expect(cuerpo['message'], 'Necesito ayuda con mi recarga.');
  });
}

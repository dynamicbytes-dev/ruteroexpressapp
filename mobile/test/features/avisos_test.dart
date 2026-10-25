import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/api/api_client.dart';
import 'package:motoruta/data/cuenta_repository.dart';
import 'package:motoruta/data/models/usuario.dart';
import 'package:motoruta/data/notificaciones_repository.dart';
import 'package:motoruta/features/sesion/sesion_controller.dart';

import '../soporte/datos_falsos.dart';
import '../soporte/servidor_falso.dart';

void main() {
  late ServidorFalso servidor;
  late SesionController sesion;

  setUp(() {
    servidor = ServidorFalso();

    final api = ApiClient(
      dio: Dio()..httpClientAdapter = servidor,
      baseUrl: 'http://localhost:8000',
    );

    sesion = SesionController(
      repositorio: CuentaRepository(api),
      almacen: TokenMemoria('tok'),
      api: api,
      notificaciones: NotificacionesRepository(api),
    );

    final perfil = PerfilCuenta.desdeJson(perfilJson(
      usuario: usuarioJson(
        estado: 'approved',
        etiquetaEstado: 'Aprobado',
        puedeOperar: true,
      ),
      paso: 'ready',
      mensaje: 'Tu cuenta está aprobada.',
    ));
    sesion.usuario = perfil.usuario;
    sesion.siguientePaso = perfil.siguientePaso;
    sesion.estado = EstadoSesion.activa;
  });

  test('carga los avisos de la bandeja y distingue los leídos', () async {
    servidor.responder('GET', '/api/notificaciones', <String, dynamic>{
      'data': <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 'aviso-1',
          'type': 'documento_por_vencer',
          'title': 'Documento por vencer',
          'message': 'Tu Licencia de conducir vence en 5 días (24/10/2026).',
          'read_at': null,
          'created_at': '2026-10-19T08:00:00-04:00',
        },
        <String, dynamic>{
          'id': 'aviso-2',
          'type': 'documento_por_vencer',
          'title': 'Documento por vencer',
          'message': 'Tu RCV vence en 15 días.',
          'read_at': '2026-10-19T09:00:00-04:00',
          'created_at': '2026-10-19T08:00:00-04:00',
        },
      ],
      'unread_count': 1,
    });

    await sesion.cargarAvisos();

    expect(sesion.avisos, hasLength(2));
    expect(sesion.avisos.first.id, 'aviso-1');
    expect(sesion.avisos.first.titulo, 'Documento por vencer');
    expect(sesion.avisos.first.leido, isFalse);
    expect(sesion.avisos.last.leido, isTrue);
  });

  test('marcar todos vuelve a pedir la bandeja', () async {
    servidor.responder('POST', '/api/notificaciones/leer-todas', <String, dynamic>{
      'message': 'Todos los avisos quedaron como leídos.',
    });
    servidor.responder('GET', '/api/notificaciones', <String, dynamic>{
      'data': <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 'aviso-1',
          'type': 'documento_por_vencer',
          'title': 'Documento por vencer',
          'message': 'Tu Licencia de conducir vence en 5 días.',
          'read_at': '2026-10-19T10:00:00-04:00',
          'created_at': '2026-10-19T08:00:00-04:00',
        },
      ],
      'unread_count': 0,
    });

    await sesion.marcarAvisosLeidos();

    expect(sesion.avisos.single.leido, isTrue);
    expect(
      servidor.peticiones.map((peticion) => peticion.path),
      containsAll(<String>['/api/notificaciones/leer-todas', '/api/notificaciones']),
    );
  });
}

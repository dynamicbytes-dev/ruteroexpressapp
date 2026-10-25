import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/api/api_client.dart';
import 'package:motoruta/core/realtime/evento_tiempo_real.dart';
import 'package:motoruta/core/realtime/tiempo_real_service.dart';
import 'package:motoruta/data/cuenta_repository.dart';
import 'package:motoruta/data/models/registro_datos.dart';
import 'package:motoruta/data/viajes_repository.dart';
import 'package:motoruta/features/sesion/sesion_controller.dart';

import '../soporte/datos_falsos.dart';
import '../soporte/servidor_falso.dart';

void main() {
  late ServidorFalso servidor;
  late TokenMemoria almacen;
  late SesionController sesion;

  setUp(() {
    servidor = ServidorFalso();
    almacen = TokenMemoria();

    final api = ApiClient(
      dio: Dio()..httpClientAdapter = servidor,
      baseUrl: 'http://localhost:8000',
    );

    sesion = SesionController(
      repositorio: CuentaRepository(api),
      almacen: almacen,
      api: api,
    );
  });

  test('sin token guardado la app abre en la bienvenida', () async {
    await sesion.iniciar();

    expect(sesion.estado, EstadoSesion.sinSesion);
    expect(servidor.peticiones, isEmpty);
  });

  test('al abrir con token guardado carga el perfil y queda listo', () async {
    almacen.token = 'tok-1';

    servidor.responder(
      'GET',
      '/api/perfil',
      perfilJson(
        usuario: usuarioJson(
          estado: 'approved',
          etiquetaEstado: 'Aprobado',
          puedeOperar: true,
          documentos: <Map<String, dynamic>>[
            documentoJson('cedula', estado: 'approved'),
          ],
        ),
        paso: 'ready',
        mensaje:
            'Tu cuenta está aprobada. Actívate para empezar a recibir viajes.',
      ),
    );

    await sesion.iniciar();

    expect(sesion.estado, EstadoSesion.activa);
    expect(sesion.siguientePaso!.listo, isTrue);
    expect(sesion.usuario!.puedeOperar, isTrue);
  });

  test('si el token venció la sesión se cierra sola', () async {
    almacen.token = 'tok-vencido';

    servidor.responder(
        'GET',
        '/api/perfil',
        <String, dynamic>{
          'message': 'No autenticado.',
        },
        codigo: 401);

    await sesion.iniciar();

    expect(sesion.estado, EstadoSesion.sinSesion);
    expect(sesion.usuario, isNull);
    expect(almacen.token, isNull);
  });

  test('carga las ciudades activas para el registro', () async {
    servidor.responder('GET', '/api/ciudades', <String, dynamic>{
      'data': <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 2,
          'name': 'Barcelona',
          'state': 'Anzoátegui',
          'display_name': 'Barcelona (Anzoátegui)',
        },
        <String, dynamic>{
          'id': 3,
          'name': 'Lechería',
          'state': 'Anzoátegui',
          'display_name': 'Lechería (Anzoátegui)',
        },
      ],
    });

    final ciudades = await sesion.ciudades();

    expect(ciudades, hasLength(2));
    expect(ciudades.first.nombre, 'Barcelona');
    expect(ciudades.last.etiqueta, 'Lechería (Anzoátegui)');
  });

  test('el registro deja la cuenta subiendo documentos', () async {
    servidor.responder(
      'POST',
      '/api/auth/register',
      <String, dynamic>{
        'message': 'Tu cuenta fue creada. Está en espera de verificación.',
        'data': <String, dynamic>{
          'token': 'tok-2',
          'token_type': 'Bearer',
          'user': usuarioJson(rol: 'passenger', requeridos: 2),
        },
      },
      codigo: 201,
    );

    servidor.responder(
      'GET',
      '/api/perfil',
      perfilJson(
        usuario: usuarioJson(rol: 'passenger', requeridos: 2),
        paso: 'upload_documents',
        mensaje: 'Completa tu registro subiendo los documentos que faltan.',
      ),
    );

    await sesion.registrarse(
      const RegistroDatos(
        rol: 'passenger',
        nombres: 'Ana',
        apellidos: 'Pérez',
        cedula: '12345678',
        correo: 'ana.perez@gmail.com',
        telefono: '0412-0000000',
        direccion: 'Calle 1, casa 2, Maracay',
        contrasena: 'clave123',
        aceptaTerminos: true,
        ciudadId: 1,
      ),
    );

    expect(sesion.estado, EstadoSesion.activa);
    expect(almacen.token, 'tok-2');
    expect(sesion.siguientePaso!.faltaSubir, isTrue);

    // El registro viaja como JSON con el rol y los datos ya normalizados.
    final peticion = servidor.peticiones.first;
    final cuerpo = peticion.data as Map<String, dynamic>;

    expect(peticion.uri.path, '/api/auth/register');
    expect(peticion.headers['Accept'], 'application/json');
    expect(cuerpo['accept_terms'], isTrue);
    expect(cuerpo['terms_version'], isNotEmpty);
    expect(cuerpo['city_id'], 1);
  });

  test('subir un documento refresca el estado y el token viaja en la cabecera',
      () async {
    servidor.responder(
      'POST',
      '/api/auth/login',
      <String, dynamic>{
        'message': 'Bienvenido de vuelta.',
        'data': <String, dynamic>{
          'token': 'tok-1',
          'token_type': 'Bearer',
          'user': usuarioJson(),
        },
      },
    );

    servidor.responder(
      'GET',
      '/api/perfil',
      perfilJson(usuario: usuarioJson(), paso: 'upload_documents'),
    );

    await sesion.entrar(
        correo: 'pedro.rojas@gmail.com', contrasena: 'clave123');

    expect(sesion.siguientePaso!.faltaSubir, isTrue);

    // Sube la cédula y el backend ya lo deja en la cola del auditor.
    servidor.responder('POST', '/api/documentos/cedula', <String, dynamic>{
      'message': 'Foto de la cédula recibido. Te falta subir: Foto selfie.',
      'data': <String, dynamic>{
        'user': usuarioJson(
          documentos: <Map<String, dynamic>>[
            documentoJson('cedula', estado: 'pending'),
          ],
        ),
      },
    });

    servidor.responder(
      'GET',
      '/api/perfil',
      perfilJson(
        usuario: usuarioJson(
          documentos: <Map<String, dynamic>>[
            documentoJson('cedula', estado: 'pending'),
          ],
        ),
        paso: 'waiting_review',
        mensaje: 'Tus documentos están en revisión.',
      ),
    );

    final mensaje = await sesion.subirDocumento(
      tipo: 'cedula',
      archivo: const ArchivoSubida(
        nombre: 'cedula.jpg',
        bytes: <int>[1, 2, 3],
        mimeType: 'image/jpeg',
      ),
    );

    expect(mensaje, contains('Foto selfie'));
    expect(sesion.siguientePaso!.enEspera, isTrue);
    expect(sesion.usuario!.verificacion.subidos, 1);

    final subida = servidor.peticiones.firstWhere(
        (peticion) => peticion.uri.path == '/api/documentos/cedula');
    expect(subida.headers['Authorization'], 'Bearer tok-1');
  });

  test('salir borra el token y vuelve a la bienvenida', () async {
    almacen.token = 'tok-1';

    servidor.responder(
      'GET',
      '/api/perfil',
      perfilJson(usuario: usuarioJson(), paso: 'upload_documents'),
    );
    servidor.responder('POST', '/api/auth/logout', <String, dynamic>{
      'message': 'Sesión cerrada.',
    });

    await sesion.iniciar();
    expect(sesion.estado, EstadoSesion.activa);

    await sesion.salir();

    expect(sesion.estado, EstadoSesion.sinSesion);
    expect(sesion.usuario, isNull);
    expect(sesion.siguientePaso, isNull);
    expect(almacen.token, isNull);
  });

  test('un evento de viaje actualiza la sesión y avisa a las pantallas',
      () async {
    final api = ApiClient(
      dio: Dio()..httpClientAdapter = servidor,
      baseUrl: 'http://localhost:8000',
    );
    final tiempoReal = TiempoRealFalso(api: api);
    final sesionRt = SesionController(
      repositorio: CuentaRepository(api),
      almacen: almacen,
      api: api,
      viajes: ViajesRepository(api),
      tiempoReal: tiempoReal,
    );

    almacen.token = 'tok-1';

    servidor.responder(
      'GET',
      '/api/perfil',
      perfilJson(
        usuario: usuarioJson(estado: 'approved', puedeOperar: true),
        paso: 'ready',
      ),
    );
    servidor.responder(
      'GET',
      '/api/viajes/1',
      <String, dynamic>{
        'data': <String, dynamic>{
          'trip': viajeJson(
            id: 1,
            estado: 'accepted',
            etiqueta: 'Aceptado',
            conConductor: true,
          ),
        },
      },
    );

    final recibidos = <EventoTiempoReal>[];
    final actualizado = Completer<EventoTiempoReal>();
    final suscripcion = sesionRt.eventosTiempoReal.listen((evento) {
      recibidos.add(evento);

      if (evento.viaje?.id == 1 && !actualizado.isCompleted) {
        actualizado.complete(evento);
      }
    });

    await sesionRt.iniciar();
    await Future<void>.delayed(Duration.zero);

    expect(sesionRt.haySesion, isTrue);
    expect(tiempoReal.conectado, isTrue);

    tiempoReal.emitir(
      const EventoTiempoReal(
        tipo: TipoEventoTiempoReal.viaje,
        viajeId: 1,
        motivo: 'aceptado',
      ),
    );

    await actualizado.future.timeout(const Duration(seconds: 2));

    expect(sesionRt.viajeActivo?.id, 1);
    expect(recibidos.any((evento) => evento.viaje?.id == 1), isTrue);
    expect(
      servidor.peticiones.any((peticion) =>
          peticion.method == 'GET' && peticion.uri.path == '/api/viajes/1'),
      isTrue,
    );

    await suscripcion.cancel();
    sesionRt.dispose();
  });
}

/// Reemplaza el WebSocket real por un canal que la prueba controla.
class TiempoRealFalso extends TiempoRealService {
  TiempoRealFalso({required super.api});

  final StreamController<EventoTiempoReal> _controlador =
      StreamController<EventoTiempoReal>.broadcast();
  bool _conectado = false;

  @override
  Stream<EventoTiempoReal> get eventos => _controlador.stream;

  @override
  bool get conectado => _conectado;

  @override
  Future<void> conectar(int usuarioId) async {
    _conectado = true;
  }

  @override
  Future<void> desconectar() async {
    _conectado = false;
  }

  void emitir(EventoTiempoReal evento) {
    _controlador.add(evento);
  }

  @override
  void dispose() {
    unawaited(_controlador.close());
    super.dispose();
  }
}

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/core/api/api_client.dart';
import 'package:gaz_core/data/cuenta_repository.dart';

import '../soporte/datos_falsos.dart';
import '../soporte/servidor_falso.dart';

void main() {
  late ServidorFalso servidor;
  late CuentaRepository cuenta;

  setUp(() {
    servidor = ServidorFalso();
    final api = ApiClient(
      dio: Dio()
        ..httpClientAdapter = servidor
        ..transformer = SyncTransformer(),
      baseUrl: 'http://localhost:8000',
    );
    cuenta = CuentaRepository(api);
  });

  test('actualiza los datos sin enviar la cédula y sin cambiar el correo',
      () async {
    servidor.responder(
      'PATCH',
      '/api/perfil',
      <String, dynamic>{
        'message': 'Datos actualizados.',
        'data': <String, dynamic>{
          'user': usuarioJson(
              rol: 'passenger', estado: 'approved', puedeOperar: true),
          'email_verification_sent': false,
        },
      },
    );

    final resultado = await cuenta.actualizarPerfil(
      nombres: 'Pedro',
      apellidos: 'Rojas',
      telefono: '04264567890',
      direccion: 'Calle Sucre, casa 45, Maracay',
    );

    expect(resultado.mensaje, 'Datos actualizados.');
    expect(resultado.correoVerificacionEnviado, isFalse);
    expect(resultado.usuario.nombres, 'Pedro');

    final peticion = servidor.peticiones.single;
    final datos = peticion.data! as Map<String, dynamic>;

    expect(peticion.method, 'PATCH');
    expect(datos['first_name'], 'Pedro');
    expect(datos['phone'], '04264567890');
    expect(datos.containsKey('national_id'), isFalse);
    expect(datos.containsKey('email'), isFalse);
    expect(datos.containsKey('current_password'), isFalse);
  });

  test(
      'al cambiar el correo envía la contraseña actual y avisa que hay que verificarlo',
      () async {
    servidor.responder(
      'PATCH',
      '/api/perfil',
      <String, dynamic>{
        'message':
            'Datos actualizados. Te enviamos un enlace para verificar el correo nuevo.',
        'data': <String, dynamic>{
          'user': usuarioJson(rol: 'passenger', correoVerificado: false),
          'email_verification_sent': true,
        },
      },
    );

    final resultado = await cuenta.actualizarPerfil(
      nombres: 'Pedro',
      apellidos: 'Rojas',
      telefono: '04264567890',
      direccion: 'Calle Sucre, casa 45, Maracay',
      correo: 'Nuevo@Gmail.com',
      contrasenaActual: 'clave12345',
    );

    expect(resultado.correoVerificacionEnviado, isTrue);

    final datos = servidor.peticiones.single.data! as Map<String, dynamic>;

    expect(datos['email'], 'nuevo@gmail.com');
    expect(datos['current_password'], 'clave12345');
  });
}

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/core/api/api_exception.dart';
import 'package:gaz_core/data/models/registro_datos.dart';

DioException _conRespuesta(
  int codigo,
  Map<String, dynamic> cuerpo, {
  DioExceptionType tipo = DioExceptionType.badResponse,
}) {
  final opciones = RequestOptions(path: '/api/auth/register');

  return DioException(
    requestOptions: opciones,
    type: tipo,
    response: Response<dynamic>(
      requestOptions: opciones,
      statusCode: codigo,
      data: cuerpo,
    ),
  );
}

void main() {
  test('un 422 trae el mensaje de cada campo', () {
    final excepcion = ApiException.desdeDio(
      _conRespuesta(422, <String, dynamic>{
        'message': 'El correo ya tiene una cuenta en GAZ.',
        'errors': <String, dynamic>{
          'email': <String>['Ese correo ya tiene una cuenta en GAZ.'],
          'national_id': <String>['Esa cédula ya está registrada.'],
        },
      }),
    );

    expect(excepcion.esValidacion, isTrue);
    expect(excepcion.codigo, 422);
    expect(
        excepcion.errorDe('email'), 'Ese correo ya tiene una cuenta en GAZ.');
    expect(excepcion.errorDe('national_id'), 'Esa cédula ya está registrada.');
    expect(excepcion.errorDe('password'), isNull);

    // Las claves del backend se traducen a las del formulario de la app.
    expect(RegistroDatos.campoDelFormulario['email'], 'correo');
    expect(RegistroDatos.campoDelFormulario['national_id'], 'cedula');
    expect(RegistroDatos.campoDelFormulario['vehicle_plate'], 'placaVehiculo');
  });

  test('el error general cubre claves sin campo propio', () {
    final excepcion = ApiException.desdeDio(
      _conRespuesta(422, <String, dynamic>{
        'message': 'Un pasajero no puede subir el RCV.',
        'errors': <String, dynamic>{
          'documentos': <String>[
            'El documento «RCV» no corresponde a tu tipo de cuenta.'
          ],
        },
      }),
    );

    expect(excepcion.errorGeneral,
        'El documento «RCV» no corresponde a tu tipo de cuenta.');
  });

  test('un 401 pide volver a entrar', () {
    final excepcion = ApiException.desdeDio(
      _conRespuesta(401, <String, dynamic>{'message': 'No autenticado.'}),
    );

    expect(excepcion.noAutorizado, isTrue);
    expect(excepcion.mensaje, 'No autenticado.');
  });

  test('un 403 explica que la cuenta no puede hacer eso', () {
    final excepcion = ApiException.desdeDio(
      _conRespuesta(403, <String, dynamic>{
        'message':
            'Tu cuenta está suspendida. Escríbenos para revisar tu caso.',
      }),
    );

    expect(excepcion.prohibido, isTrue);
    expect(excepcion.mensaje, contains('suspendida'));
  });

  test('un 429 avisa de demasiados intentos', () {
    final excepcion = ApiException.desdeDio(
      _conRespuesta(429, <String, dynamic>{
        'message': 'Demasiados intentos. Espera un momento.',
      }),
    );

    expect(excepcion.demasiadosIntentos, isTrue);
  });

  test('sin respuesta se marca como problema de conexión', () {
    final excepcion = ApiException.desdeDio(
      DioException(
        requestOptions: RequestOptions(path: '/api/perfil'),
        type: DioExceptionType.connectionError,
      ),
    );

    expect(excepcion.sinConexion, isTrue);
    expect(excepcion.codigo, isNull);
    expect(excepcion.mensaje, contains('No pudimos conectar'));
  });

  test('los códigos sin mensaje del backend usan un texto en español', () {
    final excepcion = ApiException.desdeDio(
      _conRespuesta(404, <String, dynamic>{}),
    );

    expect(excepcion.mensaje, 'No encontramos lo que buscabas.');
  });
}

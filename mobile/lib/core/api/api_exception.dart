import 'package:dio/dio.dart';

/// Error de la API traducido a algo que la app puede mostrar.
///
/// En los `422` trae el mapa `errors` del backend para pintar cada mensaje en su
/// campo; el `message` del backend se usa tal cual en el resto de los casos.
class ApiException implements Exception {
  ApiException({
    required this.mensaje,
    this.codigo,
    this.errores = const <String, List<String>>{},
    this.sinConexion = false,
  });

  final String mensaje;
  final int? codigo;
  final Map<String, List<String>> errores;
  final bool sinConexion;

  bool get esValidacion => codigo == 422;
  bool get noAutorizado => codigo == 401;
  bool get prohibido => codigo == 403;
  bool get demasiadosIntentos => codigo == 429;

  /// Primer mensaje de un campo, o `null` si ese campo está bien.
  String? errorDe(String campo) {
    final lista = errores[campo];
    return (lista == null || lista.isEmpty) ? null : lista.first;
  }

  /// Errores que no corresponden a un campo concreto (clave `*` del backend).
  String? get errorGeneral {
    for (final clave in const ['*', 'documentos', 'documentos_expiracion']) {
      final mensaje = errorDe(clave);
      if (mensaje != null) return mensaje;
    }
    return null;
  }

  /// Convierte el error crudo de Dio en un error con mensaje para la persona.
  factory ApiException.desdeDio(DioException error) {
    final respuesta = error.response;

    if (respuesta == null) {
      return ApiException(
        mensaje:
            'No pudimos conectar con GAZ. Revisa tu conexión e inténtalo de nuevo.',
        sinConexion: true,
      );
    }

    final datos = respuesta.data;
    final mensaje = datos is Map && datos['message'] is String
        ? datos['message'] as String
        : _mensajePorCodigo(respuesta.statusCode);

    final errores = <String, List<String>>{};
    if (datos is Map && datos['errors'] is Map) {
      (datos['errors'] as Map).forEach((clave, valor) {
        if (valor is List) {
          errores['$clave'] = valor.map((cada) => '$cada').toList();
        } else if (valor is String) {
          errores['$clave'] = <String>[valor];
        }
      });
    }

    return ApiException(
      mensaje: mensaje,
      codigo: respuesta.statusCode,
      errores: errores,
    );
  }

  static String _mensajePorCodigo(int? codigo) {
    return switch (codigo) {
      401 => 'Tu sesión venció. Entra de nuevo.',
      403 => 'Tu cuenta no puede hacer esa acción.',
      404 => 'No encontramos lo que buscabas.',
      429 => 'Demasiados intentos. Espera un momento y vuelve a intentarlo.',
      _ => 'Algo salió mal. Inténtalo de nuevo.',
    };
  }

  @override
  String toString() => 'ApiException($codigo): $mensaje';
}

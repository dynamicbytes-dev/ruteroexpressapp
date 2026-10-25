import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

/// Servidor de mentira: responde por método y ruta, sin red.
///
/// Se usa para probar la sesión completa (entrar, subir documentos, esperar la
/// aprobación) sin levantar el backend.
class ServidorFalso implements HttpClientAdapter {
  final Map<String, _Ruta> _rutas = <String, _Ruta>{};

  /// Todas las peticiones que recibió, para revisar cabeceras y cuerpos.
  final List<RequestOptions> peticiones = <RequestOptions>[];

  /// Deja lista una respuesta. Se puede volver a llamar para cambiarla: útil
  /// para simular que el auditor aprobó entre una petición y la siguiente.
  void responder(
    String metodo,
    String ruta,
    Map<String, dynamic> cuerpo, {
    int codigo = 200,
  }) {
    _rutas['$metodo $ruta'] = _Ruta(codigo: codigo, cuerpo: cuerpo);
  }

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    peticiones.add(options);

    final ruta = _rutas['${options.method} ${options.uri.path}'];

    if (ruta == null) {
      return ResponseBody.fromString(
        jsonEncode(<String, dynamic>{
          'message': 'Ruta no simulada: ${options.method} ${options.uri.path}',
        }),
        404,
        headers: _cabeceras,
      );
    }

    return ResponseBody.fromString(
      jsonEncode(ruta.cuerpo),
      ruta.codigo,
      headers: _cabeceras,
    );
  }

  @override
  void close({bool force = false}) {}

  static const Map<String, List<String>> _cabeceras = <String, List<String>>{
    Headers.contentTypeHeader: <String>['application/json'],
  };
}

class _Ruta {
  const _Ruta({required this.codigo, required this.cuerpo});

  final int codigo;
  final Map<String, dynamic> cuerpo;
}

import 'package:dio/dio.dart';
import 'package:http_parser/http_parser.dart';

import 'api_config.dart';
import 'api_exception.dart';

/// Archivo listo para subir, ya leído a memoria (cámara o galería).
///
/// Se usa `bytes` y no una ruta de archivo porque en web no existen rutas.
class ArchivoSubida {
  const ArchivoSubida({
    required this.nombre,
    required this.bytes,
    required this.mimeType,
  });

  final String nombre;
  final List<int> bytes;
  final String mimeType;

  /// Deduce el tipo MIME por la extensión del archivo elegido.
  static ArchivoSubida desdeBytes({
    required String nombre,
    required List<int> bytes,
  }) {
    final extension = nombre.toLowerCase().split('.').last;
    final mimeType = switch (extension) {
      'png' => 'image/png',
      'webp' => 'image/webp',
      'pdf' => 'application/pdf',
      _ => 'image/jpeg',
    };

    return ArchivoSubida(nombre: nombre, bytes: bytes, mimeType: mimeType);
  }
}

/// Cliente HTTP único de la app.
///
/// Se encarga de tres cosas: poner el token en cada petición, convertir los
/// errores en [ApiException] y avisar cuando la sesión dejó de servir.
class ApiClient {
  ApiClient({Dio? dio, String? baseUrl}) : _dio = dio ?? Dio() {
    final opciones = _dio.options;

    opciones.baseUrl = baseUrl ??
        (opciones.baseUrl.isEmpty ? ApiConfig.baseUrl : opciones.baseUrl);
    opciones.connectTimeout ??= const Duration(seconds: 15);
    opciones.receiveTimeout ??= const Duration(seconds: 40);

    // Sin esto Laravel responde a los errores de validación como si fuera una
    // página web y no devuelve el mapa `errors` que la app necesita.
    opciones.headers.putIfAbsent('Accept', () => 'application/json');
  }

  final Dio _dio;

  /// Se llama cuando el backend responde `401`: el token ya no sirve.
  void Function()? alVencerSesion;

  String? get baseUrl => _dio.options.baseUrl;

  /// Deja el token (o lo quita) para todas las peticiones siguientes.
  void guardarToken(String? token) {
    if (token == null || token.isEmpty) {
      _dio.options.headers.remove('Authorization');
    } else {
      _dio.options.headers['Authorization'] = 'Bearer $token';
    }
  }

  Future<Map<String, dynamic>> get(String ruta) {
    return _ejecutar(() => _dio.get<dynamic>(ruta));
  }

  Future<Map<String, dynamic>> post(String ruta, {Object? datos}) {
    return _ejecutar(() => _dio.post<dynamic>(ruta, data: datos));
  }

  Future<Map<String, dynamic>> put(String ruta, {Object? datos}) {
    return _ejecutar(() => _dio.put<dynamic>(ruta, data: datos));
  }

  Future<Map<String, dynamic>> patch(String ruta, {Object? datos}) {
    return _ejecutar(() => _dio.patch<dynamic>(ruta, data: datos));
  }

  Future<Map<String, dynamic>> delete(String ruta, {Object? datos}) {
    return _ejecutar(() => _dio.delete<dynamic>(ruta, data: datos));
  }

  /// Sube un archivo con progreso. [nombreCampo] es como lo espera el backend
  /// (`archivo` en `/documentos/{tipo}`, `documentos[tipo]` en el registro).
  Future<Map<String, dynamic>> subir(
    String ruta, {
    required String nombreCampo,
    required ArchivoSubida archivo,
    Map<String, String> campos = const <String, String>{},
    void Function(int enviado, int total)? alProgresar,
  }) {
    return _ejecutar(() {
      final formulario = FormData.fromMap(<String, dynamic>{
        ...campos,
        nombreCampo: MultipartFile.fromBytes(
          archivo.bytes,
          filename: archivo.nombre,
          contentType: MediaType.parse(archivo.mimeType),
        ),
      });

      return _dio.post<dynamic>(
        ruta,
        data: formulario,
        onSendProgress: alProgresar,
      );
    });
  }

  Future<Map<String, dynamic>> _ejecutar(
    Future<Response<dynamic>> Function() peticion,
  ) async {
    try {
      final respuesta = await peticion();
      final datos = respuesta.data;

      if (datos is Map<String, dynamic>) return datos;
      if (datos is Map) return Map<String, dynamic>.from(datos);

      return <String, dynamic>{};
    } on DioException catch (error) {
      final excepcion = ApiException.desdeDio(error);

      if (excepcion.noAutorizado) {
        alVencerSesion?.call();
      }

      throw excepcion;
    }
  }
}

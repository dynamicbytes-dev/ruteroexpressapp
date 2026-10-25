import 'package:intl/intl.dart';

import '../core/api/api_client.dart';
import 'models/ciudad.dart';
import 'models/registro_datos.dart';
import 'models/usuario.dart';

/// Resultado de subir un documento.
class DocumentoSubido {
  const DocumentoSubido({this.mensaje, this.usuario});

  final String? mensaje;
  final Usuario? usuario;
}

/// Resultado de editar los datos personales.
class PerfilActualizado {
  const PerfilActualizado({
    required this.usuario,
    this.mensaje,
    this.correoVerificacionEnviado = false,
  });

  final Usuario usuario;
  final String? mensaje;
  final bool correoVerificacionEnviado;
}

/// Todas las llamadas de la cuenta en un solo lugar.
///
/// La app nunca arma URLs ni lee JSON fuera de aquí: las pantallas trabajan con
/// [Usuario], [SiguientePaso] y [ApiException].
class CuentaRepository {
  CuentaRepository(this._api);

  static final DateFormat _formatoApi = DateFormat('yyyy-MM-dd');

  final ApiClient _api;

  Future<SesionIniciada> registrar(
    RegistroDatos datos, {
    ArchivoSubida? avatar,
    String? nombreDispositivo,
  }) async {
    final Map<String, dynamic> respuesta;
    if (avatar != null) {
      final mapa = datos.aJson(nombreDispositivo: nombreDispositivo);
      final campos = <String, String>{};
      mapa.forEach((clave, valor) {
        if (valor != null) campos[clave] = valor.toString();
      });

      respuesta = await _api.subir(
        '/api/auth/register',
        nombreCampo: 'avatar',
        archivo: avatar,
        campos: campos,
      );
    } else {
      respuesta = await _api.post(
        '/api/auth/register',
        datos: datos.aJson(nombreDispositivo: nombreDispositivo),
      );
    }

    return SesionIniciada.desdeJson(respuesta);
  }

  /// Ciudades activas para el selector del registro.
  Future<List<Ciudad>> ciudades() async {
    final respuesta = await _api.get('/api/ciudades');
    final datos = respuesta['data'];

    if (datos is! List) return const <Ciudad>[];

    return datos
        .whereType<Map>()
        .map((cada) => Ciudad.desdeJson(Map<String, dynamic>.from(cada)))
        .where((ciudad) => ciudad.id > 0)
        .toList();
  }

  Future<SesionIniciada> entrar({
    required String correo,
    required String contrasena,
    String? rol,
    String? nombreDispositivo,
  }) async {
    final respuesta = await _api.post(
      '/api/auth/login',
      datos: <String, dynamic>{
        'email': correo.trim().toLowerCase(),
        'password': contrasena,
        if (rol != null) 'role': rol,
        if (nombreDispositivo != null) 'device_name': nombreDispositivo,
      },
    );

    return SesionIniciada.desdeJson(respuesta);
  }

  Future<void> salir() async {
    await _api.post('/api/auth/logout');
  }

  Future<PerfilCuenta> perfil() async {
    final respuesta = await _api.get('/api/perfil');
    return PerfilCuenta.desdeJson(respuesta);
  }

  /// Actualiza nombre, apellido, teléfono, dirección y, si se indica, correo y foto.
  ///
  /// La cédula nunca se envía: el backend la rechaza explícitamente.
  Future<PerfilActualizado> actualizarPerfil({
    required String nombres,
    required String apellidos,
    required String telefono,
    required String direccion,
    String? correo,
    String? contrasenaActual,
    ArchivoSubida? avatar,
  }) async {
    final Map<String, dynamic> respuesta;
    if (avatar != null) {
      respuesta = await _api.subir(
        '/api/perfil',
        nombreCampo: 'avatar',
        archivo: avatar,
        campos: <String, String>{
          'first_name': nombres.trim(),
          'last_name': apellidos.trim(),
          'phone': telefono.trim(),
          'address': direccion.trim(),
          if (correo != null) 'email': correo.trim().toLowerCase(),
          if (contrasenaActual != null) 'current_password': contrasenaActual,
        },
      );
    } else {
      respuesta = await _api.patch(
        '/api/perfil',
        datos: <String, dynamic>{
          'first_name': nombres.trim(),
          'last_name': apellidos.trim(),
          'phone': telefono.trim(),
          'address': direccion.trim(),
          if (correo != null) 'email': correo.trim().toLowerCase(),
          if (contrasenaActual != null) 'current_password': contrasenaActual,
        },
      );
    }

    final datos = respuesta['data'];
    final usuario = datos is Map && datos['user'] is Map
        ? Usuario.desdeJson(Map<String, dynamic>.from(datos['user'] as Map))
        : null;

    if (usuario == null) {
      throw StateError('La API no devolvió el perfil actualizado.');
    }

    return PerfilActualizado(
      usuario: usuario,
      mensaje: respuesta['message'] as String?,
      correoVerificacionEnviado:
          datos is Map && datos['email_verification_sent'] == true,
    );
  }

  /// Sube la foto de perfil o selfie del usuario.
  Future<PerfilActualizado> subirAvatar(
    ArchivoSubida archivo, {
    Map<String, String>? campos,
    void Function(int enviado, int total)? alProgresar,
  }) async {
    final respuesta = await _api.subir(
      '/api/perfil',
      nombreCampo: 'avatar',
      archivo: archivo,
      campos: campos ?? const <String, String>{},
      alProgresar: alProgresar,
    );

    final datos = respuesta['data'];
    final usuario = datos is Map && datos['user'] is Map
        ? Usuario.desdeJson(Map<String, dynamic>.from(datos['user'] as Map))
        : null;

    if (usuario == null) {
      throw StateError('La API no devolvió el perfil actualizado.');
    }

    return PerfilActualizado(
      usuario: usuario,
      mensaje: respuesta['message'] as String?,
      correoVerificacionEnviado:
          datos is Map && datos['email_verification_sent'] == true,
    );
  }

  /// Sube o reemplaza un documento. El backend lo devuelve a revisión.
  Future<DocumentoSubido> subirDocumento({
    required String tipo,
    required ArchivoSubida archivo,
    DateTime? venceEl,
    void Function(int enviado, int total)? alProgresar,
  }) async {
    final respuesta = await _api.subir(
      '/api/documentos/$tipo',
      nombreCampo: 'archivo',
      archivo: archivo,
      campos: <String, String>{
        if (venceEl != null) 'vence_el': _formatoApi.format(venceEl),
      },
      alProgresar: alProgresar,
    );

    final datos = respuesta['data'];

    return DocumentoSubido(
      mensaje: respuesta['message'] as String?,
      usuario: datos is Map && datos['user'] is Map
          ? Usuario.desdeJson(Map<String, dynamic>.from(datos['user'] as Map))
          : null,
    );
  }

  Future<String?> recuperarContrasena(String correo) async {
    final respuesta = await _api.post(
      '/api/auth/forgot-password',
      datos: <String, String>{'email': correo.trim().toLowerCase()},
    );

    return respuesta['message'] as String?;
  }

  Future<String?> reenviarVerificacion() async {
    final respuesta =
        await _api.post('/api/auth/email/verification-notification');
    return respuesta['message'] as String?;
  }
}

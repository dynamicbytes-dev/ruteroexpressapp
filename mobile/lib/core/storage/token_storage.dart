import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Dónde se guarda el token de la sesión.
///
/// Es una interfaz para poder probar la sesión sin llavero del sistema.
abstract class TokenStorage {
  Future<String?> leer();
  Future<void> guardar(String token);
  Future<void> borrar();
}

/// Implementación real: llavero del sistema (Keychain en iOS, Keystore en
/// Android). Nunca se guarda el token en texto plano.
///
/// Si el almacén falla (por ejemplo en un navegador con restricciones) la app
/// sigue funcionando: la sesión dura lo que dure la app abierta.
class TokenStorageSeguro implements TokenStorage {
  TokenStorageSeguro({FlutterSecureStorage? almacen})
      : _almacen = almacen ?? const FlutterSecureStorage();

  static const String _clave = 'motoruta.token';

  final FlutterSecureStorage _almacen;

  @override
  Future<String?> leer() async {
    try {
      return await _almacen.read(key: _clave);
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> guardar(String token) async {
    try {
      await _almacen.write(key: _clave, value: token);
    } catch (_) {
      // Sin persistencia: la sesión dura lo que dure la app abierta.
    }
  }

  @override
  Future<void> borrar() async {
    try {
      await _almacen.delete(key: _clave);
    } catch (_) {
      // Nada que hacer: el token se descarta de memoria igual.
    }
  }
}

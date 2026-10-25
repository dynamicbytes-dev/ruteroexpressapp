import 'package:flutter/foundation.dart';

/// Dirección del backend, según dónde esté corriendo la app.
///
/// Se puede cambiar sin tocar código:
///
/// 
abstract class ApiConfig {
  /// Puerto donde corre `php artisan serve`.
  static const int puerto = 8000;

  /// IP de la computadora Mac en la red local (para dispositivos físicos).
  static const String hostLocal = '192.168.1.11';

  static const String _desdeEntorno = String.fromEnvironment('API_BASE_URL');

  static String get baseUrl {
    if (_desdeEntorno.isNotEmpty) {
      return _desdeEntorno.endsWith('/')
          ? _desdeEntorno.substring(0, _desdeEntorno.length - 1)
          : _desdeEntorno;
    }

    // En el navegador la app se sirve desde localhost, pero el backend de
    // `php artisan serve` escucha en IPv4. Usamos 127.0.0.1 para no depender
    // de cómo resuelva `localhost` el navegador (IPv6/IPv4).
    if (kIsWeb) {
      final host = Uri.base.host;
      final destino =
          (host.isEmpty || host == 'localhost') ? '127.0.0.1' : host;

      return 'http://$destino:$puerto';
    }

    // En dispositivo físico, conecta directamente a la IP de la Mac en la red local.
    return 'http://$hostLocal:$puerto';
  }
}

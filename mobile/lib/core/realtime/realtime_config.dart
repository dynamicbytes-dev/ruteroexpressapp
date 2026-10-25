import 'package:flutter/foundation.dart';

import '../api/api_config.dart';

/// Configuración del WebSocket de Reverb.
///
/// La clave de aplicación se pasa al compilar, igual que las demás claves:
///
/// ```bash
/// flutter run --dart-define-from-file=secrets/reverb.json
/// ```
///
/// Si no hay `REVERB_APP_KEY`, la app no abre el WebSocket y sigue funcionando
/// con el sondeo normal de la API.
abstract class RealtimeConfig {
  static const String _claveEntorno = String.fromEnvironment('REVERB_APP_KEY');
  static const String _hostEntorno = String.fromEnvironment('REVERB_HOST');
  static const int _puertoEntorno =
      int.fromEnvironment('REVERB_PORT', defaultValue: 8080);
  static const String _esquemaEntorno =
      String.fromEnvironment('REVERB_SCHEME', defaultValue: 'http');

  static bool get configurado => _claveEntorno.isNotEmpty;

  static String get appKey => _claveEntorno;

  /// Host del WebSocket.
  ///
  /// En web/iOS/macOS el backend local es `localhost`. El emulador de Android
  /// ve la máquina anfitriona en `10.0.2.2`. Para un teléfono físico se pasa
  /// `REVERB_HOST` con la IP de la computadora.
  static String get host {
    if (_hostEntorno.isNotEmpty) return _hostEntorno;

    // Si la API apunta a una IP o dominio (teléfono físico o servidor), el
    // WebSocket usa el mismo host. `REVERB_HOST` sigue mandando si se pasa.
    final api = Uri.tryParse(ApiConfig.baseUrl);
    final apiHost = api?.host ?? '';

    // En web, la API ya resolvió el host correcto (127.0.0.1 si se abrió como
    // localhost). El WebSocket debe hablar con ese mismo host.
    if (kIsWeb) {
      return apiHost.isNotEmpty ? apiHost : '127.0.0.1';
    }

    if (apiHost.isNotEmpty &&
        apiHost != 'localhost' &&
        apiHost != '127.0.0.1') {
      return apiHost;
    }

    if (defaultTargetPlatform == TargetPlatform.android) {
      return '10.0.2.2';
    }

    return 'localhost';
  }

  static int get puerto => _puertoEntorno;

  static String get esquema => _esquemaEntorno;

  static Uri get uri {
    return Uri(
      scheme: esquema == 'https' ? 'wss' : 'ws',
      host: host,
      port: puerto,
      path: '/app/$appKey',
      queryParameters: const <String, String>{
        'protocol': '7',
        'client': 'flutter',
        'version': '1.0',
      },
    );
  }
}

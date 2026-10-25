import '../core/api/api_client.dart';

/// Registro de equipos para las notificaciones push.
///
/// El token de Firebase identifica la instalación de la app. El backend lo
/// reasigna si la misma persona inicia sesión en otro equipo o si otra cuenta
/// entra en este.
class DispositivosRepository {
  const DispositivosRepository(this._api);

  final ApiClient _api;

  Future<void> registrar({
    required String token,
    required String plataforma,
    String? nombre,
  }) async {
    await _api.post(
      '/api/dispositivos',
      datos: <String, dynamic>{
        'token': token,
        'platform': plataforma,
        if (nombre != null && nombre.isNotEmpty) 'device_name': nombre,
      },
    );
  }

  Future<void> eliminar(String token) async {
    await _api.delete(
      '/api/dispositivos',
      datos: <String, dynamic>{'token': token},
    );
  }
}

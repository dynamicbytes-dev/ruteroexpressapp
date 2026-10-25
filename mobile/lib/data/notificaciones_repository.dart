import '../core/api/api_client.dart';
import 'models/aviso.dart';

/// Bandeja de avisos de la persona autenticada.
class NotificacionesRepository {
  NotificacionesRepository(this._api);

  final ApiClient _api;

  Future<List<AvisoCuenta>> avisos() async {
    final respuesta = await _api.get('/api/notificaciones');
    final datos = respuesta['data'];

    if (datos is! List) return const <AvisoCuenta>[];

    return datos
        .whereType<Map>()
        .map((cada) => AvisoCuenta.desdeJson(Map<String, dynamic>.from(cada)))
        .toList();
  }

  Future<void> marcarTodasLeidas() async {
    await _api.post('/api/notificaciones/leer-todas');
  }
}

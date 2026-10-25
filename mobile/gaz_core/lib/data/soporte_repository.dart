import '../core/api/api_client.dart';
import 'models/soporte.dart';

/// Chat de soporte de la persona autenticada.
///
/// Solo existe una conversación abierta a la vez. Enviar el primer mensaje la
/// crea; si el auditor la finaliza, la siguiente llamada devuelve `null` hasta
/// que la persona escriba de nuevo.
class SoporteRepository {
  SoporteRepository(this._api);

  final ApiClient _api;

  Future<ConversacionSoporte?> activa() async {
    final respuesta = await _api.get('/api/soporte');
    final datos = respuesta['data'];

    if (datos is! Map) return null;

    return ConversacionSoporte.desdeJson(
      Map<String, dynamic>.from(datos),
    );
  }

  Future<ConversacionSoporte> enviar(String mensaje) async {
    final respuesta = await _api.post(
      '/api/soporte/mensajes',
      datos: <String, dynamic>{'message': mensaje.trim()},
    );
    final datos = respuesta['data'];

    if (datos is! Map) {
      throw StateError('La API no devolvió la conversación de soporte.');
    }

    return ConversacionSoporte.desdeJson(
      Map<String, dynamic>.from(datos),
    );
  }
}

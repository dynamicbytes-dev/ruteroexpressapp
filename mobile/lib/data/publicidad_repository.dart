import '../core/api/api_client.dart';
import 'models/banner_promocional.dart';

/// Repositorio para consultar los banners y tarjetas de publicidad dinámicas.
class PublicidadRepository {
  PublicidadRepository(this._api);

  final ApiClient _api;

  Future<List<BannerPromocional>> banners({
    String? rol,
    int? ciudadId,
  }) async {
    final parametros = <String, String>{};
    if (rol != null && rol.isNotEmpty) parametros['role'] = rol;
    if (ciudadId != null) parametros['city_id'] = '$ciudadId';

    final uri = Uri(
      path: '/api/banners',
      queryParameters: parametros.isEmpty ? null : parametros,
    );

    final respuesta = await _api.get(uri.toString());

    final datos = respuesta['data'];
    if (datos is! List) return const <BannerPromocional>[];

    return datos
        .whereType<Map>()
        .map((cada) =>
            BannerPromocional.desdeJson(Map<String, dynamic>.from(cada)))
        .toList();
  }
}

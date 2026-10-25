import 'package:geolocator/geolocator.dart';

/// Error de ubicación con un mensaje que se puede mostrar tal cual.
class UbicacionException implements Exception {
  const UbicacionException(this.mensaje);

  final String mensaje;

  @override
  String toString() => mensaje;
}

/// Envoltura de `geolocator`: permisos, posición actual y seguimiento.
///
/// La app no guarda la ubicación en el teléfono: la reporta al backend, que es
/// quien decide qué conductores están cerca de cada viaje.
class UbicacionService {
  const UbicacionService();

  /// Pide permiso (si hace falta) y devuelve la posición actual.
  Future<Position> actual() async {
    await _verificarServicio();

    var permiso = await Geolocator.checkPermission();

    if (permiso == LocationPermission.denied) {
      permiso = await Geolocator.requestPermission();
    }

    if (permiso == LocationPermission.deniedForever) {
      throw const UbicacionException(
        'La ubicación está bloqueada para GAZ. Actívala en los ajustes del teléfono.',
      );
    }

    if (permiso == LocationPermission.denied) {
      throw const UbicacionException(
        'Necesitamos tu ubicación para conectarte y recibir viajes cercanos.',
      );
    }

    return Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );
  }

  /// Emite la posición mientras el conductor está en línea.
  Stream<Position> seguir() {
    return Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10,
      ),
    );
  }

  Future<void> abrirAjustes() => Geolocator.openAppSettings();

  Future<void> _verificarServicio() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      throw const UbicacionException(
        'Activa la ubicación del teléfono para poder conectarte.',
      );
    }
  }
}

/// Configuración del mapa.
///
/// Con `--dart-define=GEOAPIFY_API_KEY=...` se usan los tiles de Geoapify
/// (gratis, sin tarjeta). Sin clave se usan los tiles públicos de
/// OpenStreetMap. En ambos casos el mapa funciona sin claves nativas.
class MapaConfig {
  const MapaConfig._();

  static const String clave = String.fromEnvironment('GEOAPIFY_API_KEY');

  static bool get usaGeoapify => clave.isNotEmpty;

  /// Plantilla de tiles que consume `flutter_map`.
  static String get urlTiles => usaGeoapify
      ? 'https://maps.geoapify.com/v1/tile/osm-bright/{z}/{x}/{y}.png?apiKey=$clave'
      : 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

  static const String atribucionOsm = 'OpenStreetMap contributors';

  /// Atribución que exige el proveedor de tiles activo.
  static String get atribucion =>
      usaGeoapify ? '$atribucionOsm · Geoapify' : atribucionOsm;
}

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Almacén de la preferencia de tema.
///
/// Se abstrae para poder probar el controlador sin el plugin de preferencias.
abstract class AlmacenTema {
  Future<String?> leer();

  Future<void> guardar(String? valor);
}

/// Implementación real: `SharedPreferences`.
class AlmacenTemaPreferencias implements AlmacenTema {
  const AlmacenTemaPreferencias();

  @override
  Future<String?> leer() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getString(ThemeController.clave);
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> guardar(String? valor) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      if (valor == null) {
        await prefs.remove(ThemeController.clave);
      } else {
        await prefs.setString(ThemeController.clave, valor);
      }
    } catch (_) {
      // Sin preferencias la app sigue funcionando; el tema dura la sesión.
    }
  }
}

/// Implementación en memoria, para pruebas y como respaldo.
class AlmacenTemaMemoria implements AlmacenTema {
  AlmacenTemaMemoria([this.valor]);

  String? valor;

  @override
  Future<String?> leer() async => valor;

  @override
  Future<void> guardar(String? nuevo) async {
    valor = nuevo;
  }
}

/// Controla el `ThemeMode` de la app y lo persiste.
///
/// La clave es la misma del prototipo (`gaz2027-tema`) para que el panel y las
/// apps compartan la intención cuando corren en el mismo origen.
class ThemeController extends ChangeNotifier {
  ThemeController({
    AlmacenTema? almacen,
    ThemeMode modo = ThemeMode.system,
  })  : _almacen = almacen ?? AlmacenTemaMemoria(),
        _modo = modo;

  /// Carga la preferencia guardada; si no hay, queda en `system`.
  static Future<ThemeController> cargar({AlmacenTema? almacen}) async {
    final controlador = ThemeController(
      almacen: almacen ?? const AlmacenTemaPreferencias(),
    );
    await controlador.cargarPreferencia();
    return controlador;
  }

  /// Instancia sin persistencia, útil en pruebas y previews.
  static ThemeController memoria({ThemeMode modo = ThemeMode.system}) {
    return ThemeController(
      almacen: AlmacenTemaMemoria(),
      modo: modo,
    );
  }

  static const String clave = 'gaz2027-tema';

  final AlmacenTema _almacen;
  ThemeMode _modo;

  ThemeMode get modo => _modo;

  bool get esSistema => _modo == ThemeMode.system;

  bool esOscuro(Brightness plataforma) {
    return _modo == ThemeMode.dark ||
        (_modo == ThemeMode.system && plataforma == Brightness.dark);
  }

  /// Lee `light`/`dark`; cualquier otro valor deja el modo del sistema.
  Future<void> cargarPreferencia() async {
    final valor = await _almacen.leer();
    if (valor == 'light') {
      _modo = ThemeMode.light;
    } else if (valor == 'dark') {
      _modo = ThemeMode.dark;
    } else {
      _modo = ThemeMode.system;
    }
    notifyListeners();
  }

  /// Alterna claro/oscuro tomando como referencia el brillo actual.
  Future<void> alternar(Brightness actual) async {
    _modo = esOscuro(actual) ? ThemeMode.light : ThemeMode.dark;
    notifyListeners();
    await _guardar();
  }

  Future<void> usarClaro() async {
    _modo = ThemeMode.light;
    notifyListeners();
    await _guardar();
  }

  Future<void> usarOscuro() async {
    _modo = ThemeMode.dark;
    notifyListeners();
    await _guardar();
  }

  Future<void> usarSistema() async {
    _modo = ThemeMode.system;
    notifyListeners();
    await _almacen.guardar(null);
  }

  Future<void> _guardar() async {
    await _almacen.guardar(_modo == ThemeMode.dark ? 'dark' : 'light');
  }
}

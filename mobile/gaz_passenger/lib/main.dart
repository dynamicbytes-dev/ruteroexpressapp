import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:gaz_core/gaz_core.dart';

/// Arranque de GAZ (app de pasajeros).
///
/// Comparte con GAZ Driver todo el núcleo (`gaz_core`): API, modelos, sesión,
/// billetera, viajes, soporte y diseño. Esta app solo atiende cuentas de
/// pasajero; si alguien entra con una de conductor, se le pide abrir GAZ Driver.
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  final api = ApiClient();
  PushService? push;

  try {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundHandler);

    push = PushService(
      dispositivos: DispositivosRepository(api),
      messaging: FirebaseMessaging.instance,
    );
  } catch (error) {
    // Sin google-services.json/GoogleService-Info.plist la app sigue
    // funcionando con WebSocket y sondeo.
    debugPrint('Notificaciones push no disponibles: $error');
  }

  final tiempoReal = TiempoRealService(api: api);
  final sesion = SesionController(
    repositorio: CuentaRepository(api),
    almacen: TokenStorageSeguro(),
    api: api,
    billetera: BilleteraRepository(api),
    viajes: ViajesRepository(api),
    notificaciones: NotificacionesRepository(api),
    soporte: SoporteRepository(api),
    tiempoReal: tiempoReal,
    push: push,
    rolEsperado: 'passenger',
  );

  final tema = await ThemeController.cargar();

  runApp(
    GazApp(
      sesion: sesion,
      rol: 'passenger',
      titulo: 'GAZ',
      tema: tema,
    ),
  );
}

/// Handler de segundo plano exigido por `firebase_messaging`.
@pragma('vm:entry-point')
Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {
    // Si el equipo no tiene configuración de Firebase, no hay nada que hacer.
  }
}

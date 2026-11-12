import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:gaz_core/gaz_core.dart';

/// Arranque de GAZ en modo legado.
///
/// Todo el código de la app vive en `gaz_core`; este archivo solo conecta los
/// servicios de plataforma (Firebase, almacenamiento seguro, tiempo real) y
/// arranca el árbol compartido. Al no fijar `rol`, la persona puede elegir
/// pasajero o conductor desde la bienvenida; las apps `gaz_passenger` y
/// `gaz_driver` son las versiones especializadas.
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
  );

  final tema = await ThemeController.cargar();

  runApp(
    GazApp(
      sesion: sesion,
      titulo: 'GAZ',
      tema: tema,
    ),
  );
}

/// Handler de segundo plano exigido por `firebase_messaging`.
///
/// El mensaje ya trae una notificación visible, así que el sistema la muestra
/// aunque este código solo inicialice Firebase en el isolate de fondo.
@pragma('vm:entry-point')
Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {
    // Si el equipo no tiene configuración de Firebase, no hay nada que hacer.
  }
}

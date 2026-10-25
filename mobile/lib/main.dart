import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';

import 'app.dart';
import 'core/api/api_client.dart';
import 'core/push/push_service.dart';
import 'core/realtime/tiempo_real_service.dart';
import 'core/storage/token_storage.dart';
import 'data/billetera_repository.dart';
import 'data/cuenta_repository.dart';
import 'data/dispositivos_repository.dart';
import 'data/notificaciones_repository.dart';
import 'data/soporte_repository.dart';
import 'data/viajes_repository.dart';
import 'features/sesion/sesion_controller.dart';

/// Arranque de GAZ.
///
/// La dirección del backend sale de `ApiConfig` (o de
/// `--dart-define=API_BASE_URL=...`); el token vive en el llavero del sistema.
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

  runApp(MotorutaApp(sesion: sesion));
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

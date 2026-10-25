import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../../data/dispositivos_repository.dart';
import 'mensaje_push.dart';

/// Notificaciones push de Firebase.
///
/// Mantiene el token del equipo registrado en el backend y convierte los
/// mensajes en [MensajePush]. La sesión escucha [mensajes] y reutiliza la misma
/// ruta que los eventos del WebSocket. Si Firebase no está configurado, la app
/// sigue funcionando con WebSocket y sondeo.
class PushService {
  PushService({
    required DispositivosRepository dispositivos,
    FirebaseMessaging? messaging,
  })  : _dispositivos = dispositivos,
        _messaging = messaging;

  final DispositivosRepository _dispositivos;
  final FirebaseMessaging? _messaging;

  final StreamController<MensajePush> _mensajes =
      StreamController<MensajePush>.broadcast();

  StreamSubscription<RemoteMessage>? _suscripcionMensaje;
  StreamSubscription<RemoteMessage>? _suscripcionAbierta;
  StreamSubscription<String>? _suscripcionToken;

  bool _activo = false;
  bool _iniciando = false;
  bool _desactivando = false;
  bool _mensajeInicialLeido = false;
  String? _token;

  Stream<MensajePush> get mensajes => _mensajes.stream;

  /// Pide permiso, registra el token y empieza a escuchar mensajes.
  ///
  /// Se llama después de iniciar sesión. Es idempotente: los refrescos de
  /// perfil no vuelven a pedir permiso ni duplican suscripciones.
  Future<void> activar() async {
    if (_iniciando || kIsWeb) return;

    final messaging = _messaging;
    if (messaging == null) return;

    // Si ya está activo y el equipo quedó registrado, no hay nada que hacer.
    // Si el registro falló por red, este método se puede reintentar en el
    // próximo refresco de perfil aunque las suscripciones ya estén puestas.
    if (_activo && _token != null) return;

    _iniciando = true;

    try {
      if (!_activo) {
        await messaging.requestPermission(
          alert: true,
          badge: true,
          sound: true,
        );
        _activo = true;
      }

      _suscripcionMensaje ??=
          FirebaseMessaging.onMessage.listen(_recibirMensaje);
      _suscripcionAbierta ??=
          FirebaseMessaging.onMessageOpenedApp.listen(_recibirMensaje);
      _suscripcionToken ??= messaging.onTokenRefresh.listen(_registrarToken);

      if (!_mensajeInicialLeido) {
        _mensajeInicialLeido = true;
        final inicial = await messaging.getInitialMessage();
        if (inicial != null) _recibirMensaje(inicial);
      }

      final token = await messaging.getToken();
      if (token != null) await _registrarToken(token);
    } catch (_) {
      // Sin configuración de Firebase o sin permiso, la app sigue con el
      // WebSocket y el sondeo. Se permite reintentar en el próximo perfil.
      _activo = false;
    } finally {
      _iniciando = false;
    }
  }

  /// Deja de escuchar y, si corresponde, borra el token del backend y del
  /// equipo. Se llama antes de cerrar sesión para que el token siga siendo
  /// válido al pedir la baja.
  Future<void> desactivar({bool borrarToken = true}) async {
    if (_desactivando) return;

    _desactivando = true;
    _activo = false;

    try {
      await _suscripcionMensaje?.cancel();
      _suscripcionMensaje = null;
      await _suscripcionAbierta?.cancel();
      _suscripcionAbierta = null;
      await _suscripcionToken?.cancel();
      _suscripcionToken = null;

      final token = _token;
      _token = null;

      if (borrarToken && token != null) {
        try {
          await _dispositivos.eliminar(token);
        } catch (_) {
          // Si la sesión ya venció, igual se borra el token local de Firebase.
        }
      }

      if (borrarToken) {
        try {
          await _messaging?.deleteToken();
        } catch (_) {
          // Firebase pudo no estar inicializado; no afecta a la sesión.
        }
      }
    } finally {
      _desactivando = false;
    }
  }

  void _recibirMensaje(RemoteMessage mensaje) {
    if (!_activo) return;

    final push = MensajePush.desdeDatos(mensaje.data);
    if (push != null) _mensajes.add(push);
  }

  Future<void> _registrarToken(String token) async {
    if (!_activo || token.isEmpty) return;
    if (_token == token) return;

    final plataforma = _plataforma;
    if (plataforma == null) return;

    try {
      await _dispositivos.registrar(
        token: token,
        plataforma: plataforma,
      );
      _token = token;
    } catch (_) {
      // Se reintenta cuando el perfil se vuelva a refrescar.
      _token = null;
    }
  }

  String? get _plataforma {
    if (kIsWeb) return null;

    return switch (defaultTargetPlatform) {
      TargetPlatform.android => 'android',
      TargetPlatform.iOS => 'ios',
      _ => null,
    };
  }

  void dispose() {
    unawaited(_suscripcionMensaje?.cancel());
    unawaited(_suscripcionAbierta?.cancel());
    unawaited(_suscripcionToken?.cancel());
    unawaited(_mensajes.close());
  }
}

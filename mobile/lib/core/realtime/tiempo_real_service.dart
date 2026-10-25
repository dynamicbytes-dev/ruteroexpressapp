import 'dart:async';
import 'dart:convert';

import 'package:web_socket_channel/web_socket_channel.dart';

import '../api/api_client.dart';
import 'evento_tiempo_real.dart';
import 'realtime_config.dart';

/// Cliente mínimo del protocolo Pusher que usa Reverb.
///
/// Se implementa aquí, sin dependencias nativas, para que funcione igual en
/// web, Android e iOS. El flujo es:
///
/// 1. Abrir `ws://host:puerto/app/{key}`.
/// 2. Recibir `pusher:connection_established` con el `socket_id`.
/// 3. Pedir la firma a `POST /api/broadcasting/auth` con el token de Sanctum.
/// 4. Suscribirse a `private-App.Models.User.{id}`.
/// 5. Escuchar los eventos y reconectar con espera progresiva si se cae.
class TiempoRealService {
  TiempoRealService({
    required ApiClient api,
    WebSocketChannel Function(Uri)? conector,
  })  : _api = api,
        _conector = conector ?? WebSocketChannel.connect;

  final ApiClient _api;
  final WebSocketChannel Function(Uri) _conector;
  final StreamController<EventoTiempoReal> _eventos =
      StreamController<EventoTiempoReal>.broadcast();

  WebSocketChannel? _canal;
  StreamSubscription<dynamic>? _suscripcion;
  Timer? _ping;
  Timer? _reconexion;

  int? _usuarioId;
  String? _socketId;
  bool _activo = false;
  bool _conectado = false;
  int _intentos = 0;

  static const List<int> _esperas = <int>[1, 2, 5, 10, 20, 30];

  Stream<EventoTiempoReal> get eventos => _eventos.stream;

  bool get conectado => _conectado;

  bool get configurado => RealtimeConfig.configurado;

  /// Abre el canal de la persona. Si ya está abierto para el mismo usuario,
  /// no hace nada.
  Future<void> conectar(int usuarioId) async {
    if (!RealtimeConfig.configurado) return;
    if (_activo && _usuarioId == usuarioId) return;

    await desconectar();

    _usuarioId = usuarioId;
    _activo = true;
    _intentos = 0;

    await _abrir();
  }

  Future<void> _abrir() async {
    if (!_activo) return;

    _ping?.cancel();
    _ping = null;
    _conectado = false;
    _socketId = null;

    try {
      final canal = _conector(RealtimeConfig.uri);
      _canal = canal;

      _suscripcion = canal.stream.listen(
        _alMensaje,
        onError: (Object _) => _programarReconexion(),
        onDone: _programarReconexion,
        cancelOnError: true,
      );
    } catch (_) {
      _programarReconexion();
    }
  }

  void _alMensaje(dynamic crudo) {
    if (crudo is! String) return;

    final Map<String, dynamic> mensaje;

    try {
      final decodificado = jsonDecode(crudo);
      if (decodificado is! Map) return;
      mensaje = Map<String, dynamic>.from(decodificado);
    } catch (_) {
      return;
    }

    final evento = '${mensaje['event'] ?? ''}';

    switch (evento) {
      case 'pusher:connection_established':
        _alConectar(mensaje['data']);
        return;
      case 'pusher_internal:subscription_succeeded':
        _conectado = true;
        _intentos = 0;
        _programarPing();
        _eventos.add(const EventoTiempoReal.conectado());
        return;
      case 'pusher:ping':
        _enviar(<String, dynamic>{
          'event': 'pusher:pong',
          'data': <String, dynamic>{},
        });
        return;
      case 'pusher:error':
        _programarReconexion();
        return;
      default:
        if (evento.startsWith('pusher:') ||
            evento.startsWith('pusher_internal:')) {
          return;
        }

        final interpretado =
            EventoTiempoReal.desdePusher(evento, mensaje['data']);

        if (interpretado != null) _eventos.add(interpretado);
    }
  }

  void _alConectar(dynamic data) {
    final json = _mapa(data);
    final socketId = json['socket_id'];

    if (socketId is! String || _usuarioId == null) {
      _programarReconexion();
      return;
    }

    _socketId = socketId;
    unawaited(_suscribir());
  }

  Future<void> _suscribir() async {
    final socketId = _socketId;
    final usuarioId = _usuarioId;

    if (socketId == null || usuarioId == null) return;

    final canal = 'private-App.Models.User.$usuarioId';

    try {
      final respuesta = await _api.post(
        '/api/broadcasting/auth',
        datos: <String, dynamic>{
          'socket_id': socketId,
          'channel_name': canal,
        },
      );

      final auth = respuesta['auth'];

      if (auth is! String || auth.isEmpty) {
        _programarReconexion();
        return;
      }

      _enviar(<String, dynamic>{
        'event': 'pusher:subscribe',
        'data': <String, dynamic>{
          'auth': auth,
          'channel': canal,
        },
      });
    } catch (_) {
      _programarReconexion();
    }
  }

  void _enviar(Map<String, dynamic> mensaje) {
    try {
      _canal?.sink.add(jsonEncode(mensaje));
    } catch (_) {
      _programarReconexion();
    }
  }

  void _programarPing() {
    _ping?.cancel();
    _ping = Timer.periodic(const Duration(seconds: 25), (_) {
      _enviar(<String, dynamic>{
        'event': 'pusher:ping',
        'data': <String, dynamic>{},
      });
    });
  }

  void _programarReconexion() {
    if (!_activo) return;

    _conectado = false;
    _ping?.cancel();
    _ping = null;
    _socketId = null;

    unawaited(_suscripcion?.cancel());
    _suscripcion = null;

    try {
      _canal?.sink.close();
    } catch (_) {
      // El canal ya estaba cerrado.
    }

    _canal = null;

    if (_reconexion != null) return;

    _intentos = (_intentos + 1).clamp(1, _esperas.length);
    final espera = _esperas[_intentos - 1];

    _reconexion = Timer(Duration(seconds: espera), () {
      _reconexion = null;
      unawaited(_abrir());
    });
  }

  /// Cierra el canal y cancela cualquier reconexión pendiente.
  Future<void> desconectar() async {
    _activo = false;
    _conectado = false;
    _usuarioId = null;
    _socketId = null;
    _intentos = 0;

    _reconexion?.cancel();
    _reconexion = null;
    _ping?.cancel();
    _ping = null;

    await _suscripcion?.cancel();
    _suscripcion = null;

    try {
      await _canal?.sink.close();
    } catch (_) {
      // El canal ya estaba cerrado.
    }

    _canal = null;
  }

  void dispose() {
    unawaited(desconectar());
    unawaited(_eventos.close());
  }
}

Map<String, dynamic> _mapa(Object? valor) {
  if (valor is Map<String, dynamic>) return valor;
  if (valor is Map) return Map<String, dynamic>.from(valor);
  if (valor is String && valor.trim().isNotEmpty) {
    try {
      final decodificado = jsonDecode(valor);
      if (decodificado is Map) return Map<String, dynamic>.from(decodificado);
    } catch (_) {
      // Sin datos válidos: se trata como evento vacío.
    }
  }

  return const <String, dynamic>{};
}

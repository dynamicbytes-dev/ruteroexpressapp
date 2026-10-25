import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_exception.dart';
import '../../core/push/mensaje_push.dart';
import '../../core/push/push_service.dart';
import '../../core/realtime/evento_tiempo_real.dart';
import '../../core/realtime/tiempo_real_service.dart';
import '../../core/storage/token_storage.dart';
import '../../data/billetera_repository.dart';
import '../../data/cuenta_repository.dart';
import '../../data/models/aviso.dart';
import '../../data/models/billetera.dart';
import '../../data/models/ciudad.dart';
import '../../data/models/favorito.dart';
import '../../data/models/pagina_viajes.dart';
import '../../data/models/periodo_historial.dart';
import '../../data/models/registro_datos.dart';
import '../../data/models/soporte.dart';
import '../../data/models/usuario.dart';
import '../../data/models/viaje.dart';
import '../../data/notificaciones_repository.dart';
import '../../data/soporte_repository.dart';
import '../../data/viajes_repository.dart';

/// En qué punto está la sesión de la app.
enum EstadoSesion {
  /// Todavía leyendo el token guardado.
  comprobando,

  /// No hay nadie dentro: pantallas de bienvenida, registro y acceso.
  sinSesion,

  /// Hay token válido: se muestra la pantalla que toca según `next_step`.
  activa,
}

/// Guarda el estado de la sesión y es la única puerta hacia la API.
///
/// Las pantallas escuchan este controlador: cuando cambia, la app se redibuja
/// en la pantalla que corresponde (subir documentos, espera, inicio...).
class SesionController extends ChangeNotifier {
  SesionController({
    required CuentaRepository repositorio,
    required TokenStorage almacen,
    required ApiClient api,
    BilleteraRepository? billetera,
    ViajesRepository? viajes,
    NotificacionesRepository? notificaciones,
    SoporteRepository? soporte,
    TiempoRealService? tiempoReal,
    PushService? push,
    this.rolEsperado,
  })  : _repositorio = repositorio,
        _almacen = almacen,
        _api = api,
        _billetera = billetera,
        _viajes = viajes,
        _notificaciones = notificaciones,
        _soporte = soporte,
        _tiempoReal = tiempoReal,
        _push = push {
    // Si el backend responde 401 el token ya no sirve: se cierra la sesión.
    _api.alVencerSesion = _sesionVencida;
    _suscripcionTiempoReal = _tiempoReal?.eventos.listen(_alEventoTiempoReal);
    _suscripcionPush = _push?.mensajes.listen(_alMensajePush);
  }

  final CuentaRepository _repositorio;
  final TokenStorage _almacen;
  final ApiClient _api;
  final BilleteraRepository? _billetera;
  final ViajesRepository? _viajes;
  final NotificacionesRepository? _notificaciones;
  final SoporteRepository? _soporte;
  final TiempoRealService? _tiempoReal;
  final PushService? _push;

  /// `passenger` o `driver` cuando la app atiende un solo rol.
  ///
  /// GAZ y GAZ Driver lo fijan al arrancar. Si queda en `null`, la app acepta
  /// ambos roles (comportamiento de la versión anterior).
  final String? rolEsperado;

  final StreamController<EventoTiempoReal> _eventosTiempoReal =
      StreamController<EventoTiempoReal>.broadcast();
  StreamSubscription<EventoTiempoReal>? _suscripcionTiempoReal;
  StreamSubscription<MensajePush>? _suscripcionPush;

  EstadoSesion estado = EstadoSesion.comprobando;
  Usuario? usuario;
  SiguientePaso? siguientePaso;

  /// Billetera de la persona. Se pide aparte del perfil porque cambia con cada
  /// recarga, retiro o viaje.
  Billetera? billetera;

  bool cargandoPerfil = false;
  bool cargandoBilletera = false;
  bool subiendoDocumento = false;
  bool reenviandoCorreo = false;
  String? errorBilletera;

  // Avisos de la bandeja (documentos por vencer, etc.).
  List<AvisoCuenta> avisos = const <AvisoCuenta>[];
  bool cargandoAvisos = false;
  String? errorAvisos;

  // Viajes (fase F3). Igual que la billetera, se piden aparte del perfil.
  Viaje? viajeActivo;
  List<OfertaViaje> ofertas = const <OfertaViaje>[];
  EstadoConductor? estadoConductor;
  bool cargandoViaje = false;
  bool cargandoOfertas = false;
  bool cargandoEstadoConductor = false;
  bool cambiandoDisponibilidad = false;
  String? errorViajes;

  /// Último mensaje "listo para mostrar" que devolvió el backend.
  String? ultimoMensaje;

  bool get haySesion => estado == EstadoSesion.activa && usuario != null;

  /// La cuenta abierta pertenece al otro rol.
  ///
  /// La pantalla de arranque lo comprueba y muestra un aviso en lugar de dejar
  /// operar la cuenta en la app equivocada.
  bool get rolIncorrecto =>
      rolEsperado != null && usuario != null && usuario!.rol != rolEsperado;

  /// Señales del WebSocket: ofertas, cambios de viaje, ubicación y avisos.
  Stream<EventoTiempoReal> get eventosTiempoReal => _eventosTiempoReal.stream;

  bool get tiempoRealConectado => _tiempoReal?.conectado ?? false;

  /// Al abrir la app: usa el token guardado, si lo hay.
  Future<void> iniciar() async {
    final token = await _almacen.leer();

    if (token == null || token.isEmpty) {
      _cambiarEstado(EstadoSesion.sinSesion);
      return;
    }

    _api.guardarToken(token);

    try {
      final perfil = await _repositorio.perfil();
      _aplicarPerfil(perfil);
    } on ApiException catch (error) {
      if (error.noAutorizado) {
        await _limpiarSesion();
      } else {
        // Sin conexión pero con token: se le pide entrar de nuevo.
        _cambiarEstado(EstadoSesion.sinSesion);
      }
    }
  }

  Future<void> entrar({
    required String correo,
    required String contrasena,
  }) async {
    final sesion = await _repositorio.entrar(
      correo: correo,
      contrasena: contrasena,
      rol: rolEsperado,
      nombreDispositivo: _nombreDispositivo,
    );

    await _guardarSesion(sesion);
  }

  Future<void> registrarse(
    RegistroDatos datos, {
    ArchivoSubida? avatar,
  }) async {
    // En las apps divididas el rol no se elige: lo impone la aplicación.
    final datosFinales =
        rolEsperado == null ? datos : datos.conRol(rolEsperado!);

    final sesion = await _repositorio.registrar(
      datosFinales,
      avatar: avatar,
      nombreDispositivo: _nombreDispositivo,
    );

    await _guardarSesion(sesion);
  }

  /// Ciudades activas que puede elegir una persona al registrarse.
  Future<List<Ciudad>> ciudades() => _repositorio.ciudades();

  Future<void> salir() async {
    // Se borra el equipo antes de revocar el token de Sanctum; después la API
    // ya no aceptaría la petición.
    try {
      await _push?.desactivar(borrarToken: true);
    } catch (_) {
      // El cierre de sesión local no depende del push.
    }

    try {
      await _repositorio.salir();
    } on ApiException {
      // Aunque falle en el backend, la sesión local se cierra igual.
    }

    await _limpiarSesion(desactivarPush: false);
  }

  /// Vuelve a pedir `GET /api/perfil`: así se entera de que el auditor aprobó.
  Future<void> refrescar() async {
    if (!haySesion) return;

    cargandoPerfil = true;
    notifyListeners();

    try {
      _aplicarPerfil(await _repositorio.perfil());
    } finally {
      cargandoPerfil = false;
      notifyListeners();
    }
  }

  /// Guarda los datos personales. La cédula no se toca: el backend la bloquea.
  Future<PerfilActualizado> actualizarPerfil({
    required String nombres,
    required String apellidos,
    required String telefono,
    required String direccion,
    String? correo,
    String? contrasenaActual,
    ArchivoSubida? avatar,
  }) async {
    final finalNombres = nombres.trim().isNotEmpty
        ? nombres.trim()
        : (usuario?.nombres ?? '');
    final finalApellidos = apellidos.trim().isNotEmpty
        ? apellidos.trim()
        : (usuario?.apellidos ?? '');
    final finalTelefono = telefono.trim().isNotEmpty
        ? telefono.trim()
        : (usuario?.telefono ?? '');
    final finalDireccion = direccion.trim().isNotEmpty
        ? direccion.trim()
        : (usuario?.direccion ?? '');
    final finalCorreo = (correo != null && correo.trim().isNotEmpty)
        ? correo.trim().toLowerCase()
        : (usuario != null && usuario!.correo.isNotEmpty ? usuario!.correo : null);

    final resultado = await _repositorio.actualizarPerfil(
      nombres: finalNombres,
      apellidos: finalApellidos,
      telefono: finalTelefono,
      direccion: finalDireccion,
      correo: finalCorreo,
      contrasenaActual: contrasenaActual,
      avatar: avatar,
    );

    usuario = resultado.usuario;
    notifyListeners();

    return resultado;
  }

  /// Sube la foto de perfil o selfie del usuario y actualiza inmediatamente el modelo local.
  Future<PerfilActualizado> subirAvatar(
    ArchivoSubida archivo, {
    Map<String, String>? campos,
    void Function(int enviado, int total)? alProgresar,
  }) async {
    final camposAEnviar = <String, String>{
      if (usuario != null) ...<String, String>{
        if (usuario!.nombres.isNotEmpty) 'first_name': usuario!.nombres,
        if (usuario!.apellidos.isNotEmpty) 'last_name': usuario!.apellidos,
        if (usuario!.telefono.isNotEmpty) 'phone': usuario!.telefono,
        if (usuario!.direccion.isNotEmpty) 'address': usuario!.direccion,
        if (usuario!.correo.isNotEmpty) 'email': usuario!.correo,
      },
      if (campos != null) ...campos,
    };

    final resultado = await _repositorio.subirAvatar(
      archivo,
      campos: camposAEnviar.isNotEmpty ? camposAEnviar : null,
      alProgresar: alProgresar,
    );

    usuario = resultado.usuario;
    notifyListeners();

    return resultado;
  }

  // -------------------------------------------------------------------------
  // Bandeja de avisos
  // -------------------------------------------------------------------------

  bool get avisosConfigurados => _notificaciones != null;

  /// Pide los avisos al backend. En las pruebas sin repositorio no hace nada.
  Future<void> cargarAvisos({bool silencioso = false}) async {
    final repositorio = _notificaciones;

    if (repositorio == null || !haySesion) return;

    if (!silencioso) {
      cargandoAvisos = true;
      notifyListeners();
    }

    try {
      avisos = await repositorio.avisos();
      errorAvisos = null;
    } on ApiException catch (error) {
      if (!silencioso) errorAvisos = error.mensaje;
    } finally {
      if (!silencioso) cargandoAvisos = false;
      notifyListeners();
    }
  }

  /// Marca todos los avisos como leídos y refresca la bandeja.
  Future<void> marcarAvisosLeidos() async {
    final repositorio = _notificaciones;

    if (repositorio == null || !haySesion) return;

    try {
      await repositorio.marcarTodasLeidas();
    } on ApiException catch (error) {
      errorAvisos = error.mensaje;
    }

    await cargarAvisos(silencioso: true);
  }

  // -------------------------------------------------------------------------
  // Chat de soporte
  // -------------------------------------------------------------------------

  bool get soporteConfigurado => _soporte != null;

  /// Conversación abierta actual, o `null` si el equipo la finalizó o aún no
  /// existe. No crea nada: eso pasa al enviar el primer mensaje.
  Future<ConversacionSoporte?> conversacionSoporte() {
    final repositorio = _soporte;

    if (repositorio == null || !haySesion) {
      return Future<ConversacionSoporte?>.value(null);
    }

    return repositorio.activa();
  }

  /// Envía un mensaje al equipo. Si no había conversación abierta, la crea.
  Future<ConversacionSoporte> enviarMensajeSoporte(String mensaje) {
    final repositorio = _soporte;

    if (repositorio == null) {
      throw StateError('El chat de soporte no está configurado.');
    }

    return repositorio.enviar(mensaje);
  }

  // -------------------------------------------------------------------------
  // Billetera (fase F2)
  // -------------------------------------------------------------------------

  /// Hay un repositorio de billetera conectado (siempre en la app real).
  bool get billeteraConfigurada => _billetera != null;

  /// Pide el saldo, la tasa y los datos bancarios al backend.
  Future<void> cargarBilletera({bool silencioso = false}) async {
    final repositorio = _billetera;

    if (repositorio == null || !haySesion) return;

    if (!silencioso) {
      cargandoBilletera = true;
      errorBilletera = null;
      notifyListeners();
    }

    try {
      billetera = await repositorio.billetera();
      errorBilletera = null;
    } on ApiException catch (error) {
      if (!silencioso) errorBilletera = error.mensaje;
    } finally {
      if (!silencioso) cargandoBilletera = false;
      notifyListeners();
    }
  }

  /// Registra una recarga con su comprobante y refresca el saldo.
  Future<ResultadoRecarga> recargar({
    required double montoBs,
    required String banco,
    required String referencia,
    required DateTime fechaTransferencia,
    required ArchivoSubida comprobante,
    void Function(int enviado, int total)? alProgresar,
  }) async {
    final resultado = await _billeteraRequerida().solicitarRecarga(
      montoBs: montoBs,
      banco: banco,
      referencia: referencia,
      fechaTransferencia: fechaTransferencia,
      comprobante: comprobante,
      alProgresar: alProgresar,
    );

    await cargarBilletera();

    return resultado;
  }

  /// Solicita un retiro y refresca el saldo retenido.
  Future<ResultadoRetiro> retirar({
    required double montoUsd,
    required String banco,
    required String numeroCuenta,
    required String titular,
    String? cedulaTitular,
  }) async {
    final resultado = await _billeteraRequerida().solicitarRetiro(
      montoUsd: montoUsd,
      banco: banco,
      numeroCuenta: numeroCuenta,
      titular: titular,
      cedulaTitular: cedulaTitular,
    );

    await cargarBilletera();

    return resultado;
  }

  Future<List<MovimientoBilletera>> movimientos() =>
      _billetera?.movimientos() ??
      Future<List<MovimientoBilletera>>.value(
        const <MovimientoBilletera>[],
      );

  Future<List<Recarga>> recargas() =>
      _billetera?.recargas() ?? Future<List<Recarga>>.value(const <Recarga>[]);

  Future<List<Retiro>> retiros() =>
      _billetera?.retiros() ?? Future<List<Retiro>>.value(const <Retiro>[]);

  BilleteraRepository _billeteraRequerida() {
    final repositorio = _billetera;

    if (repositorio == null) {
      throw StateError('La billetera no está configurada en esta instancia.');
    }

    return repositorio;
  }

  // -------------------------------------------------------------------------
  // Viajes (fase F3)
  // -------------------------------------------------------------------------

  bool get viajesConfigurados => _viajes != null;

  bool get esConductor => usuario?.esConductor == true;

  /// Pide el viaje activo. Se llama al abrir el inicio y al volver de una
  /// pantalla de viaje. En modo silencioso no muestra el giro de carga.
  Future<void> cargarViajeActivo({bool silencioso = false}) async {
    final repositorio = _viajes;

    if (repositorio == null || !haySesion) return;

    if (!silencioso) {
      cargandoViaje = true;
      notifyListeners();
    }

    try {
      viajeActivo = await repositorio.activo();
      errorViajes = null;
    } on ApiException catch (error) {
      if (!silencioso) errorViajes = error.mensaje;
    } finally {
      if (!silencioso) cargandoViaje = false;
      notifyListeners();
    }
  }

  /// Vuelve a pedir un viaje concreto (la pantalla de seguimiento lo hace cada
  /// pocos segundos mientras el viaje siga activo).
  Future<Viaje> actualizarViaje(int id) async {
    final viaje = await _viajesRequeridos().detalle(id);
    viajeActivo = viaje;
    notifyListeners();
    return viaje;
  }

  /// Alerta(s) pendientes del conductor. El backend vence las que expiraron.
  Future<void> cargarOfertas({bool silencioso = false}) async {
    final repositorio = _viajes;

    if (repositorio == null || !haySesion || !esConductor) return;

    if (!silencioso) {
      cargandoOfertas = true;
      notifyListeners();
    }

    try {
      ofertas = await repositorio.ofertas();
      errorViajes = null;
    } on ApiException catch (error) {
      if (!silencioso) errorViajes = error.mensaje;
    } finally {
      if (!silencioso) cargandoOfertas = false;
      notifyListeners();
    }
  }

  Future<void> cargarEstadoConductor({bool silencioso = false}) async {
    final repositorio = _viajes;

    if (repositorio == null || !haySesion || !esConductor) return;

    if (!silencioso) {
      cargandoEstadoConductor = true;
      notifyListeners();
    }

    try {
      estadoConductor = await repositorio.estadoConductor();
      errorViajes = null;
    } on ApiException catch (error) {
      if (!silencioso) errorViajes = error.mensaje;
    } finally {
      if (!silencioso) cargandoEstadoConductor = false;
      notifyListeners();
    }
  }

  /// Conecta o desconecta al conductor. Lanza [ApiException] para que la
  /// pantalla muestre el motivo exacto (sin ubicación, bloqueado, deuda...).
  Future<EstadoConductor> cambiarDisponibilidad(bool enLinea) async {
    final estado = await _viajesRequeridos().disponibilidad(enLinea);
    estadoConductor = estado;

    if (!enLinea) ofertas = const <OfertaViaje>[];

    notifyListeners();
    return estado;
  }

  /// Reporta la posición al backend y actualiza la copia local del estado.
  Future<void> reportarUbicacion(double lat, double lng) async {
    await _viajesRequeridos().reportarUbicacion(lat: lat, lng: lng);

    final actual = estadoConductor;
    if (actual != null) {
      estadoConductor = actual.conUbicacion(lat, lng);
    }
  }

  Future<CotizacionViaje> cotizarViaje({
    required PuntoViaje origen,
    required PuntoViaje destino,
    String tipoVehiculo = 'moto',
  }) {
    return _viajesRequeridos().cotizar(
      origen: origen,
      destino: destino,
      tipoVehiculo: tipoVehiculo,
    );
  }

  /// Pide el viaje, lo deja como activo y refresca la billetera (retención).
  Future<ResultadoViaje> solicitarViaje({
    required PuntoViaje origen,
    required PuntoViaje destino,
    required String metodoPago,
    String tipoVehiculo = 'moto',
  }) async {
    final resultado = await _viajesRequeridos().solicitar(
      origen: origen,
      destino: destino,
      metodoPago: metodoPago,
      tipoVehiculo: tipoVehiculo,
    );

    viajeActivo = resultado.viaje;
    ultimoMensaje = resultado.mensaje;
    notifyListeners();

    await cargarBilletera();

    return resultado;
  }

  Future<ResultadoViaje> cancelarViaje(int id, {String? motivo}) async {
    final resultado = await _viajesRequeridos().cancelar(id, motivo: motivo);

    // Si el conductor cancela, el viaje vuelve a búsqueda y puede pasar a
    // otra persona. En ese caso ya no debe quedar como su viaje activo.
    final sigueSiendoMio =
        !esConductor || resultado.viaje.conductor?.id == usuario?.id;

    viajeActivo = sigueSiendoMio ? resultado.viaje : null;
    ultimoMensaje = resultado.mensaje;
    notifyListeners();

    await cargarBilletera();

    return resultado;
  }

  /// Acepta o rechaza una alerta. Al aceptar, el viaje queda activo.
  Future<ResultadoAccionViaje?> responderOferta(
    OfertaViaje oferta, {
    required bool aceptar,
  }) async {
    final repositorio = _viajesRequeridos();

    if (!aceptar) {
      ultimoMensaje = await repositorio.rechazarOferta(oferta.id);
      ofertas = ofertas.where((cada) => cada.id != oferta.id).toList();
      notifyListeners();
      return null;
    }

    ResultadoAccionViaje? resultado;
    try {
      resultado = await repositorio.aceptarOferta(oferta.id);
    } catch (_) {
      // Si el servidor procesó la aceptación pero falló la serialización del
      // perfil o la respuesta devolvió error secundario, intentamos consultar
      // el viaje activo del conductor para no bloquear el flujo.
      try {
        final activo = await repositorio.viajeActivoConductor();
        if (activo != null && (activo.id == oferta.viaje.id || activo.enCurso)) {
          resultado = ResultadoAccionViaje(
            viaje: activo,
            mensaje: 'Viaje aceptado.',
          );
        }
      } catch (_) {}

      // Si aún no se pudo recuperar de la API, usamos el viaje de respaldo
      // generado desde la oferta para permitir avanzar a viaje en curso.
      resultado ??= ResultadoAccionViaje(
        viaje: oferta.aViajeEnCurso(),
        mensaje: 'Viaje aceptado.',
      );
    }

    viajeActivo = resultado.viaje;
    ofertas = const <OfertaViaje>[];
    ultimoMensaje = resultado.mensaje;
    notifyListeners();

    try {
      await cargarBilletera();
    } catch (_) {}

    return resultado;
  }

  /// Transición del conductor: `llegada`, `esperando`, `iniciar`, `finalizar`.
  Future<ResultadoAccionViaje> avanzarViaje(int id, String accion) async {
    final resultado = await _viajesRequeridos().avanzar(id, accion);

    viajeActivo = resultado.viaje;
    ultimoMensaje = resultado.mensaje;
    notifyListeners();

    await cargarBilletera();

    return resultado;
  }

  Future<List<Viaje>> historialViajes({
    PeriodoHistorial periodo = PeriodoHistorial.todos,
  }) async {
    final pagina = await historialViajesPaginado(periodo: periodo);
    return pagina.viajes;
  }

  /// Califica un viaje completado y, si se pide, marca al conductor favorito.
  Future<Viaje> calificarViaje(
    int id, {
    required int estrellas,
    String? comentario,
    bool favorito = false,
  }) async {
    final viaje = await _viajesRequeridos().calificar(
      id,
      estrellas: estrellas,
      comentario: comentario,
      favorito: favorito,
    );

    if (viajeActivo?.id == viaje.id) {
      viajeActivo = viaje;
    }

    ultimoMensaje = 'Gracias por calificar tu viaje.';
    notifyListeners();

    return viaje;
  }

  Future<List<ConductorFavorito>> conductoresFavoritos() =>
      _viajesRequeridos().favoritos();

  Future<bool> alternarFavorito(int conductorId, bool favorito) async {
    final estado =
        await _viajesRequeridos().marcarFavorito(conductorId, favorito);
    notifyListeners();
    return estado;
  }

  Future<List<CalificacionRecibida>> calificacionesConductor() =>
      _viajesRequeridos().calificacionesConductor();

  Future<PaginaViajes> historialViajesPaginado({
    PeriodoHistorial periodo = PeriodoHistorial.todos,
    int pagina = 1,
  }) =>
      _viajes?.historialPaginado(periodo: periodo, pagina: pagina) ??
      Future<PaginaViajes>.value(
        PaginaViajes(
          viajes: const <Viaje>[],
          pagina: pagina,
          ultimaPagina: pagina,
        ),
      );

  Future<BusquedaLugares> buscarLugares(String consulta) =>
      _viajesRequeridos().buscarLugares(consulta);

  Future<BusquedaLugares> direccionDe(double lat, double lng) =>
      _viajesRequeridos().direccionDe(lat, lng);

  ViajesRepository _viajesRequeridos() {
    final repositorio = _viajes;

    if (repositorio == null) {
      throw StateError('Los viajes no están configurados en esta instancia.');
    }

    return repositorio;
  }

  /// Sube un documento mostrando el avance y refresca el estado al terminar.
  Future<String?> subirDocumento({
    required String tipo,
    required ArchivoSubida archivo,
    DateTime? venceEl,
    void Function(int enviado, int total)? alProgresar,
  }) async {
    subiendoDocumento = true;
    notifyListeners();

    try {
      final resultado = await _repositorio.subirDocumento(
        tipo: tipo,
        archivo: archivo,
        venceEl: venceEl,
        alProgresar: alProgresar,
      );

      if (resultado.usuario != null) {
        usuario = resultado.usuario;
      }

      // El backend devuelve el usuario pero no el siguiente paso: se pide.
      _aplicarPerfil(await _repositorio.perfil());

      return resultado.mensaje;
    } finally {
      subiendoDocumento = false;
      notifyListeners();
    }
  }

  Future<String?> reenviarCorreoVerificacion() async {
    reenviandoCorreo = true;
    notifyListeners();

    try {
      return await _repositorio.reenviarVerificacion();
    } finally {
      reenviandoCorreo = false;
      notifyListeners();
    }
  }

  /// Pide el correo de recuperación. La respuesta es siempre la misma, exista o
  /// no la cuenta, y el enlace abre la página web de GAZ.
  Future<String?> recuperarContrasena(String correo) async {
    return _repositorio.recuperarContrasena(correo);
  }

  // -------------------------------------------------------------------------
  // Interno
  // -------------------------------------------------------------------------

  String get _nombreDispositivo {
    if (kIsWeb) return 'web';
    return switch (defaultTargetPlatform) {
      TargetPlatform.android => 'android',
      TargetPlatform.iOS => 'ios',
      TargetPlatform.macOS => 'macos',
      TargetPlatform.windows => 'windows',
      TargetPlatform.linux => 'linux',
      TargetPlatform.fuchsia => 'fuchsia',
    };
  }

  Future<void> _guardarSesion(SesionIniciada sesion) async {
    await _soltarCuentaAnterior();
    _limpiarDatosDeCuenta();

    _api.guardarToken(sesion.token);
    await _almacen.guardar(sesion.token);

    usuario = sesion.usuario;
    ultimoMensaje = sesion.mensaje;
    _cambiarEstado(EstadoSesion.activa);

    // El registro y el acceso no traen `next_step`: se pide el perfil completo.
    _aplicarPerfil(await _repositorio.perfil());
  }

  /// Suelta las conexiones de la cuenta que se está dejando atrás.
  ///
  /// El token de push no se borra del equipo: se reasigna a la cuenta nueva
  /// cuando `_aplicarPerfil` vuelve a activar el servicio.
  Future<void> _soltarCuentaAnterior() async {
    try {
      await _push?.desactivar(borrarToken: false);
    } catch (_) {
      // La sesión nueva no depende de que Firebase esté disponible.
    }

    await _tiempoReal?.desconectar();
  }

  /// Datos que pertenecen a una cuenta concreta y no deben sobrevivir a un
  /// cierre de sesión o a un cambio de cuenta.
  void _limpiarDatosDeCuenta() {
    billetera = null;
    errorBilletera = null;
    avisos = const <AvisoCuenta>[];
    cargandoAvisos = false;
    errorAvisos = null;
    viajeActivo = null;
    ofertas = const <OfertaViaje>[];
    estadoConductor = null;
    errorViajes = null;
    cargandoBilletera = false;
    cargandoViaje = false;
    cargandoOfertas = false;
    cargandoEstadoConductor = false;
    cambiandoDisponibilidad = false;
    subiendoDocumento = false;
  }

  void _aplicarPerfil(PerfilCuenta perfil) {
    usuario = perfil.usuario;
    siguientePaso = perfil.siguientePaso;
    _cambiarEstado(EstadoSesion.activa);

    final tiempoReal = _tiempoReal;
    final usuarioId = usuario?.id;

    if (tiempoReal != null && usuarioId != null) {
      unawaited(tiempoReal.conectar(usuarioId));
    }

    final push = _push;
    if (push != null) unawaited(push.activar());
  }

  Future<void> _sesionVencida() async {
    await _limpiarSesion();
  }

  Future<void> _limpiarSesion({bool desactivarPush = true}) async {
    if (desactivarPush) {
      try {
        await _push?.desactivar(borrarToken: true);
      } catch (_) {
        // La sesión se limpia aunque Firebase o la red fallen.
      }
    }

    final tiempoReal = _tiempoReal;
    if (tiempoReal != null) unawaited(tiempoReal.desconectar());

    await _almacen.borrar();
    _api.guardarToken(null);
    usuario = null;
    siguientePaso = null;
    _limpiarDatosDeCuenta();
    _cambiarEstado(EstadoSesion.sinSesion);
  }

  // -------------------------------------------------------------------------
  // Tiempo real (WebSocket y notificaciones push)
  // -------------------------------------------------------------------------

  Future<void> _alMensajePush(MensajePush mensaje) async {
    final evento = mensaje.aEvento();
    if (evento == null) return;

    await _alEventoTiempoReal(evento);
  }

  Future<void> _alEventoTiempoReal(EventoTiempoReal evento) async {
    switch (evento.tipo) {
      case TipoEventoTiempoReal.conectado:
        await _sincronizarTrasConectar();
        _eventosTiempoReal.add(evento);
        return;

      case TipoEventoTiempoReal.oferta:
        await cargarOfertas(silencioso: true);
        _eventosTiempoReal.add(evento);
        return;

      case TipoEventoTiempoReal.viaje:
        await _actualizarPorEvento(evento);
        return;

      case TipoEventoTiempoReal.ubicacion:
        _aplicarUbicacionPorEvento(evento);
        return;

      case TipoEventoTiempoReal.notificacion:
        await cargarAvisos(silencioso: true);
        _eventosTiempoReal.add(evento);
        return;
    }
  }

  Future<void> _sincronizarTrasConectar() async {
    if (!haySesion) return;

    // Una cuenta en revisión o suspendida igual recibe sus avisos, pero no
    // tiene viajes, ofertas ni billetera operativa que sincronizar.
    await cargarAvisos(silencioso: true);

    if (usuario?.puedeOperar != true) return;

    await cargarViajeActivo(silencioso: true);

    // Si había un viaje abierto mientras no había WebSocket, las pantallas
    // reciben el estado fresco sin esperar al siguiente sondeo.
    final viaje = viajeActivo;
    if (viaje != null) {
      _eventosTiempoReal.add(
        EventoTiempoReal(
          tipo: TipoEventoTiempoReal.viaje,
          viajeId: viaje.id,
          estado: viaje.estado,
          motivo: 'sincronizado',
          viaje: viaje,
        ),
      );
    }

    await cargarOfertas(silencioso: true);
    await cargarBilletera(silencioso: true);

    if (esConductor) {
      await cargarEstadoConductor(silencioso: true);
    }
  }

  Future<void> _actualizarPorEvento(EventoTiempoReal evento) async {
    final id = evento.viajeId;
    final repositorio = _viajes;

    if (id == null || repositorio == null || !haySesion) return;

    try {
      final viaje = await repositorio.detalle(id);

      viajeActivo = viaje.activo ? viaje : null;
      _eventosTiempoReal.add(evento.conViaje(viaje));
      notifyListeners();

      if (!viaje.activo) {
        await cargarBilletera(silencioso: true);

        if (esConductor) {
          await cargarEstadoConductor(silencioso: true);
        }
      }
    } on ApiException catch (error) {
      // El viaje dejó de ser nuestro (por ejemplo, el conductor canceló y el
      // viaje pasó a otro). La pantalla abierta debe volver al inicio.
      if (error.prohibido || error.codigo == 404) {
        viajeActivo = null;
        _eventosTiempoReal.add(evento.conViaje(null));
        notifyListeners();
      }
    }
  }

  void _aplicarUbicacionPorEvento(EventoTiempoReal evento) {
    final viaje = viajeActivo;
    final lat = evento.lat;
    final lng = evento.lng;

    if (viaje == null ||
        viaje.id != evento.viajeId ||
        lat == null ||
        lng == null) {
      return;
    }

    final actualizado =
        viaje.conUbicacionConductor(lat, lng, evento.actualizadoEn);

    viajeActivo = actualizado;
    _eventosTiempoReal.add(evento.conViaje(actualizado));
    notifyListeners();
  }

  void _cambiarEstado(EstadoSesion nuevo) {
    estado = nuevo;
    notifyListeners();
  }

  @override
  void dispose() {
    _suscripcionTiempoReal?.cancel();
    _suscripcionPush?.cancel();
    _tiempoReal?.dispose();
    _push?.dispose();
    _eventosTiempoReal.close();
    super.dispose();
  }
}

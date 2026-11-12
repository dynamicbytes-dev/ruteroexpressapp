import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avatar_usuario.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/boton_tema.dart';
import '../../../core/ui/botones.dart';
import '../../../data/models/aviso.dart';
import '../../../data/models/viaje.dart';
import '../../billetera/billetera_ui.dart';
import '../../billetera/screens/movimientos_screen.dart';
import '../../billetera/screens/recargar_screen.dart';
import '../../billetera/screens/retirar_screen.dart';
import '../../sesion/accion_salir.dart';
import '../../sesion/sesion_controller.dart';
import '../../soporte/screens/soporte_chat_screen.dart';
import '../../viajes/screens/favoritos_screen.dart';
import '../../viajes/screens/historial_viajes_screen.dart';
import '../../viajes/screens/pedir_viaje_screen.dart';
import '../../viajes/screens/viaje_conductor_screen.dart';
import '../../viajes/screens/viaje_pasajero_screen.dart';
import '../../viajes/viajes_ui.dart';
import '../../../core/ui/barra_navegacion_flotante.dart';
import '../../billetera/screens/billetera_screen.dart';
import '../../viajes/widgets/panel_conductor.dart';
import '../widgets/carrusel_banners.dart';
import 'mis_datos_screen.dart';

/// Pantalla de la cuenta ya aprobada (`next_step.key = ready`).
///
/// La billetera ya funciona (fase F2). Pedir viajes y activarse llegan con la
/// fase de viajes: aquí se dice claro, sin botones que no hacen nada todavía.
class InicioScreen extends StatefulWidget {
  const InicioScreen({super.key});

  @override
  State<InicioScreen> createState() => _InicioScreenState();
}

class _InicioScreenState extends State<InicioScreen> {
  Timer? _relojPasajero;
  int _indicePestana = 0;
  final Set<int> _pestanasCargadas = <int>{0};

  int _indiceConductor = 0;
  final Set<int> _pestanasConductorCargadas = <int>{0};
  final GlobalKey<PanelConductorState> _panelConductor =
      GlobalKey<PanelConductorState>();

  void _cambiarPestana(int indice) {
    setState(() {
      _indicePestana = indice;
      _pestanasCargadas.add(indice);
    });
  }

  void _cambiarPestanaConductor(int indice) {
    setState(() {
      _indiceConductor = indice;
      _pestanasConductorCargadas.add(indice);
    });
  }

  /// Lleva al conductor a la pestaña de conexión y, si corresponde, alterna su
  /// disponibilidad con la misma lógica del interruptor de [PanelConductor].
  void _irAConexionConductor({bool alternar = false}) {
    if (_indiceConductor != 2) {
      _cambiarPestanaConductor(2);
    }

    if (!alternar) return;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      _panelConductor.currentState?.alternarConexion();
    });
  }

  @override
  void initState() {
    super.initState();

    // La billetera se pide aparte del perfil. En las pruebas sin repositorio
    // configurado el método no hace nada.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;

      final sesion = context.read<SesionController>();
      sesion.cargarBilletera();
      sesion.cargarAvisos(silencioso: true);
      sesion.cargarBanners(silencioso: true);

      if (!sesion.viajesConfigurados) return;

      sesion.cargarViajeActivo(silencioso: true);

      // El pasajero puede volver al inicio con un viaje abierto: se refresca
      // solo para mostrar si el conductor ya aceptó. Con WebSocket el cambio
      // llega al instante; este sondeo queda como respaldo.
      if (!sesion.esConductor) {
        final segundos = sesion.tiempoRealConectado ? 30 : 10;

        _relojPasajero = Timer.periodic(Duration(seconds: segundos), (_) {
          if (mounted) {
            context
                .read<SesionController>()
                .cargarViajeActivo(silencioso: true);
          }
        });
      }
    });
  }

  @override
  void dispose() {
    _relojPasajero?.cancel();
    super.dispose();
  }

  Future<void> _refrescar() async {
    final sesion = context.read<SesionController>();
    await sesion.refrescar();
    await sesion.cargarBilletera();
    await sesion.cargarAvisos(silencioso: true);
    await sesion.cargarBanners(silencioso: true);

    if (sesion.viajesConfigurados) {
      await sesion.cargarViajeActivo(silencioso: true);
    }
  }

  Future<void> _abrir(Widget pantalla) {
    return Navigator.of(context).push<void>(
      MaterialPageRoute<void>(builder: (_) => pantalla),
    );
  }

  Widget _construirConductor(
    BuildContext context,
    SesionController sesion,
    dynamic usuario,
  ) {
    final estado = sesion.estadoConductor;
    final enLinea = estado?.enLinea == true;
    final titulo = switch (_indiceConductor) {
      0 => 'Inicio',
      1 => 'Mis viajes',
      2 => 'Conexión',
      3 => 'Ganancias',
      4 => 'Mi perfil',
      _ => 'GAZ',
    };

    return Scaffold(
      extendBody: true,
      appBar: AppBar(
        title: Text(titulo),
        actions: const <Widget>[BotonTema(), AccionSalir()],
      ),
      floatingActionButton: (_indiceConductor == 0 || _indiceConductor == 4)
          ? FloatingActionButton.extended(
              onPressed: () => _abrir(const SoporteChatScreen()),
              backgroundColor: context.gaz.brand,
              foregroundColor: context.gaz.onBrand,
              icon: const Icon(Icons.support_agent_rounded),
              label: const Text('Soporte'),
            )
          : null,
      body: IndexedStack(
        index: _indiceConductor,
        children: <Widget>[
          _pestanaInicioConductor(context, sesion, usuario),
          _pestanasConductorCargadas.contains(1)
              ? const HistorialViajesScreen(mostrarAppBar: false)
              : const SizedBox.shrink(),
          _pestanaConexionConductor(context, sesion),
          _pestanasConductorCargadas.contains(3)
              ? _pestanaGananciasConductor(context, sesion)
              : const SizedBox.shrink(),
          _pestanasConductorCargadas.contains(4)
              ? _PestanaPerfil(
                  usuario: usuario,
                  sesion: sesion,
                  esConductor: true,
                  alEditarDatos: () => _abrir(const MisDatosScreen()),
                  alAbrirSoporte: () => _abrir(const SoporteChatScreen()),
                )
              : const SizedBox.shrink(),
        ],
      ),
      bottomNavigationBar: BarraNavegacionFlotante(
        indiceActual: _indiceConductor,
        alCambiarPestana: (indice) {
          if (indice == 2) {
            _irAConexionConductor(alternar: true);
          } else {
            _cambiarPestanaConductor(indice);
          }
        },
        elementos: <ElementoNavegacion>[
          const ElementoNavegacion(
            icono: Icons.home_outlined,
            iconoActivo: Icons.home_rounded,
            etiqueta: 'Inicio',
          ),
          const ElementoNavegacion(
            icono: Icons.route_outlined,
            iconoActivo: Icons.route_rounded,
            etiqueta: 'Viajes',
          ),
          ElementoNavegacion(
            icono: Icons.power_settings_new_rounded,
            etiqueta: enLinea ? 'En línea' : 'Conectar',
            esAccion: true,
          ),
          const ElementoNavegacion(
            icono: Icons.account_balance_wallet_outlined,
            iconoActivo: Icons.account_balance_wallet_rounded,
            etiqueta: 'Ganancias',
          ),
          const ElementoNavegacion(
            icono: Icons.person_outline_rounded,
            iconoActivo: Icons.person_rounded,
            etiqueta: 'Perfil',
          ),
        ],
      ),
    );
  }

  Widget _pestanaInicioConductor(
    BuildContext context,
    SesionController sesion,
    dynamic usuario,
  ) {
    final estado = sesion.estadoConductor;
    final enLinea = estado?.enLinea == true;
    final viaje = sesion.viajeActivo;

    return RefreshIndicator(
      onRefresh: _refrescar,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: <Widget>[
          Tarjeta(
            child: Row(
              children: <Widget>[
                AvatarUsuario(usuario: usuario),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        usuario.nombreCompleto.isEmpty
                            ? 'Tu cuenta'
                            : usuario.nombreCompleto,
                        style: MotorutaTheme.tituloDe(context, tamano: 20),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${usuario.rolEtiqueta} · cédula ${usuario.cedula}',
                        style: TextStyle(
                          fontSize: 14,
                          color: context.gaz.text2,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          if (sesion.avisosConfigurados &&
              sesion.avisos.any((aviso) => !aviso.leido)) ...<Widget>[
            const SizedBox(height: 18),
            _TarjetaAvisos(
              avisos: sesion.avisos.where((aviso) => !aviso.leido).toList(),
              alMarcarTodos: sesion.marcarAvisosLeidos,
            ),
          ],
          const SizedBox(height: 18),
          Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Container(
                      width: 46,
                      height: 46,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: enLinea
                            ? context.gaz.successBg
                            : context.gaz.surfaceAlt,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Icon(
                        Icons.power_settings_new_rounded,
                        color:
                            enLinea ? context.gaz.success : context.gaz.text2,
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            'MODO CONDUCTOR',
                            style: TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 0.9,
                              color: context.gaz.accent,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            estado == null
                                ? 'Consultando tu conexión…'
                                : enLinea
                                    ? 'Estás en línea'
                                    : 'Estás desconectado',
                            style: MotorutaTheme.tituloDe(context, tamano: 18),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            enLinea
                                ? 'Recibiendo alertas de viajes cercanos.'
                                : 'Conéctate para empezar a recibir viajes.',
                            style: TextStyle(
                              fontSize: 12.5,
                              color: context.gaz.text2,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (sesion.cargandoEstadoConductor)
                      const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(strokeWidth: 2.4),
                      ),
                  ],
                ),
                const SizedBox(height: 14),
                BotonPrincipal(
                  texto: estado == null
                      ? 'Ver conexión'
                      : enLinea
                          ? 'Gestionar conexión'
                          : 'Conectarme',
                  icono: Icons.power_settings_new_rounded,
                  alPresionar: () => _irAConexionConductor(
                    alternar: estado != null && !enLinea,
                  ),
                ),
              ],
            ),
          ),
          if (viaje != null && viaje.activo) ...<Widget>[
            const SizedBox(height: 18),
            Tarjeta(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: Text(
                          'Viaje en curso',
                          style: MotorutaTheme.tituloDe(context, tamano: 18),
                        ),
                      ),
                      etiquetaEstadoViaje(viaje),
                    ],
                  ),
                  const SizedBox(height: 10),
                  LineaRuta(
                    origen: viaje.origen,
                    destino: viaje.destino,
                    compacta: true,
                  ),
                  const SizedBox(height: 14),
                  BotonPrincipal(
                    texto: 'Continuar viaje',
                    icono: Icons.two_wheeler_rounded,
                    alPresionar: () => _abrir(
                      ViajeConductorScreen(inicial: viaje),
                    ).then((_) => sesion.cargarViajeActivo(silencioso: true)),
                  ),
                ],
              ),
            ),
          ],
          if (sesion.billeteraConfigurada) ...<Widget>[
            const SizedBox(height: 18),
            _TarjetaBilletera(
              sesion: sesion,
              esConductor: true,
              titulo: 'GANANCIAS',
              alRecargar: () => _abrir(const RecargarScreen()),
              alRetirar: () => _abrir(const RetirarScreen()),
              alVerMovimientos: () => _abrir(const MovimientosScreen()),
            ),
          ],
          const SizedBox(height: 14),
          Tarjeta(
            onTap: () => _cambiarPestanaConductor(1),
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
            child: Row(
              children: <Widget>[
                Container(
                  width: 44,
                  height: 44,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: MotorutaColors.brand50,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: MotorutaColors.brand100),
                  ),
                  child: const Icon(
                    Icons.route_rounded,
                    color: MotorutaColors.brand800,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Mis viajes',
                        style: TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 15.5,
                          color: context.gaz.text,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Consulta el historial de rutas realizadas',
                        style: TextStyle(
                          fontSize: 12.5,
                          color: context.gaz.text2,
                        ),
                      ),
                    ],
                  ),
                ),
                Icon(
                  Icons.chevron_right_rounded,
                  color: context.gaz.text3,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _pestanaConexionConductor(
    BuildContext context,
    SesionController sesion,
  ) {
    return RefreshIndicator(
      onRefresh: _refrescar,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: <Widget>[
          Tarjeta(
            child: Row(
              children: <Widget>[
                Container(
                  width: 46,
                  height: 46,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: MotorutaColors.brand50,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.power_settings_new_rounded,
                    color: MotorutaColors.brand800,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Conexión',
                        style: MotorutaTheme.tituloDe(context, tamano: 18),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        'Conéctate para recibir alertas de viajes cercanos.',
                        style: TextStyle(
                          fontSize: 13,
                          height: 1.35,
                          color: context.gaz.text2,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          PanelConductor(key: _panelConductor),
        ],
      ),
    );
  }

  Widget _pestanaGananciasConductor(
    BuildContext context,
    SesionController sesion,
  ) {
    final estado = sesion.estadoConductor;
    final billetera = sesion.billetera;
    final calificacion = estado?.calificacion;

    return RefreshIndicator(
      onRefresh: _refrescar,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: <Widget>[
          if (sesion.billeteraConfigurada)
            _TarjetaBilletera(
              sesion: sesion,
              esConductor: true,
              titulo: 'GANANCIAS',
              alRecargar: () => _abrir(const RecargarScreen()),
              alRetirar: () => _abrir(const RetirarScreen()),
              alVerMovimientos: () => _abrir(const MovimientosScreen()),
            )
          else
            Tarjeta(
              child: Text(
                sesion.errorBilletera ??
                    'Estamos preparando tus ganancias. Desliza hacia abajo para reintentar.',
                style: TextStyle(
                  fontSize: 13.5,
                  height: 1.4,
                  color: context.gaz.text2,
                ),
              ),
            ),
          const SizedBox(height: 18),
          Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  'Resumen de ganancias',
                  style: MotorutaTheme.tituloDe(context, tamano: 18),
                ),
                const SizedBox(height: 12),
                DatoFila(
                  etiqueta: 'Viajes completados',
                  valor: '${estado?.viajesCompletados ?? 0}',
                ),
                DatoFila(
                  etiqueta: 'Calificación',
                  valor: calificacion == null
                      ? '—'
                      : '★ ${calificacion.toStringAsFixed(1)}',
                ),
                DatoFila(
                  etiqueta: 'Saldo disponible',
                  valor:
                      billetera == null ? '—' : montoUsd(billetera.disponible),
                ),
                const SizedBox(height: 12),
                BotonSecundario(
                  texto: 'Ver movimientos',
                  icono: Icons.receipt_long_outlined,
                  alPresionar: () => _abrir(const MovimientosScreen()),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionController>();
    final usuario = sesion.usuario;

    if (usuario == null) {
      return const SizedBox.shrink();
    }

    final esConductor = usuario.esConductor;

    if (esConductor) {
      return _construirConductor(context, sesion, usuario);
    }

    final String tituloPestana = switch (_indicePestana) {
      0 => 'Inicio',
      1 => 'Mis viajes',
      2 => 'Pedir un viaje',
      3 => 'Billetera',
      4 => 'Mi perfil',
      _ => 'GAZ',
    };

    final pestanaInicio = RefreshIndicator(
      onRefresh: _refrescar,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
        children: <Widget>[
          Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    AvatarUsuario(usuario: usuario),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            usuario.nombreCompleto.isEmpty
                                ? 'Tu cuenta'
                                : usuario.nombreCompleto,
                            style: MotorutaTheme.tituloDe(context, tamano: 20),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${usuario.rolEtiqueta} · cédula ${usuario.cedula}',
                            style: TextStyle(
                              fontSize: 14,
                              color: context.gaz.text2,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Aviso(
                  tono: Tono.exito,
                  icono: Icons.verified_rounded,
                  titulo: 'Cuenta aprobada',
                  texto: sesion.siguientePaso?.mensaje ??
                      'Tu cuenta está aprobada. Ya puedes pedir tu primer viaje.',
                ),
              ],
            ),
          ),
          if (sesion.avisosConfigurados &&
              sesion.avisos.any((aviso) => !aviso.leido)) ...<Widget>[
            const SizedBox(height: 18),
            _TarjetaAvisos(
              avisos: sesion.avisos.where((aviso) => !aviso.leido).toList(),
              alMarcarTodos: sesion.marcarAvisosLeidos,
            ),
          ],
          const SizedBox(height: 18),
          _AccionesPasajero(
            viaje: sesion.viajeActivo,
            alPedir: () => _cambiarPestana(2),
            alVerViaje: (viaje) => _abrir(
              ViajePasajeroScreen(inicial: viaje),
            ).then((_) => sesion.cargarViajeActivo(silencioso: true)),
          ),
          if (sesion.banners.isNotEmpty) ...<Widget>[
            const SizedBox(height: 18),
            CarruselBanners(banners: sesion.banners),
          ],
        ],
      ),
    );

    return Scaffold(
      extendBody: true,
      appBar: AppBar(
        title: Text(tituloPestana),
        actions: const <Widget>[BotonTema(), AccionSalir()],
      ),
      floatingActionButton: (_indicePestana == 0 || _indicePestana == 4)
          ? FloatingActionButton.extended(
              onPressed: () => _abrir(const SoporteChatScreen()),
              backgroundColor: context.gaz.brand,
              foregroundColor: context.gaz.onBrand,
              icon: const Icon(Icons.support_agent_rounded),
              label: const Text('Soporte'),
            )
          : null,
      body: IndexedStack(
        index: _indicePestana,
        children: <Widget>[
          pestanaInicio,
          _pestanasCargadas.contains(1)
              ? const HistorialViajesScreen(mostrarAppBar: false)
              : const SizedBox.shrink(),
          _pestanasCargadas.contains(2)
              ? const PedirViajeScreen(mostrarAppBar: false)
              : const SizedBox.shrink(),
          _pestanasCargadas.contains(3)
              ? const BilleteraScreen(mostrarAppBar: false)
              : const SizedBox.shrink(),
          _pestanasCargadas.contains(4)
              ? _PestanaPerfil(
                  usuario: usuario,
                  sesion: sesion,
                  esConductor: false,
                  alEditarDatos: () => _abrir(const MisDatosScreen()),
                  alVerFavoritos: () => _abrir(const FavoritosScreen()),
                  alAbrirSoporte: () => _abrir(const SoporteChatScreen()),
                )
              : const SizedBox.shrink(),
        ],
      ),
      bottomNavigationBar: BarraNavegacionFlotante(
        indiceActual: _indicePestana,
        alCambiarPestana: _cambiarPestana,
        elementos: const <ElementoNavegacion>[
          ElementoNavegacion(
            icono: Icons.home_outlined,
            iconoActivo: Icons.home_rounded,
            etiqueta: 'Inicio',
          ),
          ElementoNavegacion(
            icono: Icons.route_outlined,
            iconoActivo: Icons.route_rounded,
            etiqueta: 'Viajes',
          ),
          ElementoNavegacion(
            icono: Icons.add_rounded,
            etiqueta: 'Pedir',
            esAccion: true,
          ),
          ElementoNavegacion(
            icono: Icons.account_balance_wallet_outlined,
            iconoActivo: Icons.account_balance_wallet_rounded,
            etiqueta: 'Billetera',
          ),
          ElementoNavegacion(
            icono: Icons.person_outline_rounded,
            iconoActivo: Icons.person_rounded,
            etiqueta: 'Perfil',
          ),
        ],
      ),
    );
  }
}

/// Pestaña de perfil y opciones compartida por pasajero y conductor.
class _PestanaPerfil extends StatelessWidget {
  const _PestanaPerfil({
    required this.usuario,
    required this.sesion,
    required this.esConductor,
    required this.alEditarDatos,
    required this.alAbrirSoporte,
    this.alVerFavoritos,
  });

  final dynamic usuario;
  final SesionController sesion;
  final bool esConductor;
  final VoidCallback alEditarDatos;
  final VoidCallback alAbrirSoporte;
  final VoidCallback? alVerFavoritos;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 110),
      children: <Widget>[
        Tarjeta(
          child: Row(
            children: <Widget>[
              AvatarUsuario(usuario: usuario),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      usuario.nombreCompleto.isEmpty
                          ? 'Tu cuenta'
                          : usuario.nombreCompleto,
                      style: MotorutaTheme.tituloDe(context, tamano: 20),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${usuario.rolEtiqueta} · cédula ${usuario.cedula}',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 13.5,
                        color: context.gaz.text2,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        Tarjeta(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text('Información personal',
                  style: MotorutaTheme.tituloDe(context, tamano: 18)),
              const SizedBox(height: 12),
              DatoFila(etiqueta: 'Nombre', valor: usuario.nombreCompleto),
              DatoFila(etiqueta: 'Cédula', valor: usuario.cedula),
              DatoFila(etiqueta: 'Correo', valor: usuario.correo),
              DatoFila(etiqueta: 'Teléfono', valor: usuario.telefono),
              DatoFila(etiqueta: 'Dirección', valor: usuario.direccion),
              DatoFila(
                etiqueta: 'Ciudad',
                valor: usuario.ciudad?.etiqueta ?? 'Sin asignar',
              ),
              if (esConductor && usuario.vehiculo != null)
                DatoFila(
                  etiqueta: 'Vehículo',
                  valor: usuario.vehiculo!.descripcion,
                ),
              DatoFila(
                etiqueta: 'Correo confirmado',
                valor: usuario.correoVerificado ? 'Sí' : 'Todavía no',
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        Tarjeta(
          onTap: alEditarDatos,
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
          child: Row(
            children: <Widget>[
              Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: MotorutaColors.accent50,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: MotorutaColors.accent100),
                ),
                child: const Icon(
                  Icons.edit_outlined,
                  color: MotorutaColors.brand800,
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Editar mis datos',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontWeight: FontWeight.w600,
                        fontSize: 15.5,
                        color: context.gaz.text,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Nombre, teléfono, dirección y correo',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 12.5,
                        color: context.gaz.text2,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                Icons.chevron_right_rounded,
                color: context.gaz.text3,
              ),
            ],
          ),
        ),
        if (alVerFavoritos != null) ...<Widget>[
          const SizedBox(height: 12),
          Tarjeta(
            onTap: alVerFavoritos,
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
            child: Row(
              children: <Widget>[
                Container(
                  width: 44,
                  height: 44,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: MotorutaColors.rojoFondo,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: MotorutaColors.rojo.withOpacity(0.2),
                    ),
                  ),
                  child: const Icon(
                    Icons.favorite_rounded,
                    color: MotorutaColors.rojo,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        'Mis conductores favoritos',
                        style: TextStyle(
                          fontFamily: 'Inter',
                          fontWeight: FontWeight.w600,
                          fontSize: 15.5,
                          color: context.gaz.text,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Gestiona los conductores de tu preferencia',
                        style: TextStyle(
                          fontFamily: 'Inter',
                          fontSize: 12.5,
                          color: context.gaz.text2,
                        ),
                      ),
                    ],
                  ),
                ),
                Icon(
                  Icons.chevron_right_rounded,
                  color: context.gaz.text3,
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 12),
        Tarjeta(
          onTap: alAbrirSoporte,
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
          child: Row(
            children: <Widget>[
              Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: MotorutaColors.brand50,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: MotorutaColors.brand100),
                ),
                child: const Icon(
                  Icons.support_agent_rounded,
                  color: MotorutaColors.brand800,
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      'Atención y soporte',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontWeight: FontWeight.w600,
                        fontSize: 15.5,
                        color: context.gaz.text,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Chatea con nuestro equipo de atención',
                      style: TextStyle(
                        fontFamily: 'Inter',
                        fontSize: 12.5,
                        color: context.gaz.text2,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                Icons.chevron_right_rounded,
                color: context.gaz.text3,
              ),
            ],
          ),
        ),
        const SizedBox(height: 24),
        BotonSecundario(
          texto: 'Cerrar sesión',
          icono: Icons.logout_rounded,
          alPresionar: () => context.read<SesionController>().salir(),
        ),
      ],
    );
  }
}

/// Tarjeta oscura con el saldo y las acciones de billetera.
class _TarjetaBilletera extends StatelessWidget {
  const _TarjetaBilletera({
    required this.sesion,
    required this.esConductor,
    required this.alRecargar,
    required this.alRetirar,
    required this.alVerMovimientos,
    this.titulo = 'BILLETERA',
  });

  final SesionController sesion;
  final bool esConductor;
  final VoidCallback alRecargar;
  final VoidCallback alRetirar;
  final VoidCallback alVerMovimientos;
  final String titulo;

  @override
  Widget build(BuildContext context) {
    final billetera = sesion.billetera;

    return Tarjeta(
      color: MotorutaColors.brand950,
      gradiente: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: <Color>[
          MotorutaColors.brand950,
          MotorutaColors.brand900,
          MotorutaColors.brand800,
        ],
      ),
      borde: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: <Widget>[
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: MotorutaColors.cian.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: MotorutaColors.cian.withOpacity(0.24),
                  ),
                ),
                child: Text(
                  titulo,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.9,
                    color: MotorutaColors.cian,
                  ),
                ),
              ),
              const Icon(
                Icons.account_balance_wallet_rounded,
                color: Colors.white38,
                size: 20,
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (billetera == null && sesion.cargandoBilletera) ...<Widget>[
            const SizedBox(height: 6),
            const Center(
              child: SizedBox(
                height: 26,
                width: 26,
                child: CircularProgressIndicator(
                  strokeWidth: 2.4,
                  color: MotorutaColors.cian,
                ),
              ),
            ),
            const SizedBox(height: 14),
          ] else if (billetera == null) ...<Widget>[
            Text(
              sesion.errorBilletera ??
                  'Estamos preparando tu billetera. Desliza hacia abajo para reintentar.',
              style: const TextStyle(
                fontSize: 14,
                height: 1.4,
                color: Colors.white70,
              ),
            ),
            const SizedBox(height: 14),
            BotonSecundario(
              texto: 'Reintentar',
              icono: Icons.refresh_rounded,
              claro: true,
              alPresionar: sesion.cargarBilletera,
            ),
          ] else ...<Widget>[
            Text(
              montoUsd(billetera.saldo),
              style: MotorutaTheme.tituloDe(
                context,
                tamano: 36,
                color: Colors.white,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              'Disponible ${montoUsd(billetera.disponible)}'
              '${billetera.retenido > 0 ? ' · retenido ${montoUsd(billetera.retenido)}' : ''}',
              style: const TextStyle(
                fontSize: 13,
                color: Colors.white70,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              billetera.tieneTasa
                  ? '≈ ${montoBs(billetera.saldoEnBolivares!)} · tasa ${billetera.tasa!.tasa}'
                  : 'Tasa de cambio no disponible por ahora.',
              style: const TextStyle(
                fontSize: 12.5,
                color: Colors.white54,
              ),
            ),
            const SizedBox(height: 18),
            Row(
              children: <Widget>[
                Expanded(
                  child: BotonPrincipal(
                    texto: 'Recargar',
                    icono: Icons.add_card_rounded,
                    claro: true,
                    alPresionar: alRecargar,
                  ),
                ),
                if (esConductor) ...<Widget>[
                  const SizedBox(width: 10),
                  Expanded(
                    child: BotonSecundario(
                      texto: 'Retirar',
                      icono: Icons.account_balance_wallet_outlined,
                      claro: true,
                      alPresionar: alRetirar,
                    ),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 6),
            TextButton.icon(
              onPressed: alVerMovimientos,
              style: TextButton.styleFrom(
                foregroundColor: Colors.white,
              ),
              icon: const Icon(Icons.receipt_long_outlined, size: 18),
              label: const Text('Ver movimientos'),
            ),
          ],
        ],
      ),
    );
  }
}

/// Tarjeta de viajes del pasajero: pedir uno nuevo o continuar el activo.
class _AccionesPasajero extends StatelessWidget {
  const _AccionesPasajero({
    required this.viaje,
    required this.alPedir,
    required this.alVerViaje,
  });

  final Viaje? viaje;
  final VoidCallback alPedir;
  final ValueChanged<Viaje> alVerViaje;

  @override
  Widget build(BuildContext context) {
    final activo = viaje;

    if (activo != null && activo.activo) {
      return Tarjeta(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    activo.resumenEstado,
                    style: MotorutaTheme.tituloDe(context, tamano: 18),
                  ),
                ),
                etiquetaEstadoViaje(activo),
              ],
            ),
            const SizedBox(height: 12),
            LineaRuta(
              origen: activo.origen,
              destino: activo.destino,
              compacta: true,
            ),
            const SizedBox(height: 14),
            BotonPrincipal(
              texto: 'Ver mi viaje',
              icono: Icons.two_wheeler_rounded,
              alPresionar: () => alVerViaje(activo),
            ),
          ],
        ),
      );
    }

    return Tarjeta(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                width: 42,
                height: 42,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: MotorutaColors.accent50,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: const Icon(
                  Icons.two_wheeler_rounded,
                  color: MotorutaColors.brand800,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  '¿A dónde vas hoy?',
                  style: MotorutaTheme.tituloDe(context, tamano: 18),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Elige tu recogida y destino, mira el precio antes de confirmar y sigue al conductor hasta tu puerta.',
            style: TextStyle(
              fontSize: 13.5,
              height: 1.4,
              color: context.gaz.text2,
            ),
          ),
          const SizedBox(height: 14),
          BotonPrincipal(
            texto: 'Pedir un viaje',
            icono: Icons.add_road_rounded,
            alPresionar: alPedir,
          ),
        ],
      ),
    );
  }
}

/// Avisos pendientes de la persona (hoy: documentos por vencer).
class _TarjetaAvisos extends StatelessWidget {
  const _TarjetaAvisos({
    required this.avisos,
    required this.alMarcarTodos,
  });

  final List<AvisoCuenta> avisos;
  final VoidCallback alMarcarTodos;

  @override
  Widget build(BuildContext context) {
    final visibles = avisos.take(4).toList();

    return Tarjeta(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(
                Icons.notifications_active_rounded,
                color: MotorutaColors.brand800,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text('Avisos',
                    style: MotorutaTheme.tituloDe(context, tamano: 18)),
              ),
              TextButton(
                onPressed: alMarcarTodos,
                child: const Text('Marcar leídos'),
              ),
            ],
          ),
          const SizedBox(height: 6),
          for (final aviso in visibles)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  const Padding(
                    padding: EdgeInsets.only(top: 2),
                    child: Icon(
                      Icons.warning_amber_rounded,
                      size: 20,
                      color: MotorutaColors.brand800,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          aviso.titulo,
                          style: TextStyle(
                            fontWeight: FontWeight.w600,
                            color: context.gaz.text,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          aviso.mensaje,
                          style: TextStyle(
                            fontSize: 13,
                            height: 1.35,
                            color: context.gaz.text2,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          if (avisos.length > visibles.length)
            Text(
              'Y ${avisos.length - visibles.length} aviso(s) más en tu bandeja.',
              style: TextStyle(
                fontSize: 12.5,
                color: context.gaz.text2,
              ),
            ),
        ],
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import '../../features/auth/screens/bienvenida_screen.dart';
import '../../features/documentos/screens/documentos_screen.dart';
import '../../features/inicio/screens/espera_screen.dart';
import '../../features/inicio/screens/inicio_screen.dart';
import '../../features/inicio/screens/soporte_screen.dart';
import '../../features/sesion/sesion_controller.dart';
import '../theme/app_theme.dart';
import '../theme/theme_controller.dart';
import '../ui/avisos.dart';
import '../ui/botones.dart';
import '../ui/marca.dart';

/// Rol que atiende una app concreta: `passenger`, `driver` o `null` (modo
/// legado, donde la persona elige cómo usar GAZ).
///
/// Cada aplicación (GAZ y GAZ Driver) envuelve su árbol con este `InheritedWidget`
/// para que las pantallas compartidas sepan que no deben ofrecer el otro rol.
class GazRolScope extends InheritedWidget {
  const GazRolScope({super.key, required this.rol, required super.child});

  final String? rol;

  bool get esConductor => rol == 'driver';

  /// Rol fijo de la app, o `null` si la app permite elegir (modo legado).
  static String? maybeOf(BuildContext context) {
    return context.dependOnInheritedWidgetOfExactType<GazRolScope>()?.rol;
  }

  static String of(BuildContext context) {
    return maybeOf(context) ?? 'passenger';
  }

  @override
  bool updateShouldNotify(GazRolScope oldWidget) => oldWidget.rol != rol;
}

/// Raíz común de GAZ y GAZ Driver.
///
/// Recibe la sesión ya construida (cada app decide qué repositorios y servicios
/// conecta) y el rol que acepta. El resto del árbol es idéntico: tema, idioma y
/// la compuerta que decide la pantalla según el backend.
class GazApp extends StatefulWidget {
  const GazApp({
    super.key,
    required this.sesion,
    this.rol,
    this.titulo = 'GAZ',
    this.tema,
  });

  final SesionController sesion;
  final String? rol;
  final String titulo;
  final ThemeController? tema;

  @override
  State<GazApp> createState() => _GazAppState();
}

class _GazAppState extends State<GazApp> {
  late final ThemeController _tema;
  late final bool _temaPropio;

  @override
  void initState() {
    super.initState();
    _temaPropio = widget.tema == null;
    _tema = widget.tema ?? ThemeController.memoria();
  }

  @override
  void dispose() {
    if (_temaPropio) _tema.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return GazRolScope(
      rol: widget.rol,
      child: ChangeNotifierProvider<ThemeController>.value(
        value: _tema,
        child: ChangeNotifierProvider<SesionController>.value(
          value: widget.sesion,
          child: AnimatedBuilder(
            animation: _tema,
            builder: (context, _) {
              return MaterialApp(
                title: widget.titulo,
                debugShowCheckedModeBanner: false,
                theme: MotorutaTheme.claro,
                darkTheme: MotorutaTheme.oscuro,
                themeMode: _tema.modo,
                locale: const Locale('es'),
                supportedLocales: const <Locale>[
                  Locale('es', 'VE'),
                  Locale('es'),
                  Locale('en'),
                ],
                localizationsDelegates: const <LocalizationsDelegate<Object>>[
                  GlobalMaterialLocalizations.delegate,
                  GlobalWidgetsLocalizations.delegate,
                  GlobalCupertinoLocalizations.delegate,
                ],
                home: const CompuertaSesion(),
              );
            },
          ),
        ),
      ),
    );
  }
}

/// Decide la pantalla: mirando la sesión y, ya dentro, el `next_step` del
/// backend. La app no reinterpreta estados: sigue lo que diga el servidor.
class CompuertaSesion extends StatefulWidget {
  const CompuertaSesion({super.key});

  @override
  State<CompuertaSesion> createState() => _CompuertaSesionState();
}

class _CompuertaSesionState extends State<CompuertaSesion> {
  @override
  void initState() {
    super.initState();

    // Primera llamada al abrir: usa el token guardado, si lo hay.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      context.read<SesionController>().iniciar();
    });
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionController>();

    if (sesion.rolIncorrecto) {
      return const PantallaRolIncorrecto();
    }

    return switch (sesion.estado) {
      EstadoSesion.comprobando =>
        const PantallaCargando(mensaje: 'Buscando tu sesión...'),
      EstadoSesion.sinSesion => const BienvenidaScreen(),
      EstadoSesion.activa =>
        _PantallaDeLaCuenta(paso: sesion.siguientePaso?.clave),
    };
  }
}

/// Qué pantalla mostrar según el paso que devolvió `GET /api/perfil`.
class _PantallaDeLaCuenta extends StatelessWidget {
  const _PantallaDeLaCuenta({required this.paso});

  /// `upload_documents`, `fix_documents`, `waiting_review`, `ready`...
  final String? paso;

  @override
  Widget build(BuildContext context) {
    return switch (paso) {
      // Falta subir documentos o hay que corregir uno rechazado.
      'upload_documents' || 'fix_documents' => const DocumentosScreen(),
      'waiting_review' => const EsperaScreen(),
      'ready' => const InicioScreen(),
      'contact_support' => const SoporteScreen(),
      _ => const PantallaCargando(mensaje: 'Un momento...'),
    };
  }
}

/// La persona entró con una cuenta del otro rol.
///
/// No se la deja operar: se le dice qué app le corresponde y se le ofrece
/// cerrar la sesión. Así una cuenta de conductor nunca termina usando la app
/// de pasajeros (ni al revés).
class PantallaRolIncorrecto extends StatelessWidget {
  const PantallaRolIncorrecto({super.key});

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionController>();
    final esConductor = sesion.usuario?.esConductor ?? false;
    final appCorrecta = esConductor ? 'GAZ Driver' : 'GAZ';

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Padding(
              padding: const EdgeInsets.all(28),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  const IsotipoMarca(tamano: 74),
                  const SizedBox(height: 24),
                  Text(
                    'Esta cuenta es de ${esConductor ? 'conductor' : 'pasajero'}',
                    textAlign: TextAlign.center,
                    style: MotorutaTheme.tituloDe(context, tamano: 24),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Para entrar con ella, abre $appCorrecta. '
                    'Aquí solo se usan cuentas de '
                    '${GazRolScope.of(context) == 'driver' ? 'conductor' : 'pasajero'}.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 15,
                      color: context.gaz.text2,
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 28),
                  BotonPrincipal(
                    texto: 'Cerrar sesión',
                    icono: Icons.logout_rounded,
                    alPresionar: () => context.read<SesionController>().salir(),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

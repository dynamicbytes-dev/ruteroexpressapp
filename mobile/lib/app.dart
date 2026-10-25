import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/theme/app_theme.dart';
import 'core/ui/avisos.dart';
import 'features/auth/screens/bienvenida_screen.dart';
import 'features/documentos/screens/documentos_screen.dart';
import 'features/inicio/screens/espera_screen.dart';
import 'features/inicio/screens/inicio_screen.dart';
import 'features/inicio/screens/soporte_screen.dart';
import 'features/sesion/sesion_controller.dart';

/// La app completa: tema de marca, idioma español y la compuerta de sesión.
class MotorutaApp extends StatelessWidget {
  const MotorutaApp({super.key, required this.sesion});

  final SesionController sesion;

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider<SesionController>.value(
      value: sesion,
      child: MaterialApp(
        title: 'gaz',
        debugShowCheckedModeBanner: false,
        theme: MotorutaTheme.claro,
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

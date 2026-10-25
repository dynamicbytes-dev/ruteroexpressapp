import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/api/api_client.dart';
import 'package:motoruta/core/theme/app_theme.dart';
import 'package:motoruta/data/billetera_repository.dart';
import 'package:motoruta/data/cuenta_repository.dart';
import 'package:motoruta/data/models/usuario.dart';
import 'package:motoruta/data/notificaciones_repository.dart';
import 'package:motoruta/features/auth/screens/acceso_screen.dart';
import 'package:motoruta/features/auth/screens/bienvenida_screen.dart';
import 'package:motoruta/features/auth/screens/elegir_rol_screen.dart';
import 'package:motoruta/features/auth/screens/recuperar_screen.dart';
import 'package:motoruta/features/auth/screens/registro_screen.dart';
import 'package:motoruta/features/billetera/screens/movimientos_screen.dart';
import 'package:motoruta/features/billetera/screens/recargar_screen.dart';
import 'package:motoruta/features/billetera/screens/retirar_screen.dart';
import 'package:motoruta/features/documentos/screens/documentos_screen.dart';
import 'package:motoruta/features/inicio/screens/espera_screen.dart';
import 'package:motoruta/features/inicio/screens/inicio_screen.dart';
import 'package:motoruta/features/inicio/screens/mis_datos_screen.dart';
import 'package:motoruta/features/inicio/screens/soporte_screen.dart';
import 'package:motoruta/features/sesion/sesion_controller.dart';
import 'package:provider/provider.dart';

import '../soporte/datos_falsos.dart';
import '../soporte/fuentes.dart';
import '../soporte/servidor_falso.dart';

/// Cada pantalla se dibuja en tamaño de teléfono y se revisa que no haya
/// desbordes ni errores de maquetación. Un `RenderFlex overflowed` o una
/// aserción de widget aparecen aquí como excepción.
void main() {
  // Con las tipografías reales, para que el ancho del texto sea el del teléfono.
  setUpAll(cargarFuentesDeMarca);

  const tamanos = <String, Size>{
    'teléfono chico (360x640)': Size(360, 640),
    'teléfono grande (430x932)': Size(430, 932),
  };

  /// Arma una sesión con los datos ya puestos: aquí no interesa la red.
  SesionController sesionCon(Map<String, dynamic> perfil) {
    final api = ApiClient(baseUrl: 'http://localhost:8000');
    final sesion = SesionController(
      repositorio: CuentaRepository(api),
      almacen: TokenMemoria('tok'),
      api: api,
    );

    final datos = PerfilCuenta.desdeJson(perfil);
    sesion.usuario = datos.usuario;
    sesion.siguientePaso = datos.siguientePaso;
    sesion.estado = EstadoSesion.activa;

    return sesion;
  }

  Widget envolver(SesionController sesion, Widget pantalla) {
    return ChangeNotifierProvider<SesionController>.value(
      value: sesion,
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: MotorutaTheme.claro,
        locale: const Locale('es'),
        supportedLocales: const <Locale>[Locale('es', 'VE'), Locale('es')],
        localizationsDelegates: const <LocalizationsDelegate<Object>>[
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: pantalla,
      ),
    );
  }

  final perfilBasico = perfilJson(
    usuario: usuarioJson(),
    paso: 'upload_documents',
  );

  final documentosCorregir = <Map<String, dynamic>>[
    documentoJson('cedula', estado: 'approved'),
    documentoJson('selfie', estado: 'pending'),
    documentoJson(
      'licencia_conducir',
      estado: 'rejected',
      motivoRechazo:
          'La foto está borrosa y no se lee la fecha de vencimiento.',
      venceEn: '2027-05-01',
    ),
  ];

  final documentosConFaltantes = <Map<String, dynamic>>[
    documentoJson('cedula', venceEn: '2027-05-01'),
    documentoJson('selfie'),
    documentoJson('rcv', venceEn: '2027-05-01', vencido: true),
  ];

  final documentosCompletos = <Map<String, dynamic>>[
    documentoJson('cedula', estado: 'approved'),
    documentoJson('selfie', estado: 'approved'),
    documentoJson('licencia_conducir',
        estado: 'approved', venceEn: '2027-05-01'),
    documentoJson('carnet_circulacion',
        estado: 'approved', venceEn: '2027-01-15'),
    documentoJson('rcv', estado: 'pending', venceEn: '2027-03-10'),
    documentoJson('foto_vehiculo', estado: 'approved'),
  ];

  final perfilListo = perfilJson(
    usuario: usuarioJson(
      estado: 'approved',
      etiquetaEstado: 'Aprobado',
      puedeOperar: true,
      documentos: documentosCompletos,
    ),
    paso: 'ready',
    mensaje: 'Tu cuenta está aprobada. Actívate para empezar a recibir viajes.',
  );

  /// Las pantallas con su perfil, cuando lo necesitan.
  final pantallas = <String, (Widget, Map<String, dynamic>)>{
    'bienvenida': (const BienvenidaScreen(), perfilBasico),
    'elegir rol': (const ElegirRolScreen(), perfilBasico),
    'registro conductor': (const RegistroScreen(rol: 'driver'), perfilBasico),
    'registro pasajero': (const RegistroScreen(rol: 'passenger'), perfilBasico),
    'acceso': (const AccesoScreen(), perfilBasico),
    'recuperar contraseña': (const RecuperarScreen(), perfilBasico),
    'documentos por corregir': (
      const DocumentosScreen(),
      perfilJson(
        usuario: usuarioJson(documentos: documentosCorregir),
        paso: 'fix_documents',
        mensaje:
            'Uno de tus documentos fue rechazado. Revísalo y vuelve a subirlo.',
      ),
    ),
    'documentos que faltan': (
      const DocumentosScreen(),
      perfilJson(
        usuario: usuarioJson(documentos: documentosConFaltantes),
        paso: 'upload_documents',
        mensaje: 'Completa tu registro subiendo los documentos que faltan.',
      ),
    ),
    'en espera de verificación': (
      const EsperaScreen(),
      perfilJson(
        usuario: usuarioJson(documentos: documentosCompletos),
        paso: 'waiting_review',
        mensaje: 'Tus documentos están en revisión.',
      ),
    ),
    'inicio conductor': (const InicioScreen(), perfilListo),
    'mis datos': (const MisDatosScreen(), perfilListo),
    'recargar billetera': (const RecargarScreen(), perfilListo),
    'retirar ganancias': (const RetirarScreen(), perfilListo),
    'movimientos': (const MovimientosScreen(), perfilListo),
    'cuenta suspendida': (
      const SoporteScreen(),
      perfilJson(
        usuario: usuarioJson(
          estado: 'suspended',
          etiquetaEstado: 'Suspendido',
          motivoEstado: 'Detectamos documentos vencidos.',
        ),
        paso: 'contact_support',
        mensaje: 'Tu cuenta no está operativa.',
      ),
    ),
  };

  for (final tamano in tamanos.entries) {
    group(tamano.key, () {
      for (final pantalla in pantallas.entries) {
        testWidgets('${pantalla.key} se dibuja sin errores', (tester) async {
          tester.view.physicalSize = tamano.value * 3;
          tester.view.devicePixelRatio = 3;
          addTearDown(tester.view.reset);

          await tester.pumpWidget(
            envolver(sesionCon(pantalla.value.$2), pantalla.value.$1),
          );
          await tester.pump(const Duration(milliseconds: 300));

          expect(tester.takeException(), isNull);

          // La pantalla de espera deja un reloj corriendo: se desmonta para
          // que la prueba termine sin temporizadores pendientes.
          await tester.pumpWidget(const SizedBox());
          await tester.pump();
        });
      }
    });
  }

  testWidgets('inicio muestra el saldo y las acciones de billetera',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final servidor = ServidorFalso();
    servidor.responder('GET', '/api/billetera', billeteraJson(saldo: 50));

    final api = ApiClient(
      dio: Dio()
        ..httpClientAdapter = servidor
        ..transformer = SyncTransformer(),
      baseUrl: 'http://localhost:8000',
    );

    final sesion = SesionController(
      repositorio: CuentaRepository(api),
      almacen: TokenMemoria('tok'),
      api: api,
      billetera: BilleteraRepository(api),
    );

    final datos = PerfilCuenta.desdeJson(perfilListo);
    sesion.usuario = datos.usuario;
    sesion.siguientePaso = datos.siguientePaso;
    sesion.estado = EstadoSesion.activa;

    await tester.pumpWidget(envolver(sesion, const InicioScreen()));
    await tester.pumpAndSettle();

    expect(find.text(r'$50,00'), findsOneWidget);
    expect(find.textContaining('Bs 2.000,00'), findsOneWidget);
    expect(find.text('Recargar'), findsOneWidget);
    expect(find.text('Retirar'), findsOneWidget);
    expect(find.text('Ver movimientos'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('inicio muestra los avisos pendientes', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final servidor = ServidorFalso();
    servidor.responder('GET', '/api/notificaciones', <String, dynamic>{
      'data': <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 'aviso-1',
          'type': 'documento_por_vencer',
          'title': 'Documento por vencer',
          'message': 'Tu Licencia de conducir vence en 5 días (24/10/2026).',
          'read_at': null,
          'created_at': '2026-10-19T08:00:00-04:00',
        },
      ],
      'unread_count': 1,
    });

    final api = ApiClient(
      dio: Dio()
        ..httpClientAdapter = servidor
        ..transformer = SyncTransformer(),
      baseUrl: 'http://localhost:8000',
    );

    final sesion = SesionController(
      repositorio: CuentaRepository(api),
      almacen: TokenMemoria('tok'),
      api: api,
      notificaciones: NotificacionesRepository(api),
    );

    final datos = PerfilCuenta.desdeJson(perfilListo);
    sesion.usuario = datos.usuario;
    sesion.siguientePaso = datos.siguientePaso;
    sesion.estado = EstadoSesion.activa;

    await tester.pumpWidget(envolver(sesion, const InicioScreen()));
    await tester.pumpAndSettle();

    expect(find.text('Avisos'), findsOneWidget);
    expect(find.textContaining('vence en 5 días'), findsOneWidget);
    expect(find.text('Marcar leídos'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('el registro exige aceptar los términos y abre el documento',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      envolver(
        sesionCon(perfilBasico),
        const RegistroScreen(rol: 'passenger'),
      ),
    );
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.textContaining('Acepto los términos'), findsOneWidget);

    await tester.ensureVisible(find.text('Leer términos y privacidad'));
    await tester.pump();
    await tester.tap(find.text('Leer términos y privacidad'));
    await tester.pumpAndSettle();

    expect(find.text('Términos y privacidad'), findsOneWidget);
    expect(find.text('Términos'), findsOneWidget);
    expect(find.text('Privacidad'), findsOneWidget);

    await tester.tap(find.text('Entendido'));
    await tester.pumpAndSettle();

    expect(find.text('Términos y privacidad'), findsNothing);

    await tester.ensureVisible(find.text('Crear mi cuenta'));
    await tester.pump();
    await tester.tap(find.text('Crear mi cuenta'));
    await tester.pump();

    expect(
      find.text('Debes aceptar los términos y condiciones.'),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('el registro muestra las ciudades activas del backend',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final servidor = ServidorFalso();
    servidor.responder('GET', '/api/ciudades', <String, dynamic>{
      'data': <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 2,
          'name': 'Barcelona',
          'state': 'Anzoátegui',
          'display_name': 'Barcelona (Anzoátegui)',
        },
        <String, dynamic>{
          'id': 3,
          'name': 'Lechería',
          'state': 'Anzoátegui',
          'display_name': 'Lechería (Anzoátegui)',
        },
      ],
    });

    final api = ApiClient(
      dio: Dio()
        ..httpClientAdapter = servidor
        ..transformer = SyncTransformer(),
      baseUrl: 'http://localhost:8000',
    );
    final sesion = SesionController(
      repositorio: CuentaRepository(api),
      almacen: TokenMemoria(),
      api: api,
    );

    await tester.pumpWidget(
      envolver(sesion, const RegistroScreen(rol: 'passenger')),
    );
    await tester.pumpAndSettle();

    expect(find.text('Ciudad donde usarás la app'), findsOneWidget);
    expect(find.text('Elige una ciudad'), findsOneWidget);

    final dropDown = find.byType(DropdownButtonFormField<int>);
    await tester.ensureVisible(dropDown);
    await tester.tap(dropDown);
    await tester.pumpAndSettle();

    expect(find.text('Barcelona (Anzoátegui)'), findsWidgets);
    await tester.tap(find.text('Lechería (Anzoátegui)').last);
    await tester.pumpAndSettle();

    expect(find.text('Lechería (Anzoátegui)'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('"Subir foto" abre la hoja con cámara y galería', (tester) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      envolver(
        sesionCon(
          perfilJson(
            usuario: usuarioJson(documentos: documentosConFaltantes),
            paso: 'upload_documents',
          ),
        ),
        const DocumentosScreen(),
      ),
    );
    await tester.pump(const Duration(milliseconds: 300));

    await tester.ensureVisible(find.text('Subir foto').first);
    await tester.pump();
    await tester.tap(find.text('Subir foto').first);
    await tester.pumpAndSettle();

    expect(find.text('Elegir de la galería'), findsOneWidget);
    expect(find.text('Cancelar'), findsOneWidget);

    await tester.tap(find.text('Cancelar'));
    await tester.pumpAndSettle();

    expect(find.text('Elegir de la galería'), findsNothing);
  });

  testWidgets('un documento que vence pide la fecha antes de subirlo',
      (tester) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    // Un solo documento, y de los que piden fecha de vencimiento.
    await tester.pumpWidget(
      envolver(
        sesionCon(
          perfilJson(
            usuario: usuarioJson(
              requeridos: 1,
              documentos: <Map<String, dynamic>>[
                documentoJson('licencia_conducir'),
              ],
            ),
            paso: 'upload_documents',
          ),
        ),
        const DocumentosScreen(),
      ),
    );
    await tester.pump(const Duration(milliseconds: 300));

    await tester.tap(find.text('Subir foto'));
    await tester.pumpAndSettle();

    expect(find.text('Vence el'), findsOneWidget);
    expect(
        find.text('Primero indica la fecha de vencimiento.'), findsOneWidget);
    // Sin fecha, la galería queda bloqueada: se toca y la hoja no se cierra.
    await tester.tap(find.text('Elegir de la galería'));
    await tester.pumpAndSettle();

    expect(find.text('Elegir de la galería'), findsOneWidget);
  });

  testWidgets('la lista de documentos muestra el motivo del rechazo',
      (tester) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      envolver(
        sesionCon(
          perfilJson(
            usuario: usuarioJson(documentos: documentosCorregir),
            paso: 'fix_documents',
          ),
        ),
        const DocumentosScreen(),
      ),
    );
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Motivo del rechazo'), findsOneWidget);
    expect(
      find.text('La foto está borrosa y no se lee la fecha de vencimiento.'),
      findsOneWidget,
    );
    expect(find.text('Reemplazar archivo'), findsOneWidget);
    expect(find.text('Falta subirlo'), findsNothing);
  });
}

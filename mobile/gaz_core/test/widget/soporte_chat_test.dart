import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/core/api/api_client.dart';
import 'package:gaz_core/core/theme/app_theme.dart';
import 'package:gaz_core/data/cuenta_repository.dart';
import 'package:gaz_core/data/models/usuario.dart';
import 'package:gaz_core/data/soporte_repository.dart';
import 'package:gaz_core/features/inicio/screens/inicio_screen.dart';
import 'package:gaz_core/features/sesion/sesion_controller.dart';
import 'package:gaz_core/features/soporte/screens/soporte_chat_screen.dart';
import 'package:provider/provider.dart';

import '../soporte/datos_falsos.dart';
import '../soporte/fuentes.dart';
import '../soporte/servidor_falso.dart';

void main() {
  setUpAll(cargarFuentesDeMarca);

  SesionController sesionCon(
    ServidorFalso servidor, {
    Map<String, dynamic>? perfil,
  }) {
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
      soporte: SoporteRepository(api),
    );

    if (perfil != null) {
      final datos = PerfilCuenta.desdeJson(perfil);
      sesion.usuario = datos.usuario;
      sesion.siguientePaso = datos.siguientePaso;
      sesion.estado = EstadoSesion.activa;
    } else {
      final datos = PerfilCuenta.desdeJson(
        perfilJson(
          usuario: usuarioJson(
            rol: 'passenger',
            estado: 'approved',
            etiquetaEstado: 'Aprobado',
            puedeOperar: true,
            correoVerificado: true,
          ),
          paso: 'ready',
        ),
      );
      sesion.usuario = datos.usuario;
      sesion.siguientePaso = datos.siguientePaso;
      sesion.estado = EstadoSesion.activa;
    }

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

  Map<String, dynamic> conversacionJson({
    required List<Map<String, dynamic>> mensajes,
    String? auditorName,
  }) {
    return <String, dynamic>{
      'data': <String, dynamic>{
        'id': 4,
        'status': 'open',
        'status_label': 'Abierta',
        'started_at': '2026-10-21T10:00:00-04:00',
        'last_message_at': '2026-10-21T10:05:00-04:00',
        'closed_at': null,
        'auditor_name': auditorName,
        'messages': mensajes,
      },
    };
  }

  Map<String, dynamic> mensajeJson({
    required int id,
    required String texto,
    required bool deSoporte,
  }) {
    return <String, dynamic>{
      'id': id,
      'body': texto,
      'author': deSoporte ? 'auditor' : 'user',
      'author_label': deSoporte ? 'Ana Pérez' : 'Tú',
      'from_support': deSoporte,
      'read_at': null,
      'created_at': '2026-10-21T10:05:00-04:00',
    };
  }

  testWidgets('el chat muestra la respuesta del equipo y envía un mensaje',
      (tester) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final servidor = ServidorFalso();

    servidor.responder(
      'GET',
      '/api/soporte',
      conversacionJson(
        auditorName: 'Ana Pérez',
        mensajes: <Map<String, dynamic>>[
          mensajeJson(
            id: 1,
            texto: 'Estamos revisando tu caso.',
            deSoporte: true,
          ),
        ],
      ),
    );

    servidor.responder(
      'POST',
      '/api/soporte/mensajes',
      conversacionJson(
        auditorName: 'Ana Pérez',
        mensajes: <Map<String, dynamic>>[
          mensajeJson(
            id: 1,
            texto: 'Estamos revisando tu caso.',
            deSoporte: true,
          ),
          mensajeJson(
            id: 2,
            texto: 'Gracias, quedo atento.',
            deSoporte: false,
          ),
        ],
      ),
    );

    await tester.pumpWidget(
      envolver(sesionCon(servidor), const SoporteChatScreen()),
    );
    await tester.pumpAndSettle();

    expect(find.text('Estamos revisando tu caso.'), findsOneWidget);
    expect(find.text('Atendido por Ana Pérez'), findsOneWidget);
    expect(find.text('Ana Pérez'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'Gracias, quedo atento.');
    await tester.tap(find.byTooltip('Enviar mensaje'));
    await tester.pumpAndSettle();

    expect(find.text('Gracias, quedo atento.'), findsOneWidget);

    final envio = servidor.peticiones.last;
    expect(envio.uri.path, '/api/soporte/mensajes');
    expect(
      (envio.data as Map<String, dynamic>)['message'],
      'Gracias, quedo atento.',
    );

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('si el equipo finaliza el chat, el hilo se limpia en la app',
      (tester) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final servidor = ServidorFalso();

    servidor.responder(
      'GET',
      '/api/soporte',
      conversacionJson(mensajes: <Map<String, dynamic>>[
        mensajeJson(
          id: 1,
          texto: 'Mensaje de la conversación anterior.',
          deSoporte: false,
        ),
      ]),
    );

    await tester.pumpWidget(
      envolver(sesionCon(servidor), const SoporteChatScreen()),
    );
    await tester.pumpAndSettle();

    expect(find.text('Mensaje de la conversación anterior.'), findsOneWidget);

    // El auditor la finaliza: la API deja de entregar la conversación.
    servidor.responder('GET', '/api/soporte', <String, dynamic>{'data': null});

    await tester.pump(const Duration(seconds: 5));
    await tester.pumpAndSettle();

    expect(find.text('Mensaje de la conversación anterior.'), findsNothing);
    expect(find.text('Conversación finalizada'), findsOneWidget);
    expect(find.text('Inicia otra conversación'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('inicio ofrece el botón de soporte a pasajero y conductor',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    for (final rol in <String>['passenger', 'driver']) {
      final servidor = ServidorFalso();

      await tester.pumpWidget(
        envolver(
          sesionCon(
            servidor,
            perfil: perfilJson(
              usuario: usuarioJson(
                rol: rol,
                estado: 'approved',
                etiquetaEstado: 'Aprobado',
                puedeOperar: true,
                correoVerificado: true,
              ),
              paso: 'ready',
            ),
          ),
          const InicioScreen(),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Soporte'), findsOneWidget, reason: rol);
      expect(tester.takeException(), isNull, reason: rol);
    }

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });
}

import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/api/api_client.dart';
import 'package:motoruta/core/realtime/evento_tiempo_real.dart';
import 'package:motoruta/core/realtime/tiempo_real_service.dart';
import 'package:motoruta/core/theme/app_theme.dart';
import 'package:motoruta/data/billetera_repository.dart';
import 'package:motoruta/data/cuenta_repository.dart';
import 'package:motoruta/data/models/usuario.dart';
import 'package:motoruta/data/models/viaje.dart';
import 'package:motoruta/data/viajes_repository.dart';
import 'package:motoruta/features/inicio/screens/inicio_screen.dart';
import 'package:motoruta/features/sesion/sesion_controller.dart';
import 'package:motoruta/features/viajes/screens/historial_viajes_screen.dart';
import 'package:motoruta/features/viajes/screens/oferta_screen.dart';
import 'package:motoruta/features/viajes/screens/pedir_viaje_screen.dart';
import 'package:motoruta/features/viajes/screens/viaje_conductor_screen.dart';
import 'package:motoruta/features/viajes/screens/viaje_pasajero_screen.dart';
import 'package:motoruta/features/viajes/viajes_ui.dart';
import 'package:provider/provider.dart';

import '../soporte/datos_falsos.dart';
import '../soporte/fuentes.dart';
import '../soporte/servidor_falso.dart';

void main() {
  setUpAll(cargarFuentesDeMarca);

  late ServidorFalso servidor;
  late ApiClient api;

  setUp(() {
    servidor = ServidorFalso();
    api = ApiClient(
      dio: Dio()
        ..httpClientAdapter = servidor
        ..transformer = SyncTransformer(),
      baseUrl: 'http://localhost:8000',
    );

    // Sin red en las pruebas: los tiles del mapa se sirven transparentes.
    MapaViaje.proveedorTilesPrueba = _ProveedorTilesTransparente();
  });

  tearDown(() {
    MapaViaje.proveedorTilesPrueba = null;
  });

  SesionController sesionComo(
    String rol, {
    TiempoRealService? tiempoReal,
  }) {
    final sesion = SesionController(
      repositorio: CuentaRepository(api),
      almacen: TokenMemoria('tok-widget'),
      api: api,
      billetera: BilleteraRepository(api),
      viajes: ViajesRepository(api),
      tiempoReal: tiempoReal,
    );

    final perfil = PerfilCuenta.desdeJson(
      perfilJson(
        usuario: usuarioJson(
          rol: rol,
          estado: 'approved',
          etiquetaEstado: 'Aprobado',
          puedeOperar: true,
        ),
        paso: 'ready',
      ),
    );

    sesion.usuario = perfil.usuario;
    sesion.siguientePaso = perfil.siguientePaso;
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

  Future<void> irA(WidgetTester tester, Finder destino) async {
    await tester.scrollUntilVisible(
      destino,
      260,
      scrollable: find.byType(Scrollable).first,
      maxScrolls: 30,
    );
    await tester.pumpAndSettle();
  }

  void configurarBase() {
    servidor.responder(
      'GET',
      '/api/billetera',
      billeteraJson(saldo: 100, retenido: 3.35),
    );
    servidor.responder(
      'GET',
      '/api/viajes/activo',
      <String, dynamic>{
        'data': <String, dynamic>{'trip': null},
      },
    );
  }

  testWidgets('pedir viaje muestra el mapa y permite cotizar', (tester) async {
    tester.view.physicalSize = const Size(1080, 1920);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      envolver(sesionComo('passenger'), const PedirViajeScreen()),
    );
    await tester.pump(const Duration(milliseconds: 300));

    expect(tester.takeException(), isNull);
    expect(find.text('Pedir un viaje'), findsWidgets);
    expect(find.byType(FlutterMap), findsOneWidget);
    expect(find.textContaining('Mapa pendiente'), findsNothing);

    await irA(tester, find.text('Cotizar viaje'));
    expect(find.text('Cotizar viaje'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('el pasajero pide un viaje y llega a la pantalla de seguimiento',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    configurarBase();
    servidor.responder(
      'POST',
      '/api/viajes/cotizar',
      <String, dynamic>{'data': cotizacionJson()},
    );
    servidor.responder(
      'POST',
      '/api/viajes',
      <String, dynamic>{
        'message': 'Estamos buscando un conductor para tu viaje.',
        'data': <String, dynamic>{'trip': viajeJson()},
      },
      codigo: 201,
    );

    await tester.pumpWidget(
      envolver(sesionComo('passenger'), const PedirViajeScreen()),
    );
    await tester.pump(const Duration(milliseconds: 300));

    await irA(tester, find.text('Coordenadas de prueba (desarrollo)'));
    await tester.tap(find.text('Coordenadas de prueba (desarrollo)'));
    await tester.pumpAndSettle();

    await irA(tester, find.text('Usar datos de prueba'));
    await tester.tap(find.text('Usar datos de prueba'));
    await tester.pumpAndSettle();

    await irA(tester, find.text('Cotizar viaje'));
    await tester.tap(find.text('Cotizar viaje'));
    await tester.pumpAndSettle();

    expect(find.text('Tu viaje'), findsOneWidget);
    expect(find.text(r'$3,35'), findsOneWidget);

    await tester.ensureVisible(find.text('Pedir viaje'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Pedir viaje'));
    for (var i = 0; i < 10; i++) {
      await tester.pump(const Duration(milliseconds: 150));
    }

    expect(find.text('Tu viaje'), findsWidgets);
    expect(find.textContaining('Esperando respuesta'), findsWidgets);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('la alerta del conductor muestra la cuenta regresiva',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 1920);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final oferta = OfertaViaje.desdeJson(ofertaJson(segundos: 18));

    await tester.pumpWidget(
      envolver(sesionComo('driver'), OfertaScreen(oferta: oferta)),
    );
    await tester.pump(const Duration(milliseconds: 300));

    expect(tester.takeException(), isNull);
    expect(find.text('Nuevo viaje'), findsOneWidget);
    expect(find.text('Ana Pérez'), findsOneWidget);
    expect(find.text('Aceptar viaje'), findsOneWidget);
    expect(find.text(r'$3,01'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('la alerta se cierra si el viaje deja de estar disponible',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 1920);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final tiempoReal = TiempoRealFalso(api: api);
    addTearDown(tiempoReal.dispose);

    final sesion = sesionComo('driver', tiempoReal: tiempoReal);

    final oferta = OfertaViaje.desdeJson(ofertaJson());

    servidor.responder(
      'GET',
      '/api/viajes/12',
      <String, dynamic>{
        'data': <String, dynamic>{
          'trip': viajeJson(
            id: 12,
            estado: 'cancelled',
            etiqueta: 'Cancelado',
            puedeCancelar: false,
          ),
        },
      },
    );

    await tester.pumpWidget(
      envolver(
        sesion,
        Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: TextButton(
                onPressed: () {
                  Navigator.of(context).push<void>(
                    MaterialPageRoute<void>(
                      builder: (_) => OfertaScreen(oferta: oferta),
                    ),
                  );
                },
                child: const Text('Abrir alerta'),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Abrir alerta'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Nuevo viaje'), findsOneWidget);

    tiempoReal.emitir(
      const EventoTiempoReal(
        tipo: TipoEventoTiempoReal.viaje,
        viajeId: 12,
        estado: 'cancelled',
        motivo: 'cancelado',
      ),
    );

    await tester.pump();
    await tester.pumpAndSettle();

    expect(find.text('Nuevo viaje'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('el seguimiento del pasajero muestra conductor y ruta',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final viaje = Viaje.desdeJson(
      viajeJson(estado: 'accepted', conConductor: true),
    );

    await tester.pumpWidget(
      envolver(
        sesionComo('passenger'),
        ViajePasajeroScreen(inicial: viaje),
      ),
    );
    await tester.pump(const Duration(milliseconds: 300));

    expect(tester.takeException(), isNull);
    expect(find.text('Pedro Rojas'), findsOneWidget);
    expect(find.textContaining('Bera BR 150'), findsOneWidget);

    await irA(tester, find.text('Cancelar viaje'));
    expect(find.text('Cancelar viaje'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('el viaje completado invita a volver al inicio', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final viaje = Viaje.desdeJson(
      viajeJson(
        estado: 'completed',
        etiqueta: 'Completado',
        conConductor: true,
        puedeCancelar: false,
      ),
    );

    await tester.pumpWidget(
      envolver(
        sesionComo('passenger'),
        ViajePasajeroScreen(inicial: viaje),
      ),
    );
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Viaje completado'), findsOneWidget);

    await irA(tester, find.text('Volver al inicio'));
    expect(find.text('Volver al inicio'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('el conductor ve el siguiente paso del viaje', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final viaje = Viaje.desdeJson(
      viajeJson(
        estado: 'accepted',
        etiqueta: 'Asignado',
        conConductor: true,
        conPasajero: true,
      ),
    );

    await tester.pumpWidget(
      envolver(
        sesionComo('driver'),
        ViajeConductorScreen(inicial: viaje),
      ),
    );
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Ana Pérez'), findsOneWidget);

    await irA(tester, find.text('Llegué al punto de recogida'));
    expect(find.text('Llegué al punto de recogida'), findsOneWidget);
    expect(find.text('Tu ganancia'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('el historial vacío explica que aún no hay viajes',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 1920);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    servidor.responder(
      'GET',
      '/api/viajes',
      <String, dynamic>{'data': <dynamic>[]},
    );

    await tester.pumpWidget(
      envolver(sesionComo('passenger'), const HistorialViajesScreen()),
    );
    await tester.pump(const Duration(milliseconds: 500));

    expect(find.text('Historial de viajes'), findsOneWidget);
    expect(find.text('Todavía no hay viajes'), findsOneWidget);
    expect(find.text('Semana'), findsNothing,
        reason: 'El pasajero no ve los filtros del conductor.');
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('el conductor filtra su historial y ve el estado vacío',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 1920);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    servidor.responder(
      'GET',
      '/api/viajes',
      <String, dynamic>{'data': <dynamic>[]},
    );

    await tester.pumpWidget(
      envolver(sesionComo('driver'), const HistorialViajesScreen()),
    );
    await tester.pump(const Duration(milliseconds: 500));

    expect(find.text('Todos'), findsOneWidget);
    expect(find.text('Hoy'), findsOneWidget);
    expect(find.text('Semana'), findsOneWidget);
    expect(find.text('Mes'), findsOneWidget);

    await tester.tap(find.text('Semana'));
    await tester.pump(const Duration(milliseconds: 500));

    expect(
      servidor.peticiones.last.uri.queryParameters['period'],
      'week',
    );
    expect(find.text('No hay viajes en este período'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('el conductor carga más páginas del historial', (tester) async {
    tester.view.physicalSize = const Size(1080, 1920);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    servidor.responder(
      'GET',
      '/api/viajes',
      <String, dynamic>{
        'data': <dynamic>[
          viajeJson(id: 30, origen: 'Origen de la primera página'),
        ],
        'meta': <String, dynamic>{
          'current_page': 1,
          'last_page': 2,
        },
      },
    );

    await tester.pumpWidget(
      envolver(sesionComo('driver'), const HistorialViajesScreen()),
    );
    await tester.pump(const Duration(milliseconds: 500));

    expect(find.text('Origen de la primera página'), findsOneWidget);
    expect(find.text('Ver más viajes'), findsOneWidget);

    servidor.responder(
      'GET',
      '/api/viajes',
      <String, dynamic>{
        'data': <dynamic>[
          viajeJson(id: 29, origen: 'Origen de la segunda página'),
        ],
        'meta': <String, dynamic>{
          'current_page': 2,
          'last_page': 2,
        },
      },
    );

    await tester.tap(find.text('Ver más viajes'));
    await tester.pump(const Duration(milliseconds: 500));

    expect(
      servidor.peticiones.last.uri.queryParameters['page'],
      '2',
      reason: 'La segunda página se pide con el número de página.',
    );
    expect(find.text('Origen de la primera página'), findsOneWidget);
    expect(find.text('Origen de la segunda página'), findsOneWidget);
    expect(find.text('Ver más viajes'), findsNothing);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('el inicio del pasajero ofrece pedir un viaje', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    configurarBase();

    await tester.pumpWidget(
      envolver(sesionComo('passenger'), const InicioScreen()),
    );
    await tester.pump(const Duration(milliseconds: 500));

    expect(find.text('¿A dónde vas hoy?'), findsOneWidget);
    expect(find.text('Pedir un viaje'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });

  testWidgets('el inicio del conductor muestra su conexión', (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    configurarBase();
    servidor.responder(
      'GET',
      '/api/conductor/estado',
      <String, dynamic>{'data': estadoConductorJson()},
    );
    servidor.responder(
      'GET',
      '/api/conductor/ofertas',
      <String, dynamic>{'data': <dynamic>[]},
    );

    await tester.pumpWidget(
      envolver(sesionComo('driver'), const InicioScreen()),
    );
    await tester.pump(const Duration(milliseconds: 700));

    expect(find.text('MODO CONDUCTOR'), findsOneWidget);
    expect(find.text('Estás desconectado'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });
}

/// Tile transparente para las pruebas: `flutter_map` no sale a la red.
class _ProveedorTilesTransparente extends TileProvider {
  @override
  ImageProvider getImage(TileCoordinates coordinates, TileLayer options) =>
      MemoryImage(TileProvider.transparentImage);
}

/// WebSocket controlado por la prueba.
class TiempoRealFalso extends TiempoRealService {
  TiempoRealFalso({required super.api});

  final StreamController<EventoTiempoReal> _controlador =
      StreamController<EventoTiempoReal>.broadcast();
  bool _conectado = false;

  @override
  Stream<EventoTiempoReal> get eventos => _controlador.stream;

  @override
  bool get conectado => _conectado;

  @override
  Future<void> conectar(int usuarioId) async {
    _conectado = true;
  }

  @override
  Future<void> desconectar() async {
    _conectado = false;
  }

  void emitir(EventoTiempoReal evento) {
    _controlador.add(evento);
  }

  @override
  void dispose() {
    unawaited(_controlador.close());
    super.dispose();
  }
}

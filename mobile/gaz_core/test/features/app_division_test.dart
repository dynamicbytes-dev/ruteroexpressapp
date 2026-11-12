import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/core/api/api_client.dart';
import 'package:gaz_core/core/app/gaz_app.dart';
import 'package:gaz_core/core/theme/app_theme.dart';
import 'package:gaz_core/data/cuenta_repository.dart';
import 'package:gaz_core/data/models/usuario.dart';
import 'package:gaz_core/features/auth/screens/bienvenida_screen.dart';
import 'package:gaz_core/features/auth/screens/elegir_rol_screen.dart';
import 'package:gaz_core/features/auth/screens/registro_screen.dart';
import 'package:gaz_core/features/sesion/sesion_controller.dart';
import 'package:provider/provider.dart';

import '../soporte/datos_falsos.dart';
import '../soporte/servidor_falso.dart';

void main() {
  group('División móvil gaz_core + gaz_passenger + gaz_driver', () {
    testWidgets('GazRolScope identifica rol de pasajero y conductor',
        (tester) async {
      String? rolLeido;

      await tester.pumpWidget(
        GazRolScope(
          rol: 'passenger',
          child: Builder(
            builder: (context) {
              rolLeido = GazRolScope.of(context);
              return const SizedBox();
            },
          ),
        ),
      );

      expect(rolLeido, 'passenger');

      await tester.pumpWidget(
        GazRolScope(
          rol: 'driver',
          child: Builder(
            builder: (context) {
              rolLeido = GazRolScope.of(context);
              return const SizedBox();
            },
          ),
        ),
      );

      expect(rolLeido, 'driver');
    });

    testWidgets(
        'BienvenidaScreen muestra lema de conductor cuando el scope es driver',
        (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: MotorutaTheme.claro,
          home: const GazRolScope(
            rol: 'driver',
            child: BienvenidaScreen(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Conecta, conduce y cobra tus viajes.'), findsOneWidget);
    });

    testWidgets(
        'BienvenidaScreen muestra lema de pasajero cuando el scope es passenger',
        (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          theme: MotorutaTheme.claro,
          home: const GazRolScope(
            rol: 'passenger',
            child: BienvenidaScreen(),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Tu viaje, tu ruta, tu ingreso.'), findsOneWidget);
    });

    testWidgets(
        'ElegirRolScreen va directo al registro si hay un rol fijo en el scope',
        (tester) async {
      final servidor = ServidorFalso();
      final api = ApiClient(
        dio: Dio()..httpClientAdapter = servidor,
        baseUrl: 'http://localhost:8000',
      );
      final sesion = SesionController(
        repositorio: CuentaRepository(api),
        almacen: TokenMemoria(),
        api: api,
        rolEsperado: 'passenger',
      );

      servidor.responder('GET', '/api/ciudades', <String, dynamic>{
        'data': <Map<String, dynamic>>[
          <String, dynamic>{'id': 1, 'name': 'Maracay', 'state': 'Aragua'},
        ],
      });

      await tester.pumpWidget(
        ChangeNotifierProvider<SesionController>.value(
          value: sesion,
          child: MaterialApp(
            theme: MotorutaTheme.claro,
            home: const GazRolScope(
              rol: 'passenger',
              child: ElegirRolScreen(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('¿Cómo vas a usar GAZ?'), findsNothing);
      expect(find.byType(RegistroScreen), findsOneWidget);
    });

    testWidgets(
        'PantallaRolIncorrecto avisa cuando un conductor entra a app de pasajeros',
        (tester) async {
      final servidor = ServidorFalso();
      final almacen = TokenMemoria('tok-driver');

      final api = ApiClient(
        dio: Dio()..httpClientAdapter = servidor,
        baseUrl: 'http://localhost:8000',
      );

      final sesion = SesionController(
        repositorio: CuentaRepository(api),
        almacen: almacen,
        api: api,
        rolEsperado: 'passenger',
      );

      final datos = PerfilCuenta.desdeJson(
        perfilJson(
          paso: 'ready',
          usuario: usuarioJson(
            id: 2,
            rol: 'driver',
            correo: 'conductor@gmail.com',
          ),
        ),
      );

      sesion.usuario = datos.usuario;
      sesion.siguientePaso = datos.siguientePaso;
      sesion.estado = EstadoSesion.activa;

      expect(sesion.rolIncorrecto, isTrue);

      await tester.pumpWidget(
        ChangeNotifierProvider<SesionController>.value(
          value: sesion,
          child: MaterialApp(
            theme: MotorutaTheme.claro,
            home: const GazRolScope(
              rol: 'passenger',
              child: PantallaRolIncorrecto(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byType(PantallaRolIncorrecto), findsOneWidget);
      expect(find.text('Esta cuenta es de conductor'), findsOneWidget);
      expect(
        find.textContaining('Para entrar con ella, abre GAZ Driver.'),
        findsOneWidget,
      );

      servidor.responder('POST', '/api/auth/logout', <String, dynamic>{
        'message': 'Sesión cerrada.',
      });

      await tester.tap(find.text('Cerrar sesión'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      expect(almacen.token, isNull);
    });

    testWidgets(
        'PantallaRolIncorrecto avisa cuando un pasajero entra a app de conductores',
        (tester) async {
      final api = ApiClient(baseUrl: 'http://localhost:8000');
      final sesion = SesionController(
        repositorio: CuentaRepository(api),
        almacen: TokenMemoria('tok-pass'),
        api: api,
        rolEsperado: 'driver',
      );

      final datos = PerfilCuenta.desdeJson(
        perfilJson(
          paso: 'ready',
          usuario: usuarioJson(
            id: 1,
            rol: 'passenger',
            correo: 'pasajero@gmail.com',
          ),
        ),
      );

      sesion.usuario = datos.usuario;
      sesion.siguientePaso = datos.siguientePaso;
      sesion.estado = EstadoSesion.activa;

      expect(sesion.rolIncorrecto, isTrue);

      await tester.pumpWidget(
        ChangeNotifierProvider<SesionController>.value(
          value: sesion,
          child: MaterialApp(
            theme: MotorutaTheme.claro,
            home: const GazRolScope(
              rol: 'driver',
              child: PantallaRolIncorrecto(),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byType(PantallaRolIncorrecto), findsOneWidget);
      expect(find.text('Esta cuenta es de pasajero'), findsOneWidget);
      expect(
        find.textContaining('Para entrar con ella, abre GAZ.'),
        findsOneWidget,
      );
    });

    test('SesionController envía el rol esperado al llamar a entrar()',
        () async {
      final servidor = ServidorFalso();
      final almacen = TokenMemoria();

      final api = ApiClient(
        dio: Dio()..httpClientAdapter = servidor,
        baseUrl: 'http://localhost:8000',
      );

      final sesion = SesionController(
        repositorio: CuentaRepository(api),
        almacen: almacen,
        api: api,
        rolEsperado: 'driver',
      );

      servidor.responder(
        'POST',
        '/api/auth/login',
        <String, dynamic>{
          'message': 'Bienvenido de vuelta.',
          'data': <String, dynamic>{
            'token': 'tok-2',
            'token_type': 'Bearer',
            'user': usuarioJson(
              id: 2,
              rol: 'driver',
              correo: 'comun@gmail.com',
            ),
          },
        },
      );

      servidor.responder(
        'GET',
        '/api/perfil',
        perfilJson(
          paso: 'ready',
          usuario: usuarioJson(
            id: 2,
            rol: 'driver',
            correo: 'comun@gmail.com',
          ),
        ),
      );

      await sesion.entrar(
        correo: 'comun@gmail.com',
        contrasena: 'clave12345',
      );

      final peticion = servidor.peticiones
          .firstWhere((p) => p.uri.path == '/api/auth/login');
      final cuerpo = peticion.data as Map<String, dynamic>;
      expect(peticion.uri.path, '/api/auth/login');
      expect(cuerpo['email'], 'comun@gmail.com');
      expect(cuerpo['role'], 'driver');
    });
  });
}

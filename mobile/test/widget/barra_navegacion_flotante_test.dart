import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/api/api_client.dart';
import 'package:motoruta/core/theme/app_theme.dart';
import 'package:motoruta/core/theme/motoruta_colors.dart';
import 'package:motoruta/core/ui/barra_navegacion_flotante.dart';
import 'package:motoruta/data/cuenta_repository.dart';
import 'package:motoruta/data/models/usuario.dart';
import 'package:motoruta/features/inicio/screens/inicio_screen.dart';
import 'package:motoruta/features/sesion/sesion_controller.dart';
import 'package:provider/provider.dart';

import '../soporte/datos_falsos.dart';

void main() {
  const elementos = <ElementoNavegacion>[
    ElementoNavegacion(
      icono: Icons.home_outlined,
      iconoActivo: Icons.home_rounded,
      etiqueta: 'Inicio',
    ),
    ElementoNavegacion(
      icono: Icons.history_outlined,
      iconoActivo: Icons.history_rounded,
      etiqueta: 'Historial',
    ),
    ElementoNavegacion(
      icono: Icons.two_wheeler_outlined,
      iconoActivo: Icons.two_wheeler_rounded,
      etiqueta: 'Viajes',
    ),
    ElementoNavegacion(
      icono: Icons.calendar_month_outlined,
      iconoActivo: Icons.calendar_month_rounded,
      etiqueta: 'Agendados',
      badge: 2,
    ),
    ElementoNavegacion(
      icono: Icons.person_outline_rounded,
      iconoActivo: Icons.person_rounded,
      etiqueta: 'Perfil',
    ),
  ];

  Widget envolver(Widget hijo) {
    return MaterialApp(
      theme: MotorutaTheme.claro,
      home: Scaffold(body: hijo),
    );
  }

  testWidgets('BarraNavegacionFlotante renderiza las 5 pestañas correctamente',
      (tester) async {
    int indiceSeleccionado = 0;

    await tester.pumpWidget(
      envolver(
        BarraNavegacionFlotante(
          indiceActual: indiceSeleccionado,
          alCambiarPestana: (idx) => indiceSeleccionado = idx,
          elementos: elementos,
        ),
      ),
    );

    expect(find.text('Inicio'), findsOneWidget);
    expect(find.text('Historial'), findsOneWidget);
    expect(find.text('Viajes'), findsOneWidget);
    expect(find.text('Agendados'), findsOneWidget);
    expect(find.text('Perfil'), findsOneWidget);
    expect(find.text('2'), findsOneWidget); // badge en Agendados
  });

  testWidgets('tocar una pestaña dispara alCambiarPestana con el índice correcto',
      (tester) async {
    int indiceSeleccionado = 0;

    await tester.pumpWidget(
      envolver(
        StatefulBuilder(
          builder: (context, setState) {
            return BarraNavegacionFlotante(
              indiceActual: indiceSeleccionado,
              alCambiarPestana: (idx) {
                setState(() => indiceSeleccionado = idx);
              },
              elementos: elementos,
            );
          },
        ),
      ),
    );

    // Tocar 'Historial' (índice 1)
    await tester.tap(find.text('Historial'));
    await tester.pumpAndSettle();
    expect(indiceSeleccionado, 1);

    // Tocar 'Viajes' (índice 2)
    await tester.tap(find.text('Viajes'));
    await tester.pumpAndSettle();
    expect(indiceSeleccionado, 2);

    // Tocar 'Agendados' (índice 3)
    await tester.tap(find.text('Agendados'));
    await tester.pumpAndSettle();
    expect(indiceSeleccionado, 3);

    // Tocar 'Perfil' (índice 4)
    await tester.tap(find.text('Perfil'));
    await tester.pumpAndSettle();
    expect(indiceSeleccionado, 4);

    // Tocar 'Inicio' (índice 0)
    await tester.tap(find.text('Inicio'));
    await tester.pumpAndSettle();
    expect(indiceSeleccionado, 0);
  });

  testWidgets('la pestaña activa resalta con contenedor y color de acento',
      (tester) async {
    await tester.pumpWidget(
      envolver(
        BarraNavegacionFlotante(
          indiceActual: 2, // Viajes activo
          alCambiarPestana: (_) {},
          elementos: elementos,
        ),
      ),
    );

    // Debe mostrar el icono activo de Viajes (Icons.two_wheeler_rounded)
    expect(find.byIcon(Icons.two_wheeler_rounded), findsOneWidget);

    // Debe existir un AnimatedContainer para el efecto circular
    expect(find.byType(AnimatedContainer), findsWidgets);
  });

  testWidgets(
      'InicioScreen para pasajero incluye BarraNavegacionFlotante y cambia pestañas',
      (tester) async {
    tester.view.physicalSize = const Size(1080, 2400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    final api = ApiClient(baseUrl: 'http://localhost:8000');
    final sesion = SesionController(
      repositorio: CuentaRepository(api),
      almacen: TokenMemoria('tok'),
      api: api,
    );

    final perfilListo = perfilJson(
      usuario: usuarioJson(
        rol: 'passenger',
        estado: 'approved',
        etiquetaEstado: 'Aprobado',
        puedeOperar: true,
      ),
      paso: 'ready',
      mensaje: 'Tu cuenta está aprobada. Ya puedes pedir tu primer viaje.',
    );

    final datos = PerfilCuenta.desdeJson(perfilListo);
    sesion.usuario = datos.usuario;
    sesion.siguientePaso = datos.siguientePaso;
    sesion.estado = EstadoSesion.activa;

    await tester.pumpWidget(
      ChangeNotifierProvider<SesionController>.value(
        value: sesion,
        child: MaterialApp(
          theme: MotorutaTheme.claro,
          home: const InicioScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // La barra de navegación flotante debe estar presente
    expect(find.byType(BarraNavegacionFlotante), findsOneWidget);

    // Título inicial 'Mi cuenta'
    expect(find.text('Mi cuenta'), findsOneWidget);

    // Cambiar a Billetera (índice 3)
    await tester.tap(find.text('Billetera'));
    await tester.pumpAndSettle();

    // Verifica que se muestra la vista de Billetera
    expect(find.text('Billetera'), findsWidgets);
    expect(find.text('Recargar'), findsWidgets);

    // Cambiar a Perfil (índice 4)
    await tester.tap(find.text('Perfil'));
    await tester.pumpAndSettle();

    // Verifica que se muestra la vista de perfil
    expect(find.text('Mi perfil'), findsOneWidget);
    expect(find.text('Información personal'), findsOneWidget);
    expect(find.text('Editar mis datos'), findsOneWidget);

    // Regresar a Inicio (índice 0)
    await tester.tap(find.text('Inicio'));
    await tester.pumpAndSettle();

    // Verifica que el estado y contenido de Inicio siguen intactos
    expect(find.text('Mi cuenta'), findsOneWidget);
    expect(find.text('¿A dónde vas hoy?'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
    await tester.pump();
  });
}


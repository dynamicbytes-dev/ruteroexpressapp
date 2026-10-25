import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/api/api_client.dart';
import 'package:motoruta/core/api/api_exception.dart';
import 'package:motoruta/data/billetera_repository.dart';
import 'package:motoruta/data/cuenta_repository.dart';
import 'package:motoruta/data/models/billetera.dart';
import 'package:motoruta/data/models/usuario.dart';
import 'package:motoruta/features/billetera/billetera_ui.dart';
import 'package:motoruta/features/sesion/sesion_controller.dart';

import '../soporte/datos_falsos.dart';
import '../soporte/servidor_falso.dart';

void main() {
  late ServidorFalso servidor;
  late SesionController sesion;

  setUp(() {
    servidor = ServidorFalso();

    final dio = Dio()
      ..httpClientAdapter = servidor
      ..transformer = SyncTransformer();
    final api = ApiClient(dio: dio, baseUrl: 'http://localhost:8000');

    sesion = SesionController(
      repositorio: CuentaRepository(api),
      almacen: TokenMemoria('tok-1'),
      api: api,
      billetera: BilleteraRepository(api),
    );

    final perfil = PerfilCuenta.desdeJson(
      perfilJson(
        usuario: usuarioJson(
          rol: 'driver',
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
  });

  test('carga el saldo, la tasa y los datos bancarios', () async {
    servidor.responder(
      'GET',
      '/api/billetera',
      billeteraJson(saldo: 50, retenido: 5),
    );

    await sesion.cargarBilletera();

    final billetera = sesion.billetera;

    expect(billetera, isNotNull);
    expect(billetera!.saldo, 50);
    expect(billetera.retenido, 5);
    expect(billetera.disponible, 45);
    expect(billetera.tasa!.tasa, 40);
    expect(billetera.saldoEnBolivares, 2000);
    expect(billetera.banco.banco, 'Banesco');
    expect(billetera.limites.retiroMinimo, 1);
    expect(sesion.cargandoBilletera, isFalse);
    expect(sesion.errorBilletera, isNull);
  });

  test('si la billetera falla se guarda el mensaje y no se rompe', () async {
    servidor.responder(
      'GET',
      '/api/billetera',
      <String, dynamic>{'message': 'No pudimos cargar tu billetera.'},
      codigo: 500,
    );

    await sesion.cargarBilletera();

    expect(sesion.billetera, isNull);
    expect(sesion.errorBilletera, 'No pudimos cargar tu billetera.');
    expect(sesion.cargandoBilletera, isFalse);
  });

  test('solicitar una recarga sube el comprobante y refresca el saldo',
      () async {
    servidor.responder('GET', '/api/billetera', billeteraJson(saldo: 50));
    servidor.responder(
      'POST',
      '/api/recargas',
      <String, dynamic>{
        'message': 'Recibimos tu recarga. Queda pendiente de revisión.',
        'data': <String, dynamic>{'recharge': recargaJson()},
      },
      codigo: 201,
    );

    final resultado = await sesion.recargar(
      montoBs: 4000,
      banco: 'Banesco',
      referencia: '123456789',
      fechaTransferencia: DateTime(2026, 10, 19),
      comprobante: ArchivoSubida.desdeBytes(
        nombre: 'comprobante.jpg',
        bytes: const <int>[1, 2, 3, 4],
      ),
    );

    expect(resultado.recarga.montoBs, 4000);
    expect(resultado.recarga.estado, 'pending');
    expect(resultado.mensaje, contains('Recibimos tu recarga'));

    final peticion =
        servidor.peticiones.firstWhere((p) => p.path == '/api/recargas');
    expect(peticion.method, 'POST');
    expect(peticion.data, isA<FormData>());

    // Después de recargar se vuelve a pedir la billetera; la última petición
    // tiene que ser esa consulta, no el POST.
    expect(servidor.peticiones.last.method, 'GET');
    expect(servidor.peticiones.last.path, '/api/billetera');
  });

  test('los errores de validación de la recarga se leen por campo', () async {
    servidor.responder(
      'POST',
      '/api/recargas',
      <String, dynamic>{
        'message': 'Los datos no son válidos.',
        'errors': <String, dynamic>{
          'amount_bs': <String>['El monto debe ser mayor que cero.'],
        },
      },
      codigo: 422,
    );

    try {
      await sesion.recargar(
        montoBs: 0,
        banco: 'Banesco',
        referencia: '123',
        fechaTransferencia: DateTime(2026, 10, 19),
        comprobante: ArchivoSubida.desdeBytes(
          nombre: 'comprobante.jpg',
          bytes: const <int>[1],
        ),
      );
      fail('La recarga debió lanzar ApiException.');
    } on ApiException catch (error) {
      expect(error.errorDe('amount_bs'), 'El monto debe ser mayor que cero.');
    }
  });

  test('solicitar un retiro refresca la billetera', () async {
    servidor.responder('GET', '/api/billetera', billeteraJson(saldo: 30));
    servidor.responder(
      'POST',
      '/api/retiros',
      <String, dynamic>{
        'message': 'Recibimos tu solicitud.',
        'data': <String, dynamic>{'withdrawal': retiroJson()},
      },
      codigo: 201,
    );

    final resultado = await sesion.retirar(
      montoUsd: 10,
      banco: 'Banesco',
      numeroCuenta: '01340000000000000000',
      titular: 'Pedro Rojas',
      cedulaTitular: '14988776',
    );

    expect(resultado.retiro.montoUsd, 10);
    expect(resultado.retiro.estado, 'pending');
    expect(resultado.mensaje, 'Recibimos tu solicitud.');
  });

  test('sin repositorio de billetera las listas quedan vacías', () async {
    final api = ApiClient(
      dio: Dio()
        ..httpClientAdapter = ServidorFalso()
        ..transformer = SyncTransformer(),
      baseUrl: 'http://localhost:8000',
    );
    final sinBilletera = SesionController(
      repositorio: CuentaRepository(api),
      almacen: TokenMemoria('tok-1'),
      api: api,
    );

    expect(await sinBilletera.recargas(), isEmpty);
    expect(await sinBilletera.retiros(), isEmpty);
    expect(await sinBilletera.movimientos(), isEmpty);
  });

  test('lee montos con coma o punto decimal', () {
    expect(leerDecimal('4000'), 4000);
    expect(leerDecimal('4.000,50'), 4000.50);
    expect(leerDecimal('10,25'), 10.25);
    expect(leerDecimal('10.25'), 10.25);
    expect(leerDecimal(''), isNull);
  });

  test('el modelo de recarga distingue sus estados', () {
    final pendiente = Recarga.desdeJson(recargaJson());
    final aprobada = Recarga.desdeJson(
      recargaJson(
        id: 10,
        estado: 'approved',
        etiqueta: 'Aprobada',
        tasa: 40,
        montoUsd: 100,
      ),
    );
    final rechazada = Recarga.desdeJson(
      recargaJson(
        id: 11,
        estado: 'rejected',
        etiqueta: 'Rechazada',
        motivoRechazo: 'Comprobante ilegible.',
      ),
    );

    expect(pendiente.pendiente, isTrue);
    expect(aprobada.aprobada, isTrue);
    expect(aprobada.montoUsd, 100);
    expect(rechazada.rechazada, isTrue);
    expect(rechazada.motivoRechazo, 'Comprobante ilegible.');
  });
}

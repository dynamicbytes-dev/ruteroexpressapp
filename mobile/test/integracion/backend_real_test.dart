import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/api/api_client.dart';
import 'package:motoruta/core/api/api_exception.dart';
import 'package:motoruta/data/cuenta_repository.dart';
import 'package:motoruta/data/models/registro_datos.dart';
import 'package:motoruta/features/sesion/sesion_controller.dart';

import '../soporte/datos_falsos.dart';

/// Prueba contra el backend de verdad, no contra un servidor simulado.
///
/// Está apagada por defecto porque necesita la base de datos y
/// `php artisan serve` corriendo. Se prende así:
///
/// ```
/// cd backend && php artisan serve
/// cd mobile && flutter test --dart-define=API_REAL=true test/integracion
/// ```
///
/// Crea una cuenta de prueba con correo y cédula únicos (marca de tiempo), sube
/// una cédula de mentira en JPEG y revisa que quede en la cola del auditor.
const bool _habilitada = bool.fromEnvironment('API_REAL');

void main() {
  test(
    'el backend real acepta el registro y la subida de un documento',
    () async {
      const base = String.fromEnvironment(
        'API_URL',
        defaultValue: 'http://localhost:8000',
      );

      final api = ApiClient(baseUrl: base);
      final almacen = TokenMemoria();
      final sesion = SesionController(
        repositorio: CuentaRepository(api),
        almacen: almacen,
        api: api,
      );

      // Datos únicos para que la prueba se pueda repetir sin chocar con las
      // reglas de correo, cédula y placa únicas.
      final marca = DateTime.now().millisecondsSinceEpoch;
      final sufijo = marca.toString().substring(marca.toString().length - 7);

      await sesion.registrarse(
        RegistroDatos(
          rol: 'driver',
          nombres: 'Prueba',
          apellidos: 'Integración',
          cedula: '1$sufijo',
          correo: 'prueba.$marca@gmail.com',
          telefono: '0414$sufijo',
          direccion: 'Avenida de prueba, casa $sufijo, Maracay',
          contrasena: 'clave12345',
          aceptaTerminos: true,
          ciudadId: 1,
          marcaVehiculo: 'Bera',
          modeloVehiculo: 'BR 150',
          anioVehiculo: '2022',
          placaVehiculo: 'P$sufijo',
        ),
      );

      expect(sesion.estado, EstadoSesion.activa);
      expect(sesion.usuario!.estado, 'pending');
      expect(sesion.usuario!.puedeOperar, isFalse);
      expect(sesion.siguientePaso!.faltaSubir, isTrue);
      expect(almacen.token, isNotNull);

      // Un JPEG real de 1x1: el backend detecta el tipo por el contenido.
      final jpeg = base64Decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'
        'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAAB'
        'AAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==',
      );

      final mensaje = await sesion.subirDocumento(
        tipo: 'cedula',
        archivo: ArchivoSubida.desdeBytes(nombre: 'cedula.jpg', bytes: jpeg),
      );

      expect(mensaje, isNotNull);
      expect(mensaje, contains('Foto de la cédula'));
      expect(sesion.usuario!.verificacion.subidos, 1);
      expect(sesion.usuario!.verificacion.items.first.subido, isTrue);

      // Una fecha de vencimiento en el pasado debe rebotar.
      await expectLater(
        sesion.subirDocumento(
          tipo: 'licencia_conducir',
          archivo:
              ArchivoSubida.desdeBytes(nombre: 'licencia.jpg', bytes: jpeg),
          venceEl: DateTime.now().subtract(const Duration(days: 2)),
        ),
        throwsA(isA<ApiException>()),
      );

      await sesion.salir();
      expect(sesion.estado, EstadoSesion.sinSesion);
      expect(almacen.token, isNull);
    },
    skip: _habilitada
        ? false
        : 'Necesita el backend corriendo: flutter test --dart-define=API_REAL=true test/integracion',
  );
}

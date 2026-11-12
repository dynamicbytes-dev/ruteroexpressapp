import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/core/legal/terminos_version.dart';
import 'package:gaz_core/data/models/registro_datos.dart';

void main() {
  RegistroDatos conductor({String tipo = 'moto'}) {
    return RegistroDatos(
      rol: 'driver',
      nombres: 'Pedro',
      apellidos: 'Rojas',
      cedula: '14988776',
      correo: 'pedro@gmail.com',
      telefono: '0412-1234567',
      direccion: 'Calle Sucre, casa 45, Maracay',
      contrasena: 'clave12345',
      aceptaTerminos: true,
      ciudadId: 2,
      tipoVehiculo: tipo,
      marcaVehiculo: 'Toyota',
      modeloVehiculo: 'Corolla',
      anioVehiculo: '2022',
      placaVehiculo: 'ac456de',
    );
  }

  test('el conductor envía el tipo de vehículo y los datos del carro', () {
    final json = conductor(tipo: 'carro').aJson();

    expect(json['vehicle_type'], 'carro');
    expect(json['vehicle_brand'], 'Toyota');
    expect(json['vehicle_model'], 'Corolla');
    expect(json['vehicle_year'], 2022);
    expect(json['vehicle_plate'], 'AC456DE');
  });

  test('el registro envía la ciudad elegida', () {
    expect(conductor().aJson()['city_id'], 2);
    expect(RegistroDatos.campoDelFormulario['city_id'], 'ciudad');
  });

  test('si no se elige tipo, se asume moto', () {
    final datos = conductor();
    expect(datos.aJson()['vehicle_type'], 'moto');
  });

  test('el pasajero no envía datos del vehículo', () {
    final json = const RegistroDatos(
      rol: 'passenger',
      nombres: 'María',
      apellidos: 'Pérez',
      cedula: '12345678',
      correo: 'maria@gmail.com',
      telefono: '0412-1234567',
      direccion: 'Av. Bolívar, casa 12, Maracay',
      contrasena: 'clave12345',
      aceptaTerminos: true,
    ).aJson();

    expect(json.containsKey('vehicle_type'), isFalse);
    expect(json.containsKey('vehicle_brand'), isFalse);
  });

  test('el registro envía la aceptación y la versión de los términos', () {
    final json = conductor().aJson();

    expect(json['accept_terms'], isTrue);
    expect(json['terms_version'], versionTerminos);
    expect(RegistroDatos.campoDelFormulario['accept_terms'], 'terminos');
    expect(RegistroDatos.campoDelFormulario['terms_version'], 'terminos');
  });

  test('el error de tipo de vehículo se pinta en el selector', () {
    expect(RegistroDatos.campoDelFormulario['vehicle_type'], 'tipoVehiculo');
  });
}

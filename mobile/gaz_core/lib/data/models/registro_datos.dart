import '../../core/legal/terminos_version.dart';

/// Datos que la persona llena en el formulario de registro.
///
/// Son exactamente los campos de `POST /api/auth/register`
/// (`backend/app/Http/Requests/Api/RegisterRequest.php`).
class RegistroDatos {
  const RegistroDatos({
    required this.rol,
    required this.nombres,
    required this.apellidos,
    required this.cedula,
    required this.correo,
    required this.telefono,
    required this.direccion,
    required this.contrasena,
    required this.aceptaTerminos,
    this.ciudadId,
    this.tipoVehiculo,
    this.marcaVehiculo,
    this.modeloVehiculo,
    this.anioVehiculo,
    this.placaVehiculo,
  });

  final String rol; // 'passenger' | 'driver'
  final String nombres;
  final String apellidos;
  final String cedula;
  final String correo;
  final String telefono;
  final String direccion;
  final String contrasena;
  final bool aceptaTerminos;
  final int? ciudadId;
  final String? tipoVehiculo; // 'moto' | 'carro'
  final String? marcaVehiculo;
  final String? modeloVehiculo;
  final String? anioVehiculo;
  final String? placaVehiculo;

  bool get esConductor => rol == 'driver';

  /// Copia los datos con otro rol.
  ///
  /// Lo usan GAZ y GAZ Driver: el formulario ya no pregunta pasajero/conductor,
  /// la app impone el suyo incluso si la pantalla venía con otro valor.
  RegistroDatos conRol(String nuevoRol) {
    return RegistroDatos(
      rol: nuevoRol,
      nombres: nombres,
      apellidos: apellidos,
      cedula: cedula,
      correo: correo,
      telefono: telefono,
      direccion: direccion,
      contrasena: contrasena,
      aceptaTerminos: aceptaTerminos,
      ciudadId: ciudadId,
      tipoVehiculo: tipoVehiculo,
      marcaVehiculo: marcaVehiculo,
      modeloVehiculo: modeloVehiculo,
      anioVehiculo: anioVehiculo,
      placaVehiculo: placaVehiculo,
    );
  }

  /// Cuerpo JSON tal como lo espera el backend.
  Map<String, dynamic> aJson({String? nombreDispositivo}) {
    return <String, dynamic>{
      'role': rol,
      'first_name': nombres.trim(),
      'last_name': apellidos.trim(),
      'national_id': cedula.trim().replaceAll('.', ''),
      'email': correo.trim().toLowerCase(),
      'phone': telefono.trim(),
      'address': direccion.trim(),
      if (ciudadId != null) 'city_id': ciudadId,
      'password': contrasena,
      'password_confirmation': contrasena,
      'accept_terms': aceptaTerminos,
      'terms_version': versionTerminos,
      if (nombreDispositivo != null) 'device_name': nombreDispositivo,
      if (esConductor) ...<String, dynamic>{
        'vehicle_type': tipoVehiculo ?? 'moto',
        'vehicle_brand': marcaVehiculo?.trim(),
        'vehicle_model': modeloVehiculo?.trim(),
        'vehicle_year': int.tryParse(
            (anioVehiculo ?? '').trim().replaceAll(RegExp(r'\D'), '')),
        'vehicle_plate': placaVehiculo?.trim().toUpperCase(),
      },
    };
  }

  /// Traduce los nombres del JSON a las claves del formulario, para pintar los
  /// errores de validación en el campo correcto.
  static const Map<String, String> campoDelFormulario = <String, String>{
    'first_name': 'nombres',
    'last_name': 'apellidos',
    'national_id': 'cedula',
    'email': 'correo',
    'phone': 'telefono',
    'address': 'direccion',
    'city_id': 'ciudad',
    'password': 'contrasena',
    'accept_terms': 'terminos',
    'terms_version': 'terminos',
    'vehicle_type': 'tipoVehiculo',
    'vehicle_brand': 'marcaVehiculo',
    'vehicle_model': 'modeloVehiculo',
    'vehicle_year': 'anioVehiculo',
    'vehicle_plate': 'placaVehiculo',
  };
}

/// Conductor favorito de un pasajero, con su reputación y vehículo.
class ConductorFavorito {
  const ConductorFavorito({
    required this.id,
    required this.nombre,
    this.calificacion,
    this.calificaciones = 0,
    this.viajesCompletados = 0,
    this.vehiculo,
    this.enLinea = false,
  });

  final int id;
  final String nombre;
  final double? calificacion;
  final int calificaciones;
  final int viajesCompletados;
  final String? vehiculo;
  final bool enLinea;

  factory ConductorFavorito.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    return ConductorFavorito(
      id: _entero(json['id']),
      nombre: _texto(json['name']) ?? 'Conductor',
      calificacion: _decimalONull(json['rating']),
      calificaciones: _entero(json['ratings_count']),
      viajesCompletados: _entero(json['trips_completed']),
      vehiculo: _texto(json['vehicle']),
      enLinea: json['is_online'] == true,
    );
  }
}

/// Calificación que recibió el conductor (sin datos del pasajero).
class CalificacionRecibida {
  const CalificacionRecibida({
    required this.estrellas,
    this.comentario,
    this.fecha,
  });

  final int estrellas;
  final String? comentario;
  final DateTime? fecha;

  String get textoEstrellas => '★' * estrellas + '☆' * (5 - estrellas);

  factory CalificacionRecibida.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    return CalificacionRecibida(
      estrellas: _entero(json['rating']),
      comentario: _texto(json['comment']),
      fecha: _fecha(json['created_at']),
    );
  }
}

int _entero(Object? valor) {
  if (valor is int) return valor;
  if (valor is num) return valor.toInt();
  return int.tryParse('$valor') ?? 0;
}

double? _decimalONull(Object? valor) {
  if (valor == null) return null;
  if (valor is num) return valor.toDouble();
  return double.tryParse('$valor');
}

String? _texto(Object? valor) {
  if (valor == null) return null;
  final texto = '$valor'.trim();
  return texto.isEmpty ? null : texto;
}

DateTime? _fecha(Object? valor) {
  if (valor == null) return null;
  return DateTime.tryParse('$valor')?.toLocal();
}

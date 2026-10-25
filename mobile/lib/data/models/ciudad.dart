/// Ciudad de operación de GAZ.
///
/// Cada ciudad es una zona independiente: tiene sus propias tarifas y sus
/// propios conductores. La app no calcula precios ni decide la zona: solo
/// muestra lo que devuelve el backend.
class Ciudad {
  const Ciudad({
    required this.id,
    required this.nombre,
    required this.estado,
    this.nombreCompleto,
  });

  final int id;
  final String nombre;
  final String estado;
  final String? nombreCompleto;

  /// "Lechería (Anzoátegui)", listo para un selector.
  String get etiqueta {
    final completo = nombreCompleto?.trim();

    if (completo != null && completo.isNotEmpty) return completo;

    return estado.isEmpty ? nombre : '$nombre ($estado)';
  }

  factory Ciudad.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    return Ciudad(
      id: _entero(json['id']),
      nombre: _texto(json['name']) ?? '',
      estado: _texto(json['state']) ?? '',
      nombreCompleto: _texto(json['display_name']),
    );
  }
}

int _entero(Object? valor) {
  if (valor is int) return valor;
  if (valor is num) return valor.toInt();
  return int.tryParse('$valor') ?? 0;
}

String? _texto(Object? valor) {
  if (valor == null) return null;
  final texto = '$valor'.trim();
  return texto.isEmpty ? null : texto;
}

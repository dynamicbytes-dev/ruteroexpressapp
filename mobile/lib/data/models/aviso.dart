/// Aviso de la bandeja de la app (`GET /api/notificaciones`).
///
/// Hoy llegan los documentos por vencer. En F4 se sumarán los avisos de viaje
/// y el mismo registro alimentará el push.
class AvisoCuenta {
  const AvisoCuenta({
    required this.id,
    required this.tipo,
    required this.titulo,
    required this.mensaje,
    required this.leido,
    this.creadoEn,
  });

  final String id;
  final String tipo;
  final String titulo;
  final String mensaje;
  final bool leido;
  final DateTime? creadoEn;

  factory AvisoCuenta.desdeJson(Map<String, dynamic> json) {
    return AvisoCuenta(
      id: _texto(json['id']) ?? '',
      tipo: _texto(json['type']) ?? 'general',
      titulo: _texto(json['title']) ?? 'Aviso de GAZ',
      mensaje: _texto(json['message']) ?? '',
      leido: json['read_at'] != null,
      creadoEn: _fecha(json['created_at']),
    );
  }
}

String? _texto(Object? valor) {
  if (valor is! String || valor.trim().isEmpty) return null;
  return valor;
}

DateTime? _fecha(Object? valor) {
  if (valor is! String || valor.isEmpty) return null;
  return DateTime.tryParse(valor)?.toLocal();
}

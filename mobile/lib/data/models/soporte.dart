/// Conversación del chat de soporte.
///
/// La API móvil solo entrega la conversación abierta. Si el auditor la
/// finaliza, `GET /api/soporte` deja de devolverla y el hilo desaparece de la
/// app; el historial completo se conserva en el panel.
class ConversacionSoporte {
  const ConversacionSoporte({
    required this.id,
    required this.estado,
    required this.etiquetaEstado,
    required this.mensajes,
    this.auditorNombre,
    this.iniciadaEn,
    this.ultimaActividad,
    this.cerradaEn,
  });

  final int id;
  final String estado;
  final String etiquetaEstado;
  final List<MensajeSoporte> mensajes;

  /// Nombre del auditor que atendió el chat. Puede ser null si todavía nadie
  /// del equipo ha respondido o si la cuenta fue eliminada.
  final String? auditorNombre;
  final DateTime? iniciadaEn;
  final DateTime? ultimaActividad;
  final DateTime? cerradaEn;

  bool get abierta => estado == 'open';

  factory ConversacionSoporte.desdeJson(Map<String, dynamic> json) {
    final mensajes = json['messages'];

    return ConversacionSoporte(
      id: int.tryParse('${json['id']}') ?? 0,
      estado: _texto(json['status']) ?? 'open',
      etiquetaEstado: _texto(json['status_label']) ?? 'Abierta',
      mensajes: mensajes is List
          ? mensajes
              .whereType<Map>()
              .map((cada) =>
                  MensajeSoporte.desdeJson(Map<String, dynamic>.from(cada)))
              .toList()
          : const <MensajeSoporte>[],
      iniciadaEn: _fecha(json['started_at']),
      ultimaActividad: _fecha(json['last_message_at']),
      cerradaEn: _fecha(json['closed_at']),
      auditorNombre: _texto(json['auditor_name']),
    );
  }
}

/// Un mensaje del hilo. `deSoporte` distingue al equipo de la persona.
class MensajeSoporte {
  const MensajeSoporte({
    required this.id,
    required this.texto,
    required this.deSoporte,
    required this.autorEtiqueta,
    this.creadoEn,
    this.leidoEn,
  });

  final int id;
  final String texto;
  final bool deSoporte;
  final String autorEtiqueta;
  final DateTime? creadoEn;
  final DateTime? leidoEn;

  factory MensajeSoporte.desdeJson(Map<String, dynamic> json) {
    return MensajeSoporte(
      id: int.tryParse('${json['id']}') ?? 0,
      texto: _texto(json['body']) ?? '',
      deSoporte: json['from_support'] == true,
      autorEtiqueta: _texto(json['author_label']) ??
          (json['from_support'] == true ? 'Soporte GAZ' : 'Tú'),
      creadoEn: _fecha(json['created_at']),
      leidoEn: _fecha(json['read_at']),
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

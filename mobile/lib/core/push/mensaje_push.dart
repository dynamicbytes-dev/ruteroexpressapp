import '../realtime/evento_tiempo_real.dart';

/// Tipo de aviso que llegó por Firebase Cloud Messaging.
enum TipoMensajePush { oferta, viaje, aviso }

/// Mensaje push ya interpretado, sin depender de los tipos de Firebase.
///
/// El backend manda datos mínimos en `data` (tipo, viaje, oferta, estado). La
/// app los convierte en un [EventoTiempoReal] para reutilizar la misma lógica
/// que ya usa el WebSocket: pedir el detalle a la API y avisar a las pantallas.
class MensajePush {
  const MensajePush({
    required this.tipo,
    this.viajeId,
    this.ofertaId,
    this.avisoId,
    this.estado,
    this.motivo,
  });

  final TipoMensajePush tipo;
  final int? viajeId;
  final int? ofertaId;
  final String? avisoId;
  final String? estado;
  final String? motivo;

  /// Lee el mapa `data` de un mensaje de FCM.
  static MensajePush? desdeDatos(Map<String, dynamic> datos) {
    final tipo = switch (datos['tipo']?.toString()) {
      'oferta' => TipoMensajePush.oferta,
      'viaje' => TipoMensajePush.viaje,
      'aviso' => TipoMensajePush.aviso,
      _ => null,
    };

    if (tipo == null) return null;

    return MensajePush(
      tipo: tipo,
      viajeId: _entero(datos['trip_id']),
      ofertaId: _entero(datos['offer_id']),
      avisoId: _texto(datos['notification_id']),
      estado: _texto(datos['status']),
      motivo: _texto(datos['motivo']),
    );
  }

  /// Convierte el push en la señal que ya entienden las pantallas.
  EventoTiempoReal? aEvento() {
    return switch (tipo) {
      TipoMensajePush.oferta => EventoTiempoReal(
          tipo: TipoEventoTiempoReal.oferta,
          ofertaId: ofertaId,
          viajeId: viajeId,
          estado: 'pending',
        ),
      TipoMensajePush.viaje => viajeId == null
          ? null
          : EventoTiempoReal(
              tipo: TipoEventoTiempoReal.viaje,
              viajeId: viajeId,
              estado: estado,
              motivo: motivo ?? 'push',
            ),
      TipoMensajePush.aviso => EventoTiempoReal(
          tipo: TipoEventoTiempoReal.notificacion,
          notificacionId: avisoId,
        ),
    };
  }
}

int? _entero(Object? valor) {
  if (valor is num) return valor.toInt();
  return int.tryParse('$valor');
}

String? _texto(Object? valor) {
  if (valor == null) return null;
  final texto = '$valor'.trim();
  return texto.isEmpty ? null : texto;
}

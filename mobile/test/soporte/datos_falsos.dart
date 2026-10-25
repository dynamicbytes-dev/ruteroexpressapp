/// Datos con la misma forma que devuelve el backend, para las pruebas.
library;

import 'package:motoruta/core/storage/token_storage.dart';

/// Token en memoria: reemplaza al llavero del sistema en las pruebas.
class TokenMemoria implements TokenStorage {
  TokenMemoria([this.token]);

  String? token;

  @override
  Future<String?> leer() async => token;

  @override
  Future<void> guardar(String valor) async => token = valor;

  @override
  Future<void> borrar() async => token = null;
}

/// Un documento de la lista de verificación.
Map<String, dynamic> documentoJson(
  String tipo, {
  String? estado,
  String? etiqueta,
  String? motivoRechazo,
  String? venceEn,
  bool vencido = false,
}) {
  return <String, dynamic>{
    'type': tipo,
    'label': etiqueta ?? _etiquetas[tipo] ?? tipo,
    'hint': 'Pista de $tipo.',
    'uploaded': estado != null,
    'status': estado,
    'status_label': _etiquetasEstado[estado] ?? 'Falta subirlo',
    'rejection_reason': motivoRechazo,
    'uploaded_at': estado == null ? null : '2026-10-18T23:30:12-04:00',
    'expires_at': venceEn,
    'expired': vencido,
  };
}

/// Una persona, como la devuelve `UserResource`.
Map<String, dynamic> usuarioJson({
  int id = 7,
  String rol = 'driver',
  String estado = 'pending',
  String etiquetaEstado = 'En espera de verificación',
  String? motivoEstado,
  bool puedeOperar = false,
  bool correoVerificado = false,
  List<Map<String, dynamic>> documentos = const <Map<String, dynamic>>[],
  int? requeridos,
  List<String> faltantes = const <String>[],
  String? fotoUrl,
  String correo = 'pedro.rojas@gmail.com',
  String cedula = '14988776',
}) {
  final totalRequeridos = requeridos ?? (rol == 'driver' ? 6 : 2);
  final aprobados = documentos.where((d) => d['status'] == 'approved').length;
  final pendientes = documentos.where((d) => d['status'] == 'pending').length;
  final rechazados = documentos.where((d) => d['status'] == 'rejected').length;

  // Como en el backend: `uploaded` cuenta documentos de verdad subidos, no los
  // que aparecen en la lista como "falta subirlo".
  final subidos = documentos.where((d) => d['uploaded'] == true).length;

  return <String, dynamic>{
    'id': id,
    'role': rol,
    'role_label': rol == 'driver' ? 'Conductor' : 'Pasajero',
    'first_name': 'Pedro',
    'last_name': 'Rojas',
    'full_name': 'Pedro Rojas',
    'national_id': cedula,
    'email': correo,
    'phone': '04264567890',
    'address': 'Calle Sucre, casa 45, Maracay',
    'status': estado,
    'status_label': etiquetaEstado,
    'status_reason': motivoEstado,
    'can_operate': puedeOperar,
    'email_verified': correoVerificado,
    'photo_url': fotoUrl,
    'submitted_at':
        subidos >= totalRequeridos ? '2026-10-18T23:30:12-04:00' : null,
    'approved_at': puedeOperar ? '2026-10-19T09:00:00-04:00' : null,
    'created_at': '2026-10-18T23:12:02-04:00',
    'verification': <String, dynamic>{
      'required': totalRequeridos,
      'uploaded': subidos,
      'approved': aprobados,
      'pending': pendientes,
      'rejected': rechazados,
      'missing_labels': faltantes,
      'items': documentos,
    },
    if (rol == 'driver')
      'driver_profile': <String, dynamic>{
        'vehicle_type': 'moto',
        'vehicle_type_label': 'Moto',
        'vehicle_brand': 'Bera',
        'vehicle_model': 'BR 150',
        'vehicle_year': 2022,
        'vehicle_plate': 'AB123CD',
        'vehicle_description': 'Moto · Bera BR 150 2022 · AB123CD',
        'is_online': false,
      },
  };
}


/// Envoltura de `GET /api/perfil`.
Map<String, dynamic> perfilJson({
  required Map<String, dynamic> usuario,
  required String paso,
  String? mensaje,
}) {
  return <String, dynamic>{
    'data': <String, dynamic>{
      'user': usuario,
      'next_step': <String, dynamic>{'key': paso, 'message': mensaje},
    },
  };
}

/// Envoltura de `GET /api/billetera`.
Map<String, dynamic> billeteraJson({
  double saldo = 50,
  double retenido = 0,
  double? tasa = 40,
  double? equivalenteLocal,
  List<Map<String, dynamic>> movimientos = const <Map<String, dynamic>>[],
}) {
  return <String, dynamic>{
    'data': <String, dynamic>{
      'balance': saldo,
      'held': retenido,
      'available': saldo - retenido,
      'currency': 'USD',
      'symbol': r'$',
      'local_currency': 'VES',
      'local_symbol': 'Bs',
      'exchange_rate': tasa == null
          ? null
          : <String, dynamic>{
              'rate': tasa,
              'effective_date': '2026-10-19',
              'local_equivalent': equivalenteLocal ?? saldo * tasa,
            },
      'limits': <String, dynamic>{
        'min_withdrawal_usd': 1,
        'negative_cap_usd': -5,
      },
      'bank': <String, dynamic>{
        'bank': 'Banesco',
        'account': '0134 0000 0000 0000 0000',
        'holder': 'GAZ C.A.',
        'rif': 'J-000000000',
        'instructions': 'Transfiere y sube el comprobante.',
      },
      'recent_transactions': movimientos,
    },
  };
}

/// Una recarga de `GET /api/recargas` o de `data.recharge`.
Map<String, dynamic> recargaJson({
  int id = 9,
  double montoBs = 4000,
  String estado = 'pending',
  String etiqueta = 'Por revisar',
  double? tasa,
  double? montoUsd,
  String? motivoRechazo,
}) {
  return <String, dynamic>{
    'id': id,
    'amount_bs': montoBs,
    'exchange_rate': tasa,
    'amount_usd': montoUsd,
    'bank': 'Banesco',
    'reference': '123456789',
    'transferred_at': '2026-10-19',
    'status': estado,
    'status_label': etiqueta,
    'rejection_reason': motivoRechazo,
    'reviewed_at': estado == 'pending' ? null : '2026-10-19T12:00:00-04:00',
    'created_at': '2026-10-19T11:00:00-04:00',
  };
}

/// Un retiro de `GET /api/retiros` o de `data.withdrawal`.
Map<String, dynamic> retiroJson({
  int id = 4,
  double montoUsd = 10,
  String estado = 'pending',
  String etiqueta = 'En proceso',
  String? motivoRechazo,
}) {
  return <String, dynamic>{
    'id': id,
    'amount_usd': montoUsd,
    'bank': 'Banesco',
    'account_number': '01340000000000000000',
    'account_holder': 'Pedro Rojas',
    'account_id': '14988776',
    'status': estado,
    'status_label': etiqueta,
    'rejection_reason': motivoRechazo,
    'reviewed_at': estado == 'pending' ? null : '2026-10-19T12:00:00-04:00',
    'created_at': '2026-10-19T11:00:00-04:00',
  };
}

const Map<String, String> _etiquetas = <String, String>{
  'cedula': 'Foto de la cédula',
  'selfie': 'Foto selfie',
  'licencia_conducir': 'Licencia de conducir',
  'carnet_circulacion': 'Carnet de circulación',
  'rcv': 'RCV (seguro del vehículo)',
  'foto_vehiculo': 'Foto del vehículo',
};

const Map<String, String> _etiquetasEstado = <String, String>{
  'pending': 'En revisión',
  'approved': 'Aprobado',
  'rejected': 'Rechazado',
};

/// Un viaje como lo devuelve `TripResource`.
Map<String, dynamic> viajeJson({
  int id = 12,
  String estado = 'offered',
  String etiqueta = 'Alerta enviada',
  String metodoPago = 'wallet',
  bool conConductor = false,
  bool conPasajero = false,
  bool puedeCancelar = true,
  double total = 3.35,
  double comision = 0.34,
  double ganancia = 3.01,
  String origen = 'Av. Francisco de Miranda',
  String destino = 'Plaza Venezuela',
  String tipoVehiculo = 'moto',
  bool favorito = false,
  Map<String, dynamic>? calificacion,
  bool puedoCalificar = false,
}) {
  return <String, dynamic>{
    'id': id,
    'status': estado,
    'status_label': etiqueta,
    'payment_method': metodoPago,
    'payment_method_label': metodoPago == 'wallet' ? 'Billetera' : 'Efectivo',
    'vehicle_type': tipoVehiculo,
    'vehicle_type_label': tipoVehiculo == 'carro' ? 'Carro' : 'Moto',
    'origin': <String, dynamic>{
      'address': origen,
      'lat': 10.4961,
      'lng': -66.8517,
    },
    'destination': <String, dynamic>{
      'address': destino,
      'lat': 10.5003,
      'lng': -66.8428,
    },
    'distance_km': 5.0,
    'duration_minutes': 12,
    'fare': <String, dynamic>{
      'base': 1.0,
      'price_per_km': 0.35,
      'price_per_minute': 0.05,
      'minimum': 1.5,
      'minimum_applied': false,
      'dynamic_multiplier': 1.0,
      'night_surcharge_percent': 0,
      'subtotal': 3.35,
      'vehicle_surcharge': 0,
      'total': total,
      'commission_percent': 10,
      'commission_amount': comision,
      'driver_earning': ganancia,
      'exchange_rate': 40,
      'local_total': total * 40,
      'breakdown': <String, dynamic>{'route_source': 'estimada'},
    },
    'driver': conConductor
        ? <String, dynamic>{
            'id': 8,
            'name': 'Pedro Rojas',
            'phone': '04264567890',
            'rating': 4.8,
            'ratings_count': 21,
            'trips_completed': 21,
            'vehicle': 'Bera BR 150 2022 · AB123CD',
            'is_favorite': favorito,
            'location': <String, dynamic>{'lat': 10.4965, 'lng': -66.8510},
            'location_updated_at': '2026-10-19T12:00:00-04:00',
          }
        : null,
    'passenger': conPasajero
        ? <String, dynamic>{
            'id': 7,
            'name': 'Ana Pérez',
            'phone': '04141234567',
          }
        : null,
    'current_offer': null,
    'rating': calificacion,
    'can_rate': puedoCalificar,
    'can_cancel': puedeCancelar,
    'requested_at': '2026-10-19T12:00:00-04:00',
    'accepted_at': conConductor ? '2026-10-19T12:00:10-04:00' : null,
    'arrived_at': null,
    'started_at': null,
    'completed_at': null,
    'cancelled_at': null,
    'cancelled_by': null,
    'cancellation_reason': null,
  };
}

/// Una alerta de viaje como la devuelve `TripOfferResource`.
Map<String, dynamic> ofertaJson({
  int id = 7,
  int viajeId = 12,
  int segundos = 18,
  String pasajero = 'Ana Pérez',
}) {
  return <String, dynamic>{
    'id': id,
    'status': 'pending',
    'distance_to_pickup_km': 1.2,
    'offered_at': '2026-10-19T12:00:00-04:00',
    'expires_at': '2026-10-19T12:00:20-04:00',
    'seconds_left': segundos,
    'trip': <String, dynamic>{
      'id': viajeId,
      'passenger_name': pasajero,
      'origin': <String, dynamic>{
        'address': 'Av. Francisco de Miranda',
        'lat': 10.4961,
        'lng': -66.8517,
      },
      'destination': <String, dynamic>{
        'address': 'Plaza Venezuela',
        'lat': 10.5003,
        'lng': -66.8428,
      },
      'distance_km': 5.0,
      'duration_minutes': 12,
      'payment_method': 'wallet',
      'payment_method_label': 'Billetera',
      'total': 3.35,
      'driver_earning': 3.01,
      'commission_amount': 0.34,
    },
  };
}

/// Una cotización de `POST /api/viajes/cotizar`.
Map<String, dynamic> cotizacionJson({
  double total = 3.35,
  String tipoVehiculo = 'moto',
  double recargoVehiculo = 0,
}) {
  return <String, dynamic>{
    'distance_km': 5.0,
    'duration_minutes': 12,
    'base_fare': 1.0,
    'price_per_km': 0.35,
    'price_per_minute': 0.05,
    'minimum_fare': 1.5,
    'minimum_applied': false,
    'dynamic_multiplier': 1.0,
    'night_surcharge_percent': 0,
    'night': false,
    'subtotal': total,
    'vehicle_type': tipoVehiculo,
    'vehicle_type_label': tipoVehiculo == 'carro' ? 'Carro' : 'Moto',
    'vehicle_surcharge': recargoVehiculo,
    'total': total,
    'commission_percent': 10,
    'commission_amount': 0.34,
    'driver_earning': 3.01,
    'route_source': 'estimada',
    'exchange_rate': 40,
    'local_total': total * 40,
  };
}

/// Estado del conductor de `GET /api/conductor/estado`.
Map<String, dynamic> estadoConductorJson({
  bool enLinea = false,
  bool disponible = false,
  int rechazos = 0,
  double saldo = 20,
  String? bloqueadoHasta,
  double? calificacion = 4.8,
  int calificaciones = 12,
}) {
  return <String, dynamic>{
    'online': enLinea,
    'available': disponible,
    'blocked_until': bloqueadoHasta,
    'blocked_reason': null,
    'rejections_in_window': rechazos,
    'rejections_before_block': 3,
    'trips_completed': 8,
    'rating': calificacion,
    'ratings_count': calificaciones,
    'balance': saldo,
    'available_balance': saldo,
    'negative_cap': -5,
    'location': <String, dynamic>{'lat': 10.4961, 'lng': -66.8517},
    'location_updated_at': '2026-10-19T12:00:00-04:00',
  };
}

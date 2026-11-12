/// Modelos de la billetera tal como los devuelve el backend (fase F2).
///
/// Reflejan `GET /api/billetera`, `GET /api/recargas`, `GET /api/retiros` y
/// `GET /api/billetera/movimientos`. La app solo muestra los números: el saldo,
/// la tasa y los montos siempre los calcula el servidor.
library;

/// Billetera de la persona autenticada.
class Billetera {
  const Billetera({
    required this.saldo,
    required this.retenido,
    required this.disponible,
    required this.moneda,
    required this.simbolo,
    required this.monedaLocal,
    required this.simboloLocal,
    required this.tasa,
    required this.limites,
    required this.banco,
    required this.movimientosRecientes,
  });

  final double saldo;
  final double retenido;
  final double disponible;
  final String moneda;
  final String simbolo;
  final String monedaLocal;
  final String simboloLocal;
  final TasaCambio? tasa;
  final LimitesBilletera limites;
  final DatosBancarios banco;
  final List<MovimientoBilletera> movimientosRecientes;

  bool get tieneTasa => tasa != null && tasa!.tasa > 0;

  /// Equivalente en bolívares del saldo, según la tasa vigente.
  double? get saldoEnBolivares {
    if (!tieneTasa) return null;
    return tasa!.equivalenteLocal;
  }

  factory Billetera.desdeJson(Map<String, dynamic> json) {
    final datos = json['data'] is Map
        ? Map<String, dynamic>.from(json['data'] as Map)
        : json;

    return Billetera(
      saldo: _decimal(datos['balance']),
      retenido: _decimal(datos['held']),
      disponible: _decimal(datos['available']),
      moneda: _texto(datos['currency']) ?? 'USD',
      simbolo: _texto(datos['symbol']) ?? r'$',
      monedaLocal: _texto(datos['local_currency']) ?? 'VES',
      simboloLocal: _texto(datos['local_symbol']) ?? 'Bs',
      tasa: datos['exchange_rate'] is Map
          ? TasaCambio.desdeJson(
              Map<String, dynamic>.from(datos['exchange_rate'] as Map),
            )
          : null,
      limites: LimitesBilletera.desdeJson(datos['limits']),
      banco: DatosBancarios.desdeJson(datos['bank']),
      movimientosRecientes: _listaMapas(datos['recent_transactions'])
          .map(MovimientoBilletera.desdeJson)
          .toList(),
    );
  }
}

/// Tasa de cambio vigente (bolívares por dólar).
class TasaCambio {
  const TasaCambio({
    required this.tasa,
    required this.equivalenteLocal,
    this.fecha,
    this.actualizadaEn,
  });

  final double tasa;
  final double equivalenteLocal;
  final String? fecha;
  final DateTime? actualizadaEn;

  factory TasaCambio.desdeJson(Map<String, dynamic> json) {
    return TasaCambio(
      tasa: _decimal(json['rate']),
      equivalenteLocal: _decimal(json['local_equivalent']),
      fecha: _texto(json['effective_date']),
      actualizadaEn: _fecha(json['updated_at']),
    );
  }
}

/// Límites operativos de la billetera.
class LimitesBilletera {
  const LimitesBilletera({
    required this.retiroMinimo,
    required this.topeNegativo,
  });

  final double retiroMinimo;
  final double topeNegativo;

  factory LimitesBilletera.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    return LimitesBilletera(
      retiroMinimo: _decimal(json['min_withdrawal_usd']),
      topeNegativo: _decimal(json['negative_cap_usd']),
    );
  }
}

/// Cuenta nacional a la que se transfiere para recargar.
class DatosBancarios {
  const DatosBancarios({
    required this.banco,
    required this.cuenta,
    required this.titular,
    required this.rif,
    required this.instrucciones,
  });

  final String banco;
  final String cuenta;
  final String titular;
  final String rif;
  final String instrucciones;

  bool get completo =>
      banco.isNotEmpty && cuenta.isNotEmpty && titular.isNotEmpty;

  factory DatosBancarios.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    return DatosBancarios(
      banco: _texto(json['bank']) ?? '',
      cuenta: _texto(json['account']) ?? '',
      titular: _texto(json['holder']) ?? '',
      rif: _texto(json['rif']) ?? '',
      instrucciones: _texto(json['instructions']) ?? '',
    );
  }
}

/// Un movimiento del libro mayor de la billetera.
class MovimientoBilletera {
  const MovimientoBilletera({
    required this.id,
    required this.tipo,
    required this.tipoEtiqueta,
    required this.direccion,
    required this.monto,
    required this.montoConSigno,
    required this.saldoDespues,
    this.descripcion,
    this.creadoEn,
  });

  final int id;
  final String tipo;
  final String tipoEtiqueta;
  final String direccion;
  final double monto;
  final double montoConSigno;
  final double saldoDespues;
  final String? descripcion;
  final DateTime? creadoEn;

  bool get esCredito => direccion == 'credit';

  factory MovimientoBilletera.desdeJson(Map<String, dynamic> json) {
    return MovimientoBilletera(
      id: _entero(json['id']),
      tipo: _texto(json['type']) ?? '',
      tipoEtiqueta: _texto(json['type_label']) ?? '',
      direccion: _texto(json['direction']) ?? 'credit',
      monto: _decimal(json['amount']),
      montoConSigno: _decimal(json['signed_amount']),
      saldoDespues: _decimal(json['balance_after']),
      descripcion: _texto(json['description']),
      creadoEn: _fecha(json['created_at']),
    );
  }
}

/// Recarga con comprobante de transferencia.
class Recarga {
  const Recarga({
    required this.id,
    required this.montoBs,
    required this.banco,
    required this.referencia,
    required this.estado,
    required this.estadoEtiqueta,
    this.tasa,
    this.montoUsd,
    this.fechaTransferencia,
    this.motivoRechazo,
    this.revisadaEn,
    this.creadaEn,
  });

  final int id;
  final double montoBs;
  final double? tasa;
  final double? montoUsd;
  final String banco;
  final String referencia;
  final String? fechaTransferencia;
  final String estado;
  final String estadoEtiqueta;
  final String? motivoRechazo;
  final DateTime? revisadaEn;
  final DateTime? creadaEn;

  bool get pendiente => estado == 'pending';
  bool get aprobada => estado == 'approved';
  bool get rechazada => estado == 'rejected';

  factory Recarga.desdeJson(Map<String, dynamic> json) {
    return Recarga(
      id: _entero(json['id']),
      montoBs: _decimal(json['amount_bs']),
      tasa: json['exchange_rate'] == null
          ? null
          : _decimal(json['exchange_rate']),
      montoUsd:
          json['amount_usd'] == null ? null : _decimal(json['amount_usd']),
      banco: _texto(json['bank']) ?? '',
      referencia: _texto(json['reference']) ?? '',
      fechaTransferencia: _texto(json['transferred_at']),
      estado: _texto(json['status']) ?? 'pending',
      estadoEtiqueta: _texto(json['status_label']) ?? '',
      motivoRechazo: _texto(json['rejection_reason']),
      revisadaEn: _fecha(json['reviewed_at']),
      creadaEn: _fecha(json['created_at']),
    );
  }
}

/// Retiro de ganancias solicitado por un conductor.
class Retiro {
  const Retiro({
    required this.id,
    required this.montoUsd,
    required this.banco,
    required this.numeroCuenta,
    required this.titular,
    required this.estado,
    required this.estadoEtiqueta,
    this.cedulaTitular,
    this.motivoRechazo,
    this.revisadoEn,
    this.creadoEn,
  });

  final int id;
  final double montoUsd;
  final String banco;
  final String numeroCuenta;
  final String titular;
  final String? cedulaTitular;
  final String estado;
  final String estadoEtiqueta;
  final String? motivoRechazo;
  final DateTime? revisadoEn;
  final DateTime? creadoEn;

  bool get pendiente => estado == 'pending';
  bool get pagado => estado == 'paid';
  bool get rechazado => estado == 'rejected';

  factory Retiro.desdeJson(Map<String, dynamic> json) {
    return Retiro(
      id: _entero(json['id']),
      montoUsd: _decimal(json['amount_usd']),
      banco: _texto(json['bank']) ?? '',
      numeroCuenta: _texto(json['account_number']) ?? '',
      titular: _texto(json['account_holder']) ?? '',
      cedulaTitular: _texto(json['account_id']),
      estado: _texto(json['status']) ?? 'pending',
      estadoEtiqueta: _texto(json['status_label']) ?? '',
      motivoRechazo: _texto(json['rejection_reason']),
      revisadoEn: _fecha(json['reviewed_at']),
      creadoEn: _fecha(json['created_at']),
    );
  }
}

/// Listas paginadas de Laravel: `{ "data": [ ... ] }`.
List<Map<String, dynamic>> listaDeRespuesta(Map<String, dynamic> json) {
  final datos = json['data'];
  return datos is List ? _listaMapas(datos) : const <Map<String, dynamic>>[];
}

String? textoDeRespuesta(Map<String, dynamic> json) =>
    json['message'] is String ? json['message'] as String : null;

// ---------------------------------------------------------------------------
// Lectura defensiva del JSON
// ---------------------------------------------------------------------------

List<Map<String, dynamic>> _listaMapas(Object? valor) {
  if (valor is! List) return const <Map<String, dynamic>>[];

  return valor
      .whereType<Map>()
      .map((cada) => Map<String, dynamic>.from(cada))
      .toList();
}

String? _texto(Object? valor) {
  if (valor == null) return null;
  final texto = '$valor'.trim();
  return texto.isEmpty ? null : texto;
}

int _entero(Object? valor) {
  if (valor is num) return valor.toInt();
  return int.tryParse('$valor') ?? 0;
}

double _decimal(Object? valor) {
  if (valor is num) return valor.toDouble();
  return double.tryParse('$valor') ?? 0;
}

DateTime? _fecha(Object? valor) {
  if (valor is! String || valor.isEmpty) return null;
  return DateTime.tryParse(valor);
}

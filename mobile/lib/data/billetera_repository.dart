import '../core/api/api_client.dart';
import 'models/billetera.dart';

/// Resultado de pedir una recarga o un retiro.
class ResultadoRecarga {
  const ResultadoRecarga({required this.recarga, this.mensaje});

  final Recarga recarga;
  final String? mensaje;
}

class ResultadoRetiro {
  const ResultadoRetiro({required this.retiro, this.mensaje});

  final Retiro retiro;
  final String? mensaje;
}

/// Llamadas de la billetera a la API. Igual que [CuentaRepository], aquí se
/// traduce el JSON a modelos y ningún widget toca URLs.
class BilleteraRepository {
  BilleteraRepository(this._api);

  final ApiClient _api;

  Future<Billetera> billetera() async {
    final respuesta = await _api.get('/api/billetera');
    return Billetera.desdeJson(respuesta);
  }

  Future<List<MovimientoBilletera>> movimientos() async {
    final respuesta = await _api.get('/api/billetera/movimientos');
    return listaDeRespuesta(respuesta)
        .map(MovimientoBilletera.desdeJson)
        .toList();
  }

  Future<List<Recarga>> recargas() async {
    final respuesta = await _api.get('/api/recargas');
    return listaDeRespuesta(respuesta).map(Recarga.desdeJson).toList();
  }

  Future<List<Retiro>> retiros() async {
    final respuesta = await _api.get('/api/retiros');
    return listaDeRespuesta(respuesta).map(Retiro.desdeJson).toList();
  }

  /// Sube el comprobante y registra la recarga. La tasa se aplica cuando el
  /// auditor la aprueba, no aquí.
  Future<ResultadoRecarga> solicitarRecarga({
    required double montoBs,
    required String banco,
    required String referencia,
    required DateTime fechaTransferencia,
    required ArchivoSubida comprobante,
    void Function(int enviado, int total)? alProgresar,
  }) async {
    final respuesta = await _api.subir(
      '/api/recargas',
      nombreCampo: 'comprobante',
      archivo: comprobante,
      campos: <String, String>{
        'amount_bs': montoBs.toStringAsFixed(2),
        'bank': banco,
        'reference': referencia,
        'transferred_at': _fechaApi(fechaTransferencia),
      },
      alProgresar: alProgresar,
    );

    final datos = respuesta['data'];
    final recarga = datos is Map && datos['recharge'] is Map
        ? Recarga.desdeJson(
            Map<String, dynamic>.from(datos['recharge'] as Map),
          )
        : null;

    if (recarga == null) {
      throw StateError('La API no devolvió la recarga creada.');
    }

    return ResultadoRecarga(
      recarga: recarga,
      mensaje: textoDeRespuesta(respuesta),
    );
  }

  Future<ResultadoRetiro> solicitarRetiro({
    required double montoUsd,
    required String banco,
    required String numeroCuenta,
    required String titular,
    String? cedulaTitular,
  }) async {
    final respuesta = await _api.post(
      '/api/retiros',
      datos: <String, dynamic>{
        'amount_usd': montoUsd.toStringAsFixed(2),
        'bank': banco,
        'account_number': numeroCuenta,
        'account_holder': titular,
        if (cedulaTitular != null && cedulaTitular.isNotEmpty)
          'account_id': cedulaTitular,
      },
    );

    final datos = respuesta['data'];
    final retiro = datos is Map && datos['withdrawal'] is Map
        ? Retiro.desdeJson(
            Map<String, dynamic>.from(datos['withdrawal'] as Map),
          )
        : null;

    if (retiro == null) {
      throw StateError('La API no devolvió el retiro creado.');
    }

    return ResultadoRetiro(
      retiro: retiro,
      mensaje: textoDeRespuesta(respuesta),
    );
  }

  static String _fechaApi(DateTime fecha) {
    final mes = fecha.month.toString().padLeft(2, '0');
    final dia = fecha.day.toString().padLeft(2, '0');
    return '${fecha.year}-$mes-$dia';
  }
}

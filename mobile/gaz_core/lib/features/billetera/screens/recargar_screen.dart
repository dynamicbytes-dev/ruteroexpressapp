import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../core/ui/campos.dart';
import '../../../core/ui/mensajes.dart';
import '../../../data/models/billetera.dart';
import '../../sesion/sesion_controller.dart';
import '../billetera_ui.dart';

/// Formulario de recarga con comprobante + historial de recargas.
class RecargarScreen extends StatefulWidget {
  const RecargarScreen({super.key});

  @override
  State<RecargarScreen> createState() => _RecargarScreenState();
}

class _RecargarScreenState extends State<RecargarScreen> {
  final TextEditingController _monto = TextEditingController();
  final TextEditingController _banco = TextEditingController();
  final TextEditingController _referencia = TextEditingController();

  DateTime _fecha = DateTime.now();
  XFile? _comprobante;

  List<Recarga> _historial = const <Recarga>[];
  bool _cargandoHistorial = true;
  bool _enviando = false;
  double _progreso = 0;
  String? _errorGeneral;
  final Map<String, String> _errores = <String, String>{};

  @override
  void initState() {
    super.initState();
    _cargarHistorial();
  }

  @override
  void dispose() {
    _monto.dispose();
    _banco.dispose();
    _referencia.dispose();
    super.dispose();
  }

  Future<void> _cargarHistorial() async {
    try {
      final recargas = await context.read<SesionController>().recargas();
      if (!mounted) return;
      setState(() {
        _historial = recargas;
        _cargandoHistorial = false;
      });
    } on ApiException {
      if (!mounted) return;
      setState(() => _cargandoHistorial = false);
    }
  }

  Future<void> _elegirComprobante() async {
    try {
      final elegido = await ImagePicker().pickImage(
        source: ImageSource.gallery,
        imageQuality: 88,
        maxWidth: 2200,
      );

      if (elegido == null) return;

      final bytes = await elegido.readAsBytes();

      if (bytes.length > 6 * 1024 * 1024) {
        if (!mounted) return;
        mostrarMensaje(context, 'El comprobante pesa más de 6 MB.',
            error: true);
        return;
      }

      if (!mounted) return;
      setState(() {
        _comprobante = elegido;
        _errores.remove('comprobante');
      });
    } catch (_) {
      if (!mounted) return;
      mostrarMensaje(context, 'No pudimos abrir la galería.', error: true);
    }
  }

  Future<void> _enviar() async {
    final sesion = context.read<SesionController>();
    final monto = leerDecimal(_monto.text);

    final errores = <String, String>{};

    if (monto == null || monto <= 0) {
      errores['amount_bs'] = 'Escribe el monto que transferiste en bolívares.';
    }
    if (_banco.text.trim().isEmpty) {
      errores['bank'] =
          'Indica el banco desde el que hiciste la transferencia.';
    }
    if (_referencia.text.trim().length < 3) {
      errores['reference'] =
          'Escribe el número de referencia de la transferencia.';
    }
    if (_fecha.isAfter(DateTime.now())) {
      errores['transferred_at'] = 'La fecha no puede estar en el futuro.';
    }
    if (_comprobante == null) {
      errores['comprobante'] = 'Adjunta la foto del comprobante.';
    }

    if (errores.isNotEmpty) {
      setState(() {
        _errores
          ..clear()
          ..addAll(errores);
        _errorGeneral = null;
      });
      return;
    }

    final bytes = await _comprobante!.readAsBytes();

    setState(() {
      _enviando = true;
      _progreso = 0;
      _errores.clear();
      _errorGeneral = null;
    });

    try {
      final resultado = await sesion.recargar(
        montoBs: monto!,
        banco: _banco.text.trim(),
        referencia: _referencia.text.trim(),
        fechaTransferencia: _fecha,
        comprobante: ArchivoSubida.desdeBytes(
          nombre: _comprobante!.name,
          bytes: bytes,
        ),
        alProgresar: (enviado, total) {
          if (!mounted || total <= 0) return;
          setState(() => _progreso = enviado / total);
        },
      );

      if (!mounted) return;

      _monto.clear();
      _referencia.clear();
      setState(() {
        _comprobante = null;
        _fecha = DateTime.now();
        _historial = <Recarga>[resultado.recarga, ..._historial];
      });

      mostrarMensaje(
        context,
        resultado.mensaje ??
            'Recibimos tu recarga. Queda pendiente de revisión.',
      );
    } on ApiException catch (error) {
      if (!mounted) return;

      setState(() {
        _errorGeneral = error.errorGeneral;
        for (final campo in <String>[
          'amount_bs',
          'bank',
          'reference',
          'transferred_at',
          'comprobante',
        ]) {
          final mensaje = error.errorDe(campo);
          if (mensaje != null) _errores[campo] = mensaje;
        }
      });

      if (error.errorGeneral == null && _errores.isEmpty) {
        mostrarMensaje(context, error.mensaje, error: true);
      }
    } finally {
      if (mounted) {
        setState(() {
          _enviando = false;
          _progreso = 0;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionController>();
    final billetera = sesion.billetera;
    final banco = billetera?.banco;

    return Scaffold(
      appBar: AppBar(title: const Text('Recargar billetera')),
      body: RefreshIndicator(
        onRefresh: () async {
          await sesion.cargarBilletera();
          await _cargarHistorial();
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
          children: <Widget>[
            if (billetera != null)
              Tarjeta(
                child: Row(
                  children: <Widget>[
                    const Icon(Icons.account_balance_wallet_outlined,
                        color: MotorutaColors.brand700),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Saldo disponible: ${montoUsd(billetera.disponible)}',
                        style: TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w600,
                          color: context.gaz.text,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            if (billetera != null) const SizedBox(height: 16),
            if (banco != null && banco.completo) ...<Widget>[
              _TarjetaBanco(banco: banco),
              const SizedBox(height: 18),
            ],
            Tarjeta(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Text('Datos de tu transferencia',
                      style: MotorutaTheme.tituloDe(context, tamano: 19)),
                  const SizedBox(height: 6),
                  Text(
                    'Haz la transferencia o pago móvil a la cuenta indicada y registra aquí los datos. El saldo se acredita cuando el equipo verifica el comprobante.',
                    style: TextStyle(
                      fontSize: 14,
                      height: 1.45,
                      color: context.gaz.text2,
                    ),
                  ),
                  const SizedBox(height: 18),
                  CampoTexto(
                    controlador: _monto,
                    etiqueta: 'Monto transferido (Bs)',
                    prefijo: 'Bs ',
                    pista: '4.000,00',
                    tipoTeclado: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    error: _errores['amount_bs'],
                    habilitado: !_enviando,
                  ),
                  const SizedBox(height: 14),
                  CampoTexto(
                    controlador: _banco,
                    etiqueta: 'Banco de origen',
                    pista: 'Banesco, Mercantil, Provincial...',
                    icono: Icons.account_balance_outlined,
                    error: _errores['bank'],
                    habilitado: !_enviando,
                  ),
                  const SizedBox(height: 14),
                  CampoTexto(
                    controlador: _referencia,
                    etiqueta: 'Número de referencia',
                    pista: 'Como aparece en el comprobante',
                    icono: Icons.tag_rounded,
                    error: _errores['reference'],
                    habilitado: !_enviando,
                  ),
                  const SizedBox(height: 14),
                  CampoFecha(
                    etiqueta: 'Fecha de la transferencia',
                    valor: _fecha,
                    primerDia:
                        DateTime.now().subtract(const Duration(days: 365)),
                    ultimoDia: DateTime.now(),
                    error: _errores['transferred_at'],
                    alElegir: (fecha) => setState(() => _fecha = fecha),
                  ),
                  const SizedBox(height: 18),
                  _SelectorComprobante(
                    archivo: _comprobante,
                    error: _errores['comprobante'],
                    habilitado: !_enviando,
                    alElegir: _elegirComprobante,
                  ),
                  if (_errorGeneral != null) ...<Widget>[
                    const SizedBox(height: 14),
                    Aviso(tono: Tono.error, texto: _errorGeneral!),
                  ],
                  if (_enviando) ...<Widget>[
                    const SizedBox(height: 18),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(999),
                      child: LinearProgressIndicator(
                        value: _progreso == 0 ? null : _progreso,
                        minHeight: 8,
                        backgroundColor: context.gaz.border,
                        color: MotorutaColors.brand700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _progreso == 0
                          ? 'Enviando...'
                          : 'Enviando ${(_progreso * 100).round()}%',
                      style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600,
                        color: MotorutaColors.brand700,
                      ),
                    ),
                  ] else ...<Widget>[
                    const SizedBox(height: 20),
                    BotonPrincipal(
                      texto: 'Enviar recarga',
                      icono: Icons.upload_rounded,
                      alPresionar: _enviar,
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 26),
            Text('Tus recargas',
                style: MotorutaTheme.tituloDe(context, tamano: 19)),
            const SizedBox(height: 12),
            if (_cargandoHistorial)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(20),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (_historial.isEmpty)
              const Aviso(
                tono: Tono.neutro,
                texto: 'Todavía no has hecho ninguna recarga.',
              )
            else
              for (final recarga in _historial) ...<Widget>[
                _TarjetaRecarga(recarga: recarga),
                const SizedBox(height: 12),
              ],
          ],
        ),
      ),
    );
  }
}

/// Cuenta nacional a la que se transfiere.
class _TarjetaBanco extends StatelessWidget {
  const _TarjetaBanco({required this.banco});

  final DatosBancarios banco;

  @override
  Widget build(BuildContext context) {
    return Tarjeta(
      color: MotorutaColors.brand50,
      borde: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(Icons.account_balance_rounded,
                  size: 20, color: MotorutaColors.brand800),
              const SizedBox(width: 8),
              Text('Transfiere a',
                  style: MotorutaTheme.tituloDe(context, tamano: 16)),
            ],
          ),
          const SizedBox(height: 12),
          DatoFila(etiqueta: 'Banco', valor: banco.banco),
          DatoFila(etiqueta: 'Cuenta', valor: banco.cuenta),
          DatoFila(etiqueta: 'Titular', valor: banco.titular),
          if (banco.rif.isNotEmpty) DatoFila(etiqueta: 'RIF', valor: banco.rif),
          if (banco.instrucciones.isNotEmpty) ...<Widget>[
            const SizedBox(height: 8),
            Text(
              banco.instrucciones,
              style: TextStyle(
                fontSize: 13,
                height: 1.4,
                color: context.gaz.text2,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Botón para adjuntar la foto del comprobante.
class _SelectorComprobante extends StatelessWidget {
  const _SelectorComprobante({
    required this.archivo,
    required this.error,
    required this.habilitado,
    required this.alElegir,
  });

  final XFile? archivo;
  final String? error;
  final bool habilitado;
  final VoidCallback alElegir;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        BotonSecundario(
          texto:
              archivo == null ? 'Adjuntar comprobante' : 'Cambiar comprobante',
          icono: archivo == null
              ? Icons.add_photo_alternate_outlined
              : Icons.sync_rounded,
          alPresionar: habilitado ? alElegir : null,
        ),
        if (archivo != null) ...<Widget>[
          const SizedBox(height: 8),
          Row(
            children: <Widget>[
              const Icon(Icons.check_circle_outline_rounded,
                  size: 18, color: MotorutaColors.verde),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  archivo!.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 13,
                    color: context.gaz.text2,
                  ),
                ),
              ),
            ],
          ),
        ],
        if (error != null) ...<Widget>[
          const SizedBox(height: 6),
          Text(
            error!,
            style: const TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: MotorutaColors.rojo,
            ),
          ),
        ],
      ],
    );
  }
}

/// Una recarga del historial, con su estado y motivo si fue rechazada.
class _TarjetaRecarga extends StatelessWidget {
  const _TarjetaRecarga({required this.recarga});

  final Recarga recarga;

  @override
  Widget build(BuildContext context) {
    return Tarjeta(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      montoBs(recarga.montoBs),
                      style: MotorutaTheme.tituloDe(context, tamano: 18),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${recarga.banco} · Ref. ${recarga.referencia}',
                      style: TextStyle(
                        fontSize: 13,
                        color: context.gaz.text2,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              etiquetaFinanciera(recarga.estado, recarga.estadoEtiqueta),
            ],
          ),
          if (recarga.aprobada && recarga.montoUsd != null) ...<Widget>[
            const SizedBox(height: 10),
            Text(
              'Se acreditaron ${montoUsd(recarga.montoUsd!)}'
              '${recarga.tasa == null ? '' : ' · tasa ${recarga.tasa}'}',
              style: const TextStyle(
                fontSize: 13.5,
                fontWeight: FontWeight.w600,
                color: MotorutaColors.verde,
              ),
            ),
          ],
          if (recarga.rechazada && recarga.motivoRechazo != null) ...<Widget>[
            const SizedBox(height: 10),
            Aviso(
              tono: Tono.error,
              titulo: 'Motivo del rechazo',
              texto: recarga.motivoRechazo!,
            ),
          ],
          const SizedBox(height: 8),
          Text(
            fechaConHora(recarga.creadaEn),
            style: TextStyle(
              fontSize: 12,
              color: context.gaz.text3,
            ),
          ),
        ],
      ),
    );
  }
}

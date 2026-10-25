import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

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

/// Solicitud de retiro de ganancias + historial de retiros.
class RetirarScreen extends StatefulWidget {
  const RetirarScreen({super.key});

  @override
  State<RetirarScreen> createState() => _RetirarScreenState();
}

class _RetirarScreenState extends State<RetirarScreen> {
  final TextEditingController _monto = TextEditingController();
  final TextEditingController _banco = TextEditingController();
  final TextEditingController _cuenta = TextEditingController();
  final TextEditingController _titular = TextEditingController();
  final TextEditingController _cedula = TextEditingController();

  List<Retiro> _historial = const <Retiro>[];
  bool _cargandoHistorial = true;
  bool _enviando = false;
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
    _cuenta.dispose();
    _titular.dispose();
    _cedula.dispose();
    super.dispose();
  }

  Future<void> _cargarHistorial() async {
    try {
      final retiros = await context.read<SesionController>().retiros();
      if (!mounted) return;
      setState(() {
        _historial = retiros;
        _cargandoHistorial = false;
      });
    } on ApiException {
      if (!mounted) return;
      setState(() => _cargandoHistorial = false);
    }
  }

  Future<void> _enviar() async {
    final sesion = context.read<SesionController>();
    final billetera = sesion.billetera;
    final monto = leerDecimal(_monto.text);
    final disponible = billetera?.disponible ?? 0;
    final minimo = billetera?.limites.retiroMinimo ?? 1;

    final errores = <String, String>{};

    if (monto == null || monto <= 0) {
      errores['amount_usd'] = 'Escribe cuánto quieres retirar.';
    } else if (monto < minimo) {
      errores['amount_usd'] = 'El retiro mínimo es ${montoUsd(minimo)}.';
    } else if (monto > disponible) {
      errores['amount_usd'] =
          'Solo tienes ${montoUsd(disponible)} disponibles.';
    }

    if (_banco.text.trim().isEmpty) {
      errores['bank'] = 'Indica el banco de tu cuenta.';
    }
    if (_cuenta.text.trim().length < 4) {
      errores['account_number'] = 'Escribe el número de cuenta completo.';
    }
    if (_titular.text.trim().length < 3) {
      errores['account_holder'] = 'Escribe el nombre del titular.';
    }
    if (_cedula.text.trim().isNotEmpty &&
        !RegExp(r'^\d{6,9}$').hasMatch(_cedula.text.trim())) {
      errores['account_id'] = 'La cédula debe tener entre 6 y 9 dígitos.';
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

    setState(() {
      _enviando = true;
      _errores.clear();
      _errorGeneral = null;
    });

    try {
      final resultado = await sesion.retirar(
        montoUsd: monto!,
        banco: _banco.text.trim(),
        numeroCuenta: _cuenta.text.trim(),
        titular: _titular.text.trim(),
        cedulaTitular: _cedula.text.trim().isEmpty ? null : _cedula.text.trim(),
      );

      if (!mounted) return;

      _monto.clear();
      setState(() {
        _historial = <Retiro>[resultado.retiro, ..._historial];
      });

      mostrarMensaje(
        context,
        resultado.mensaje ??
            'Recibimos tu solicitud. El monto quedó retenido mientras se procesa.',
      );
    } on ApiException catch (error) {
      if (!mounted) return;

      setState(() {
        _errorGeneral = error.errorGeneral;
        for (final campo in <String>[
          'amount_usd',
          'bank',
          'account_number',
          'account_holder',
          'account_id',
        ]) {
          final mensaje = error.errorDe(campo);
          if (mensaje != null) _errores[campo] = mensaje;
        }
      });

      if (error.errorGeneral == null && _errores.isEmpty) {
        mostrarMensaje(context, error.mensaje, error: true);
      }
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionController>();
    final billetera = sesion.billetera;

    return Scaffold(
      appBar: AppBar(title: const Text('Retirar ganancias')),
      body: RefreshIndicator(
        onRefresh: () async {
          await sesion.cargarBilletera();
          await _cargarHistorial();
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
          children: <Widget>[
            if (billetera != null)
              _ResumenDisponible(
                disponible: billetera.disponible,
                retenido: billetera.retenido,
                minimo: billetera.limites.retiroMinimo,
                alRetirarTodo: () => setState(() {
                  _monto.text = billetera.disponible.toStringAsFixed(2);
                }),
              ),
            if (billetera != null) const SizedBox(height: 18),
            Tarjeta(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Text('Cuenta destino',
                      style: MotorutaTheme.titulo(tamano: 19)),
                  const SizedBox(height: 6),
                  const Text(
                    'El equipo transfiere a esta cuenta y sube el comprobante. Mientras se procesa, el monto queda retenido en tu billetera.',
                    style: TextStyle(
                      fontSize: 14,
                      height: 1.45,
                      color: MotorutaColors.ink600,
                    ),
                  ),
                  const SizedBox(height: 18),
                  CampoTexto(
                    controlador: _monto,
                    etiqueta: 'Monto a retirar (USD)',
                    prefijo: r'$ ',
                    pista: '10,00',
                    tipoTeclado: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    error: _errores['amount_usd'],
                    habilitado: !_enviando,
                  ),
                  const SizedBox(height: 14),
                  CampoTexto(
                    controlador: _banco,
                    etiqueta: 'Banco',
                    pista: 'Banesco, Mercantil, Provincial...',
                    icono: Icons.account_balance_outlined,
                    error: _errores['bank'],
                    habilitado: !_enviando,
                  ),
                  const SizedBox(height: 14),
                  CampoTexto(
                    controlador: _cuenta,
                    etiqueta: 'Número de cuenta',
                    pista: '20 dígitos',
                    icono: Icons.numbers_rounded,
                    tipoTeclado: TextInputType.number,
                    error: _errores['account_number'],
                    habilitado: !_enviando,
                  ),
                  const SizedBox(height: 14),
                  CampoTexto(
                    controlador: _titular,
                    etiqueta: 'Titular de la cuenta',
                    icono: Icons.person_outline_rounded,
                    error: _errores['account_holder'],
                    habilitado: !_enviando,
                  ),
                  const SizedBox(height: 14),
                  CampoTexto(
                    controlador: _cedula,
                    etiqueta: 'Cédula del titular (opcional)',
                    pista: 'Sin puntos',
                    icono: Icons.badge_outlined,
                    tipoTeclado: TextInputType.number,
                    error: _errores['account_id'],
                    habilitado: !_enviando,
                  ),
                  if (_errorGeneral != null) ...<Widget>[
                    const SizedBox(height: 14),
                    Aviso(tono: Tono.error, texto: _errorGeneral!),
                  ],
                  const SizedBox(height: 20),
                  BotonPrincipal(
                    texto: 'Solicitar retiro',
                    icono: Icons.account_balance_wallet_outlined,
                    cargando: _enviando,
                    alPresionar: billetera == null ? null : _enviar,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 26),
            Text('Tus retiros', style: MotorutaTheme.titulo(tamano: 19)),
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
                texto: 'Todavía no has solicitado retiros.',
              )
            else
              for (final retiro in _historial) ...<Widget>[
                _TarjetaRetiro(retiro: retiro),
                const SizedBox(height: 12),
              ],
          ],
        ),
      ),
    );
  }
}

/// Saldo disponible, retenido y atajo para retirar todo.
class _ResumenDisponible extends StatelessWidget {
  const _ResumenDisponible({
    required this.disponible,
    required this.retenido,
    required this.minimo,
    required this.alRetirarTodo,
  });

  final double disponible;
  final double retenido;
  final double minimo;
  final VoidCallback alRetirarTodo;

  @override
  Widget build(BuildContext context) {
    return Tarjeta(
      color: MotorutaColors.brand900,
      borde: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Text(
            'DISPONIBLE PARA RETIRAR',
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.8,
              color: MotorutaColors.cian,
            ),
          ),
          const SizedBox(height: 8),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              Expanded(
                child: Text(
                  montoUsd(disponible),
                  style: MotorutaTheme.titulo(
                    tamano: 34,
                    color: Colors.white,
                  ),
                ),
              ),
              if (disponible > 0)
                TextButton(
                  onPressed: alRetirarTodo,
                  style: TextButton.styleFrom(
                    foregroundColor: Colors.white,
                    backgroundColor: Colors.white.withOpacity(0.12),
                  ),
                  child: const Text('Retirar todo'),
                ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            retenido > 0
                ? 'Retenido en solicitudes en proceso: ${montoUsd(retenido)}'
                : 'Retiro mínimo: ${montoUsd(minimo)}',
            style: const TextStyle(
              fontSize: 12.5,
              color: Colors.white70,
            ),
          ),
        ],
      ),
    );
  }
}

/// Un retiro del historial.
class _TarjetaRetiro extends StatelessWidget {
  const _TarjetaRetiro({required this.retiro});

  final Retiro retiro;

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
                      montoUsd(retiro.montoUsd),
                      style: MotorutaTheme.titulo(tamano: 18),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${retiro.banco} · ${retiro.numeroCuenta}',
                      style: const TextStyle(
                        fontSize: 13,
                        color: MotorutaColors.ink500,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              etiquetaFinanciera(retiro.estado, retiro.estadoEtiqueta),
            ],
          ),
          if (retiro.rechazado && retiro.motivoRechazo != null) ...<Widget>[
            const SizedBox(height: 10),
            Aviso(
              tono: Tono.error,
              titulo: 'Motivo del rechazo',
              texto: retiro.motivoRechazo!,
            ),
          ],
          const SizedBox(height: 8),
          Text(
            fechaConHora(retiro.creadoEn),
            style: const TextStyle(
              fontSize: 12,
              color: MotorutaColors.ink400,
            ),
          ),
        ],
      ),
    );
  }
}

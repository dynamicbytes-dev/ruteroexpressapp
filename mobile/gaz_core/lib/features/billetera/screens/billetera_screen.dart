import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../data/models/billetera.dart';
import '../../sesion/sesion_controller.dart';
import '../billetera_ui.dart';
import 'movimientos_screen.dart';
import 'recargar_screen.dart';
import 'retirar_screen.dart';

/// Pantalla principal de la Billetera para consultar saldo, recargar y
/// gestionar los movimientos y transacciones financieras.
class BilleteraScreen extends StatefulWidget {
  const BilleteraScreen({
    super.key,
    this.mostrarAppBar = true,
  });

  final bool mostrarAppBar;

  @override
  State<BilleteraScreen> createState() => _BilleteraScreenState();
}

class _BilleteraScreenState extends State<BilleteraScreen> {
  List<MovimientoBilletera> _movimientos = const <MovimientoBilletera>[];
  bool _cargandoMovimientos = true;
  String? _errorMovimientos;

  @override
  void initState() {
    super.initState();
    _cargarTodo();
  }

  Future<void> _cargarTodo() async {
    final sesion = context.read<SesionController>();
    if (!sesion.haySesion) return;

    await Future.wait(<Future<void>>[
      sesion.cargarBilletera(silencioso: true),
      _cargarMovimientos(),
    ]);
  }

  Future<void> _cargarMovimientos() async {
    try {
      final lista = await context.read<SesionController>().movimientos();
      if (!mounted) return;
      setState(() {
        _movimientos = lista;
        _errorMovimientos = null;
        _cargandoMovimientos = false;
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _errorMovimientos = error.mensaje;
        _cargandoMovimientos = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _cargandoMovimientos = false);
    }
  }

  void _abrir(Widget pantalla) {
    Navigator.of(context)
        .push(MaterialPageRoute<void>(builder: (_) => pantalla))
        .then((_) => _cargarTodo());
  }

  void _copiarTexto(String etiqueta, String valor) {
    Clipboard.setData(ClipboardData(text: valor));
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('$etiqueta copiado al portapapeles'),
        duration: const Duration(seconds: 2),
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionController>();
    final billetera = sesion.billetera;
    final esConductor = sesion.esConductor;
    final bottomInset = MediaQuery.of(context).padding.bottom;
    final paddingInferior = 110.0 + bottomInset;

    return Scaffold(
      appBar:
          widget.mostrarAppBar ? AppBar(title: const Text('Billetera')) : null,
      body: RefreshIndicator(
        onRefresh: _cargarTodo,
        child: ListView(
          padding: EdgeInsets.fromLTRB(20, 16, 20, paddingInferior),
          children: <Widget>[
            // Tarjeta principal con saldo y tasa BCV
            _TarjetaSaldoBilletera(
              sesion: sesion,
              billetera: billetera,
              esConductor: esConductor,
              alRecargar: () => _abrir(const RecargarScreen()),
              alRetirar: () => _abrir(const RetirarScreen()),
              alVerMovimientos: () => _abrir(const MovimientosScreen()),
            ),
            const SizedBox(height: 18),

            // Acciones financieras rápidas
            Row(
              children: <Widget>[
                Expanded(
                  child: _BotonAccionRapida(
                    icono: Icons.add_card_rounded,
                    colorIcono: MotorutaColors.cian,
                    fondoIcono: MotorutaColors.cian.withOpacity(0.12),
                    titulo: 'Recargar',
                    subtitulo: 'Pago Móvil / Transf.',
                    alPresionar: () => _abrir(const RecargarScreen()),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _BotonAccionRapida(
                    icono: Icons.receipt_long_rounded,
                    colorIcono: MotorutaColors.brand800,
                    fondoIcono: MotorutaColors.accent50,
                    titulo: 'Movimientos',
                    subtitulo: 'Historial completo',
                    alPresionar: () => _abrir(const MovimientosScreen()),
                  ),
                ),
                if (esConductor) ...<Widget>[
                  const SizedBox(width: 12),
                  Expanded(
                    child: _BotonAccionRapida(
                      icono: Icons.account_balance_wallet_outlined,
                      colorIcono: MotorutaColors.verde,
                      fondoIcono: MotorutaColors.verde.withOpacity(0.12),
                      titulo: 'Retirar',
                      subtitulo: 'A tu cuenta',
                      alPresionar: () => _abrir(const RetirarScreen()),
                    ),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 18),

            // Datos de recarga bancaria oficial si están configurados
            if (billetera != null && billetera.banco.completo) ...<Widget>[
              Tarjeta(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: MotorutaColors.brand800.withOpacity(0.08),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Icon(
                            Icons.account_balance_rounded,
                            size: 20,
                            color: MotorutaColors.brand800,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: <Widget>[
                              Text(
                                'Datos para recargas',
                                style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  fontSize: 14.5,
                                  color: context.gaz.text,
                                ),
                              ),
                              Text(
                                'Toca cualquier dato para copiarlo',
                                style: TextStyle(
                                  fontSize: 12,
                                  color: context.gaz.text2,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    _ItemDatoBancario(
                      etiqueta: 'Banco',
                      valor: billetera.banco.banco,
                      alCopiar: () =>
                          _copiarTexto('Banco', billetera.banco.banco),
                    ),
                    const Divider(height: 14),
                    _ItemDatoBancario(
                      etiqueta: 'Cuenta / Teléfono',
                      valor: billetera.banco.cuenta,
                      alCopiar: () => _copiarTexto(
                          'Número de cuenta/teléfono', billetera.banco.cuenta),
                    ),
                    const Divider(height: 14),
                    _ItemDatoBancario(
                      etiqueta: 'Titular',
                      valor: billetera.banco.titular,
                      alCopiar: () =>
                          _copiarTexto('Titular', billetera.banco.titular),
                    ),
                    if (billetera.banco.rif.isNotEmpty) ...<Widget>[
                      const Divider(height: 14),
                      _ItemDatoBancario(
                        etiqueta: 'Cédula / RIF',
                        valor: billetera.banco.rif,
                        alCopiar: () =>
                            _copiarTexto('Cédula/RIF', billetera.banco.rif),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 18),
            ],

            // Encabezado de transacciones recientes
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    'Transacciones recientes',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: context.gaz.text,
                    ),
                  ),
                ),
                if (_movimientos.isNotEmpty)
                  TextButton(
                    onPressed: () => _abrir(const MovimientosScreen()),
                    child: const Text('Ver todas'),
                  ),
              ],
            ),
            const SizedBox(height: 8),

            // Contenido de movimientos
            if (_cargandoMovimientos)
              const Center(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 30),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (_errorMovimientos != null)
              Aviso(tono: Tono.error, texto: _errorMovimientos!)
            else if (_movimientos.isEmpty)
              Tarjeta(
                padding:
                    const EdgeInsets.symmetric(horizontal: 20, vertical: 26),
                child: Column(
                  children: <Widget>[
                    Icon(
                      Icons.receipt_long_outlined,
                      size: 44,
                      color: context.gaz.text3,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Sin movimientos todavía',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: context.gaz.text,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Cuando recargues tu saldo o realices pagos de viajes, cada movimiento aparecerá detallado aquí.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 13,
                        height: 1.35,
                        color: context.gaz.text2,
                      ),
                    ),
                    const SizedBox(height: 16),
                    BotonSecundario(
                      texto: 'Hacer una recarga',
                      icono: Icons.add_card_rounded,
                      alPresionar: () => _abrir(const RecargarScreen()),
                    ),
                  ],
                ),
              )
            else
              ..._movimientos.take(10).map((movimiento) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _TarjetaMovimiento(movimiento: movimiento),
                  )),
          ],
        ),
      ),
    );
  }
}

/// Tarjeta de saldo y cotización
class _TarjetaSaldoBilletera extends StatelessWidget {
  const _TarjetaSaldoBilletera({
    required this.sesion,
    required this.billetera,
    required this.esConductor,
    required this.alRecargar,
    required this.alRetirar,
    required this.alVerMovimientos,
  });

  final SesionController sesion;
  final Billetera? billetera;
  final bool esConductor;
  final VoidCallback alRecargar;
  final VoidCallback alRetirar;
  final VoidCallback alVerMovimientos;

  @override
  Widget build(BuildContext context) {
    return Tarjeta(
      color: MotorutaColors.brand950,
      gradiente: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: <Color>[
          MotorutaColors.brand950,
          MotorutaColors.brand900,
          MotorutaColors.brand800,
        ],
      ),
      borde: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: <Widget>[
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: MotorutaColors.cian.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: MotorutaColors.cian.withOpacity(0.24),
                  ),
                ),
                child: const Text(
                  'BILLETERA DIGITAL',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.9,
                    color: MotorutaColors.cian,
                  ),
                ),
              ),
              const Icon(
                Icons.account_balance_wallet_rounded,
                color: Colors.white38,
                size: 20,
              ),
            ],
          ),
          const SizedBox(height: 14),
          if (billetera == null && sesion.cargandoBilletera) ...<Widget>[
            const SizedBox(height: 10),
            const Center(
              child: SizedBox(
                height: 28,
                width: 28,
                child: CircularProgressIndicator(
                  strokeWidth: 2.5,
                  color: MotorutaColors.cian,
                ),
              ),
            ),
            const SizedBox(height: 14),
          ] else if (billetera == null) ...<Widget>[
            Text(
              sesion.errorBilletera ??
                  'Estamos preparando tu billetera. Desliza hacia abajo para reintentar.',
              style: const TextStyle(
                fontSize: 14,
                height: 1.4,
                color: Colors.white70,
              ),
            ),
            const SizedBox(height: 14),
            BotonSecundario(
              texto: 'Reintentar',
              icono: Icons.refresh_rounded,
              claro: true,
              alPresionar: sesion.cargarBilletera,
            ),
          ] else ...<Widget>[
            Text(
              montoUsd(billetera!.saldo),
              style: MotorutaTheme.tituloDe(
                context,
                tamano: 36,
                color: Colors.white,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              'Disponible ${montoUsd(billetera!.disponible)}'
              '${billetera!.retenido > 0 ? ' · retenido ${montoUsd(billetera!.retenido)}' : ''}',
              style: const TextStyle(
                fontSize: 13,
                color: Colors.white70,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              billetera!.tieneTasa
                  ? '≈ ${montoBs(billetera!.saldoEnBolivares!)} · tasa ${billetera!.tasa!.tasa} Bs/\$'
                  : 'Tasa oficial BCV no disponible por ahora.',
              style: const TextStyle(
                fontSize: 12.5,
                color: Colors.white54,
              ),
            ),
            const SizedBox(height: 18),
            Row(
              children: <Widget>[
                Expanded(
                  child: BotonPrincipal(
                    texto: 'Recargar saldo',
                    icono: Icons.add_card_rounded,
                    claro: true,
                    alPresionar: alRecargar,
                  ),
                ),
                if (esConductor) ...<Widget>[
                  const SizedBox(width: 10),
                  Expanded(
                    child: BotonSecundario(
                      texto: 'Retirar',
                      icono: Icons.account_balance_wallet_outlined,
                      claro: true,
                      alPresionar: alRetirar,
                    ),
                  ),
                ],
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// Botón con estilo de tarjeta para acciones rápidas
class _BotonAccionRapida extends StatelessWidget {
  const _BotonAccionRapida({
    required this.icono,
    required this.colorIcono,
    required this.fondoIcono,
    required this.titulo,
    required this.subtitulo,
    required this.alPresionar,
  });

  final IconData icono;
  final Color colorIcono;
  final Color fondoIcono;
  final String titulo;
  final String subtitulo;
  final VoidCallback alPresionar;

  @override
  Widget build(BuildContext context) {
    return Tarjeta(
      onTap: alPresionar,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: fondoIcono,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icono, size: 20, color: colorIcono),
          ),
          const SizedBox(height: 10),
          Text(
            titulo,
            style: TextStyle(
              fontSize: 14.5,
              fontWeight: FontWeight.w700,
              color: context.gaz.text,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            subtitulo,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 11.5,
              color: context.gaz.text2,
            ),
          ),
        ],
      ),
    );
  }
}

/// Fila para copiar datos bancarios
class _ItemDatoBancario extends StatelessWidget {
  const _ItemDatoBancario({
    required this.etiqueta,
    required this.valor,
    required this.alCopiar,
  });

  final String etiqueta;
  final String valor;
  final VoidCallback alCopiar;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: alCopiar,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: <Widget>[
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    etiqueta,
                    style: TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w500,
                      color: context.gaz.text2,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    valor,
                    style: TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w600,
                      color: context.gaz.text,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.copy_rounded,
              size: 16,
              color: MotorutaColors.brand800,
            ),
          ],
        ),
      ),
    );
  }
}

/// Tarjeta de cada movimiento o transacción
class _TarjetaMovimiento extends StatelessWidget {
  const _TarjetaMovimiento({required this.movimiento});

  final MovimientoBilletera movimiento;

  @override
  Widget build(BuildContext context) {
    final color =
        movimiento.esCredito ? MotorutaColors.verde : MotorutaColors.rojo;

    return Tarjeta(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Container(
            width: 40,
            height: 40,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: color.withOpacity(0.10),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              movimiento.esCredito
                  ? Icons.south_west_rounded
                  : Icons.north_east_rounded,
              size: 20,
              color: color,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  movimiento.tipoEtiqueta.isEmpty
                      ? movimiento.tipo
                      : movimiento.tipoEtiqueta,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: context.gaz.text,
                  ),
                ),
                if (movimiento.descripcion != null) ...<Widget>[
                  const SizedBox(height: 2),
                  Text(
                    movimiento.descripcion!,
                    style: TextStyle(
                      fontSize: 12,
                      height: 1.35,
                      color: context.gaz.text2,
                    ),
                  ),
                ],
                const SizedBox(height: 4),
                Text(
                  fechaConHora(movimiento.creadoEn),
                  style: TextStyle(
                    fontSize: 11.5,
                    color: context.gaz.text3,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              Text(
                '${movimiento.esCredito ? '+' : '−'}${montoUsd(movimiento.monto)}',
                style: TextStyle(
                  fontSize: 14.5,
                  fontWeight: FontWeight.w700,
                  color: color,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Saldo ${montoUsd(movimiento.saldoDespues)}',
                style: TextStyle(
                  fontSize: 11,
                  color: context.gaz.text3,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

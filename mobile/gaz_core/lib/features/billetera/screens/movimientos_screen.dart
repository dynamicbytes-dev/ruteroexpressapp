import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../data/models/billetera.dart';
import '../../sesion/sesion_controller.dart';
import '../billetera_ui.dart';

/// Historial completo de movimientos del libro mayor.
class MovimientosScreen extends StatefulWidget {
  const MovimientosScreen({super.key});

  @override
  State<MovimientosScreen> createState() => _MovimientosScreenState();
}

class _MovimientosScreenState extends State<MovimientosScreen> {
  List<MovimientoBilletera> _movimientos = const <MovimientoBilletera>[];
  bool _cargando = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    try {
      final movimientos = await context.read<SesionController>().movimientos();
      if (!mounted) return;
      setState(() {
        _movimientos = movimientos;
        _error = null;
        _cargando = false;
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.mensaje;
        _cargando = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Movimientos')),
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
          children: <Widget>[
            if (_cargando)
              const Center(
                child: Padding(
                  padding: EdgeInsets.all(40),
                  child: CircularProgressIndicator(),
                ),
              )
            else if (_error != null)
              Aviso(tono: Tono.error, texto: _error!)
            else if (_movimientos.isEmpty)
              const Aviso(
                tono: Tono.neutro,
                titulo: 'Sin movimientos',
                texto:
                    'Cuando recargues, pagues un viaje o retires ganancias, el movimiento aparecerá aquí.',
              )
            else ...<Widget>[
              Text(
                'Tu libro mayor',
                style: MotorutaTheme.tituloDe(context, tamano: 20),
              ),
              const SizedBox(height: 4),
              Text(
                'Cada movimiento queda registrado y no se edita. Si hay una corrección, aparece como un ajuste.',
                style: TextStyle(
                  fontSize: 13.5,
                  height: 1.4,
                  color: context.gaz.text2,
                ),
              ),
              const SizedBox(height: 16),
              for (final movimiento in _movimientos) ...<Widget>[
                _FilaMovimiento(movimiento: movimiento),
                const SizedBox(height: 10),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

class _FilaMovimiento extends StatelessWidget {
  const _FilaMovimiento({required this.movimiento});

  final MovimientoBilletera movimiento;

  @override
  Widget build(BuildContext context) {
    final color =
        movimiento.esCredito ? MotorutaColors.verde : MotorutaColors.rojo;

    return Tarjeta(
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
                    fontSize: 14.5,
                    fontWeight: FontWeight.w600,
                    color: context.gaz.text,
                  ),
                ),
                if (movimiento.descripcion != null) ...<Widget>[
                  const SizedBox(height: 2),
                  Text(
                    movimiento.descripcion!,
                    style: TextStyle(
                      fontSize: 12.5,
                      height: 1.35,
                      color: context.gaz.text2,
                    ),
                  ),
                ],
                const SizedBox(height: 4),
                Text(
                  fechaConHora(movimiento.creadoEn),
                  style: TextStyle(
                    fontSize: 12,
                    color: context.gaz.text3,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: <Widget>[
              Text(
                '${movimiento.esCredito ? '+' : '−'}${montoUsd(movimiento.monto)}',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: color,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Saldo ${montoUsd(movimiento.saldoDespues)}',
                style: TextStyle(
                  fontSize: 11.5,
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

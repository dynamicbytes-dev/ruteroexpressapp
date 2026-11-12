import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../data/models/favorito.dart';
import '../../sesion/sesion_controller.dart';

/// Calificaciones que recibió el conductor, con los comentarios del pasajero.
class CalificacionesConductorScreen extends StatefulWidget {
  const CalificacionesConductorScreen({super.key});

  @override
  State<CalificacionesConductorScreen> createState() =>
      _CalificacionesConductorScreenState();
}

class _CalificacionesConductorScreenState
    extends State<CalificacionesConductorScreen> {
  List<CalificacionRecibida> _calificaciones = const <CalificacionRecibida>[];
  bool _cargando = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    if (mounted) setState(() => _cargando = true);

    try {
      final calificaciones =
          await context.read<SesionController>().calificacionesConductor();

      if (!mounted) return;

      setState(() {
        _calificaciones = calificaciones;
        _error = null;
        _cargando = false;
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.mensaje;
        _cargando = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'No pudimos cargar tus calificaciones.';
        _cargando = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mis calificaciones')),
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 36),
          children: <Widget>[
            if (_cargando)
              const Padding(
                padding: EdgeInsets.only(top: 40),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null) ...<Widget>[
              Aviso(tono: Tono.error, texto: _error!),
              const SizedBox(height: 14),
              BotonSecundario(texto: 'Reintentar', alPresionar: _cargar),
            ] else if (_calificaciones.isEmpty)
              Tarjeta(
                child: Column(
                  children: <Widget>[
                    const Icon(
                      Icons.star_outline_rounded,
                      size: 44,
                      color: MotorutaColors.brand800,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Todavía no tienes calificaciones',
                      textAlign: TextAlign.center,
                      style: MotorutaTheme.tituloDe(context, tamano: 18),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Cuando termines tus primeros viajes, los pasajeros podrán calificarte.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 13.5,
                        height: 1.4,
                        color: context.gaz.text2,
                      ),
                    ),
                  ],
                ),
              )
            else
              ..._calificaciones.map(
                (calificacion) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Tarjeta(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Row(
                          children: <Widget>[
                            Expanded(
                              child: Text(
                                calificacion.textoEstrellas,
                                style: const TextStyle(
                                  fontSize: 20,
                                  letterSpacing: 1.5,
                                  color: MotorutaColors.amarillo,
                                ),
                              ),
                            ),
                            Text(
                              calificacion.fecha == null
                                  ? ''
                                  : '${calificacion.fecha!.day}/${calificacion.fecha!.month}/${calificacion.fecha!.year}',
                              style: TextStyle(
                                fontSize: 11.5,
                                color: context.gaz.text3,
                              ),
                            ),
                          ],
                        ),
                        if (calificacion.comentario != null) ...<Widget>[
                          const SizedBox(height: 8),
                          Text(
                            calificacion.comentario!,
                            style: TextStyle(
                              fontSize: 13.5,
                              height: 1.4,
                              color: context.gaz.text2,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

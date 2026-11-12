import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../data/models/viaje.dart';
import '../../sesion/sesion_controller.dart';

/// Abre la hoja para calificar el viaje recién terminado.
///
/// Devuelve el viaje actualizado (con la calificación y, si se marcó, el
/// conductor favorito) o `null` si la persona cerró sin enviar.
Future<Viaje?> mostrarCalificarViaje(
  BuildContext context, {
  required Viaje viaje,
}) {
  return showModalBottomSheet<Viaje>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => _CalificarViajeSheet(viaje: viaje),
  );
}

class _CalificarViajeSheet extends StatefulWidget {
  const _CalificarViajeSheet({required this.viaje});

  final Viaje viaje;

  @override
  State<_CalificarViajeSheet> createState() => _CalificarViajeSheetState();
}

class _CalificarViajeSheetState extends State<_CalificarViajeSheet> {
  final TextEditingController _comentario = TextEditingController();

  int _estrellas = 0;
  bool _favorito = false;
  bool _enviando = false;
  String? _error;

  @override
  void dispose() {
    _comentario.dispose();
    super.dispose();
  }

  Future<void> _enviar() async {
    if (_estrellas == 0) {
      setState(() => _error = 'Elige de 1 a 5 estrellas.');
      return;
    }

    setState(() {
      _enviando = true;
      _error = null;
    });

    final yaEsFavorito = widget.viaje.conductor?.esFavorito == true;

    try {
      final viaje = await context.read<SesionController>().calificarViaje(
            widget.viaje.id,
            estrellas: _estrellas,
            comentario: _comentario.text,
            favorito: yaEsFavorito ? true : _favorito,
          );

      if (mounted) Navigator.of(context).pop(viaje);
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.mensaje;
          _enviando = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = 'No pudimos guardar la calificación. Inténtalo de nuevo.';
          _enviando = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final conductor = widget.viaje.conductor;
    final alturaTeclado = MediaQuery.viewInsetsOf(context).bottom;

    return Container(
      padding: EdgeInsets.fromLTRB(20, 12, 20, 20 + alturaTeclado),
      decoration: BoxDecoration(
        color: context.gaz.surface,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: context.gaz.borderStrong,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Text(
                '¿Cómo estuvo tu viaje?',
                style: MotorutaTheme.tituloDe(context, tamano: 21),
              ),
              const SizedBox(height: 4),
              Text(
                conductor == null
                    ? 'Tu calificación ayuda a mejorar el servicio.'
                    : 'Califica a ${conductor.nombre}. Tu opinión ayuda a mejorar el servicio.',
                style: TextStyle(
                  fontSize: 13.5,
                  height: 1.4,
                  color: context.gaz.text2,
                ),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List<Widget>.generate(5, (indice) {
                  final valor = indice + 1;
                  final activa = valor <= _estrellas;

                  return IconButton(
                    key: ValueKey<String>('estrella-$valor'),
                    tooltip: '$valor estrella${valor == 1 ? '' : 's'}',
                    onPressed: _enviando
                        ? null
                        : () {
                            HapticFeedback.selectionClick();
                            setState(() {
                              _estrellas = valor;
                              _error = null;
                            });
                          },
                    iconSize: 40,
                    icon: Icon(
                      activa ? Icons.star_rounded : Icons.star_outline_rounded,
                      color:
                          activa ? MotorutaColors.amarillo : context.gaz.text3,
                    ),
                  );
                }),
              ),
              const SizedBox(height: 4),
              TextField(
                controller: _comentario,
                enabled: !_enviando,
                maxLength: 500,
                maxLines: 3,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'Comentario (opcional)',
                  hintText: 'Cuéntanos cómo condujo, si fue puntual…',
                  alignLabelWithHint: true,
                ),
              ),
              if (conductor?.esFavorito == true) ...<Widget>[
                const SizedBox(height: 12),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: MotorutaColors.rojo.withOpacity(0.08),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: MotorutaColors.rojo.withOpacity(0.2),
                    ),
                  ),
                  child: const Row(
                    children: <Widget>[
                      Icon(
                        Icons.favorite_rounded,
                        color: MotorutaColors.rojo,
                        size: 20,
                      ),
                      SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Conductor guardado en tus favoritos',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: MotorutaColors.rojo,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ] else
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _favorito,
                  onChanged: _enviando
                      ? null
                      : (valor) => setState(() => _favorito = valor),
                  title: const Text(
                    'Marcar como conductor favorito',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  subtitle: const Text(
                    'La próxima vez le daremos prioridad si está en línea y cerca.',
                    style: TextStyle(fontSize: 12.5),
                  ),
                ),
              if (_error != null) ...<Widget>[
                const SizedBox(height: 12),
                Aviso(tono: Tono.error, texto: _error!),
              ],
              const SizedBox(height: 16),
              BotonPrincipal(
                texto: 'Enviar calificación',
                icono: Icons.star_rounded,
                cargando: _enviando,
                alPresionar: _enviando ? null : _enviar,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

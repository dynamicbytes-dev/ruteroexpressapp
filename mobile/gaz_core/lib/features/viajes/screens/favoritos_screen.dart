import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../data/models/favorito.dart';
import '../../sesion/sesion_controller.dart';

/// Conductores que el pasajero marcó como favoritos.
///
/// Se les da prioridad al pedir un viaje si están en línea y cerca.
class FavoritosScreen extends StatefulWidget {
  const FavoritosScreen({super.key});

  @override
  State<FavoritosScreen> createState() => _FavoritosScreenState();
}

class _FavoritosScreenState extends State<FavoritosScreen> {
  List<ConductorFavorito> _favoritos = const <ConductorFavorito>[];
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
      final favoritos =
          await context.read<SesionController>().conductoresFavoritos();

      if (!mounted) return;

      setState(() {
        _favoritos = favoritos;
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
        _error = 'No pudimos cargar tus conductores favoritos.';
        _cargando = false;
      });
    }
  }

  Future<void> _quitar(ConductorFavorito conductor) async {
    final confirmado = await showDialog<bool>(
      context: context,
      builder: (contexto) => AlertDialog(
        title: const Text('Quitar de favoritos'),
        content: Text(
          '${conductor.nombre} dejará de tener prioridad cuando pidas un viaje.',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.of(contexto).pop(false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(contexto).pop(true),
            child: const Text('Quitar'),
          ),
        ],
      ),
    );

    if (confirmado != true || !mounted) return;

    try {
      await context
          .read<SesionController>()
          .alternarFavorito(conductor.id, false);

      if (!mounted) return;

      setState(() {
        _favoritos =
            _favoritos.where((cada) => cada.id != conductor.id).toList();
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${conductor.nombre} ya no está en tus favoritos.')),
      );
    } on ApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.mensaje)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mis conductores favoritos')),
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 36),
          children: <Widget>[
            const Text(
              'Cuando pidas un viaje, intentamos primero con uno de ellos si está en línea y cerca.',
              style: TextStyle(
                fontSize: 13.5,
                height: 1.4,
                color: MotorutaColors.ink600,
              ),
            ),
            const SizedBox(height: 16),
            if (_cargando)
              const Padding(
                padding: EdgeInsets.only(top: 40),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null) ...<Widget>[
              Aviso(tono: Tono.error, texto: _error!),
              const SizedBox(height: 14),
              BotonSecundario(texto: 'Reintentar', alPresionar: _cargar),
            ] else if (_favoritos.isEmpty)
              Tarjeta(
                child: Column(
                  children: <Widget>[
                    const Icon(
                      Icons.favorite_border_rounded,
                      size: 44,
                      color: MotorutaColors.brand800,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Todavía no tienes favoritos',
                      textAlign: TextAlign.center,
                      style: MotorutaTheme.titulo(tamano: 18),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Al terminar un viaje puedes marcar a tu conductor con el corazón o al calificarlo.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 13.5,
                        height: 1.4,
                        color: MotorutaColors.ink600,
                      ),
                    ),
                  ],
                ),
              )
            else
              ..._favoritos.map(
                (conductor) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _TarjetaFavorito(
                    conductor: conductor,
                    alQuitar: () => _quitar(conductor),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _TarjetaFavorito extends StatelessWidget {
  const _TarjetaFavorito({
    required this.conductor,
    required this.alQuitar,
  });

  final ConductorFavorito conductor;
  final VoidCallback alQuitar;

  @override
  Widget build(BuildContext context) {
    return Tarjeta(
      child: Row(
        children: <Widget>[
          Container(
            width: 48,
            height: 48,
            alignment: Alignment.center,
            decoration: const BoxDecoration(
              color: MotorutaColors.brand800,
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.person_rounded, color: Colors.white),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Flexible(
                      child: Text(
                        conductor.nombre,
                        style: MotorutaTheme.titulo(tamano: 16),
                      ),
                    ),
                    const SizedBox(width: 8),
                    if (conductor.enLinea)
                      const Text(
                        'En línea',
                        style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w600,
                          color: MotorutaColors.verde,
                        ),
                      ),
                  ],
                ),
                if (conductor.vehiculo != null)
                  Text(
                    conductor.vehiculo!,
                    style: const TextStyle(
                      fontSize: 12.5,
                      color: MotorutaColors.ink600,
                    ),
                  ),
                Text(
                  conductor.calificacion == null
                      ? 'Sin calificaciones'
                      : '★ ${conductor.calificacion!.toStringAsFixed(1)} · '
                          '${conductor.viajesCompletados} viajes',
                  style: const TextStyle(
                    fontSize: 12,
                    color: MotorutaColors.ink500,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Quitar de favoritos',
            onPressed: alQuitar,
            icon: const Icon(
              Icons.favorite_rounded,
              color: MotorutaColors.rojo,
            ),
          ),
        ],
      ),
    );
  }
}

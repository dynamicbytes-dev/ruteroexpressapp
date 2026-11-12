import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/app_theme.dart';
import '../theme/motoruta_colors.dart';

/// Envoltura con micro-interacción de escala elástica y respuesta háptica.
class _EfectoPulsar extends StatefulWidget {
  const _EfectoPulsar({
    required this.child,
    required this.habilitado,
  });

  final Widget child;
  final bool habilitado;

  @override
  State<_EfectoPulsar> createState() => _EfectoPulsarState();
}

class _EfectoPulsarState extends State<_EfectoPulsar> {
  bool _presionado = false;

  void _alBajar(PointerDownEvent _) {
    if (!widget.habilitado) return;
    setState(() => _presionado = true);
    HapticFeedback.lightImpact();
  }

  void _alSubir(PointerUpEvent _) {
    if (_presionado) {
      setState(() => _presionado = false);
    }
  }

  void _alCancelar(PointerCancelEvent _) {
    if (_presionado) {
      setState(() => _presionado = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      onPointerDown: _alBajar,
      onPointerUp: _alSubir,
      onPointerCancel: _alCancelar,
      child: AnimatedScale(
        scale: _presionado ? 0.975 : 1.0,
        duration: const Duration(milliseconds: 110),
        curve: Curves.easeOutCubic,
        child: AnimatedOpacity(
          opacity: _presionado ? 0.92 : 1.0,
          duration: const Duration(milliseconds: 110),
          curve: Curves.easeOutCubic,
          child: widget.child,
        ),
      ),
    );
  }
}

/// Botón principal de la app con micro-interacción táctil.
///
/// [claro] pinta el botón en cian con texto morado: es la versión para las
/// pantallas de fondo oscuro, donde el morado no se vería.
class BotonPrincipal extends StatelessWidget {
  const BotonPrincipal({
    super.key,
    required this.texto,
    this.alPresionar,
    this.cargando = false,
    this.icono,
    this.claro = false,
  });

  final String texto;
  final VoidCallback? alPresionar;
  final bool cargando;
  final IconData? icono;
  final bool claro;

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    final colorTexto = claro ? MotorutaColors.brand900 : esquema.onPrimary;
    final habilitado = !cargando && alPresionar != null;

    final boton = FilledButton(
      onPressed: cargando ? null : alPresionar,
      style: FilledButton.styleFrom(
        minimumSize: const Size.fromHeight(54),
        padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 15),
        backgroundColor: claro ? MotorutaColors.cian : esquema.primary,
        foregroundColor: colorTexto,
        disabledBackgroundColor:
            claro ? MotorutaColors.brand600 : context.gaz.borderStrong,
        disabledForegroundColor:
            claro ? MotorutaColors.brand300 : context.gaz.text3,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
        textStyle: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 16,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.2,
        ),
      ),
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 180),
        child: cargando
            ? SizedBox(
                key: const ValueKey<String>('cargando'),
                height: 22,
                width: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.4,
                  color: colorTexto,
                ),
              )
            : Row(
                key: const ValueKey<String>('contenido'),
                mainAxisAlignment: MainAxisAlignment.center,
                children: <Widget>[
                  if (icono != null) ...<Widget>[
                    Icon(icono, size: 20, color: colorTexto),
                    const SizedBox(width: 10),
                  ],
                  // En pantallas angostas el texto baja de línea en vez de
                  // desbordarse.
                  Flexible(child: Text(texto, textAlign: TextAlign.center)),
                ],
              ),
      ),
    );

    return _EfectoPulsar(
      habilitado: habilitado,
      child: boton,
    );
  }
}

/// Botón secundario: sin relleno, sobre el fondo que esté, con micro-interacción.
class BotonSecundario extends StatelessWidget {
  const BotonSecundario({
    super.key,
    required this.texto,
    this.alPresionar,
    this.icono,
    this.claro = false,
  });

  final String texto;
  final VoidCallback? alPresionar;
  final IconData? icono;
  final bool claro;

  @override
  Widget build(BuildContext context) {
    final color = claro ? Colors.white : Theme.of(context).colorScheme.primary;
    final habilitado = alPresionar != null;

    final boton = OutlinedButton(
      onPressed: alPresionar,
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(54),
        foregroundColor: color,
        padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 15),
        side: BorderSide(
          color:
              claro ? Colors.white.withOpacity(0.35) : context.gaz.borderStrong,
          width: 1.2,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
        textStyle: const TextStyle(
          fontFamily: 'Inter',
          fontSize: 15.5,
          fontWeight: FontWeight.w600,
          letterSpacing: -0.2,
        ),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          if (icono != null) ...<Widget>[
            Icon(icono, size: 20, color: color),
            const SizedBox(width: 10),
          ],
          Flexible(child: Text(texto, textAlign: TextAlign.center)),
        ],
      ),
    );

    return _EfectoPulsar(
      habilitado: habilitado,
      child: boton,
    );
  }
}

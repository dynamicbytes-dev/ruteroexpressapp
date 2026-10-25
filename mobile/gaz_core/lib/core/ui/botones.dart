import 'package:flutter/material.dart';

import '../theme/motoruta_colors.dart';

/// Botón principal de la app.
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
    final colorTexto = claro ? MotorutaColors.brand900 : Colors.white;

    return FilledButton(
      onPressed: cargando ? null : alPresionar,
      style: FilledButton.styleFrom(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
        backgroundColor: claro ? MotorutaColors.cian : null,
        foregroundColor: claro ? MotorutaColors.brand900 : null,
        disabledBackgroundColor: claro ? MotorutaColors.brand600 : null,
        disabledForegroundColor: claro ? MotorutaColors.brand300 : null,
      ),
      child: cargando
          ? SizedBox(
              height: 22,
              width: 22,
              child: CircularProgressIndicator(
                strokeWidth: 2.4,
                color: claro ? MotorutaColors.brand900 : Colors.white,
              ),
            )
          : Row(
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
    );
  }
}

/// Botón secundario: sin relleno, sobre el fondo que esté.
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
    final color = claro ? Colors.white : MotorutaColors.brand800;

    return OutlinedButton(
      onPressed: alPresionar,
      style: OutlinedButton.styleFrom(
        foregroundColor: color,
        side: BorderSide(
          color: claro ? Colors.white.withOpacity(0.35) : MotorutaColors.ink200,
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
  }
}

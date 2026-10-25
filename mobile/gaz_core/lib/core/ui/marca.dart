import 'package:flutter/material.dart';

import '../theme/motoruta_colors.dart';

/// Palabra "gaz" estilizada con la tipografía geométrica de marca.
///
/// El cuerpo principal ("ga") va en blanco puro (#FFFFFF) o el color que
/// corresponda al fondo, y la letra "z" en Cian Brillante (#00F9FF).
class WordmarkGaz extends StatelessWidget {
  const WordmarkGaz({
    super.key,
    this.tamano = 28,
    this.colorCuerpo = Colors.white,
    this.colorAcento = MotorutaColors.cian,
    this.letterSpacing = -0.8,
  });

  final double tamano;
  final Color colorCuerpo;
  final Color colorAcento;
  final double letterSpacing;

  @override
  Widget build(BuildContext context) {
    return Text.rich(
      TextSpan(
        style: TextStyle(
          fontFamily: 'Sora',
          fontSize: tamano,
          fontWeight: FontWeight.w700,
          letterSpacing: letterSpacing,
          height: 1.0,
        ),
        children: <TextSpan>[
          TextSpan(text: 'ga', style: TextStyle(color: colorCuerpo)),
          TextSpan(text: 'z', style: TextStyle(color: colorAcento)),
        ],
      ),
      semanticsLabel: 'gaz',
    );
  }
}

/// Isotipo de la marca: rectángulo con bordes redondeados en Morado Oscuro (#310F38),
/// con la palabra "gaz" en su interior (letras "ga" en blanco puro y "z" en cian brillante).
class IsotipoMarca extends StatelessWidget {
  const IsotipoMarca({
    super.key,
    this.tamano = 44,
    this.color = Colors.white,
    this.radioBorde,
    this.conSombra = true,
  });

  final double tamano;
  final Color color;
  final double? radioBorde;
  final bool conSombra;

  @override
  Widget build(BuildContext context) {
    final double radio = radioBorde ?? (tamano * 0.23);
    final double tamanoFuente = tamano * 0.44;

    return Semantics(
      label: 'gaz',
      child: Container(
        width: tamano,
        height: tamano,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: MotorutaColors.brand800, // #310F38 Morado Oscuro
          borderRadius: BorderRadius.circular(radio),
          boxShadow: conSombra
              ? <BoxShadow>[
                  BoxShadow(
                    color: Colors.black.withOpacity(0.35),
                    blurRadius: tamano * 0.16,
                    offset: Offset(0, tamano * 0.06),
                  ),
                ]
              : null,
        ),
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: tamano * 0.10),
            child: WordmarkGaz(
              tamano: tamanoFuente,
              colorCuerpo: color,
              colorAcento: MotorutaColors.cian,
              letterSpacing: -tamanoFuente * 0.04,
            ),
          ),
        ),
      ),
    );
  }
}

/// Logotipo completo: Isotipo (insignia morada) + la palabra "gaz" con la tipografía de marca.
class LogotipoMarca extends StatelessWidget {
  const LogotipoMarca({
    super.key,
    this.tamanoIsotipo = 36,
    this.tamanoTexto = 26,
    this.color = Colors.white,
    this.mostrarIsotipo = true,
  });

  final double tamanoIsotipo;
  final double tamanoTexto;
  final Color color;
  final bool mostrarIsotipo;

  @override
  Widget build(BuildContext context) {
    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: Alignment.centerLeft,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.center,
        children: <Widget>[
          if (mostrarIsotipo) ...<Widget>[
            IsotipoMarca(tamano: tamanoIsotipo, color: color),
            SizedBox(width: tamanoIsotipo * 0.32),
          ],
          WordmarkGaz(
            tamano: tamanoTexto,
            colorCuerpo: color,
            colorAcento: MotorutaColors.cian,
          ),
        ],
      ),
    );
  }
}

/// Fondo morado de marca con contenido desplazable.
///
/// Es el marco de las pantallas de bienvenida, registro y acceso.
class PantallaMarca extends StatelessWidget {
  const PantallaMarca({
    super.key,
    required this.hijos,
    this.mostrarRetroceso = false,
    this.espacioAbajo = 32,
  });

  final List<Widget> hijos;
  final bool mostrarRetroceso;
  final double espacioAbajo;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MotorutaColors.brand950,
      body: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          const DecoratedBox(
            decoration: BoxDecoration(gradient: MotorutaColors.fondoMarca),
          ),
          // Resplandor ambiental superior en tono morado de marca
          Positioned(
            top: -100,
            left: -50,
            right: -50,
            height: 380,
            child: IgnorePointer(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: RadialGradient(
                    center: const Alignment(0, -0.4),
                    radius: 0.85,
                    colors: <Color>[
                      MotorutaColors.brand500.withOpacity(0.28),
                      Colors.transparent,
                    ],
                  ),
                ),
              ),
            ),
          ),
          SafeArea(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                if (mostrarRetroceso)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Padding(
                      padding: const EdgeInsets.only(left: 8, top: 4),
                      child: IconButton(
                        onPressed: () => Navigator.of(context).maybePop(),
                        icon: const Icon(Icons.arrow_back_rounded),
                        color: Colors.white,
                        tooltip: 'Volver',
                      ),
                    ),
                  ),
                Expanded(
                  child: SingleChildScrollView(
                    padding: EdgeInsets.fromLTRB(24, 8, 24, espacioAbajo),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: hijos,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

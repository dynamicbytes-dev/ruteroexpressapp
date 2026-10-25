import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../theme/motoruta_colors.dart';
import 'marca.dart';

/// Tono de un aviso o etiqueta, con los mismos colores del panel.
enum Tono {
  info,
  exito,
  aviso,
  error,
  neutro;

  Color get fondo => switch (this) {
        Tono.info => MotorutaColors.accent50,
        Tono.exito => MotorutaColors.verdeFondo,
        Tono.aviso => MotorutaColors.amarilloFondo,
        Tono.error => MotorutaColors.rojoFondo,
        Tono.neutro => MotorutaColors.ink100,
      };

  Color get color => switch (this) {
        Tono.info => MotorutaColors.accent800,
        Tono.exito => MotorutaColors.verde,
        Tono.aviso => MotorutaColors.amarillo,
        Tono.error => MotorutaColors.rojo,
        Tono.neutro => MotorutaColors.ink700,
      };

  IconData get icono => switch (this) {
        Tono.info => Icons.info_outline_rounded,
        Tono.exito => Icons.check_circle_outline_rounded,
        Tono.aviso => Icons.schedule_rounded,
        Tono.error => Icons.error_outline_rounded,
        Tono.neutro => Icons.circle_outlined,
      };
}

/// Franja de color con un mensaje (el mismo lenguaje del panel web).
class Aviso extends StatelessWidget {
  const Aviso({
    super.key,
    required this.texto,
    this.tono = Tono.info,
    this.titulo,
    this.icono,
  });

  final String texto;
  final Tono tono;
  final String? titulo;
  final IconData? icono;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: tono.fondo,
        borderRadius: BorderRadius.circular(MotorutaTheme.radio),
        border: Border.all(color: tono.color.withOpacity(0.22)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Icon(icono ?? tono.icono, size: 22, color: tono.color),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                if (titulo != null) ...<Widget>[
                  Text(
                    titulo!,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                      color: tono.color,
                    ),
                  ),
                  const SizedBox(height: 2),
                ],
                Text(
                  texto,
                  style: const TextStyle(
                    fontSize: 14.5,
                    height: 1.4,
                    color: MotorutaColors.ink800,
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

/// Etiqueta pequeña de estado ("Aprobado", "En revisión", "Rechazado"...).
class EtiquetaEstado extends StatelessWidget {
  const EtiquetaEstado({
    super.key,
    required this.texto,
    this.tono = Tono.neutro,
    this.icono,
  });

  final String texto;
  final Tono tono;
  final IconData? icono;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: tono.fondo,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if (icono != null) ...<Widget>[
            Icon(icono, size: 14, color: tono.color),
            const SizedBox(width: 5),
          ],
          Text(
            texto,
            style: TextStyle(
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              color: tono.color,
            ),
          ),
        ],
      ),
    );
  }
}

/// Tarjeta blanca con la sombra suave del panel.
class Tarjeta extends StatelessWidget {
  const Tarjeta({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(20),
    this.color = Colors.white,
    this.borde = true,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color color;
  final bool borde;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(MotorutaTheme.radioGrande),
        border: borde ? Border.all(color: MotorutaColors.ink100) : null,
        boxShadow: MotorutaTheme.sombraTarjeta,
      ),
      child: child,
    );
  }
}

/// Renglón "etiqueta — valor", para las fichas de datos.
class DatoFila extends StatelessWidget {
  const DatoFila({super.key, required this.etiqueta, required this.valor});

  final String etiqueta;
  final String valor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 120,
            child: Text(
              etiqueta,
              style: const TextStyle(
                fontSize: 14,
                color: MotorutaColors.ink500,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          Expanded(
            child: Text(
              valor,
              style: const TextStyle(
                fontSize: 14.5,
                color: MotorutaColors.ink900,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Pantalla completa con el logo y un giro de carga.
class PantallaCargando extends StatelessWidget {
  const PantallaCargando({super.key, this.mensaje});

  final String? mensaje;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MotorutaColors.brand900,
      body: DecoratedBox(
        decoration: const BoxDecoration(gradient: MotorutaColors.fondoMarca),
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              const IsotipoMarca(tamano: 78, color: Colors.white),
              const SizedBox(height: 28),
              const SizedBox(
                width: 26,
                height: 26,
                child: CircularProgressIndicator(
                  strokeWidth: 2.6,
                  color: MotorutaColors.cian,
                ),
              ),
              if (mensaje != null) ...<Widget>[
                const SizedBox(height: 18),
                Text(
                  mensaje!,
                  style: const TextStyle(color: Colors.white70, fontSize: 14.5),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

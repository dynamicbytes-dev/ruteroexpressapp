import 'package:flutter/material.dart';

import '../../core/theme/motoruta_colors.dart';

/// Modelo de datos para las tarjetas publicitarias y banners promocionales
/// consumidos dinámicamente desde el endpoint `GET /api/banners`.
class BannerPromocional {
  const BannerPromocional({
    required this.id,
    required this.titulo,
    this.subtitulo,
    this.etiqueta,
    this.imagenUrl,
    required this.colorFondo,
    this.colorFondoFin,
    required this.colorTexto,
    this.enlace,
    required this.textoBoton,
    this.rolObjetivo = 'passenger',
  });

  final int id;
  final String titulo;
  final String? subtitulo;
  final String? etiqueta;
  final String? imagenUrl;
  final Color colorFondo;
  final Color? colorFondoFin;
  final Color colorTexto;
  final String? enlace;
  final String textoBoton;
  final String rolObjetivo;

  factory BannerPromocional.desdeJson(Map<String, dynamic> json) {
    return BannerPromocional(
      id: json['id'] is int
          ? json['id'] as int
          : int.tryParse('${json['id']}') ?? 0,
      titulo: (json['title'] ?? json['titulo'] ?? '').toString(),
      subtitulo: _texto(json['subtitle'] ?? json['subtitulo']),
      etiqueta: _texto(json['tag'] ?? json['etiqueta']),
      imagenUrl: _texto(json['image_url'] ?? json['imagen_url']),
      colorFondo: _color(
        json['background_color'] ?? json['color_fondo'],
        MotorutaColors.brand900,
      ),
      colorFondoFin: _colorOpcional(
        json['background_color_end'] ?? json['color_fondo_fin'],
      ),
      colorTexto: _color(
        json['text_color'] ?? json['color_texto'],
        Colors.white,
      ),
      enlace: _texto(json['action_url'] ?? json['enlace']),
      textoBoton:
          _texto(json['action_text'] ?? json['texto_boton']) ?? 'Ver más',
      rolObjetivo:
          _texto(json['target_role'] ?? json['rol_objetivo']) ?? 'passenger',
    );
  }
}

String? _texto(Object? valor) {
  if (valor is! String || valor.trim().isEmpty) return null;
  return valor.trim();
}

Color _color(Object? valor, Color porDefecto) {
  if (valor is! String || valor.trim().isEmpty) return porDefecto;
  var hex = valor.replaceAll('#', '').trim();
  if (hex.length == 6) hex = 'FF$hex';
  if (hex.length == 8) {
    final entero = int.tryParse(hex, radix: 16);
    if (entero != null) return Color(entero);
  }
  return porDefecto;
}

Color? _colorOpcional(Object? valor) {
  if (valor is! String || valor.trim().isEmpty) return null;
  var hex = valor.replaceAll('#', '').trim();
  if (hex.length == 6) hex = 'FF$hex';
  if (hex.length == 8) {
    final entero = int.tryParse(hex, radix: 16);
    if (entero != null) return Color(entero);
  }
  return null;
}

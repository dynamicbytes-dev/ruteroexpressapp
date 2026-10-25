import 'package:flutter/material.dart';

/// Paleta de GAZ.
///
/// Son exactamente los mismos valores del panel web
/// (`backend/tailwind.config.js`): una sola identidad en el panel y en la app.
abstract class MotorutaColors {
  // Morado de marca: `brand800` es el color principal (#310F38).
  static const Color brand50 = Color(0xFFF7F2F9);
  static const Color brand100 = Color(0xFFEEE1F2);
  static const Color brand200 = Color(0xFFD9C1E2);
  static const Color brand300 = Color(0xFFBC94CB);
  static const Color brand400 = Color(0xFF9A62AE);
  static const Color brand500 = Color(0xFF763D8C);
  static const Color brand600 = Color(0xFF5A2270);
  static const Color brand700 = Color(0xFF451754);
  static const Color brand800 = Color(0xFF310F38);
  static const Color brand900 = Color(0xFF230A29);
  static const Color brand950 = Color(0xFF16051B);

  // Cian de marca: `accent400` es el color exacto (#00F9FF). Los tonos oscuros
  // existen para poder escribir en cian sobre fondos claros.
  static const Color accent50 = Color(0xFFE8FEFF);
  static const Color accent100 = Color(0xFFC4FDFF);
  static const Color accent200 = Color(0xFF8CFCFF);
  static const Color accent300 = Color(0xFF4CFAFF);
  static const Color accent400 = Color(0xFF00F9FF);
  static const Color accent500 = Color(0xFF00D3D9);
  static const Color accent600 = Color(0xFF00A6AB);
  static const Color accent700 = Color(0xFF00787C);
  static const Color accent800 = Color(0xFF004C4F);
  static const Color accent900 = Color(0xFF00292B);

  // Neutros fríos, para fondos, bordes y texto.
  static const Color ink50 = Color(0xFFF6F6F8);
  static const Color ink100 = Color(0xFFECECF1);
  static const Color ink200 = Color(0xFFD7D8E0);
  static const Color ink300 = Color(0xFFB4B6C4);
  static const Color ink400 = Color(0xFF8B8EA1);
  static const Color ink500 = Color(0xFF6B6E82);
  static const Color ink600 = Color(0xFF53566A);
  static const Color ink700 = Color(0xFF3E4052);
  static const Color ink800 = Color(0xFF2A2C3A);
  static const Color ink900 = Color(0xFF16171F);
  static const Color ink950 = Color(0xFF0C0D12);

  // Estados (los mismos tonos del panel).
  static const Color amarillo = Color(0xFFB45309);
  static const Color amarilloFondo = Color(0xFFFEF3C7);
  static const Color rojo = Color(0xFFB91C1C);
  static const Color rojoFondo = Color(0xFFFEE2E2);
  static const Color verde = Color(0xFF047857);
  static const Color verdeFondo = Color(0xFFD1FAE5);

  /// Nombre corto para el color principal.
  static const Color marca = brand800;

  /// Nombre corto para el cian de marca.
  static const Color cian = accent400;

  /// Degradado del fondo oscuro (el mismo del panel).
  static const LinearGradient fondoMarca = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [brand900, brand800, brand700],
  );
}

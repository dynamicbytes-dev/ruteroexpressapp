import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/ui/avisos.dart';

/// Formatos y tonos compartidos por las pantallas de billetera.
final NumberFormat _miles = NumberFormat('#,##0.00', 'es');
final DateFormat _fecha = DateFormat('dd/MM/yyyy', 'es');
final DateFormat _fechaHora = DateFormat('dd/MM/yyyy · HH:mm', 'es');

String montoUsd(double valor) => '\$${_miles.format(valor)}';

String montoBs(double valor) => 'Bs ${_miles.format(valor)}';

String fechaCorta(DateTime? valor) =>
    valor == null ? '—' : _fecha.format(valor.toLocal());

String fechaConHora(DateTime? valor) =>
    valor == null ? '—' : _fechaHora.format(valor.toLocal());

/// Convierte lo que escribe la persona: acepta coma o punto decimal.
double? leerDecimal(String texto) {
  final limpio = texto.trim().replaceAll(RegExp(r'[^0-9.,]'), '');

  if (limpio.isEmpty) return null;

  // Formato venezolano: 4.000,50 -> 4000.50. Si solo hay punto, se toma como
  // decimal (4.50). Si hay coma, los puntos son separadores de miles.
  final normalizado = limpio.contains(',')
      ? limpio.replaceAll('.', '').replaceAll(',', '.')
      : limpio;

  return double.tryParse(normalizado);
}

/// Etiqueta de estado de una recarga o un retiro.
Widget etiquetaFinanciera(String estado, String etiqueta) {
  final (Tono tono, IconData icono) = switch (estado) {
    'approved' || 'paid' => (Tono.exito, Icons.check_rounded),
    'rejected' => (Tono.error, Icons.close_rounded),
    'pending' => (Tono.aviso, Icons.schedule_rounded),
    _ => (Tono.neutro, Icons.circle_outlined),
  };

  return EtiquetaEstado(
    texto: etiqueta.isEmpty ? estado : etiqueta,
    tono: tono,
    icono: icono,
  );
}

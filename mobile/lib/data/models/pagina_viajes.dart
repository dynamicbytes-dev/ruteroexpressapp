import 'viaje.dart';

/// Una página del historial de viajes.
///
/// La API entrega páginas de 20. [pagina] y [ultimaPagina] salen de `meta`;
/// si una respuesta antigua no trae esa información, se asume una sola página.
class PaginaViajes {
  const PaginaViajes({
    required this.viajes,
    required this.pagina,
    required this.ultimaPagina,
  });

  final List<Viaje> viajes;
  final int pagina;
  final int ultimaPagina;

  bool get hayMas => pagina < ultimaPagina;
}

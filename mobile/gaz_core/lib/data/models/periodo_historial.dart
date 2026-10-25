/// Períodos rápidos del historial de viajes.
///
/// El nombre del valor de la API viaja en [apiValue]. `todos` no envía filtro,
/// para conservar el comportamiento anterior.
enum PeriodoHistorial {
  todos(null, 'Todos'),
  hoy('day', 'Hoy'),
  semana('week', 'Semana'),
  mes('month', 'Mes');

  const PeriodoHistorial(this.apiValue, this.etiqueta);

  final String? apiValue;
  final String etiqueta;

  bool get filtra => apiValue != null;
}

import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/data/models/ciudad.dart';

void main() {
  test('lee una ciudad del backend y arma su etiqueta', () {
    final ciudad = Ciudad.desdeJson(<String, dynamic>{
      'id': 4,
      'name': 'Lechería',
      'state': 'Anzoátegui',
      'display_name': 'Lechería (Anzoátegui)',
    });

    expect(ciudad.id, 4);
    expect(ciudad.nombre, 'Lechería');
    expect(ciudad.estado, 'Anzoátegui');
    expect(ciudad.etiqueta, 'Lechería (Anzoátegui)');
  });

  test('si el backend no manda el nombre completo usa nombre y estado', () {
    final ciudad = Ciudad.desdeJson(<String, dynamic>{
      'id': 7,
      'name': 'Cumaná',
      'state': 'Sucre',
    });

    expect(ciudad.etiqueta, 'Cumaná (Sucre)');
  });

  test('un id inválido queda en cero para descartarlo', () {
    final ciudad = Ciudad.desdeJson(<String, dynamic>{'id': 'sin-id'});

    expect(ciudad.id, 0);
  });
}

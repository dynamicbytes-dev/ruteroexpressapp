import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/data/models/viaje.dart';

import '../soporte/datos_falsos.dart';

void main() {
  test('lee un viaje completo del backend', () {
    final viaje = Viaje.desdeJson(
      viajeJson(
        estado: 'in_progress',
        etiqueta: 'En curso',
        conConductor: true,
        conPasajero: true,
      ),
    );

    expect(viaje.id, 12);
    expect(viaje.estado, 'in_progress');
    expect(viaje.activo, isTrue);
    expect(viaje.enCurso, isTrue);
    expect(viaje.buscando, isFalse);
    expect(viaje.origen.direccion, 'Av. Francisco de Miranda');
    expect(viaje.destino.lat, 10.5003);
    expect(viaje.tarifa.total, 3.35);
    expect(viaje.tarifa.comision, 0.34);
    expect(viaje.tarifa.gananciaConductor, 3.01);
    expect(viaje.tarifa.totalLocal, 134.0);
    expect(viaje.conductor?.nombre, 'Pedro Rojas');
    expect(viaje.conductor?.vehiculo, 'Bera BR 150 2022 · AB123CD');
    expect(viaje.conductor?.lat, 10.4965);
    expect(viaje.pasajero?.telefono, '04141234567');
    expect(viaje.puedeCancelar, isTrue);
  });

  test('un viaje sin conductor no expone datos de contacto', () {
    final viaje = Viaje.desdeJson(viajeJson());

    expect(viaje.buscando, isTrue);
    expect(viaje.conductor, isNull);
    expect(viaje.pasajero, isNull);
    expect(viaje.resumenEstado, 'Esperando respuesta del conductor');
  });

  test('reconoce los estados terminales', () {
    expect(
      Viaje.desdeJson(viajeJson(estado: 'completed')).completado,
      isTrue,
    );
    expect(
      Viaje.desdeJson(
        viajeJson(estado: 'cancelled', etiqueta: 'Cancelado'),
      ).cancelado,
      isTrue,
    );
    expect(
      Viaje.desdeJson(
        viajeJson(estado: 'no_driver', etiqueta: 'Sin conductor'),
      ).cancelado,
      isTrue,
    );
    expect(Viaje.desdeJson(viajeJson()).activo, isTrue);
  });

  test('lee la alerta con su resumen y cuenta regresiva', () {
    final oferta = OfertaViaje.desdeJson(ofertaJson(segundos: 14));

    expect(oferta.id, 7);
    expect(oferta.pendiente, isTrue);
    expect(oferta.segundosRestantes, 14);
    expect(oferta.distanciaRecogidaKm, 1.2);
    expect(oferta.viaje.pasajero, 'Ana Pérez');
    expect(oferta.viaje.ganancia, 3.01);
    expect(oferta.viaje.comision, 0.34);
  });

  test('lee la cotización y sus banderas', () {
    final cotizacion = CotizacionViaje.desdeJson(cotizacionJson());

    expect(cotizacion.total, 3.35);
    expect(cotizacion.distanciaKm, 5);
    expect(cotizacion.duracionMinutos, 12);
    expect(cotizacion.ganancia, 3.01);
    expect(cotizacion.esEstimada, isTrue);
    expect(cotizacion.totalLocal, 134.0);
  });

  test('lee el estado del conductor y detecta bloqueo o deuda', () {
    final libre = EstadoConductor.desdeJson(estadoConductorJson());
    final bloqueado = EstadoConductor.desdeJson(
      estadoConductorJson(
        enLinea: false,
        bloqueadoHasta:
            DateTime.now().add(const Duration(minutes: 30)).toIso8601String(),
        saldo: -6,
      ),
    );

    expect(libre.enLinea, isFalse);
    expect(libre.rechazosParaBloqueo, 3);
    expect(libre.topeNegativo, -5);
    expect(libre.calificacion, 4.8);
    expect(libre.calificaciones, 12);
    expect(bloqueado.bloqueado, isTrue);
    expect(bloqueado.saldoBajo, isTrue);
  });

  test('lee la calificación del viaje y la marca de favorito', () {
    final viaje = Viaje.desdeJson(
      viajeJson(
        estado: 'completed',
        etiqueta: 'Completado',
        conConductor: true,
        favorito: true,
        puedoCalificar: true,
        calificacion: <String, dynamic>{
          'rating': 4,
          'comment': 'Muy amable.',
          'created_at': '2026-10-19T12:30:00-04:00',
        },
      ),
    );

    expect(viaje.completado, isTrue);
    expect(viaje.puedoCalificar, isTrue);
    expect(viaje.conductor?.esFavorito, isTrue);
    expect(viaje.calificacion?.estrellas, 4);
    expect(viaje.calificacion?.comentario, 'Muy amable.');
    expect(viaje.calificacion?.textoEstrellas, '★★★★☆');
    expect(viaje.conFavorito(false).conductor?.esFavorito, isFalse);
  });

  test('conUbicacion conserva el estado y actualiza las coordenadas', () {
    final estado = EstadoConductor.desdeJson(estadoConductorJson());
    final actualizado = estado.conUbicacion(10.51, -66.90);

    expect(actualizado.lat, 10.51);
    expect(actualizado.lng, -66.90);
    expect(actualizado.saldo, estado.saldo);
    expect(actualizado.ubicacionActualizada, isNotNull);
  });
}

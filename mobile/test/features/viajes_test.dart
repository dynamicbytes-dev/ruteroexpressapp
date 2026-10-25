import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/api/api_client.dart';
import 'package:motoruta/core/api/api_exception.dart';
import 'package:motoruta/data/billetera_repository.dart';
import 'package:motoruta/data/cuenta_repository.dart';
import 'package:motoruta/data/models/periodo_historial.dart';
import 'package:motoruta/data/models/usuario.dart';
import 'package:motoruta/data/models/viaje.dart';
import 'package:motoruta/data/viajes_repository.dart';
import 'package:motoruta/features/sesion/sesion_controller.dart';

import '../soporte/datos_falsos.dart';
import '../soporte/servidor_falso.dart';

void main() {
  late ServidorFalso servidor;
  late ApiClient api;

  setUp(() {
    servidor = ServidorFalso();
    api = ApiClient(
      dio: Dio()
        ..httpClientAdapter = servidor
        ..transformer = SyncTransformer(),
      baseUrl: 'http://localhost:8000',
    );
  });

  SesionController sesionComo(String rol) {
    final sesion = SesionController(
      repositorio: CuentaRepository(api),
      almacen: TokenMemoria('tok-viajes'),
      api: api,
      billetera: BilleteraRepository(api),
      viajes: ViajesRepository(api),
    );

    final perfil = PerfilCuenta.desdeJson(
      perfilJson(
        usuario: usuarioJson(
          rol: rol,
          estado: 'approved',
          etiquetaEstado: 'Aprobado',
          puedeOperar: true,
        ),
        paso: 'ready',
      ),
    );

    sesion.usuario = perfil.usuario;
    sesion.siguientePaso = perfil.siguientePaso;
    sesion.estado = EstadoSesion.activa;

    return sesion;
  }

  test('carga el viaje activo del pasajero', () async {
    servidor.responder(
      'GET',
      '/api/viajes/activo',
      <String, dynamic>{
        'data': <String, dynamic>{
          'trip': viajeJson(estado: 'accepted', conConductor: true),
        },
      },
    );

    final sesion = sesionComo('passenger');
    await sesion.cargarViajeActivo();

    expect(sesion.viajeActivo, isNotNull);
    expect(sesion.viajeActivo!.estado, 'accepted');
    expect(sesion.viajeActivo!.conductor!.nombre, 'Pedro Rojas');
    expect(sesion.cargandoViaje, isFalse);
  });

  test('carga las alertas del conductor', () async {
    servidor.responder(
      'GET',
      '/api/conductor/ofertas',
      <String, dynamic>{
        'data': <dynamic>[ofertaJson()],
      },
    );

    final sesion = sesionComo('driver');
    await sesion.cargarOfertas();

    expect(sesion.ofertas, hasLength(1));
    expect(sesion.ofertas.first.viaje.pasajero, 'Ana Pérez');
    expect(sesion.ofertas.first.segundosRestantes, 18);
  });

  test('conecta al conductor y guarda su estado', () async {
    servidor.responder(
      'PUT',
      '/api/conductor/disponibilidad',
      <String, dynamic>{
        'message': 'Estás conectado.',
        'data': estadoConductorJson(enLinea: true, disponible: true),
      },
    );

    final sesion = sesionComo('driver');
    final estado = await sesion.cambiarDisponibilidad(true);

    expect(estado.enLinea, isTrue);
    expect(sesion.estadoConductor!.disponible, isTrue);

    final peticion = servidor.peticiones.single;
    expect(peticion.method, 'PUT');
    expect(peticion.path, '/api/conductor/disponibilidad');
    expect(peticion.data, <String, dynamic>{'online': true});
  });

  test('reportar ubicación actualiza la copia local del estado', () async {
    servidor.responder(
      'GET',
      '/api/conductor/estado',
      <String, dynamic>{
        'data': estadoConductorJson(enLinea: true, disponible: true),
      },
    );
    servidor.responder(
      'POST',
      '/api/conductor/ubicacion',
      <String, dynamic>{
        'data': <String, dynamic>{
          'location': <String, dynamic>{'lat': 10.51, 'lng': -66.90},
          'updated_at': '2026-10-19T12:00:10-04:00',
        },
      },
    );

    final sesion = sesionComo('driver');
    await sesion.cargarEstadoConductor();
    await sesion.reportarUbicacion(10.51, -66.90);

    expect(sesion.estadoConductor!.lat, 10.51);
    expect(sesion.estadoConductor!.lng, -66.90);
  });

  test('solicitar viaje retiene el saldo y deja el viaje activo', () async {
    servidor.responder(
      'POST',
      '/api/viajes',
      <String, dynamic>{
        'message': 'Estamos buscando un conductor para tu viaje.',
        'data': <String, dynamic>{'trip': viajeJson()},
      },
      codigo: 201,
    );
    servidor.responder(
      'GET',
      '/api/billetera',
      billeteraJson(saldo: 100, retenido: 3.35),
    );

    final sesion = sesionComo('passenger');
    final resultado = await sesion.solicitarViaje(
      origen: const PuntoViaje(
        direccion: 'Origen',
        lat: 10.4961,
        lng: -66.8517,
      ),
      destino: const PuntoViaje(
        direccion: 'Destino',
        lat: 10.5003,
        lng: -66.8428,
      ),
      metodoPago: 'wallet',
    );

    expect(resultado.viaje.id, 12);
    expect(resultado.mensaje, contains('buscando un conductor'));
    expect(sesion.viajeActivo, isNotNull);
    expect(sesion.billetera!.retenido, 3.35);

    final peticion = servidor.peticiones
        .firstWhere((p) => p.method == 'POST' && p.path == '/api/viajes');
    final datos = peticion.data! as Map<String, dynamic>;

    expect(datos['payment_method'], 'wallet');
    expect(datos['origin_lat'], 10.4961);
    expect(datos['destination_address'], 'Destino');
    expect(datos['vehicle_type'], 'moto');
  });

  test('cotizar carro envía el tipo y lee el recargo de un dólar', () async {
    servidor.responder(
      'POST',
      '/api/viajes/cotizar',
      <String, dynamic>{
        'data': cotizacionJson(
          total: 4.35,
          tipoVehiculo: 'carro',
          recargoVehiculo: 1,
        ),
      },
    );

    final sesion = sesionComo('passenger');
    final cotizacion = await sesion.cotizarViaje(
      origen: const PuntoViaje(
        direccion: 'Origen',
        lat: 10.4961,
        lng: -66.8517,
      ),
      destino: const PuntoViaje(
        direccion: 'Destino',
        lat: 10.5003,
        lng: -66.8428,
      ),
      tipoVehiculo: 'carro',
    );

    expect(cotizacion.tipoVehiculo, 'carro');
    expect(cotizacion.tipoVehiculoEtiqueta, 'Carro');
    expect(cotizacion.recargoVehiculo, 1);
    expect(cotizacion.total, 4.35);

    final peticion = servidor.peticiones.firstWhere(
        (p) => p.method == 'POST' && p.path == '/api/viajes/cotizar');
    final datos = peticion.data! as Map<String, dynamic>;

    expect(datos['vehicle_type'], 'carro');
  });

  test('solicitar carro envía el tipo y el viaje lo conserva', () async {
    servidor.responder(
      'POST',
      '/api/viajes',
      <String, dynamic>{
        'message': 'Estamos buscando un conductor para tu viaje.',
        'data': <String, dynamic>{
          'trip': viajeJson(total: 4.35, tipoVehiculo: 'carro'),
        },
      },
      codigo: 201,
    );
    servidor.responder(
      'GET',
      '/api/billetera',
      billeteraJson(saldo: 100, retenido: 4.35),
    );

    final sesion = sesionComo('passenger');
    await sesion.solicitarViaje(
      origen: const PuntoViaje(
        direccion: 'Origen',
        lat: 10.4961,
        lng: -66.8517,
      ),
      destino: const PuntoViaje(
        direccion: 'Destino',
        lat: 10.5003,
        lng: -66.8428,
      ),
      metodoPago: 'wallet',
      tipoVehiculo: 'carro',
    );

    final peticion = servidor.peticiones
        .firstWhere((p) => p.method == 'POST' && p.path == '/api/viajes');
    final datos = peticion.data! as Map<String, dynamic>;

    expect(datos['vehicle_type'], 'carro');
    expect(sesion.viajeActivo!.tipoVehiculo, 'carro');
    expect(sesion.viajeActivo!.tipoVehiculoEtiqueta, 'Carro');
  });

  test('aceptar una alerta asigna el viaje y limpia las ofertas', () async {
    servidor.responder(
      'GET',
      '/api/conductor/ofertas',
      <String, dynamic>{
        'data': <dynamic>[ofertaJson()],
      },
    );
    servidor.responder(
      'POST',
      '/api/ofertas/7/aceptar',
      <String, dynamic>{
        'message': 'Viaje aceptado.',
        'data': <String, dynamic>{
          'trip': viajeJson(
            estado: 'accepted',
            etiqueta: 'Asignado',
            conConductor: true,
            conPasajero: true,
          ),
        },
      },
    );
    servidor.responder('GET', '/api/billetera', billeteraJson(saldo: 20));

    final sesion = sesionComo('driver');
    await sesion.cargarOfertas();
    final oferta = sesion.ofertas.first;
    final resultado = await sesion.responderOferta(oferta, aceptar: true);

    expect(resultado, isNotNull);
    expect(resultado!.viaje.estado, 'accepted');
    expect(sesion.viajeActivo!.pasajero!.nombre, 'Ana Pérez');
    expect(sesion.ofertas, isEmpty);
  });

  test(
      'si aceptar la alerta falla por error secundario en el backend, avanza con viaje en curso de respaldo',
      () async {
    servidor.responder(
      'GET',
      '/api/conductor/ofertas',
      <String, dynamic>{
        'data': <dynamic>[ofertaJson()],
      },
    );
    servidor.responder(
      'POST',
      '/api/ofertas/7/aceptar',
      <String, dynamic>{
        'message': 'Call to undefined method App\\Models\\User::photoUrl()',
      },
      codigo: 500,
    );
    servidor.responder(
      'GET',
      '/api/conductor/viaje-activo',
      <String, dynamic>{
        'message': 'Error al cargar perfil',
      },
      codigo: 500,
    );

    final sesion = sesionComo('driver');
    await sesion.cargarOfertas();
    final oferta = sesion.ofertas.first;
    final resultado = await sesion.responderOferta(oferta, aceptar: true);

    expect(resultado, isNotNull);
    expect(resultado!.viaje.estado, 'accepted');
    expect(resultado.viaje.enCurso, isTrue);
    expect(resultado.viaje.id, oferta.viaje.id);
    expect(sesion.viajeActivo, isNotNull);
    expect(sesion.viajeActivo!.id, oferta.viaje.id);
    expect(sesion.ofertas, isEmpty);
  });

  test('rechazar una alerta la quita de la lista', () async {
    servidor.responder(
      'GET',
      '/api/conductor/ofertas',
      <String, dynamic>{
        'data': <dynamic>[ofertaJson()],
      },
    );
    servidor.responder(
      'POST',
      '/api/ofertas/7/rechazar',
      <String, dynamic>{
        'message': 'Rechazaste el viaje.',
        'data': <String, dynamic>{'trip_id': 12},
      },
    );

    final sesion = sesionComo('driver');
    await sesion.cargarOfertas();
    final resultado = await sesion.responderOferta(
      sesion.ofertas.first,
      aceptar: false,
    );

    expect(resultado, isNull);
    expect(sesion.ofertas, isEmpty);
    expect(sesion.ultimoMensaje, 'Rechazaste el viaje.');
  });

  test('avanzar el viaje actualiza el estado y la billetera', () async {
    servidor.responder(
      'POST',
      '/api/viajes/12/iniciar',
      <String, dynamic>{
        'message': 'El viaje comenzó.',
        'data': <String, dynamic>{
          'trip': viajeJson(
            estado: 'in_progress',
            etiqueta: 'En curso',
            conConductor: true,
            conPasajero: true,
          ),
        },
      },
    );
    servidor.responder('GET', '/api/billetera', billeteraJson(saldo: 20));

    final sesion = sesionComo('driver');
    final resultado = await sesion.avanzarViaje(12, 'iniciar');

    expect(resultado.viaje.estado, 'in_progress');
    expect(sesion.viajeActivo!.estado, 'in_progress');
    expect(sesion.ultimoMensaje, 'El viaje comenzó.');
  });

  test('cancelar el viaje devuelve el estado cancelado', () async {
    servidor.responder(
      'POST',
      '/api/viajes/12/cancelar',
      <String, dynamic>{
        'message': 'El viaje fue cancelado.',
        'data': <String, dynamic>{
          'trip': viajeJson(
            estado: 'cancelled',
            etiqueta: 'Cancelado',
            puedeCancelar: false,
          ),
        },
      },
    );
    servidor.responder('GET', '/api/billetera', billeteraJson(saldo: 100));

    final sesion = sesionComo('passenger');
    final resultado =
        await sesion.cancelarViaje(12, motivo: 'Cambié de planes');

    expect(resultado.viaje.estado, 'cancelled');
    expect(sesion.viajeActivo!.cancelado, isTrue);
    expect(resultado.mensaje, 'El viaje fue cancelado.');
  });

  test('los errores del backend llegan con su mensaje', () async {
    servidor.responder(
      'POST',
      '/api/viajes',
      <String, dynamic>{
        'message': 'Saldo insuficiente: necesitas \$3,35 y tienes \$0,00.',
      },
      codigo: 422,
    );

    final sesion = sesionComo('passenger');

    try {
      await sesion.solicitarViaje(
        origen: const PuntoViaje(
          direccion: 'Origen',
          lat: 10.4961,
          lng: -66.8517,
        ),
        destino: const PuntoViaje(
          direccion: 'Destino',
          lat: 10.5003,
          lng: -66.8428,
        ),
        metodoPago: 'wallet',
      );
      fail('Debió lanzar ApiException.');
    } on ApiException catch (error) {
      expect(error.mensaje, contains('Saldo insuficiente'));
      expect(error.esValidacion, isTrue);
    }
  });

  test('lee el historial paginado del backend', () async {
    servidor.responder(
      'GET',
      '/api/viajes',
      <String, dynamic>{
        'data': <dynamic>[
          viajeJson(
            id: 20,
            estado: 'completed',
            etiqueta: 'Completado',
            conConductor: true,
          ),
          viajeJson(
            id: 19,
            estado: 'cancelled',
            etiqueta: 'Cancelado',
            puedeCancelar: false,
          ),
        ],
      },
    );

    final sesion = sesionComo('passenger');
    final viajes = await sesion.historialViajes();

    expect(viajes, hasLength(2));
    expect(viajes.first.id, 20);
    expect(viajes.first.completado, isTrue);
    expect(viajes.last.cancelado, isTrue);
  });

  test('el historial envía el período elegido a la API', () async {
    servidor.responder(
      'GET',
      '/api/viajes',
      <String, dynamic>{'data': <dynamic>[]},
    );

    final sesion = sesionComo('driver');

    await sesion.historialViajes(periodo: PeriodoHistorial.mes);
    expect(
      servidor.peticiones.last.uri.queryParameters['period'],
      'month',
    );

    await sesion.historialViajes();
    expect(
      servidor.peticiones.last.uri.queryParameters.containsKey('period'),
      isFalse,
      reason: 'Todos no debe enviar filtro.',
    );
  });

  test('el historial pide páginas adicionales y lee el paginador', () async {
    servidor.responder(
      'GET',
      '/api/viajes',
      <String, dynamic>{
        'data': <dynamic>[
          viajeJson(id: 8, origen: 'Origen de la segunda página'),
        ],
        'meta': <String, dynamic>{
          'current_page': 2,
          'last_page': 3,
        },
      },
    );

    final sesion = sesionComo('driver');
    final pagina = await sesion.historialViajesPaginado(
      periodo: PeriodoHistorial.semana,
      pagina: 2,
    );

    final peticion = servidor.peticiones.last;
    expect(peticion.uri.queryParameters['period'], 'week');
    expect(peticion.uri.queryParameters['page'], '2');
    expect(pagina.viajes.single.id, 8);
    expect(pagina.pagina, 2);
    expect(pagina.ultimaPagina, 3);
    expect(pagina.hayMas, isTrue);
  });

  test('buscar direcciones lee disponibilidad y resultados', () async {
    servidor.responder(
      'GET',
      '/api/ubicaciones/buscar',
      <String, dynamic>{
        'data': <String, dynamic>{
          'available': true,
          'results': <dynamic>[
            <String, dynamic>{
              'address': 'Plaza Venezuela, Caracas',
              'lat': 10.5003,
              'lng': -66.8428,
            },
          ],
        },
      },
    );

    final sesion = sesionComo('passenger');
    final busqueda = await sesion.buscarLugares('Plaza Venezuela');

    expect(busqueda.disponible, isTrue);
    expect(busqueda.lugares, hasLength(1));
    expect(busqueda.lugares.first.direccion, contains('Plaza Venezuela'));
  });

  test('califica el viaje y actualiza el viaje activo', () async {
    servidor.responder(
      'GET',
      '/api/viajes/activo',
      <String, dynamic>{
        'data': <String, dynamic>{
          'trip': viajeJson(
            id: 9,
            estado: 'completed',
            conConductor: true,
            puedoCalificar: true,
          ),
        },
      },
    );
    servidor.responder(
      'POST',
      '/api/viajes/9/calificar',
      <String, dynamic>{
        'data': <String, dynamic>{
          'trip': viajeJson(
            id: 9,
            estado: 'completed',
            conConductor: true,
            favorito: true,
            calificacion: <String, dynamic>{
              'rating': 5,
              'comment': 'Muy amable.',
            },
          ),
        },
      },
    );

    final sesion = sesionComo('passenger');
    await sesion.cargarViajeActivo();
    final viaje = await sesion.calificarViaje(
      9,
      estrellas: 5,
      comentario: 'Muy amable.',
      favorito: true,
    );

    final peticion = servidor.peticiones.last;
    expect(peticion.method, 'POST');
    expect(peticion.data['rating'], 5);
    expect(peticion.data['comment'], 'Muy amable.');
    expect(peticion.data['favorite'], isTrue);
    expect(viaje.calificacion?.estrellas, 5);
    expect(sesion.viajeActivo?.calificacion?.estrellas, 5);
    expect(sesion.viajeActivo?.conductor?.esFavorito, isTrue);
  });

  test('lista los conductores favoritos del pasajero', () async {
    servidor.responder(
      'GET',
      '/api/conductores-favoritos',
      <String, dynamic>{
        'data': <dynamic>[
          <String, dynamic>{
            'id': 8,
            'name': 'Pedro Rojas',
            'rating': 4.8,
            'ratings_count': 21,
            'trips_completed': 21,
            'vehicle': 'Bera BR 150 · AB123CD',
            'is_online': true,
          },
        ],
      },
    );

    final sesion = sesionComo('passenger');
    final favoritos = await sesion.conductoresFavoritos();

    expect(favoritos, hasLength(1));
    expect(favoritos.single.nombre, 'Pedro Rojas');
    expect(favoritos.single.calificacion, 4.8);
    expect(favoritos.single.enLinea, isTrue);
  });

  test('quitar un favorito usa DELETE', () async {
    servidor.responder(
      'DELETE',
      '/api/conductores-favoritos/8',
      <String, dynamic>{
        'data': <String, dynamic>{'is_favorite': false},
      },
    );

    final sesion = sesionComo('passenger');
    final estado = await sesion.alternarFavorito(8, false);

    expect(estado, isFalse);
    expect(servidor.peticiones.last.method, 'DELETE');
    expect(servidor.peticiones.last.uri.path, '/api/conductores-favoritos/8');
  });

  test('el conductor lee sus calificaciones recibidas', () async {
    servidor.responder(
      'GET',
      '/api/conductor/calificaciones',
      <String, dynamic>{
        'data': <dynamic>[
          <String, dynamic>{
            'rating': 5,
            'stars': '★★★★★',
            'comment': 'Excelente conducción.',
            'created_at': '2026-10-21T12:00:00-04:00',
          },
        ],
      },
    );

    final sesion = sesionComo('driver');
    final calificaciones = await sesion.calificacionesConductor();

    expect(calificaciones, hasLength(1));
    expect(calificaciones.single.estrellas, 5);
    expect(calificaciones.single.comentario, 'Excelente conducción.');
  });
}

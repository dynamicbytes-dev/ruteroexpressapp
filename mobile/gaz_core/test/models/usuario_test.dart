import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/data/models/usuario.dart';

void main() {
  test('lee la respuesta completa de GET /api/perfil', () {
    final perfil = PerfilCuenta.desdeJson(<String, dynamic>{
      'data': <String, dynamic>{
        'user': <String, dynamic>{
          'id': 12,
          'role': 'driver',
          'role_label': 'Conductor',
          'first_name': 'Pedro',
          'last_name': 'Rojas',
          'full_name': 'Pedro Rojas',
          'national_id': '14988776',
          'email': 'pedro.rojas@gmail.com',
          'phone': '04264567890',
          'address': 'Calle Sucre, casa 45, Maracay',
          'city': <String, dynamic>{
            'id': 2,
            'name': 'Barcelona',
            'state': 'Anzoátegui',
            'display_name': 'Barcelona (Anzoátegui)',
          },
          'status': 'pending',
          'status_label': 'En espera de verificación',
          'status_reason': null,
          'can_operate': false,
          'email_verified': false,
          'photo_url':
              'http://localhost:8000/api/perfil/foto/3?user=12&expires=1&signature=abc',
          'submitted_at': '2026-10-18T23:30:12-04:00',
          'approved_at': null,
          'created_at': '2026-10-18T23:12:02-04:00',
          'verification': <String, dynamic>{
            'required': 6,
            'uploaded': 6,
            'approved': 4,
            'pending': 1,
            'rejected': 1,
            'missing_labels': <String>[],
            'items': <Map<String, dynamic>>[
              <String, dynamic>{
                'type': 'cedula',
                'label': 'Foto de la cédula',
                'hint': 'Ambas caras de la cédula, nítidas y sin reflejos.',
                'uploaded': true,
                'status': 'approved',
                'status_label': 'Aprobado',
                'rejection_reason': null,
                'uploaded_at': '2026-10-18T23:30:12-04:00',
                'expires_at': null,
                'expired': false,
              },
              <String, dynamic>{
                'type': 'licencia_conducir',
                'label': 'Licencia de conducir',
                'hint':
                    'Licencia vigente, se debe leer la fecha de vencimiento.',
                'uploaded': true,
                'status': 'rejected',
                'status_label': 'Rechazado',
                'rejection_reason': 'La foto está borrosa, vuelve a tomarla.',
                'uploaded_at': '2026-10-18T23:30:12-04:00',
                'expires_at': '2027-05-01',
                'expired': false,
              },
              <String, dynamic>{
                'type': 'foto_vehiculo',
                'label': 'Foto del vehículo',
                'hint': 'Foto completa del vehículo, con la placa visible.',
                'uploaded': true,
                'status': 'approved',
                'status_label': 'Aprobado',
                'rejection_reason': null,
                'uploaded_at': '2026-10-18T23:30:12-04:00',
                'expires_at': null,
                'expired': false,
              },
            ],
          },
          'driver_profile': <String, dynamic>{
            'vehicle_type': 'moto',
            'vehicle_type_label': 'Moto',
            'vehicle_brand': 'Bera',
            'vehicle_model': 'BR 150',
            'vehicle_year': 2022,
            'vehicle_plate': 'AB123CD',
            'vehicle_description': 'Moto · Bera BR 150 2022 · AB123CD',
            'is_online': false,
            'city': <String, dynamic>{
              'id': 2,
              'name': 'Barcelona',
              'state': 'Anzoátegui',
            },
          },
        },
        'next_step': <String, dynamic>{
          'key': 'fix_documents',
          'message':
              'Uno de tus documentos fue rechazado. Revísalo y vuelve a subirlo.',
        },
      },
    });

    final usuario = perfil.usuario;

    expect(usuario.id, 12);
    expect(usuario.rol, 'driver');
    expect(usuario.rolEtiqueta, 'Conductor');
    expect(usuario.esConductor, isTrue);
    expect(usuario.iniciales, 'PR');
    expect(usuario.puedeOperar, isFalse);
    expect(usuario.correoVerificado, isFalse);
    expect(usuario.fotoUrl, contains('/api/perfil/foto/3'));
    expect(usuario.ciudad?.nombre, 'Barcelona');
    expect(usuario.ciudad?.etiqueta, 'Barcelona (Anzoátegui)');
    expect(usuario.enviadoEn, isNotNull);
    expect(usuario.vehiculo!.descripcion, 'Moto · Bera BR 150 2022 · AB123CD');
    expect(usuario.vehiculo!.tipoEtiqueta, 'Moto');
    expect(usuario.vehiculo!.enLinea, isFalse);
    expect(usuario.vehiculo!.ciudad?.nombre, 'Barcelona');

    expect(usuario.verificacion.requeridos, 6);
    expect(usuario.verificacion.subidos, 6);
    expect(usuario.verificacion.rechazados, 1);
    expect(usuario.verificacion.hayRechazado, isTrue);
    expect(usuario.verificacion.avance, 1.0);

    expect(perfil.siguientePaso.hayQueCorregir, isTrue);
    expect(perfil.siguientePaso.clave, 'fix_documents');
  });

  test('un documento rechazado se puede volver a subir y pide vencimiento', () {
    final documento = DocumentoEstado.desdeJson(<String, dynamic>{
      'type': 'rcv',
      'label': 'RCV (seguro del vehículo)',
      'hint': 'Póliza de responsabilidad civil vigente.',
      'uploaded': true,
      'status': 'rejected',
      'status_label': 'Rechazado',
      'rejection_reason': 'La póliza está vencida.',
      'uploaded_at': '2026-09-01T10:00:00-04:00',
      'expires_at': '2026-08-30',
      'expired': true,
    });

    expect(documento.rechazado, isTrue);
    expect(documento.vencido, isTrue);
    expect(documento.sePuedeSubir, isTrue);
    expect(documento.requiereVencimiento, isTrue);
    expect(documento.venceEn, isNotNull);
  });

  test('la cédula, la selfie y la foto del vehículo no piden vencimiento', () {
    for (final tipo in <String>['cedula', 'selfie', 'foto_vehiculo']) {
      final documento = DocumentoEstado.desdeJson(<String, dynamic>{
        'type': tipo,
        'label': tipo,
        'hint': '',
        'uploaded': false,
        'status': null,
        'status_label': 'Falta subirlo',
      });

      expect(documento.requiereVencimiento, isFalse, reason: tipo);
      expect(documento.sePuedeSubir, isTrue, reason: tipo);
      expect(documento.aprobado, isFalse, reason: tipo);
    }
  });

  test('sin documentos la cuenta solo puede subirlos', () {
    final perfil = PerfilCuenta.desdeJson(<String, dynamic>{
      'data': <String, dynamic>{
        'user': <String, dynamic>{
          'id': 1,
          'role': 'passenger',
          'role_label': 'Pasajero',
          'verification': <String, dynamic>{
            'required': 2,
            'uploaded': 0,
            'approved': 0,
            'pending': 0,
            'rejected': 0,
            'missing_labels': <String>['Foto de la cédula', 'Foto selfie'],
            'items': <Map<String, dynamic>>[],
          },
        },
        'next_step': <String, dynamic>{
          'key': 'upload_documents',
          'message': 'Completa tu registro subiendo los documentos que faltan.',
        },
      },
    });

    expect(perfil.usuario.verificacion.faltaSubir, isTrue);
    expect(
      perfil.usuario.verificacion.etiquetasFaltantes,
      <String>['Foto de la cédula', 'Foto selfie'],
    );
    expect(perfil.usuario.verificacion.avance, 0);
    expect(perfil.siguientePaso.faltaSubir, isTrue);
  });
}

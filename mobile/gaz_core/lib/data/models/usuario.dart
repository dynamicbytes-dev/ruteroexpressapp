/// Modelos de la cuenta tal como los devuelve el backend.
///
/// Reflejan `GET /api/perfil` (`backend/app/Http/Resources/UserResource.php`).
/// Ninguna de estas clases inventa datos: si un campo no viene, queda en `null`
/// y la pantalla lo trata como "sin información".
library;

import 'ciudad.dart';

/// Persona autenticada (pasajero o conductor).
class Usuario {
  const Usuario({
    required this.id,
    required this.rol,
    required this.rolEtiqueta,
    required this.nombres,
    required this.apellidos,
    required this.nombreCompleto,
    required this.cedula,
    required this.correo,
    required this.telefono,
    required this.direccion,
    required this.estado,
    required this.estadoEtiqueta,
    required this.puedeOperar,
    required this.correoVerificado,
    required this.verificacion,
    this.fotoUrl,
    this.ciudad,
    this.motivoEstado,
    this.enviadoEn,
    this.aprobadoEn,
    this.creadoEn,
    this.vehiculo,
  });

  final int id;
  final String rol;
  final String rolEtiqueta;
  final String nombres;
  final String apellidos;
  final String nombreCompleto;
  final String cedula;
  final String correo;
  final String telefono;
  final String direccion;
  final Ciudad? ciudad;
  final String estado;
  final String estadoEtiqueta;
  final String? motivoEstado;
  final bool puedeOperar;
  final bool correoVerificado;

  /// URL firmada de la selfie vigente, si la persona ya subió una imagen.
  /// Si es null, la app muestra las iniciales.
  final String? fotoUrl;

  final ResumenDocumentos verificacion;
  final PerfilConductor? vehiculo;
  final DateTime? enviadoEn;
  final DateTime? aprobadoEn;
  final DateTime? creadoEn;

  bool get esConductor => rol == 'driver';

  /// Iniciales para el avatar (por si el nombre viene vacío).
  String get iniciales {
    final partes = <String>[
      nombres.trim(),
      apellidos.trim(),
    ].where((parte) => parte.isNotEmpty).toList();

    if (partes.isEmpty) return '?';

    return partes
        .map((parte) => parte.substring(0, 1))
        .take(2)
        .join()
        .toUpperCase();
  }

  factory Usuario.desdeJson(Map<String, dynamic> json) {
    return Usuario(
      id: _entero(json['id']),
      rol: _texto(json['role']) ?? 'passenger',
      rolEtiqueta: _texto(json['role_label']) ?? '',
      nombres: _texto(json['first_name']) ?? '',
      apellidos: _texto(json['last_name']) ?? '',
      nombreCompleto: _texto(json['full_name']) ?? '',
      cedula: _texto(json['national_id']) ?? '',
      correo: _texto(json['email']) ?? '',
      telefono: _texto(json['phone']) ?? '',
      direccion: _texto(json['address']) ?? '',
      ciudad: json['city'] is Map ? Ciudad.desdeJson(json['city']) : null,
      estado: _texto(json['status']) ?? 'pending',
      estadoEtiqueta: _texto(json['status_label']) ?? '',
      motivoEstado: _texto(json['status_reason']),
      puedeOperar: json['can_operate'] == true,
      correoVerificado: json['email_verified'] == true,
      fotoUrl: _texto(json['photo_url']) ??
          _texto(json['avatar']) ??
          _texto(json['avatar_url']),
      enviadoEn: _fecha(json['submitted_at']),
      aprobadoEn: _fecha(json['approved_at']),
      creadoEn: _fecha(json['created_at']),
      verificacion: ResumenDocumentos.desdeJson(json['verification']),
      vehiculo: json['driver_profile'] is Map
          ? PerfilConductor.desdeJson(
              Map<String, dynamic>.from(json['driver_profile'] as Map),
            )
          : null,
    );
  }

  Usuario copiarCon({
    String? fotoUrl,
    String? nombres,
    String? apellidos,
    String? nombreCompleto,
    String? correo,
    String? telefono,
    String? direccion,
    Ciudad? ciudad,
    String? estado,
    String? estadoEtiqueta,
    String? motivoEstado,
    bool? puedeOperar,
    bool? correoVerificado,
    ResumenDocumentos? verificacion,
    PerfilConductor? vehiculo,
  }) {
    return Usuario(
      id: id,
      rol: rol,
      rolEtiqueta: rolEtiqueta,
      nombres: nombres ?? this.nombres,
      apellidos: apellidos ?? this.apellidos,
      nombreCompleto: nombreCompleto ?? this.nombreCompleto,
      cedula: cedula,
      correo: correo ?? this.correo,
      telefono: telefono ?? this.telefono,
      direccion: direccion ?? this.direccion,
      ciudad: ciudad ?? this.ciudad,
      estado: estado ?? this.estado,
      estadoEtiqueta: estadoEtiqueta ?? this.estadoEtiqueta,
      motivoEstado: motivoEstado ?? this.motivoEstado,
      puedeOperar: puedeOperar ?? this.puedeOperar,
      correoVerificado: correoVerificado ?? this.correoVerificado,
      verificacion: verificacion ?? this.verificacion,
      fotoUrl: fotoUrl ?? this.fotoUrl,
      enviadoEn: enviadoEn,
      aprobadoEn: aprobadoEn,
      creadoEn: creadoEn,
      vehiculo: vehiculo ?? this.vehiculo,
    );
  }
}

/// Cuántos documentos van por cada estado y cuáles faltan.
class ResumenDocumentos {
  const ResumenDocumentos({
    required this.requeridos,
    required this.subidos,
    required this.aprobados,
    required this.pendientes,
    required this.rechazados,
    required this.etiquetasFaltantes,
    required this.items,
  });

  final int requeridos;
  final int subidos;
  final int aprobados;
  final int pendientes;
  final int rechazados;
  final List<String> etiquetasFaltantes;
  final List<DocumentoEstado> items;

  bool get faltaSubir => etiquetasFaltantes.isNotEmpty;
  bool get hayRechazado => rechazados > 0;
  bool get todoAprobado => requeridos > 0 && aprobados >= requeridos;

  /// Avance de 0 a 1 para la barra de progreso (documentos enviados).
  double get avance => requeridos == 0 ? 0 : (subidos / requeridos).clamp(0, 1);

  factory ResumenDocumentos.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    return ResumenDocumentos(
      requeridos: _entero(json['required']),
      subidos: _entero(json['uploaded']),
      aprobados: _entero(json['approved']),
      pendientes: _entero(json['pending']),
      rechazados: _entero(json['rejected']),
      etiquetasFaltantes: _listaTexto(json['missing_labels']),
      items: (json['items'] is List)
          ? (json['items'] as List)
              .whereType<Map>()
              .map((cada) => DocumentoEstado.desdeJson(
                    Map<String, dynamic>.from(cada),
                  ))
              .toList()
          : const <DocumentoEstado>[],
    );
  }
}

/// Estado de un documento concreto de la lista de verificación.
class DocumentoEstado {
  const DocumentoEstado({
    required this.tipo,
    required this.etiqueta,
    required this.pista,
    required this.subido,
    required this.estadoEtiqueta,
    required this.vencido,
    this.estado,
    this.motivoRechazo,
    this.subidoEn,
    this.venceEn,
  });

  final String tipo;
  final String etiqueta;
  final String pista;
  final bool subido;
  final String? estado;
  final String estadoEtiqueta;
  final String? motivoRechazo;
  final DateTime? subidoEn;
  final DateTime? venceEn;
  final bool vencido;

  bool get aprobado => estado == 'approved';
  bool get rechazado => estado == 'rejected';
  bool get enRevision => estado == 'pending';

  /// Los tres documentos que el backend exige con fecha de vencimiento.
  bool get requiereVencimiento => const <String>{
        'licencia_conducir',
        'carnet_circulacion',
        'rcv'
      }.contains(tipo);

  /// Se puede subir si nunca se subió o si el auditor lo rechazó.
  bool get sePuedeSubir => !subido || rechazado;

  factory DocumentoEstado.desdeJson(Map<String, dynamic> json) {
    return DocumentoEstado(
      tipo: _texto(json['type']) ?? '',
      etiqueta: _texto(json['label']) ?? '',
      pista: _texto(json['hint']) ?? '',
      subido: json['uploaded'] == true,
      estado: _texto(json['status']),
      estadoEtiqueta: _texto(json['status_label']) ?? 'Falta subirlo',
      motivoRechazo: _texto(json['rejection_reason']),
      subidoEn: _fecha(json['uploaded_at']),
      venceEn: _fecha(json['expires_at']),
      vencido: json['expired'] == true,
    );
  }
}

/// Datos del vehículo (solo conductores).
class PerfilConductor {
  const PerfilConductor({
    required this.marca,
    required this.modelo,
    required this.anio,
    required this.placa,
    required this.descripcion,
    required this.enLinea,
    this.tipo = '',
    this.tipoEtiqueta = '',
    this.ciudad,
  });

  final String marca;
  final String modelo;
  final int? anio;
  final String placa;
  final String descripcion;
  final bool enLinea;
  final String tipo;
  final String tipoEtiqueta;
  final Ciudad? ciudad;

  factory PerfilConductor.desdeJson(Map<String, dynamic> json) {
    return PerfilConductor(
      marca: _texto(json['vehicle_brand']) ?? '',
      modelo: _texto(json['vehicle_model']) ?? '',
      anio: json['vehicle_year'] is num
          ? (json['vehicle_year'] as num).toInt()
          : null,
      placa: _texto(json['vehicle_plate']) ?? '',
      descripcion: _texto(json['vehicle_description']) ?? '',
      enLinea: json['is_online'] == true,
      tipo: _texto(json['vehicle_type']) ?? '',
      tipoEtiqueta: _texto(json['vehicle_type_label']) ?? '',
      ciudad: json['city'] is Map ? Ciudad.desdeJson(json['city']) : null,
    );
  }
}

/// Qué pantalla debe ver la persona según lo que le falta.
class SiguientePaso {
  const SiguientePaso({required this.clave, this.mensaje});

  final String clave;
  final String? mensaje;

  bool get faltaSubir => clave == 'upload_documents';
  bool get hayQueCorregir => clave == 'fix_documents';
  bool get enEspera => clave == 'waiting_review';
  bool get listo => clave == 'ready';
  bool get contactarSoporte => clave == 'contact_support';

  factory SiguientePaso.desdeJson(Object? crudo) {
    final json = crudo is Map
        ? Map<String, dynamic>.from(crudo)
        : const <String, dynamic>{};

    return SiguientePaso(
      clave: _texto(json['key']) ?? 'upload_documents',
      mensaje: _texto(json['message']),
    );
  }
}

/// Respuesta de `GET /api/perfil` y de la subida de un documento.
class PerfilCuenta {
  const PerfilCuenta({required this.usuario, required this.siguientePaso});

  final Usuario usuario;
  final SiguientePaso siguientePaso;

  factory PerfilCuenta.desdeJson(Map<String, dynamic> json) {
    final datos = json['data'] is Map
        ? Map<String, dynamic>.from(json['data'] as Map)
        : <String, dynamic>{};

    return PerfilCuenta(
      usuario: Usuario.desdeJson(
        datos['user'] is Map
            ? Map<String, dynamic>.from(datos['user'] as Map)
            : const <String, dynamic>{},
      ),
      siguientePaso: SiguientePaso.desdeJson(datos['next_step']),
    );
  }
}

/// Resultado de entrar o registrarse: el token y la persona.
class SesionIniciada {
  const SesionIniciada({
    required this.token,
    required this.usuario,
    this.mensaje,
  });

  final String token;
  final Usuario usuario;
  final String? mensaje;

  factory SesionIniciada.desdeJson(Map<String, dynamic> json) {
    final datos = json['data'] is Map
        ? Map<String, dynamic>.from(json['data'] as Map)
        : <String, dynamic>{};

    return SesionIniciada(
      token: _texto(datos['token']) ?? '',
      usuario: Usuario.desdeJson(
        datos['user'] is Map
            ? Map<String, dynamic>.from(datos['user'] as Map)
            : const <String, dynamic>{},
      ),
      mensaje: _texto(json['message']),
    );
  }
}

// ---------------------------------------------------------------------------
// Ayudas de lectura: el JSON de la API siempre es igual, pero conviene no
// reventar si un campo llega nulo o con otro tipo.
// ---------------------------------------------------------------------------

String? _texto(Object? valor) {
  if (valor == null) return null;
  final texto = '$valor'.trim();
  return texto.isEmpty ? null : texto;
}

int _entero(Object? valor) {
  if (valor is num) return valor.toInt();
  return int.tryParse('$valor') ?? 0;
}

DateTime? _fecha(Object? valor) {
  if (valor is! String || valor.isEmpty) return null;
  return DateTime.tryParse(valor);
}

List<String> _listaTexto(Object? valor) {
  if (valor is! List) return const <String>[];
  return valor.map((cada) => '$cada').toList();
}

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../core/ui/campos.dart';
import '../../../core/ui/marca.dart';
import '../../../core/ui/mensajes.dart';
import '../../../data/models/ciudad.dart';
import '../../../data/models/registro_datos.dart';
import '../../sesion/sesion_controller.dart';
import '../legal/terminos_sheet.dart';

/// Formulario de registro, con los campos extra del conductor.
///
/// Los documentos no se piden aquí: se suben después, uno por uno, con barra de
/// progreso. Al crear la cuenta la app ya queda dentro y muestra el paso que
/// toca (subir documentos / espera de verificación).
class RegistroScreen extends StatefulWidget {
  const RegistroScreen({super.key, required this.rol});

  /// `passenger` o `driver`.
  final String rol;

  @override
  State<RegistroScreen> createState() => _RegistroScreenState();
}

class _RegistroScreenState extends State<RegistroScreen> {
  final _nombres = TextEditingController();
  final _apellidos = TextEditingController();
  final _cedula = TextEditingController();
  final _correo = TextEditingController();
  final _telefono = TextEditingController();
  final _direccion = TextEditingController();
  final _contrasena = TextEditingController();
  final _confirmacion = TextEditingController();

  final _marca = TextEditingController();
  final _modelo = TextEditingController();
  final _anio = TextEditingController();
  final _placa = TextEditingController();

  Map<String, String> _errores = <String, String>{};
  bool _enviando = false;
  bool _verContrasena = false;
  bool _aceptaTerminos = false;

  Uint8List? _fotoBytes;
  String? _fotoNombre;

  List<Ciudad> _ciudades = const <Ciudad>[];
  int? _ciudadId;
  bool _cargandoCiudades = true;
  String? _errorCiudades;

  /// `moto` o `carro`: el conductor elige con qué vehículo trabajará.
  String _tipoVehiculo = 'moto';

  bool get _esConductor => widget.rol == 'driver';

  @override
  void initState() {
    super.initState();
    _cargarCiudades();
  }

  /// Las ciudades las define el backend: la app no mantiene una lista paralela
  /// que pueda quedar desactualizada.
  Future<void> _cargarCiudades() async {
    setState(() {
      _cargandoCiudades = true;
      _errorCiudades = null;
    });

    try {
      final ciudades = await context.read<SesionController>().ciudades();

      if (!mounted) return;

      setState(() {
        _ciudades = ciudades;

        // Si la ciudad elegida dejó de estar activa, hay que volver a elegir
        // para no enviar un id que el backend ya no acepta.
        if (_ciudadId != null &&
            !ciudades.any((ciudad) => ciudad.id == _ciudadId)) {
          _ciudadId = null;
        }

        _cargandoCiudades = false;
        _errorCiudades = ciudades.isEmpty
            ? 'No hay ciudades disponibles en este momento.'
            : null;
      });
    } on ApiException catch (error) {
      if (!mounted) return;

      setState(() {
        _cargandoCiudades = false;
        _errorCiudades = error.mensaje.isEmpty
            ? 'No pudimos cargar las ciudades.'
            : error.mensaje;
      });
    }
  }

  @override
  void dispose() {
    for (final controlador in <TextEditingController>[
      _nombres,
      _apellidos,
      _cedula,
      _correo,
      _telefono,
      _direccion,
      _contrasena,
      _confirmacion,
      _marca,
      _modelo,
      _anio,
      _placa,
    ]) {
      controlador.dispose();
    }

    super.dispose();
  }

  Future<void> _seleccionarFoto() async {
    final origen = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            ListTile(
              leading: const Icon(Icons.camera_alt_rounded),
              title: const Text('Tomar foto con la cámara'),
              onTap: () => Navigator.of(context).pop(ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_rounded),
              title: const Text('Elegir de la galería'),
              onTap: () => Navigator.of(context).pop(ImageSource.gallery),
            ),
          ],
        ),
      ),
    );

    if (origen == null) return;

    final picker = ImagePicker();
    final imagen = await picker.pickImage(
      source: origen,
      maxWidth: 1600,
      maxHeight: 1600,
      imageQuality: 85,
    );

    if (imagen == null) return;

    final bytes = await imagen.readAsBytes();
    if (bytes.lengthInBytes > 6 * 1024 * 1024) {
      if (mounted) {
        mostrarMensaje(context, 'La foto no debe pesar más de 6 MB.',
            error: true);
      }
      return;
    }

    setState(() {
      _fotoBytes = bytes;
      _fotoNombre = imagen.name.isNotEmpty ? imagen.name : 'avatar.jpg';
    });
  }

  // -------------------------------------------------------------------------
  // Envío
  // -------------------------------------------------------------------------

  Future<void> _enviar() async {
    final locales = _revisarLocalmente();

    if (locales.isNotEmpty) {
      setState(() => _errores = locales);
      mostrarMensaje(context, 'Revisa los datos marcados en rojo.',
          error: true);
      return;
    }

    setState(() {
      _errores = <String, String>{};
      _enviando = true;
    });

    ArchivoSubida? avatar;
    if (_fotoBytes != null) {
      avatar = ArchivoSubida.desdeBytes(
        nombre: _fotoNombre ?? 'avatar.jpg',
        bytes: _fotoBytes!,
      );
    }

    try {
      await context.read<SesionController>().registrarse(
            RegistroDatos(
              rol: widget.rol,
              nombres: _nombres.text,
              apellidos: _apellidos.text,
              cedula: _cedula.text,
              correo: _correo.text,
              telefono: _telefono.text,
              direccion: _direccion.text,
              contrasena: _contrasena.text,
              aceptaTerminos: _aceptaTerminos,
              ciudadId: _ciudadId,
              tipoVehiculo: _tipoVehiculo,
              marcaVehiculo: _marca.text,
              modeloVehiculo: _modelo.text,
              anioVehiculo: _anio.text,
              placaVehiculo: _placa.text,
            ),
            avatar: avatar,
          );

      if (!mounted) return;

      // La app ya entra: se quitan las pantallas de registro y se muestra la
      // que corresponde (subir documentos o espera de verificación).
      Navigator.of(context).popUntil((ruta) => ruta.isFirst);
    } on ApiException catch (error) {
      if (!mounted) return;

      final mapeados = <String, String>{};
      error.errores.forEach((campo, mensajes) {
        final clave = RegistroDatos.campoDelFormulario[campo];
        if (clave != null && mensajes.isNotEmpty) {
          mapeados[clave] = mensajes.first;
        }
      });

      setState(() => _errores = mapeados);

      final general = mapeados.isEmpty
          ? (error.errorGeneral ?? error.mensaje)
          : 'Revisa los datos marcados en rojo.';

      mostrarMensaje(context, general, error: true);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  /// Lo que se puede revisar sin molestar al backend.
  Map<String, String> _revisarLocalmente() {
    final errores = <String, String>{};

    if (_nombres.text.trim().length < 2) {
      errores['nombres'] = 'Escribe tu nombre (mínimo 2 letras).';
    }
    if (_apellidos.text.trim().length < 2) {
      errores['apellidos'] = 'Escribe tu apellido (mínimo 2 letras).';
    }

    final cedula = _cedula.text.trim();
    if (cedula.length < 6 || cedula.length > 9) {
      errores['cedula'] = 'La cédula debe tener entre 6 y 9 dígitos.';
    }

    final correo = _correo.text.trim().toLowerCase();
    if (correo.isEmpty || !correo.contains('@')) {
      errores['correo'] = 'Escribe tu correo electrónico.';
    } else if (!correo.endsWith('@gmail.com')) {
      errores['correo'] =
          'Por ahora solo aceptamos correos de Gmail (@gmail.com).';
    }

    if (_telefono.text.trim().length < 10) {
      errores['telefono'] = 'Escribe tu teléfono, por ejemplo 0412-1234567.';
    }

    if (_direccion.text.trim().length < 10) {
      errores['direccion'] =
          'Escribe tu dirección con más detalle (calle, casa, ciudad).';
    }

    if (_ciudadId == null) {
      errores['ciudad'] = _esConductor
          ? 'Elige la ciudad donde vas a operar.'
          : 'Elige la ciudad donde vas a usar GAZ.';
    }

    if (_contrasena.text.length < 8) {
      errores['contrasena'] = 'La contraseña debe tener al menos 8 caracteres.';
    } else if (!RegExp(r'[A-Za-z]').hasMatch(_contrasena.text) ||
        !RegExp(r'\d').hasMatch(_contrasena.text)) {
      errores['contrasena'] = 'Debe incluir al menos una letra y un número.';
    }

    if (_confirmacion.text != _contrasena.text) {
      errores['confirmacion'] = 'Las dos contraseñas no coinciden.';
    }

    if (!_aceptaTerminos) {
      errores['terminos'] = 'Debes aceptar los términos y condiciones.';
    }

    if (_esConductor) {
      if (_marca.text.trim().length < 2) {
        errores['marcaVehiculo'] =
            'Escribe la marca de tu vehículo (Honda, Toyota...).';
      }
      if (_modelo.text.trim().isEmpty) {
        errores['modeloVehiculo'] = 'Escribe el modelo de tu vehículo.';
      }

      final anio = int.tryParse(_anio.text.trim());
      final anioMaximo = DateTime.now().year + 1;
      if (anio == null || anio < 1990 || anio > anioMaximo) {
        errores['anioVehiculo'] = 'El año debe estar entre 1990 y $anioMaximo.';
      }

      if (_placa.text.trim().length < 4) {
        errores['placaVehiculo'] = 'Escribe la placa de tu vehículo.';
      }
    }

    return errores;
  }

  // -------------------------------------------------------------------------
  // Pantalla
  // -------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return PantallaMarca(
      mostrarRetroceso: true,
      hijos: <Widget>[
        Text(
          _esConductor ? 'Cuenta de conductor' : 'Cuenta de pasajero',
          style: MotorutaTheme.tituloClaro(tamano: 30),
        ),
        const SizedBox(height: 10),
        Text(
          _esConductor
              ? 'Llena tus datos y los de tu vehículo. Después subirás tus documentos y la foto de tu vehículo para que el equipo los revise.'
              : 'Llena tus datos. Después subirás tu cédula y una selfie para que el equipo los revise.',
          style: MotorutaTheme.subtituloClaro,
        ),
        const SizedBox(height: 24),
        Tarjeta(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text('Tus datos',
                  style: MotorutaTheme.tituloDe(context, tamano: 18)),
              const SizedBox(height: 16),
              Center(
                child: Stack(
                  clipBehavior: Clip.none,
                  children: <Widget>[
                    CircleAvatar(
                      radius: 46,
                      backgroundColor: context.gaz.border,
                      backgroundImage:
                          _fotoBytes != null ? MemoryImage(_fotoBytes!) : null,
                      child: _fotoBytes == null
                          ? Icon(
                              Icons.person_rounded,
                              size: 48,
                              color: context.gaz.text3,
                            )
                          : null,
                    ),
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: Material(
                        color: MotorutaColors.brand800,
                        shape: const CircleBorder(),
                        elevation: 2,
                        child: InkWell(
                          customBorder: const CircleBorder(),
                          onTap: _enviando ? null : _seleccionarFoto,
                          child: const Padding(
                            padding: EdgeInsets.all(8.0),
                            child: Icon(
                              Icons.camera_alt_rounded,
                              size: 20,
                              color: Colors.white,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              Center(
                child: Text(
                  _fotoNombre != null
                      ? 'Foto seleccionada'
                      : 'Foto de perfil / Selfie (opcional)',
                  style: TextStyle(
                    fontSize: 13,
                    color: context.gaz.text2,
                  ),
                ),
              ),
              const SizedBox(height: 16),
              CampoTexto(
                controlador: _nombres,
                etiqueta: 'Nombre',
                pista: 'Pedro José',
                icono: Icons.person_outline_rounded,
                error: _errores['nombres'],
                maxLongitud: 80,
                autocompletado: const <String>[AutofillHints.givenName],
              ),
              const SizedBox(height: 14),
              CampoTexto(
                controlador: _apellidos,
                etiqueta: 'Apellido',
                pista: 'Rojas Pérez',
                icono: Icons.person_outline_rounded,
                error: _errores['apellidos'],
                maxLongitud: 80,
                autocompletado: const <String>[AutofillHints.familyName],
              ),
              const SizedBox(height: 14),
              CampoTexto(
                controlador: _cedula,
                etiqueta: 'Cédula',
                pista: '14988776',
                textoAyuda: 'Solo números, sin puntos.',
                icono: Icons.badge_outlined,
                error: _errores['cedula'],
                tipoTeclado: TextInputType.number,
                maxLongitud: 9,
                formateadores: <TextInputFormatter>[
                  FilteringTextInputFormatter.digitsOnly,
                ],
              ),
              const SizedBox(height: 14),
              CampoTexto(
                controlador: _correo,
                etiqueta: 'Correo electrónico',
                pista: 'pedro.rojas@gmail.com',
                textoAyuda: 'Por ahora solo correos de Gmail.',
                icono: Icons.alternate_email_rounded,
                error: _errores['correo'],
                tipoTeclado: TextInputType.emailAddress,
                maxLongitud: 255,
                autocompletado: const <String>[AutofillHints.email],
              ),
              const SizedBox(height: 14),
              CampoTexto(
                controlador: _telefono,
                etiqueta: 'Teléfono',
                pista: '0412-1234567',
                icono: Icons.phone_outlined,
                error: _errores['telefono'],
                tipoTeclado: TextInputType.phone,
                maxLongitud: 18,
                autocompletado: const <String>[AutofillHints.telephoneNumber],
              ),
              const SizedBox(height: 14),
              CampoTexto(
                controlador: _direccion,
                etiqueta: 'Dirección',
                pista: 'Calle Sucre, casa 45, Maracay',
                icono: Icons.home_outlined,
                error: _errores['direccion'],
                maxLongitud: 255,
                maxLineas: 2,
                accionTexto: TextInputAction.newline,
              ),
              const SizedBox(height: 14),
              if (_cargandoCiudades)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Row(
                    children: <Widget>[
                      SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      ),
                      SizedBox(width: 12),
                      Text('Cargando ciudades...'),
                    ],
                  ),
                )
              else if (_errorCiudades != null)
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      _errorCiudades!,
                      style: const TextStyle(
                        fontSize: 12.5,
                        color: MotorutaColors.rojo,
                      ),
                    ),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: TextButton(
                        onPressed: _cargarCiudades,
                        child: const Text('Reintentar'),
                      ),
                    ),
                  ],
                )
              else
                DropdownButtonFormField<int>(
                  value: _ciudadId,
                  isExpanded: true,
                  hint: const Text('Elige una ciudad'),
                  decoration: InputDecoration(
                    labelText: _esConductor
                        ? 'Ciudad donde vas a operar'
                        : 'Ciudad donde usarás la app',
                    helperText: _esConductor
                        ? 'Solo recibirás viajes de esta zona.'
                        : 'Los viajes que pidas serán de esta ciudad.',
                    errorText: _errores['ciudad'],
                    prefixIcon:
                        const Icon(Icons.location_city_outlined, size: 20),
                  ),
                  items: _ciudades
                      .map(
                        (ciudad) => DropdownMenuItem<int>(
                          value: ciudad.id,
                          child: Text(
                            ciudad.etiqueta,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (valor) {
                    setState(() {
                      _ciudadId = valor;
                      _errores.remove('ciudad');
                    });
                  },
                ),
            ],
          ),
        ),
        if (_esConductor) ...<Widget>[
          const SizedBox(height: 18),
          Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text('Tu vehículo',
                    style: MotorutaTheme.tituloDe(context, tamano: 18)),
                const SizedBox(height: 6),
                Text(
                  'Elige con qué vehículo vas a hacer los viajes.',
                  style: TextStyle(fontSize: 13.5, color: context.gaz.text2),
                ),
                const SizedBox(height: 14),
                SegmentedButton<String>(
                  showSelectedIcon: false,
                  style: SegmentedButton.styleFrom(
                    selectedBackgroundColor: MotorutaColors.brand800,
                    selectedForegroundColor: Colors.white,
                    foregroundColor: context.gaz.text2,
                    side: BorderSide(
                      color: context.gaz.border,
                      width: 1.2,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    textStyle: const TextStyle(
                      fontWeight: FontWeight.w600,
                      fontSize: 14,
                    ),
                  ),
                  segments: const <ButtonSegment<String>>[
                    ButtonSegment<String>(
                      value: 'moto',
                      label: Text('Moto'),
                      icon: Icon(Icons.two_wheeler_outlined),
                    ),
                    ButtonSegment<String>(
                      value: 'carro',
                      label: Text('Carro'),
                      icon: Icon(Icons.directions_car_outlined),
                    ),
                  ],
                  selected: <String>{_tipoVehiculo},
                  onSelectionChanged: (seleccion) =>
                      setState(() => _tipoVehiculo = seleccion.first),
                ),
                if (_errores['tipoVehiculo'] != null) ...<Widget>[
                  const SizedBox(height: 8),
                  Text(
                    _errores['tipoVehiculo']!,
                    style: const TextStyle(
                        fontSize: 12.5, color: MotorutaColors.rojo),
                  ),
                ],
                const SizedBox(height: 16),
                CampoTexto(
                  controlador: _marca,
                  etiqueta: 'Marca',
                  pista: _tipoVehiculo == 'moto' ? 'Bera' : 'Toyota',
                  icono: _tipoVehiculo == 'moto'
                      ? Icons.two_wheeler_outlined
                      : Icons.directions_car_outlined,
                  error: _errores['marcaVehiculo'],
                  maxLongitud: 60,
                ),
                const SizedBox(height: 14),
                CampoTexto(
                  controlador: _modelo,
                  etiqueta: 'Modelo',
                  pista: _tipoVehiculo == 'moto' ? 'BR 150' : 'Corolla',
                  icono: Icons.pin_outlined,
                  error: _errores['modeloVehiculo'],
                  maxLongitud: 60,
                ),
                const SizedBox(height: 14),
                CampoTexto(
                  controlador: _anio,
                  etiqueta: 'Año',
                  pista: '2022',
                  icono: Icons.calendar_today_outlined,
                  error: _errores['anioVehiculo'],
                  tipoTeclado: TextInputType.number,
                  maxLongitud: 4,
                  formateadores: <TextInputFormatter>[
                    FilteringTextInputFormatter.digitsOnly,
                  ],
                ),
                const SizedBox(height: 14),
                CampoTexto(
                  controlador: _placa,
                  etiqueta: 'Placa',
                  pista: 'AB123CD',
                  textoAyuda: 'Se guarda en mayúsculas y sin guiones.',
                  icono: Icons.confirmation_number_outlined,
                  error: _errores['placaVehiculo'],
                  mayusculas: true,
                  maxLongitud: 12,
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 18),
        Tarjeta(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text('Tu clave',
                  style: MotorutaTheme.tituloDe(context, tamano: 18)),
              const SizedBox(height: 16),
              CampoTexto(
                controlador: _contrasena,
                etiqueta: 'Contraseña',
                textoAyuda: 'Mínimo 8 caracteres, con una letra y un número.',
                icono: Icons.lock_outline_rounded,
                error: _errores['contrasena'],
                ocultarTexto: !_verContrasena,
                sufijo: IconButton(
                  onPressed: () =>
                      setState(() => _verContrasena = !_verContrasena),
                  icon: Icon(
                    _verContrasena
                        ? Icons.visibility_off_outlined
                        : Icons.visibility_outlined,
                    size: 20,
                  ),
                  tooltip: _verContrasena ? 'Ocultar' : 'Mostrar',
                ),
              ),
              const SizedBox(height: 14),
              CampoTexto(
                controlador: _confirmacion,
                etiqueta: 'Repite la contraseña',
                icono: Icons.lock_outline_rounded,
                error: _errores['confirmacion'],
                ocultarTexto: !_verContrasena,
                accionTexto: TextInputAction.done,
                alEnviar: (_) => _enviar(),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        Tarjeta(
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Checkbox(
                    value: _aceptaTerminos,
                    onChanged: (valor) {
                      setState(() {
                        _aceptaTerminos = valor ?? false;
                        if (_aceptaTerminos) _errores.remove('terminos');
                      });
                    },
                  ),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: Text(
                        'Acepto los términos y condiciones y la política de privacidad.',
                        style: TextStyle(
                          fontSize: 14,
                          height: 1.4,
                          color: context.gaz.text2,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              if (_errores['terminos'] != null) ...<Widget>[
                const SizedBox(height: 4),
                Text(
                  _errores['terminos']!,
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: MotorutaColors.rojo,
                  ),
                ),
              ],
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: () => mostrarTerminos(context),
                  icon: const Icon(Icons.description_outlined, size: 18),
                  label: const Text('Leer términos y privacidad'),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        BotonPrincipal(
          texto: 'Crear mi cuenta',
          claro: true,
          cargando: _enviando,
          icono: Icons.check_rounded,
          alPresionar: _enviar,
        ),
        const SizedBox(height: 16),
        const Aviso(
          tono: Tono.neutro,
          icono: Icons.schedule_rounded,
          texto:
              'Tu cuenta queda "en espera de verificación" hasta que el equipo apruebe tus documentos. Mientras tanto puedes entrar a la app y ver en qué va todo.',
        ),
      ],
    );
  }
}

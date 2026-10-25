import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/avatar_usuario.dart';
import '../../../core/ui/botones.dart';
import '../../../core/ui/campos.dart';
import '../../../core/ui/mensajes.dart';
import '../../sesion/sesion_controller.dart';

/// Edición de los datos personales.
///
/// La cédula se muestra bloqueada: es la llave de identidad y solo el auditor
/// puede corregirla con un motivo. El correo se puede cambiar, pero exige la
/// contraseña actual y vuelve a quedar pendiente de verificación.
class MisDatosScreen extends StatefulWidget {
  const MisDatosScreen({super.key});

  @override
  State<MisDatosScreen> createState() => _MisDatosScreenState();
}

class _MisDatosScreenState extends State<MisDatosScreen> {
  late final TextEditingController _nombres;
  late final TextEditingController _apellidos;
  late final TextEditingController _cedula;
  late final TextEditingController _telefono;
  late final TextEditingController _direccion;
  late final TextEditingController _correo;
  final TextEditingController _contrasenaActual = TextEditingController();

  late String _correoInicial;
  bool _guardando = false;
  bool _subiendoFoto = false;
  bool _mostrarContrasena = false;
  String? _error;
  String? _exito;
  Map<String, List<String>> _errores = const <String, List<String>>{};

  @override
  void initState() {
    super.initState();

    final usuario = context.read<SesionController>().usuario;
    _nombres = TextEditingController(text: usuario?.nombres ?? '');
    _apellidos = TextEditingController(text: usuario?.apellidos ?? '');
    _cedula = TextEditingController(text: usuario?.cedula ?? '');
    _telefono = TextEditingController(text: usuario?.telefono ?? '');
    _direccion = TextEditingController(text: usuario?.direccion ?? '');
    _correoInicial = usuario?.correo ?? '';
    _correo = TextEditingController(text: _correoInicial);
  }

  @override
  void dispose() {
    _nombres.dispose();
    _apellidos.dispose();
    _cedula.dispose();
    _telefono.dispose();
    _direccion.dispose();
    _correo.dispose();
    _contrasenaActual.dispose();
    super.dispose();
  }

  bool get _correoCambio =>
      _correo.text.trim().toLowerCase() != _correoInicial.toLowerCase();

  String get _ciudadEtiqueta {
    final usuario = context.read<SesionController>().usuario;

    // Para un conductor manda su zona de operación (puede haber cambiado por
    // solicitud al auditor); el `city` de la cuenta es la ciudad de registro.
    return usuario?.vehiculo?.ciudad?.etiqueta ??
        usuario?.ciudad?.etiqueta ??
        'Sin ciudad asignada';
  }

  bool get _esConductor =>
      context.read<SesionController>().usuario?.esConductor ?? false;

  Future<void> _cambiarFoto() async {
    final origen = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: <Widget>[
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Tomar foto con la cámara'),
              onTap: () => Navigator.of(context).pop(ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Elegir de la galería'),
              onTap: () => Navigator.of(context).pop(ImageSource.gallery),
            ),
          ],
        ),
      ),
    );

    if (origen == null || !mounted) return;

    try {
      final elegido = await ImagePicker().pickImage(
        source: origen,
        imageQuality: 88,
        maxWidth: 2000,
      );

      if (elegido == null || !mounted) return;

      final bytes = await elegido.readAsBytes();
      if (bytes.length > 6 * 1024 * 1024) {
        mostrarMensaje(
          context,
          'La foto pesa más de 6 MB. Elige otra imagen.',
          error: true,
        );
        return;
      }

      setState(() {
        _subiendoFoto = true;
        _error = null;
      });

      final usuarioActual = context.read<SesionController>().usuario;
      final campos = <String, String>{
        'first_name': _nombres.text.trim().isNotEmpty
            ? _nombres.text.trim()
            : (usuarioActual?.nombres ?? ''),
        'last_name': _apellidos.text.trim().isNotEmpty
            ? _apellidos.text.trim()
            : (usuarioActual?.apellidos ?? ''),
        'phone': _telefono.text.trim().isNotEmpty
            ? _telefono.text.trim()
            : (usuarioActual?.telefono ?? ''),
        'address': _direccion.text.trim().isNotEmpty
            ? _direccion.text.trim()
            : (usuarioActual?.direccion ?? ''),
        if (usuarioActual?.correo != null && usuarioActual!.correo.isNotEmpty)
          'email': usuarioActual.correo,
      };

      final nombre = elegido.name.isNotEmpty ? elegido.name : 'avatar.jpg';
      final resultado = await context.read<SesionController>().subirAvatar(
            ArchivoSubida.desdeBytes(
              nombre: nombre.contains('.') ? nombre : '$nombre.jpg',
              bytes: bytes,
            ),
            campos: campos,
          );

      if (!mounted) return;
      mostrarMensaje(context, resultado.mensaje ?? 'Foto de perfil actualizada.');
    } on ApiException catch (e) {
      if (!mounted) return;
      mostrarMensaje(context, e.errorDe('avatar') ?? e.mensaje, error: true);
    } catch (_) {
      if (!mounted) return;
      mostrarMensaje(
        context,
        'No pudimos abrir la cámara o la galería.',
        error: true,
      );
    } finally {
      if (mounted) setState(() => _subiendoFoto = false);
    }
  }

  Future<void> _guardar() async {
    final nombres = _nombres.text.trim();
    final apellidos = _apellidos.text.trim();
    final telefono = _telefono.text.trim();
    final direccion = _direccion.text.trim();
    final correo = _correo.text.trim().toLowerCase();
    final contrasena = _contrasenaActual.text;

    if (nombres.length < 2 || apellidos.length < 2) {
      setState(() => _error = 'Escribe tu nombre y tu apellido.');
      return;
    }

    if (telefono.length < 10) {
      setState(() => _error = 'Escribe un teléfono válido.');
      return;
    }

    if (direccion.length < 8) {
      setState(() => _error = 'Escribe una dirección completa.');
      return;
    }

    if (_correoCambio && contrasena.isEmpty) {
      setState(
        () => _error = 'Escribe tu contraseña actual para cambiar el correo.',
      );
      return;
    }

    setState(() {
      _guardando = true;
      _error = null;
      _exito = null;
      _errores = const <String, List<String>>{};
    });

    try {
      final resultado = await context.read<SesionController>().actualizarPerfil(
            nombres: nombres,
            apellidos: apellidos,
            telefono: telefono,
            direccion: direccion,
            correo: _correoCambio ? correo : null,
            contrasenaActual: _correoCambio ? contrasena : null,
          );

      if (!mounted) return;

      setState(() {
        _correoInicial = correo;
        _contrasenaActual.clear();
        _exito = resultado.mensaje ??
            (resultado.correoVerificacionEnviado
                ? 'Datos actualizados. Revisa tu correo para confirmar la dirección nueva.'
                : 'Datos actualizados.');
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.errores.isEmpty ? error.mensaje : null;
        _errores = error.errores;
      });
    } finally {
      if (mounted) setState(() => _guardando = false);
    }
  }

  String? _errorDe(String campo) {
    final lista = _errores[campo];
    return (lista == null || lista.isEmpty) ? null : lista.first;
  }

  @override
  Widget build(BuildContext context) {
    final usuario = context.watch<SesionController>().usuario;

    return Scaffold(
      appBar: AppBar(title: const Text('Mis datos')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 36),
        children: <Widget>[
          Tarjeta(
            child: Column(
              children: <Widget>[
                Center(
                  child: Stack(
                    alignment: Alignment.center,
                    children: <Widget>[
                      if (usuario != null)
                        AvatarUsuario(usuario: usuario, tamano: 92),
                      if (_subiendoFoto)
                        Container(
                          width: 92,
                          height: 92,
                          decoration: BoxDecoration(
                            color: Colors.black.withOpacity(0.45),
                            shape: BoxShape.circle,
                          ),
                          child: const Center(
                            child: SizedBox(
                              width: 28,
                              height: 28,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.5,
                                color: Colors.white,
                              ),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                TextButton.icon(
                  onPressed: _subiendoFoto ? null : _cambiarFoto,
                  icon: const Icon(Icons.camera_alt_outlined, size: 20),
                  label: Text(_subiendoFoto
                      ? 'Subiendo foto...'
                      : (usuario?.fotoUrl == null
                          ? 'Subir foto de perfil'
                          : 'Cambiar foto de perfil')),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  'Datos personales',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 6),
                const Text(
                  'La cédula es de solo lectura. Si tiene un error, el equipo debe corregirla con un registro de auditoría.',
                  style: TextStyle(fontSize: 12.5, height: 1.4),
                ),
                const SizedBox(height: 16),
                CampoTexto(
                  controlador: _cedula,
                  etiqueta: 'Cédula de identidad',
                  prefijo: 'V-',
                  icono: Icons.badge_outlined,
                  habilitado: false,
                ),
                const SizedBox(height: 14),
                CampoTexto(
                  controlador: _nombres,
                  etiqueta: 'Nombres',
                  icono: Icons.person_outline_rounded,
                  error: _errorDe('first_name'),
                  autocompletado: const <String>[AutofillHints.givenName],
                ),
                const SizedBox(height: 14),
                CampoTexto(
                  controlador: _apellidos,
                  etiqueta: 'Apellidos',
                  icono: Icons.person_outline_rounded,
                  error: _errorDe('last_name'),
                  autocompletado: const <String>[AutofillHints.familyName],
                ),
                const SizedBox(height: 14),
                CampoTexto(
                  controlador: _telefono,
                  etiqueta: 'Teléfono',
                  icono: Icons.phone_outlined,
                  tipoTeclado: TextInputType.phone,
                  error: _errorDe('phone'),
                  autocompletado: const <String>[AutofillHints.telephoneNumber],
                  formateadores: <TextInputFormatter>[
                    FilteringTextInputFormatter.allow(RegExp(r'[0-9+\s-]')),
                  ],
                ),
                const SizedBox(height: 14),
                CampoTexto(
                  controlador: _direccion,
                  etiqueta: 'Dirección',
                  icono: Icons.home_outlined,
                  maxLineas: 2,
                  error: _errorDe('address'),
                  autocompletado: const <String>[
                    AutofillHints.streetAddressLine1
                  ],
                ),
                const SizedBox(height: 14),
                InputDecorator(
                  decoration: InputDecoration(
                    labelText: _esConductor ? 'Zona de operación' : 'Ciudad',
                    helperText: _esConductor
                        ? 'Solo el auditor puede cambiarla si lo solicitas.'
                        : 'Se define al registrarte. Si necesitas cambiarla, escríbenos.',
                    prefixIcon:
                        const Icon(Icons.location_city_outlined, size: 20),
                  ),
                  child: Text(
                    _ciudadEtiqueta,
                    style: const TextStyle(fontWeight: FontWeight.w500),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  'Correo electrónico',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 6),
                const Text(
                  'Si cambias el correo, te enviaremos un enlace de verificación. Mientras no lo confirmes, la dirección nueva queda pendiente.',
                  style: TextStyle(fontSize: 12.5, height: 1.4),
                ),
                const SizedBox(height: 14),
                CampoTexto(
                  controlador: _correo,
                  etiqueta: 'Correo',
                  icono: Icons.alternate_email_rounded,
                  tipoTeclado: TextInputType.emailAddress,
                  error: _errorDe('email'),
                  autocompletado: const <String>[AutofillHints.email],
                  alCambiar: (_) => setState(() {}),
                ),
                if (_correoCambio) ...<Widget>[
                  const SizedBox(height: 14),
                  CampoTexto(
                    controlador: _contrasenaActual,
                    etiqueta: 'Contraseña actual',
                    icono: Icons.lock_outline_rounded,
                    ocultarTexto: !_mostrarContrasena,
                    error: _errorDe('current_password'),
                    autocompletado: const <String>[AutofillHints.password],
                    sufijo: IconButton(
                      onPressed: () => setState(
                        () => _mostrarContrasena = !_mostrarContrasena,
                      ),
                      icon: Icon(
                        _mostrarContrasena
                            ? Icons.visibility_off_outlined
                            : Icons.visibility_outlined,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (_error != null) ...<Widget>[
            const SizedBox(height: 14),
            Aviso(tono: Tono.error, texto: _error!),
          ],
          if (_exito != null) ...<Widget>[
            const SizedBox(height: 14),
            Aviso(
              tono: Tono.exito,
              titulo: 'Datos guardados',
              texto: _exito!,
            ),
          ],
          const SizedBox(height: 22),
          BotonPrincipal(
            texto: 'Guardar cambios',
            icono: Icons.save_outlined,
            cargando: _guardando,
            alPresionar: _guardando ? null : _guardar,
          ),
        ],
      ),
    );
  }
}

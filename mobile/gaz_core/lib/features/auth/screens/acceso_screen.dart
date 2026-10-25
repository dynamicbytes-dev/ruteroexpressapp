import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../core/ui/campos.dart';
import '../../../core/ui/marca.dart';
import '../../../core/ui/mensajes.dart';
import '../../sesion/sesion_controller.dart';
import 'elegir_rol_screen.dart';
import 'recuperar_screen.dart';

/// Entrada con correo y contraseña.
///
/// Una cuenta en espera de verificación **sí entra**: la app le muestra su
/// estado y lo que le falta.
class AccesoScreen extends StatefulWidget {
  const AccesoScreen({super.key});

  @override
  State<AccesoScreen> createState() => _AccesoScreenState();
}

class _AccesoScreenState extends State<AccesoScreen> {
  final _correo = TextEditingController();
  final _contrasena = TextEditingController();

  Map<String, String> _errores = <String, String>{};
  bool _enviando = false;
  bool _verContrasena = false;

  @override
  void dispose() {
    _correo.dispose();
    _contrasena.dispose();
    super.dispose();
  }

  Future<void> _entrar() async {
    final errores = <String, String>{};

    if (_correo.text.trim().isEmpty) {
      errores['correo'] = 'Escribe tu correo.';
    }
    if (_contrasena.text.isEmpty) {
      errores['contrasena'] = 'Escribe tu contraseña.';
    }

    if (errores.isNotEmpty) {
      setState(() => _errores = errores);
      return;
    }

    setState(() {
      _errores = <String, String>{};
      _enviando = true;
    });

    try {
      await context.read<SesionController>().entrar(
            correo: _correo.text,
            contrasena: _contrasena.text,
          );

      if (!mounted) return;
      Navigator.of(context).popUntil((ruta) => ruta.isFirst);
    } on ApiException catch (error) {
      if (!mounted) return;

      setState(() {
        _errores = <String, String>{
          if (error.errorDe('email') != null) 'correo': error.errorDe('email')!,
          if (error.errorDe('password') != null)
            'contrasena': error.errorDe('password')!,
        };
      });

      if (_errores.isEmpty) {
        mostrarMensaje(context, error.mensaje, error: true);
      }
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PantallaMarca(
      mostrarRetroceso: true,
      hijos: <Widget>[
        const SizedBox(height: 4),
        const Center(child: LogotipoMarca(tamanoIsotipo: 44, tamanoTexto: 30)),
        const SizedBox(height: 26),
        Text('Entrar', style: MotorutaTheme.tituloClaro(tamano: 30)),
        const SizedBox(height: 8),
        const Text(
          'Bienvenido de vuelta. Tu cuenta y tus viajes te esperan.',
          style: TextStyle(fontSize: 15, color: Colors.white70, height: 1.45),
        ),
        const SizedBox(height: 24),
        Tarjeta(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              CampoTexto(
                controlador: _correo,
                etiqueta: 'Correo electrónico',
                pista: 'pedro.rojas@gmail.com',
                icono: Icons.alternate_email_rounded,
                error: _errores['correo'],
                tipoTeclado: TextInputType.emailAddress,
                autocompletado: const <String>[AutofillHints.email],
              ),
              const SizedBox(height: 14),
              CampoTexto(
                controlador: _contrasena,
                etiqueta: 'Contraseña',
                icono: Icons.lock_outline_rounded,
                error: _errores['contrasena'],
                ocultarTexto: !_verContrasena,
                accionTexto: TextInputAction.done,
                autocompletado: const <String>[AutofillHints.password],
                alEnviar: (_) => _entrar(),
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
            ],
          ),
        ),
        const SizedBox(height: 22),
        BotonPrincipal(
          texto: 'Entrar',
          claro: true,
          cargando: _enviando,
          icono: Icons.login_rounded,
          alPresionar: _entrar,
        ),
        const SizedBox(height: 6),
        Align(
          alignment: Alignment.center,
          child: TextButton(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const RecuperarScreen()),
            ),
            style: TextButton.styleFrom(foregroundColor: MotorutaColors.cian),
            child: const Text('¿Olvidaste tu contraseña?'),
          ),
        ),
        const SizedBox(height: 10),
        const Divider(color: Colors.white24, height: 32),
        Center(
          child: Wrap(
            alignment: WrapAlignment.center,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: <Widget>[
              const Text(
                '¿No tienes cuenta?',
                style: TextStyle(color: Colors.white70, fontSize: 14.5),
              ),
              TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                      builder: (_) => const ElegirRolScreen()),
                ),
                style:
                    TextButton.styleFrom(foregroundColor: MotorutaColors.cian),
                child: const Text('Crear una'),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

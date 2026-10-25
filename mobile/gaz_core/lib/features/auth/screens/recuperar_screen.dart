import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../core/ui/campos.dart';
import '../../../core/ui/marca.dart';
import '../../sesion/sesion_controller.dart';

/// Recuperar la contraseña.
///
/// El enlace del correo abre la página web de GAZ (funciona igual desde el
/// teléfono o desde la computadora) y desde ahí se cambia la clave.
class RecuperarScreen extends StatefulWidget {
  const RecuperarScreen({super.key});

  @override
  State<RecuperarScreen> createState() => _RecuperarScreenState();
}

class _RecuperarScreenState extends State<RecuperarScreen> {
  final _correo = TextEditingController();

  String? _error;
  String? _enviado;
  bool _enviando = false;

  @override
  void dispose() {
    _correo.dispose();
    super.dispose();
  }

  Future<void> _enviar() async {
    final correo = _correo.text.trim().toLowerCase();

    if (correo.isEmpty || !correo.contains('@')) {
      setState(() => _error = 'Escribe el correo con el que te registraste.');
      return;
    }

    setState(() {
      _error = null;
      _enviando = true;
    });

    try {
      final mensaje =
          await context.read<SesionController>().recuperarContrasena(correo);

      if (!mounted) return;
      setState(() => _enviado = mensaje ?? 'Revisa tu correo.');
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.errorDe('email') ?? error.mensaje);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final enviado = _enviado;

    return PantallaMarca(
      mostrarRetroceso: true,
      hijos: <Widget>[
        const SizedBox(height: 4),
        Text('Recuperar contraseña',
            style: MotorutaTheme.tituloClaro(tamano: 30)),
        const SizedBox(height: 10),
        const Text(
          'Te enviamos un enlace para crear una contraseña nueva. El enlace se abre en el navegador.',
          style: TextStyle(fontSize: 15, color: Colors.white70, height: 1.45),
        ),
        const SizedBox(height: 24),
        Tarjeta(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              if (enviado != null)
                Aviso(
                  tono: Tono.exito,
                  titulo: 'Correo enviado',
                  texto: '$enviado\n\nSi no lo ves, revisa la carpeta de spam.',
                )
              else ...<Widget>[
                CampoTexto(
                  controlador: _correo,
                  etiqueta: 'Correo electrónico',
                  pista: 'pedro.rojas@gmail.com',
                  icono: Icons.alternate_email_rounded,
                  error: _error,
                  tipoTeclado: TextInputType.emailAddress,
                  accionTexto: TextInputAction.done,
                  autocompletado: const <String>[AutofillHints.email],
                  alEnviar: (_) => _enviar(),
                ),
                const SizedBox(height: 18),
                BotonPrincipal(
                  texto: 'Enviarme el enlace',
                  cargando: _enviando,
                  icono: Icons.send_rounded,
                  alPresionar: _enviar,
                ),
              ],
            ],
          ),
        ),
        if (enviado != null) ...<Widget>[
          const SizedBox(height: 18),
          Tarjeta(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                const Aviso(
                  tono: Tono.neutro,
                  texto:
                      'Cuando cambies la contraseña en el navegador, regresa a la app y entra con tu clave nueva.',
                ),
                const SizedBox(height: 16),
                BotonPrincipal(
                  texto: 'Volver a entrar',
                  icono: Icons.login_rounded,
                  alPresionar: () => Navigator.of(context).pop(),
                ),
              ],
            ),
          ),
        ],
        const SizedBox(height: 16),
        Center(
          child: TextButton(
            onPressed: () {
              if (enviado != null) {
                setState(() => _enviado = null);
                return;
              }
              Navigator.of(context).pop();
            },
            style: TextButton.styleFrom(foregroundColor: Colors.white70),
            child: Text(enviado != null ? 'Usar otro correo' : 'Volver'),
          ),
        ),
      ],
    );
  }
}

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/avatar_usuario.dart';
import '../../../core/ui/botones.dart';
import '../../../core/ui/mensajes.dart';
import '../../../data/models/usuario.dart';
import '../../sesion/accion_salir.dart';
import '../../sesion/sesion_controller.dart';
import '../../soporte/widgets/accion_soporte.dart';

/// Pantalla "en espera de verificación".
///
/// El equipo revisa los documentos a mano. La app refresca sola: cada minuto y
/// cada vez que la persona vuelve a la app, para que el cambio se vea apenas
/// el auditor apruebe la cuenta.
class EsperaScreen extends StatefulWidget {
  const EsperaScreen({super.key});

  @override
  State<EsperaScreen> createState() => _EsperaScreenState();
}

class _EsperaScreenState extends State<EsperaScreen>
    with WidgetsBindingObserver {
  Timer? _reloj;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _reloj = Timer.periodic(
        const Duration(seconds: 60), (_) => _refrescar(silencioso: true));
  }

  @override
  void dispose() {
    _reloj?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState estado) {
    if (estado == AppLifecycleState.resumed) {
      _refrescar(silencioso: true);
    }
  }

  Future<void> _refrescar({bool silencioso = false}) async {
    if (!mounted) return;

    try {
      await context.read<SesionController>().refrescar();
    } on ApiException catch (error) {
      if (!mounted || silencioso) return;
      mostrarMensaje(context, error.mensaje, error: true);
    }
  }

  Future<void> _reenviarCorreo() async {
    final sesion = context.read<SesionController>();

    try {
      final mensaje = await sesion.reenviarCorreoVerificacion();
      if (!mounted) return;
      mostrarMensaje(context, mensaje ?? 'Correo enviado.');
    } on ApiException catch (error) {
      if (!mounted) return;
      mostrarMensaje(context, error.mensaje, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionController>();
    final usuario = sesion.usuario;

    if (usuario == null) {
      return const SizedBox.shrink();
    }

    final resumen = usuario.verificacion;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Verificación'),
        actions: const <Widget>[AccionSoporte(), AccionSalir()],
      ),
      body: RefreshIndicator(
        onRefresh: () => _refrescar(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
          children: <Widget>[
            Tarjeta(
              child: Column(
                children: <Widget>[
                  AvatarUsuario(usuario: usuario, tamano: 72),
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: const BoxDecoration(
                      color: MotorutaColors.brand50,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.hourglass_top_rounded,
                      size: 26,
                      color: MotorutaColors.brand800,
                    ),
                  ),
                  const SizedBox(height: 18),
                  Text(
                    usuario.estadoEtiqueta.isEmpty
                        ? 'En espera de verificación'
                        : usuario.estadoEtiqueta,
                    textAlign: TextAlign.center,
                    style: MotorutaTheme.titulo(tamano: 22),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    sesion.siguientePaso?.mensaje ??
                        'Estamos revisando tus documentos. Te avisaremos apenas estén aprobados.',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 14.5,
                      color: MotorutaColors.ink600,
                      height: 1.45,
                    ),
                  ),
                  if (usuario.motivoEstado != null) ...<Widget>[
                    const SizedBox(height: 18),
                    Aviso(
                      tono: Tono.error,
                      titulo: 'Nota del equipo',
                      texto: usuario.motivoEstado!,
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 18),
            Tarjeta(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  Text('Tus documentos',
                      style: MotorutaTheme.titulo(tamano: 18)),
                  const SizedBox(height: 14),
                  Row(
                    children: <Widget>[
                      Flexible(
                        child: Text(
                          '${resumen.aprobados} de ${resumen.requeridos} aprobados',
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color: MotorutaColors.ink800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Flexible(
                        child: Text(
                          '${resumen.pendientes} en revisión',
                          textAlign: TextAlign.right,
                          style: const TextStyle(
                            fontSize: 13.5,
                            color: MotorutaColors.ink500,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(999),
                    child: LinearProgressIndicator(
                      value: resumen.requeridos == 0
                          ? 0
                          : resumen.aprobados / resumen.requeridos,
                      minHeight: 8,
                      backgroundColor: MotorutaColors.ink100,
                      color: MotorutaColors.verde,
                    ),
                  ),
                  const SizedBox(height: 18),
                  for (final documento in resumen.items)
                    _RenglonDocumento(documento: documento),
                ],
              ),
            ),
            if (!usuario.correoVerificado) ...<Widget>[
              const SizedBox(height: 18),
              Tarjeta(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    const Aviso(
                      tono: Tono.aviso,
                      icono: Icons.mark_email_unread_outlined,
                      titulo: 'Confirma tu correo',
                      texto:
                          'Revisa tu correo y confirma la cuenta: es la vía para recuperar la contraseña si la olvidas.',
                    ),
                    const SizedBox(height: 14),
                    BotonSecundario(
                      texto: sesion.reenviandoCorreo
                          ? 'Enviando...'
                          : 'Reenviar el correo',
                      icono: Icons.outgoing_mail,
                      alPresionar:
                          sesion.reenviandoCorreo ? null : _reenviarCorreo,
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 22),
            BotonPrincipal(
              texto: 'Actualizar estado',
              icono: Icons.refresh_rounded,
              cargando: sesion.cargandoPerfil,
              alPresionar: () => _refrescar(),
            ),
            const SizedBox(height: 14),
            const Center(
              child: Text(
                'La app revisa sola cada minuto. También puedes deslizar hacia abajo.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12.5, color: MotorutaColors.ink500),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Renglón del documento con su estado, en la pantalla de espera.
class _RenglonDocumento extends StatelessWidget {
  const _RenglonDocumento({required this.documento});

  final DocumentoEstado documento;

  @override
  Widget build(BuildContext context) {
    final (Tono tono, IconData icono) = switch (documento.estado) {
      'approved' => (Tono.exito, Icons.check_circle_outline_rounded),
      'rejected' => (Tono.error, Icons.error_outline_rounded),
      'pending' => (Tono.aviso, Icons.schedule_rounded),
      _ => (Tono.neutro, Icons.circle_outlined),
    };

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(
        children: <Widget>[
          Icon(icono, size: 20, color: tono.color),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              documento.etiqueta,
              style: const TextStyle(
                fontSize: 14.5,
                color: MotorutaColors.ink800,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          const SizedBox(width: 10),
          EtiquetaEstado(texto: documento.estadoEtiqueta, tono: tono),
        ],
      ),
    );
  }
}

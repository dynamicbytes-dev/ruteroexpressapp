import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../core/ui/botones.dart';
import '../../../core/ui/campos.dart';
import '../../../core/ui/mensajes.dart';
import '../../../data/models/usuario.dart';
import '../../sesion/accion_salir.dart';
import '../../sesion/sesion_controller.dart';
import '../../soporte/widgets/accion_soporte.dart';

/// Lista de verificación: sube lo que falta y vuelve a subir lo rechazado.
///
/// Es la pantalla de `upload_documents` y `fix_documents`. Cada documento se
/// sube por separado, con barra de progreso, y al terminar el backend dice qué
/// sigue (por ejemplo: "Ya enviaste todo").
class DocumentosScreen extends StatefulWidget {
  const DocumentosScreen({super.key});

  @override
  State<DocumentosScreen> createState() => _DocumentosScreenState();
}

class _DocumentosScreenState extends State<DocumentosScreen> {
  /// Tipo de documento que se está subiendo (para pintar su progreso).
  String? _tipoSubiendo;
  double _progreso = 0;

  Future<void> _abrirHoja(DocumentoEstado documento) async {
    final seleccion = await showModalBottomSheet<_Seleccion>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _HojaDocumento(documento: documento),
    );

    if (seleccion == null || !mounted) return;

    await _subir(documento, seleccion);
  }

  Future<void> _subir(DocumentoEstado documento, _Seleccion seleccion) async {
    final sesion = context.read<SesionController>();
    final elegido = await _elegirArchivo(seleccion.origen);

    if (elegido == null) return;

    final bytes = await elegido.readAsBytes();

    if (bytes.length > 6 * 1024 * 1024) {
      if (!mounted) return;
      mostrarMensaje(context, 'La foto pesa más de 6 MB. Intenta con otra.',
          error: true);
      return;
    }

    setState(() {
      _tipoSubiendo = documento.tipo;
      _progreso = 0;
    });

    try {
      final mensaje = await sesion.subirDocumento(
        tipo: documento.tipo,
        archivo: ArchivoSubida.desdeBytes(
          nombre: _nombreArchivo(elegido.name),
          bytes: bytes,
        ),
        venceEl: seleccion.venceEl,
        alProgresar: (enviado, total) {
          if (!mounted || total <= 0) return;
          setState(() => _progreso = enviado / total);
        },
      );

      if (!mounted) return;
      mostrarMensaje(context, mensaje ?? 'Documento recibido.');
    } on ApiException catch (error) {
      if (!mounted) return;

      mostrarMensaje(
        context,
        error.errorDe('archivo') ??
            error.errorDe('vence_el') ??
            error.errorGeneral ??
            error.mensaje,
        error: true,
      );
    } finally {
      if (mounted) {
        setState(() {
          _tipoSubiendo = null;
          _progreso = 0;
        });
      }
    }
  }

  Future<XFile?> _elegirArchivo(ImageSource origen) async {
    try {
      return await ImagePicker().pickImage(
        source: origen,
        imageQuality: 88,
        maxWidth: 2200,
      );
    } catch (_) {
      if (mounted) {
        mostrarMensaje(context, 'No pudimos abrir la cámara o la galería.',
            error: true);
      }
      return null;
    }
  }

  String _nombreArchivo(String nombre) {
    if (nombre.trim().isEmpty) return 'documento.jpg';
    return nombre.contains('.') ? nombre : '$nombre.jpg';
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<SesionController>();
    final usuario = sesion.usuario;

    if (usuario == null) {
      return const SizedBox.shrink();
    }

    final resumen = usuario.verificacion;
    final corrigiendo = sesion.siguientePaso?.hayQueCorregir ?? false;

    return Scaffold(
      appBar: AppBar(
        title: Text(corrigiendo ? 'Corregir documentos' : 'Tus documentos'),
        actions: const <Widget>[AccionSoporte(), AccionSalir()],
      ),
      body: RefreshIndicator(
        onRefresh: sesion.refrescar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 40),
          children: <Widget>[
            _Encabezado(usuario: usuario, corrigiendo: corrigiendo),
            const SizedBox(height: 18),
            if (!usuario.correoVerificado) ...<Widget>[
              _AvisoCorreo(
                reenviando: sesion.reenviandoCorreo,
                alReenviar: () => _reenviarCorreo(sesion),
              ),
              const SizedBox(height: 18),
            ],
            for (final documento in resumen.items) ...<Widget>[
              _TarjetaDocumento(
                documento: documento,
                subiendo: _tipoSubiendo == documento.tipo,
                progreso: _progreso,
                habilitado: _tipoSubiendo == null,
                alSubir: () => _abrirHoja(documento),
              ),
              const SizedBox(height: 14),
            ],
            if (resumen.items.isEmpty)
              const Aviso(
                tono: Tono.neutro,
                texto:
                    'No pudimos cargar tu lista de documentos. Desliza hacia abajo para actualizar.',
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _reenviarCorreo(SesionController sesion) async {
    try {
      final mensaje = await sesion.reenviarCorreoVerificacion();
      if (!mounted) return;
      mostrarMensaje(context, mensaje ?? 'Correo enviado.');
    } on ApiException catch (error) {
      if (!mounted) return;
      mostrarMensaje(context, error.mensaje, error: true);
    }
  }
}

/// Tarjeta de arriba: en qué va el registro y qué sigue.
class _Encabezado extends StatelessWidget {
  const _Encabezado({required this.usuario, required this.corrigiendo});

  final Usuario usuario;
  final bool corrigiendo;

  @override
  Widget build(BuildContext context) {
    final resumen = usuario.verificacion;

    return Tarjeta(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            corrigiendo ? 'Falta corregir' : 'Completa tu registro',
            style: MotorutaTheme.titulo(tamano: 21),
          ),
          const SizedBox(height: 8),
          Text(
            corrigiendo
                ? 'El auditor rechazó un documento. Lee el motivo, corrige y vuelve a subirlo.'
                : 'Sube cada documento desde tu teléfono. En cuanto estén todos, tu cuenta entra a la revisión del equipo.',
            style: const TextStyle(
                fontSize: 14.5, color: MotorutaColors.ink600, height: 1.45),
          ),
          const SizedBox(height: 18),
          Row(
            children: <Widget>[
              Text(
                '${resumen.subidos} de ${resumen.requeridos} enviados',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: MotorutaColors.ink800,
                ),
              ),
              const Spacer(),
              Text(
                '${(resumen.avance * 100).round()}%',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: MotorutaColors.brand700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: resumen.avance,
              minHeight: 8,
              backgroundColor: MotorutaColors.ink100,
              color: MotorutaColors.brand700,
            ),
          ),
          if (resumen.etiquetasFaltantes.isNotEmpty) ...<Widget>[
            const SizedBox(height: 14),
            Text(
              'Falta subir: ${resumen.etiquetasFaltantes.join(', ')}.',
              style:
                  const TextStyle(fontSize: 13.5, color: MotorutaColors.ink600),
            ),
          ],
        ],
      ),
    );
  }
}

/// Aviso para confirmar el correo, con botón de reenvío.
class _AvisoCorreo extends StatelessWidget {
  const _AvisoCorreo({required this.reenviando, required this.alReenviar});

  final bool reenviando;
  final VoidCallback alReenviar;

  @override
  Widget build(BuildContext context) {
    return Tarjeta(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const Aviso(
            tono: Tono.aviso,
            icono: Icons.mark_email_unread_outlined,
            titulo: 'Confirma tu correo',
            texto:
                'Te enviamos un correo de confirmación. Revisarlo es la vía para recuperar la contraseña si algún día la olvidas.',
          ),
          const SizedBox(height: 14),
          BotonSecundario(
            texto: 'Reenviar el correo',
            icono: Icons.outgoing_mail,
            alPresionar: reenviando ? null : alReenviar,
          ),
        ],
      ),
    );
  }
}

/// Un documento de la lista: estado, motivo de rechazo y botón para subirlo.
class _TarjetaDocumento extends StatelessWidget {
  const _TarjetaDocumento({
    required this.documento,
    required this.subiendo,
    required this.progreso,
    required this.habilitado,
    required this.alSubir,
  });

  final DocumentoEstado documento;
  final bool subiendo;
  final double progreso;
  final bool habilitado;
  final VoidCallback alSubir;

  @override
  Widget build(BuildContext context) {
    return Tarjeta(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                child: Text(
                  documento.etiqueta,
                  style: MotorutaTheme.titulo(tamano: 17),
                ),
              ),
              const SizedBox(width: 10),
              _EtiquetaDocumento(documento: documento),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            documento.pista,
            style: const TextStyle(
              fontSize: 13.5,
              color: MotorutaColors.ink500,
              height: 1.4,
            ),
          ),
          if (documento.rechazado &&
              documento.motivoRechazo != null) ...<Widget>[
            const SizedBox(height: 14),
            Aviso(
              tono: Tono.error,
              titulo: 'Motivo del rechazo',
              texto: documento.motivoRechazo!,
            ),
          ],
          if (documento.vencido) ...<Widget>[
            const SizedBox(height: 14),
            const Aviso(
              tono: Tono.aviso,
              texto:
                  'Este documento está vencido: súbelo de nuevo para seguir operando.',
            ),
          ] else if (documento.venceEn != null) ...<Widget>[
            const SizedBox(height: 10),
            Text(
              'Vence el ${_fecha(documento.venceEn!)}',
              style:
                  const TextStyle(fontSize: 12.5, color: MotorutaColors.ink500),
            ),
          ],
          if (documento.aprobado && documento.subido) ...<Widget>[
            const SizedBox(height: 10),
            Text(
              documento.subidoEn == null
                  ? 'Aprobado por el equipo.'
                  : 'Subido el ${_fecha(documento.subidoEn!)}.',
              style:
                  const TextStyle(fontSize: 12.5, color: MotorutaColors.ink500),
            ),
          ],
          if (subiendo) ...<Widget>[
            const SizedBox(height: 16),
            ClipRRect(
              borderRadius: BorderRadius.circular(999),
              child: LinearProgressIndicator(
                value: progreso == 0 ? null : progreso,
                minHeight: 7,
                backgroundColor: MotorutaColors.ink100,
                color: MotorutaColors.accent600,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              progreso == 0
                  ? 'Enviando...'
                  : 'Enviando ${(progreso * 100).round()}%',
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: MotorutaColors.accent700,
              ),
            ),
          ] else if (documento.sePuedeSubir) ...<Widget>[
            const SizedBox(height: 16),
            BotonSecundario(
              texto: documento.subido ? 'Reemplazar archivo' : 'Subir foto',
              icono: documento.subido
                  ? Icons.refresh_rounded
                  : Icons.upload_rounded,
              alPresionar: habilitado ? alSubir : null,
            ),
          ] else if (documento.enRevision) ...<Widget>[
            const SizedBox(height: 12),
            const Text(
              'Ya está en manos del equipo. Te avisamos apenas lo revisen.',
              style: TextStyle(
                fontSize: 13,
                color: MotorutaColors.ink500,
                fontStyle: FontStyle.italic,
              ),
            ),
          ],
        ],
      ),
    );
  }

  static String _fecha(DateTime fecha) {
    final local = fecha.toLocal();
    final dia = local.day.toString().padLeft(2, '0');
    final mes = local.month.toString().padLeft(2, '0');
    return '$dia/$mes/${local.year}';
  }
}

/// Etiqueta de estado del documento, con el tono que corresponde.
class _EtiquetaDocumento extends StatelessWidget {
  const _EtiquetaDocumento({required this.documento});

  final DocumentoEstado documento;

  @override
  Widget build(BuildContext context) {
    if (documento.rechazado) {
      return EtiquetaEstado(
        texto: documento.estadoEtiqueta.isEmpty
            ? 'Rechazado'
            : documento.estadoEtiqueta,
        tono: Tono.error,
        icono: Icons.close_rounded,
      );
    }

    if (documento.aprobado) {
      return EtiquetaEstado(
        texto: documento.estadoEtiqueta.isEmpty
            ? 'Aprobado'
            : documento.estadoEtiqueta,
        tono: Tono.exito,
        icono: Icons.check_rounded,
      );
    }

    if (documento.enRevision) {
      return EtiquetaEstado(
        texto: documento.estadoEtiqueta.isEmpty
            ? 'En revisión'
            : documento.estadoEtiqueta,
        tono: Tono.aviso,
        icono: Icons.schedule_rounded,
      );
    }

    return const EtiquetaEstado(texto: 'Falta subirlo', tono: Tono.neutro);
  }
}

/// Lo que se eligió en la hoja: de dónde sale el archivo y su vencimiento.
class _Seleccion {
  const _Seleccion({required this.origen, this.venceEl});

  final ImageSource origen;
  final DateTime? venceEl;
}

/// Hoja inferior para elegir cámara o galería, y la fecha si el documento vence.
class _HojaDocumento extends StatefulWidget {
  const _HojaDocumento({required this.documento});

  final DocumentoEstado documento;

  @override
  State<_HojaDocumento> createState() => _HojaDocumentoState();
}

class _HojaDocumentoState extends State<_HojaDocumento> {
  DateTime? _venceEl;

  @override
  Widget build(BuildContext context) {
    final documento = widget.documento;
    final faltaFecha = documento.requiereVencimiento && _venceEl == null;

    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(22, 14, 22, 22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Center(
                child: Container(
                  width: 44,
                  height: 4,
                  decoration: BoxDecoration(
                    color: MotorutaColors.ink200,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Text(documento.etiqueta, style: MotorutaTheme.titulo(tamano: 20)),
              const SizedBox(height: 6),
              Text(
                documento.pista,
                style: const TextStyle(
                  fontSize: 13.5,
                  color: MotorutaColors.ink600,
                  height: 1.4,
                ),
              ),
              if (documento.requiereVencimiento) ...<Widget>[
                const SizedBox(height: 18),
                CampoFecha(
                  etiqueta: 'Vence el',
                  valor: _venceEl,
                  pista: 'Elegir fecha',
                  textoAyuda: 'Debe ser una fecha posterior a hoy.',
                  alElegir: (fecha) => setState(() => _venceEl = fecha),
                ),
              ],
              const SizedBox(height: 22),
              if (!kIsWeb) ...<Widget>[
                _OpcionOrigen(
                  icono: Icons.photo_camera_outlined,
                  texto: 'Tomar foto',
                  habilitado: !faltaFecha,
                  alTocar: () => Navigator.of(context).pop(
                    _Seleccion(origen: ImageSource.camera, venceEl: _venceEl),
                  ),
                ),
                const SizedBox(height: 12),
              ],
              _OpcionOrigen(
                icono: Icons.photo_library_outlined,
                texto: 'Elegir de la galería',
                habilitado: !faltaFecha,
                alTocar: () => Navigator.of(context).pop(
                  _Seleccion(origen: ImageSource.gallery, venceEl: _venceEl),
                ),
              ),
              if (faltaFecha) ...<Widget>[
                const SizedBox(height: 12),
                const Text(
                  'Primero indica la fecha de vencimiento.',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: MotorutaColors.amarillo,
                  ),
                ),
              ],
              const SizedBox(height: 8),
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Cancelar'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Botón grande de la hoja: cámara o galería.
class _OpcionOrigen extends StatelessWidget {
  const _OpcionOrigen({
    required this.icono,
    required this.texto,
    required this.habilitado,
    required this.alTocar,
  });

  final IconData icono;
  final String texto;
  final bool habilitado;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: habilitado ? MotorutaColors.brand50 : MotorutaColors.ink50,
      borderRadius: BorderRadius.circular(MotorutaTheme.radio),
      child: InkWell(
        borderRadius: BorderRadius.circular(MotorutaTheme.radio),
        onTap: habilitado ? alTocar : null,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 18),
          child: Row(
            children: <Widget>[
              Icon(
                icono,
                size: 22,
                color: habilitado
                    ? MotorutaColors.brand800
                    : MotorutaColors.ink300,
              ),
              const SizedBox(width: 14),
              Text(
                texto,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w600,
                  color: habilitado
                      ? MotorutaColors.brand900
                      : MotorutaColors.ink400,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/avisos.dart';
import '../../../data/models/soporte.dart';
import '../../sesion/sesion_controller.dart';

/// Chat de soporte para pasajeros y conductores.
///
/// El hilo se refresca cada pocos segundos. Si el auditor da la conversación
/// por terminada, el backend deja de devolverla y aquí se limpia el chat para
/// que la persona pueda iniciar uno nuevo.
class SoporteChatScreen extends StatefulWidget {
  const SoporteChatScreen({super.key});

  @override
  State<SoporteChatScreen> createState() => _SoporteChatScreenState();
}

class _SoporteChatScreenState extends State<SoporteChatScreen>
    with WidgetsBindingObserver {
  final TextEditingController _campo = TextEditingController();
  final ScrollController _scroll = ScrollController();

  Timer? _reloj;
  ConversacionSoporte? _conversacion;
  bool _cargando = true;
  bool _enviando = false;
  bool _finalizadaPorEquipo = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    WidgetsBinding.instance.addPostFrameCallback((_) => _cargar());

    // Respaldo del chat: cada 5 segundos se buscan respuestas del equipo.
    _reloj = Timer.periodic(
      const Duration(seconds: 5),
      (_) => _cargar(silencioso: true),
    );
  }

  @override
  void dispose() {
    _reloj?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    _campo.dispose();
    _scroll.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState estado) {
    if (estado == AppLifecycleState.resumed) {
      _cargar(silencioso: true);
    }
  }

  Future<void> _cargar({bool silencioso = false}) async {
    if (!mounted) return;

    final sesion = context.read<SesionController>();

    if (!sesion.soporteConfigurado) {
      if (!mounted) return;
      setState(() {
        _cargando = false;
        _error = 'El chat de soporte no está disponible en esta versión.';
      });
      return;
    }

    if (!silencioso && _conversacion == null) {
      setState(() => _cargando = true);
    }

    final ultimoAnterior = _conversacion?.mensajes.isNotEmpty == true
        ? _conversacion!.mensajes.last.id
        : null;

    try {
      final conversacion = await sesion.conversacionSoporte();
      if (!mounted) return;

      final estabaAbierta = _conversacion != null;
      final ultimoNuevo = conversacion?.mensajes.isNotEmpty == true
          ? conversacion!.mensajes.last.id
          : null;

      setState(() {
        _conversacion = conversacion;
        _cargando = false;
        _error = null;

        if (conversacion != null) {
          _finalizadaPorEquipo = false;
        } else if (estabaAbierta) {
          // El auditor cerró el chat mientras la pantalla estaba abierta.
          _finalizadaPorEquipo = true;
        }
      });

      if (conversacion != null &&
          (ultimoAnterior == null || ultimoAnterior != ultimoNuevo)) {
        _irAlFinal();
      }
    } on ApiException catch (error) {
      if (!mounted) return;

      setState(() {
        _cargando = false;
        if (!silencioso) _error = error.mensaje;
      });
    }
  }

  Future<void> _enviar() async {
    final texto = _campo.text.trim();

    if (texto.isEmpty || _enviando) return;

    setState(() {
      _enviando = true;
      _error = null;
    });

    try {
      final conversacion =
          await context.read<SesionController>().enviarMensajeSoporte(texto);

      if (!mounted) return;

      _campo.clear();
      setState(() {
        _conversacion = conversacion;
        _finalizadaPorEquipo = false;
        _enviando = false;
      });
      _irAlFinal();
    } on ApiException catch (error) {
      if (!mounted) return;

      setState(() {
        _enviando = false;
        _error = error.mensaje;
      });
    }
  }

  void _irAlFinal() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_scroll.hasClients) return;

      _scroll.animateTo(
        _scroll.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final mensajes = _conversacion?.mensajes ?? const <MensajeSoporte>[];
    final auditor = _conversacion?.auditorNombre;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Soporte'),
        actions: <Widget>[
          IconButton(
            onPressed: _cargando ? null : () => _cargar(),
            tooltip: 'Actualizar chat',
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Column(
        children: <Widget>[
          if (_finalizadaPorEquipo)
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Aviso(
                tono: Tono.aviso,
                icono: Icons.task_alt_rounded,
                titulo: 'Conversación finalizada',
                texto:
                    'El equipo dio por terminada esta conversación. Ya puedes escribir de nuevo para iniciar otra.',
              ),
            ),
          if (auditor != null && auditor.trim().isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
                decoration: BoxDecoration(
                  color: MotorutaColors.brand50,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: <Widget>[
                    const Icon(
                      Icons.support_agent_rounded,
                      size: 18,
                      color: MotorutaColors.brand700,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Atendido por $auditor',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: MotorutaColors.brand800,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
              child: Aviso(
                tono: Tono.error,
                titulo: 'No pudimos actualizar el chat',
                texto: _error!,
              ),
            ),
          Expanded(
            child: _cargando
                ? const Center(child: CircularProgressIndicator())
                : mensajes.isEmpty
                    ? _Bienvenida(finalizada: _finalizadaPorEquipo)
                    : ListView.builder(
                        controller: _scroll,
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
                        itemCount: mensajes.length,
                        itemBuilder: (context, indice) =>
                            _Burbuja(mensaje: mensajes[indice]),
                      ),
          ),
          _Compositor(
            controlador: _campo,
            enviando: _enviando,
            alEnviar: _enviar,
          ),
        ],
      ),
    );
  }
}

/// Estado inicial: explica cómo funciona el chat antes del primer mensaje.
class _Bienvenida extends StatelessWidget {
  const _Bienvenida({required this.finalizada});

  final bool finalizada;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 28, 20, 28),
      children: <Widget>[
        Center(
          child: Container(
            padding: const EdgeInsets.all(18),
            decoration: const BoxDecoration(
              color: MotorutaColors.brand50,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.support_agent_rounded,
              size: 36,
              color: MotorutaColors.brand800,
            ),
          ),
        ),
        const SizedBox(height: 18),
        Text(
          finalizada ? 'Inicia otra conversación' : 'Estamos para ayudarte',
          textAlign: TextAlign.center,
          style: MotorutaTheme.titulo(tamano: 22),
        ),
        const SizedBox(height: 8),
        Text(
          finalizada
              ? 'Escribe tu nueva consulta y el equipo de GAZ te responderá por aquí.'
              : 'Cuéntanos qué necesitas. Un miembro del equipo de GAZ te responderá en este mismo chat.',
          textAlign: TextAlign.center,
          style: const TextStyle(
            fontSize: 14.5,
            height: 1.45,
            color: MotorutaColors.ink600,
          ),
        ),
        const SizedBox(height: 22),
        const Aviso(
          tono: Tono.info,
          icono: Icons.lock_clock_outlined,
          titulo: 'Ten en cuenta',
          texto:
              'No compartas contraseñas ni códigos de verificación. Cuando el equipo dé la conversación por terminada, este chat se cerrará.',
        ),
      ],
    );
  }
}

/// Burbuja de un mensaje. El usuario escribe a la derecha y soporte a la
/// izquierda, como en cualquier chat.
class _Burbuja extends StatelessWidget {
  const _Burbuja({required this.mensaje});

  final MensajeSoporte mensaje;

  @override
  Widget build(BuildContext context) {
    final deSoporte = mensaje.deSoporte;

    return Align(
      alignment: deSoporte ? Alignment.centerLeft : Alignment.centerRight,
      child: Container(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.78,
        ),
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 11),
        decoration: BoxDecoration(
          color: deSoporte ? Colors.white : MotorutaColors.brand800,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(deSoporte ? 4 : 16),
            bottomRight: Radius.circular(deSoporte ? 16 : 4),
          ),
          border: deSoporte ? Border.all(color: MotorutaColors.ink100) : null,
          boxShadow: deSoporte ? MotorutaTheme.sombraTarjeta : null,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              deSoporte
                  ? (mensaje.autorEtiqueta.trim().isEmpty
                      ? 'Soporte'
                      : mensaje.autorEtiqueta)
                  : 'Tú',
              style: TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
                color:
                    deSoporte ? MotorutaColors.brand700 : MotorutaColors.cian,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              mensaje.texto,
              style: TextStyle(
                fontSize: 14.5,
                height: 1.4,
                color: deSoporte ? MotorutaColors.ink900 : Colors.white,
              ),
            ),
            if (mensaje.creadoEn != null) ...<Widget>[
              const SizedBox(height: 4),
              Text(
                DateFormat('d/M · HH:mm').format(mensaje.creadoEn!),
                style: TextStyle(
                  fontSize: 10.5,
                  color: deSoporte
                      ? MotorutaColors.ink400
                      : Colors.white.withOpacity(0.62),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Campo de escritura y botón de enviar, fijos al fondo.
class _Compositor extends StatelessWidget {
  const _Compositor({
    required this.controlador,
    required this.enviando,
    required this.alEnviar,
  });

  final TextEditingController controlador;
  final bool enviando;
  final VoidCallback alEnviar;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: MotorutaColors.ink100)),
      ),
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
      child: SafeArea(
        top: false,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: <Widget>[
            Expanded(
              child: TextField(
                controller: controlador,
                minLines: 1,
                maxLines: 4,
                maxLength: 2000,
                keyboardType: TextInputType.multiline,
                textCapitalization: TextCapitalization.sentences,
                style: const TextStyle(
                  fontSize: 15.5,
                  color: MotorutaColors.ink900,
                ),
                decoration: InputDecoration(
                  hintText: 'Escribe tu mensaje...',
                  counterText: '',
                  filled: true,
                  fillColor: MotorutaColors.ink50,
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 12,
                  ),
                  border: MotorutaTheme.borde(color: MotorutaColors.ink100),
                  enabledBorder:
                      MotorutaTheme.borde(color: MotorutaColors.ink100),
                  focusedBorder: MotorutaTheme.borde(
                    color: MotorutaColors.brand700,
                    grosor: 1.4,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filled(
              onPressed: enviando ? null : alEnviar,
              tooltip: 'Enviar mensaje',
              style: IconButton.styleFrom(
                backgroundColor: MotorutaColors.brand800,
                foregroundColor: Colors.white,
                disabledBackgroundColor: MotorutaColors.ink200,
                minimumSize: const Size(48, 48),
              ),
              icon: enviando
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.send_rounded),
            ),
          ],
        ),
      ),
    );
  }
}

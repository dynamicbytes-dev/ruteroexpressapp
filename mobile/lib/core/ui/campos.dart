import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../theme/motoruta_colors.dart';

/// Campo de texto con el estilo de la app.
///
/// El error se pasa ya calculado (normalmente del mapa `errors` del backend),
/// por eso no se usa `TextFormField` ni validación de formulario.
class CampoTexto extends StatefulWidget {
  const CampoTexto({
    super.key,
    required this.controlador,
    required this.etiqueta,
    this.pista,
    this.textoAyuda,
    this.error,
    this.icono,
    this.prefijo,
    this.sufijo,
    this.tipoTeclado = TextInputType.text,
    this.accionTexto = TextInputAction.next,
    this.ocultarTexto = false,
    this.maxLineas = 1,
    this.maxLongitud,
    this.habilitado = true,
    this.autocompletado,
    this.formateadores,
    this.alEnviar,
    this.alCambiar,
    this.mayusculas = false,
    this.focusNode,
  });

  final TextEditingController controlador;
  final String etiqueta;
  final String? pista;
  final String? textoAyuda;
  final String? error;
  final IconData? icono;
  final String? prefijo;
  final Widget? sufijo;
  final TextInputType tipoTeclado;
  final TextInputAction accionTexto;
  final bool ocultarTexto;
  final int maxLineas;
  final int? maxLongitud;
  final bool habilitado;
  final List<String>? autocompletado;
  final List<TextInputFormatter>? formateadores;
  final ValueChanged<String>? alEnviar;
  final ValueChanged<String>? alCambiar;
  final bool mayusculas;
  final FocusNode? focusNode;

  @override
  State<CampoTexto> createState() => _CampoTextoState();
}

class _CampoTextoState extends State<CampoTexto> {
  FocusNode? _nodoInterno;
  bool _tieneFoco = false;

  FocusNode get _nodo => widget.focusNode ?? (_nodoInterno ??= FocusNode());

  @override
  void initState() {
    super.initState();
    _nodo.addListener(_alCambiarFoco);
  }

  @override
  void didUpdateWidget(CampoTexto anterior) {
    super.didUpdateWidget(anterior);
    if (anterior.focusNode != widget.focusNode) {
      (anterior.focusNode ?? _nodoInterno)?.removeListener(_alCambiarFoco);
      _nodo.addListener(_alCambiarFoco);
    }
  }

  @override
  void dispose() {
    _nodo.removeListener(_alCambiarFoco);
    _nodoInterno?.dispose();
    super.dispose();
  }

  void _alCambiarFoco() {
    if (mounted && _tieneFoco != _nodo.hasFocus) {
      setState(() => _tieneFoco = _nodo.hasFocus);
    }
  }

  @override
  Widget build(BuildContext context) {
    // Un campo de varias líneas con acción "nueva línea" tiene que declarar
    // teclado multilínea: si no, Flutter lanza una aserción.
    final teclado = widget.maxLineas > 1 && widget.tipoTeclado == TextInputType.text
        ? TextInputType.multiline
        : widget.tipoTeclado;

    final colorIcono = widget.error != null
        ? MotorutaColors.rojo
        : (_tieneFoco ? MotorutaColors.brand800 : MotorutaColors.ink400);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        TextField(
          controller: widget.controlador,
          focusNode: _nodo,
          enabled: widget.habilitado,
          obscureText: widget.ocultarTexto,
          keyboardType: teclado,
          textInputAction: widget.accionTexto,
          maxLines: widget.ocultarTexto ? 1 : widget.maxLineas,
          maxLength: widget.maxLongitud,
          autocorrect: !widget.ocultarTexto,
          enableSuggestions: !widget.ocultarTexto,
          autofillHints: widget.autocompletado,
          inputFormatters: widget.formateadores,
          textCapitalization: widget.mayusculas
              ? TextCapitalization.characters
              : TextCapitalization.none,
          onSubmitted: widget.alEnviar,
          onChanged: widget.alCambiar,
          onTap: () => HapticFeedback.selectionClick(),
          style: const TextStyle(
            fontSize: 16,
            color: MotorutaColors.ink900,
            fontWeight: FontWeight.w500,
          ),
          decoration: InputDecoration(
            labelText: widget.etiqueta,
            hintText: widget.pista,
            errorText: widget.error,
            helperText: widget.textoAyuda,
            counterText: '',
            prefixIcon: widget.icono == null
                ? null
                : Icon(
                    widget.icono,
                    size: 20,
                    color: colorIcono,
                  ),
            prefixText: widget.prefijo,
            suffixIcon: widget.sufijo,
          ),
        ),
      ],
    );
  }
}

/// Campo de fecha (se toca y abre el calendario).
///
/// Se usa para las fechas de vencimiento de licencia, carnet y RCV.
class CampoFecha extends StatelessWidget {
  const CampoFecha({
    super.key,
    required this.etiqueta,
    required this.valor,
    required this.alElegir,
    this.error,
    this.pista,
    this.textoAyuda,
    this.primerDia,
    this.ultimoDia,
  });

  final String etiqueta;
  final DateTime? valor;
  final ValueChanged<DateTime> alElegir;
  final String? error;
  final String? pista;
  final String? textoAyuda;

  /// Rango permitido. Por defecto, desde mañana (vencimientos).
  final DateTime? primerDia;
  final DateTime? ultimoDia;

  static final DateFormat _formato = DateFormat('dd/MM/yyyy');

  @override
  Widget build(BuildContext context) {
    final hoy = DateTime.now();
    final primero = primerDia ?? DateTime(hoy.year, hoy.month, hoy.day + 1);
    final ultimo = ultimoDia ?? DateTime(hoy.year + 12, hoy.month, hoy.day);

    var inicial = valor ??
        (primerDia != null
            ? DateTime(hoy.year, hoy.month, hoy.day)
            : DateTime(hoy.year + 1, hoy.month, hoy.day));

    if (inicial.isBefore(primero)) inicial = primero;
    if (inicial.isAfter(ultimo)) inicial = ultimo;

    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () async {
        HapticFeedback.lightImpact();
        final elegida = await showDatePicker(
          context: context,
          initialDate: inicial,
          firstDate: primero,
          lastDate: ultimo,
          locale: const Locale('es'),
          helpText: etiqueta,
          cancelText: 'Cancelar',
          confirmText: 'Aceptar',
        );

        if (elegida != null) {
          alElegir(elegida);
        }
      },
      child: InputDecorator(
        isEmpty: valor == null,
        decoration: InputDecoration(
          labelText: etiqueta,
          errorText: error,
          helperText: textoAyuda,
          suffixIcon: const Icon(Icons.event_outlined, size: 20),
        ),
        child: Text(
          valor == null ? (pista ?? 'Elegir fecha') : _formato.format(valor!),
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w500,
            color:
                valor == null ? MotorutaColors.ink400 : MotorutaColors.ink900,
          ),
        ),
      ),
    );
  }
}

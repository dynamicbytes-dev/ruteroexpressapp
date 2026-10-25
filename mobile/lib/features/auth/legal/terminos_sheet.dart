import 'package:flutter/material.dart';

import '../../../core/legal/terminos_version.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/botones.dart';
import 'terminos_contenido.dart';

/// Abre los términos y la política de privacidad vigentes.
Future<void> mostrarTerminos(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (_) => const TerminosSheet(),
  );
}

/// Hoja inferior con los dos documentos legales.
class TerminosSheet extends StatelessWidget {
  const TerminosSheet({super.key});

  @override
  Widget build(BuildContext context) {
    final alto = MediaQuery.sizeOf(context).height * 0.88;

    return SizedBox(
      height: alto,
      child: DefaultTabController(
        length: 2,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const SizedBox(height: 10),
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: MotorutaColors.ink200,
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 8, 0),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: Text(
                      'Términos y privacidad',
                      style: MotorutaTheme.titulo(tamano: 20),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Cerrar',
                    onPressed: () => Navigator.of(context).maybePop(),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            const TabBar(
              labelColor: MotorutaColors.brand800,
              unselectedLabelColor: MotorutaColors.ink500,
              indicatorColor: MotorutaColors.brand800,
              dividerColor: MotorutaColors.ink100,
              tabs: <Widget>[
                Tab(text: 'Términos'),
                Tab(text: 'Privacidad'),
              ],
            ),
            const Expanded(
              child: TabBarView(
                children: <Widget>[
                  _DocumentoLegal(secciones: terminosLegales),
                  _DocumentoLegal(secciones: privacidadLegal),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
              child: BotonPrincipal(
                texto: 'Entendido',
                icono: Icons.check_rounded,
                alPresionar: () => Navigator.of(context).maybePop(),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DocumentoLegal extends StatelessWidget {
  const _DocumentoLegal({required this.secciones});

  final List<SeccionLegal> secciones;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
      children: <Widget>[
        const Text(
          'Versión $versionTerminos',
          style: TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w600,
            color: MotorutaColors.ink500,
          ),
        ),
        const SizedBox(height: 2),
        const Text(
          'Última actualización: $fechaTerminos',
          style: TextStyle(
            fontSize: 13,
            color: MotorutaColors.ink500,
          ),
        ),
        const SizedBox(height: 20),
        for (final seccion in secciones) ...<Widget>[
          Text(
            seccion.titulo,
            style: MotorutaTheme.titulo(tamano: 16),
          ),
          const SizedBox(height: 8),
          for (final parrafo in seccion.parrafos) ...<Widget>[
            Text(
              parrafo,
              style: const TextStyle(
                fontSize: 14.5,
                height: 1.5,
                color: MotorutaColors.ink700,
              ),
            ),
            const SizedBox(height: 10),
          ],
          const SizedBox(height: 8),
        ],
      ],
    );
  }
}

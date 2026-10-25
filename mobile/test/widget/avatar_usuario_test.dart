import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/core/theme/app_theme.dart';
import 'package:motoruta/core/ui/avatar_usuario.dart';
import 'package:motoruta/data/models/usuario.dart';

import '../soporte/datos_falsos.dart';

void main() {
  testWidgets('sin selfie el avatar muestra las iniciales', (tester) async {
    final usuario = Usuario.desdeJson(usuarioJson(rol: 'passenger'));

    await tester.pumpWidget(
      MaterialApp(
        theme: MotorutaTheme.claro,
        home: Scaffold(body: AvatarUsuario(usuario: usuario)),
      ),
    );

    expect(usuario.fotoUrl, isNull);
    expect(find.text('PR'), findsOneWidget);
    expect(find.byType(Image), findsNothing);
  });
}

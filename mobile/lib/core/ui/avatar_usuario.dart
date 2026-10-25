import 'package:flutter/material.dart';

import '../../data/models/usuario.dart';
import '../theme/app_theme.dart';
import '../theme/motoruta_colors.dart';

/// Foto de perfil de la persona autenticada.
///
/// Usa la selfie aprobada o en revisión. Si todavía no hay foto, si el archivo
/// no es una imagen o la URL firmada vence, muestra las iniciales.
class AvatarUsuario extends StatelessWidget {
  const AvatarUsuario({
    super.key,
    required this.usuario,
    this.tamano = 54,
  });

  final Usuario usuario;
  final double tamano;

  @override
  Widget build(BuildContext context) {
    final foto = usuario.fotoUrl?.trim();
    final tieneFoto = foto != null && foto.isNotEmpty;

    return CircleAvatar(
      radius: tamano / 2,
      backgroundColor: MotorutaColors.brand800,
      backgroundImage: tieneFoto ? NetworkImage(foto) : null,
      onBackgroundImageError: tieneFoto ? (_, __) {} : null,
      child: tieneFoto ? null : _iniciales(),
    );
  }

  Widget _iniciales() {
    return Text(
      usuario.iniciales,
      style: MotorutaTheme.titulo(
        tamano: tamano * 0.37,
        color: Colors.white,
      ),
    );
  }
}

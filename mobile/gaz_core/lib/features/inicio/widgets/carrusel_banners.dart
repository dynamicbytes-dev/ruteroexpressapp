import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/theme/motoruta_colors.dart';
import '../../../core/ui/botones.dart';
import '../../../data/models/banner_promocional.dart';

/// Carrusel horizontal moderno de tarjetas publicitarias y promocionales.
///
/// Diseñado bajo los estándares de aplicaciones de movilidad: bordes muy redondeados
/// (20px-24px), sombras sutiles, tipografía Poppins y paleta de acentos de la marca.
class CarruselBanners extends StatefulWidget {
  const CarruselBanners({
    super.key,
    required this.banners,
  });

  final List<BannerPromocional> banners;

  @override
  State<CarruselBanners> createState() => _CarruselBannersState();
}

class _CarruselBannersState extends State<CarruselBanners> {
  late final PageController _pageController;
  int _paginaActual = 0;
  Timer? _temporizadorAutoScroll;

  @override
  void initState() {
    super.initState();
    _pageController = PageController(viewportFraction: 0.92);

    if (widget.banners.length > 1) {
      _temporizadorAutoScroll = Timer.periodic(
        const Duration(seconds: 6),
        (_) {
          if (!mounted || !_pageController.hasClients) return;
          final siguiente = (_paginaActual + 1) % widget.banners.length;
          _pageController.animateToPage(
            siguiente,
            duration: const Duration(milliseconds: 500),
            curve: Curves.easeOutCubic,
          );
        },
      );
    }
  }

  @override
  void dispose() {
    _temporizadorAutoScroll?.cancel();
    _pageController.dispose();
    super.dispose();
  }

  void _abrirDetalle(BannerPromocional banner) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => _HojaDetalleBanner(banner: banner),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (widget.banners.isEmpty) {
      return const SizedBox.shrink();
    }

    final soloUno = widget.banners.length == 1;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        SizedBox(
          height: 148,
          child: soloUno
              ? Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: _TarjetaBanner(
                    banner: widget.banners.first,
                    alTocar: () => _abrirDetalle(widget.banners.first),
                  ),
                )
              : PageView.builder(
                  controller: _pageController,
                  itemCount: widget.banners.length,
                  onPageChanged: (indice) {
                    setState(() => _paginaActual = indice);
                  },
                  itemBuilder: (context, index) {
                    final banner = widget.banners[index];
                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 5),
                      child: _TarjetaBanner(
                        banner: banner,
                        alTocar: () => _abrirDetalle(banner),
                      ),
                    );
                  },
                ),
        ),
        if (!soloUno) ...<Widget>[
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List<Widget>.generate(
              widget.banners.length,
              (index) {
                final activo = index == _paginaActual;
                return AnimatedContainer(
                  duration: const Duration(milliseconds: 260),
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  width: activo ? 20 : 6,
                  height: 6,
                  decoration: BoxDecoration(
                    color: activo
                        ? MotorutaColors.brand800
                        : MotorutaColors.ink200,
                    borderRadius: BorderRadius.circular(10),
                  ),
                );
              },
            ),
          ),
        ],
      ],
    );
  }
}

class _TarjetaBanner extends StatelessWidget {
  const _TarjetaBanner({
    required this.banner,
    required this.alTocar,
  });

  final BannerPromocional banner;
  final VoidCallback alTocar;

  @override
  Widget build(BuildContext context) {
    final tieneFin = banner.colorFondoFin != null;

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: tieneFin
              ? <Color>[banner.colorFondo, banner.colorFondoFin!]
              : <Color>[banner.colorFondo, banner.colorFondo],
        ),
        border: Border.all(
          color: Colors.white.withOpacity(0.12),
          width: 1,
        ),
        boxShadow: MotorutaTheme.sombraTarjeta,
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: alTocar,
          splashColor: Colors.white.withOpacity(0.15),
          highlightColor: Colors.white.withOpacity(0.08),
          child: Stack(
            children: <Widget>[
              // Decoración o imagen lateral
              if (banner.imagenUrl != null)
                Positioned(
                  right: -10,
                  bottom: -10,
                  top: -10,
                  width: 130,
                  child: Opacity(
                    opacity: 0.88,
                    child: Image.network(
                      banner.imagenUrl!,
                      fit: BoxFit.contain,
                      alignment: Alignment.centerRight,
                      errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                    ),
                  ),
                )
              else
                Positioned(
                  right: -20,
                  bottom: -20,
                  child: Opacity(
                    opacity: 0.08,
                    child: Icon(
                      Icons.local_offer_rounded,
                      size: 140,
                      color: banner.colorTexto,
                    ),
                  ),
                ),

              // Contenido textual con tipografía Poppins
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 14, 18, 14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: <Widget>[
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        if (banner.etiqueta != null &&
                            banner.etiqueta!.isNotEmpty) ...<Widget>[
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 2.5,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.22),
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(
                                color: Colors.white.withOpacity(0.2),
                                width: 0.8,
                              ),
                            ),
                            child: Text(
                              banner.etiqueta!,
                              style: TextStyle(
                                fontFamily: 'Poppins',
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 0.6,
                                color: banner.colorTexto,
                              ),
                            ),
                          ),
                          const SizedBox(height: 6),
                        ],
                        SizedBox(
                          width: banner.imagenUrl != null ? 190 : null,
                          child: Text(
                            banner.titulo,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontFamily: 'Poppins',
                              fontSize: 15.5,
                              fontWeight: FontWeight.w700,
                              height: 1.25,
                              color: banner.colorTexto,
                            ),
                          ),
                        ),
                        if (banner.subtitulo != null &&
                            banner.subtitulo!.isNotEmpty) ...<Widget>[
                          const SizedBox(height: 3),
                          SizedBox(
                            width: banner.imagenUrl != null ? 180 : null,
                            child: Text(
                              banner.subtitulo!,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                fontFamily: 'Poppins',
                                fontSize: 11.5,
                                fontWeight: FontWeight.w400,
                                color: banner.colorTexto.withOpacity(0.85),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    Row(
                      children: <Widget>[
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.black.withOpacity(0.24),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: <Widget>[
                              Text(
                                banner.textoBoton,
                                style: TextStyle(
                                  fontFamily: 'Poppins',
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w600,
                                  color: banner.colorTexto,
                                ),
                              ),
                              const SizedBox(width: 4),
                              Icon(
                                Icons.arrow_forward_rounded,
                                size: 13,
                                color: banner.colorTexto,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Hoja modal inferior con detalles completos de la tarjeta promocional.
class _HojaDetalleBanner extends StatelessWidget {
  const _HojaDetalleBanner({required this.banner});

  final BannerPromocional banner;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      padding: const EdgeInsets.fromLTRB(22, 12, 22, 32),
      child: SafeArea(
        top: false,
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
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
            const SizedBox(height: 20),
            if (banner.etiqueta != null)
              Align(
                alignment: Alignment.centerLeft,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 3,
                  ),
                  decoration: BoxDecoration(
                    color: MotorutaColors.brand50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: MotorutaColors.brand100),
                  ),
                  child: Text(
                    banner.etiqueta!,
                    style: const TextStyle(
                      fontFamily: 'Poppins',
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: MotorutaColors.brand800,
                    ),
                  ),
                ),
              ),
            const SizedBox(height: 10),
            Text(
              banner.titulo,
              style: MotorutaTheme.titulo(tamano: 20),
            ),
            if (banner.subtitulo != null) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                banner.subtitulo!,
                style: const TextStyle(
                  fontFamily: 'Poppins',
                  fontSize: 14,
                  height: 1.45,
                  color: MotorutaColors.ink600,
                ),
              ),
            ],
            if (banner.imagenUrl != null) ...<Widget>[
              const SizedBox(height: 16),
              ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: Image.network(
                  banner.imagenUrl!,
                  height: 140,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                ),
              ),
            ],
            const SizedBox(height: 24),
            BotonPrincipal(
              texto: banner.textoBoton,
              alPresionar: () {
                Navigator.of(context).pop();
                // Si el enlace tiene un protocolo web o acción
                if (banner.enlace != null && banner.enlace!.isNotEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(
                        'Abriendo: ${banner.enlace}',
                        style: const TextStyle(fontFamily: 'Poppins'),
                      ),
                    ),
                  );
                }
              },
            ),
          ],
        ),
      ),
    );
  }
}

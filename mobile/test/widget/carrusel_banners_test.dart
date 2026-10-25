import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:motoruta/data/models/banner_promocional.dart';
import 'package:motoruta/features/inicio/widgets/carrusel_banners.dart';

void main() {
  testWidgets('CarruselBanners renderiza tarjeta promocional con textos y badge',
      (tester) async {
    const banners = <BannerPromocional>[
      BannerPromocional(
        id: 1,
        titulo: 'Promo Especial de Bienvenida',
        subtitulo: 'Descuento en tu primer viaje',
        etiqueta: '50% OFF',
        colorFondo: Color(0xFF310F38),
        colorFondoFin: Color(0xFF9A0065),
        colorTexto: Colors.white,
        textoBoton: 'Aprovechar',
      ),
    ];

    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: CarruselBanners(banners: banners),
        ),
      ),
    );

    expect(find.text('Promo Especial de Bienvenida'), findsOneWidget);
    expect(find.text('Descuento en tu primer viaje'), findsOneWidget);
    expect(find.text('50% OFF'), findsOneWidget);
    expect(find.text('Aprovechar'), findsOneWidget);
  });

  testWidgets('CarruselBanners retorna SizedBox.shrink cuando no hay banners',
      (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: CarruselBanners(banners: []),
        ),
      ),
    );

    expect(find.byType(PageView), findsNothing);
  });
}

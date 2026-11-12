import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:gaz_core/data/models/banner_promocional.dart';

void main() {
  group('BannerPromocional', () {
    test('parsea correctamente un banner desde JSON del backend', () {
      final json = <String, dynamic>{
        'id': 1,
        'title': '¡50% de descuento en tu primer viaje!',
        'subtitle': 'Usa el cupón GAZ50 al pedir tu moto o carro',
        'tag': 'PROMO',
        'image_url': 'https://gaz.app/banner1.png',
        'background_color': '#310F38',
        'background_color_end': '#9A0065',
        'text_color': '#FFFFFF',
        'action_url': 'https://gaz.app/promo',
        'action_text': 'Aprovechar',
        'target_role': 'passenger',
      };

      final banner = BannerPromocional.desdeJson(json);

      expect(banner.id, 1);
      expect(banner.titulo, '¡50% de descuento en tu primer viaje!');
      expect(banner.subtitulo, 'Usa el cupón GAZ50 al pedir tu moto o carro');
      expect(banner.etiqueta, 'PROMO');
      expect(banner.imagenUrl, 'https://gaz.app/banner1.png');
      expect(banner.colorFondo, const Color(0xFF310F38));
      expect(banner.colorFondoFin, const Color(0xFF9A0065));
      expect(banner.colorTexto, const Color(0xFFFFFFFF));
      expect(banner.enlace, 'https://gaz.app/promo');
      expect(banner.textoBoton, 'Aprovechar');
      expect(banner.rolObjetivo, 'passenger');
    });

    test('aplica valores por defecto cuando faltan campos opcionales', () {
      final json = <String, dynamic>{
        'id': '2',
        'title': 'Banner simple',
      };

      final banner = BannerPromocional.desdeJson(json);

      expect(banner.id, 2);
      expect(banner.titulo, 'Banner simple');
      expect(banner.subtitulo, isNull);
      expect(banner.etiqueta, isNull);
      expect(banner.imagenUrl, isNull);
      expect(banner.colorFondoFin, isNull);
      expect(banner.textoBoton, 'Ver más');
      expect(banner.rolObjetivo, 'passenger');
    });
  });
}

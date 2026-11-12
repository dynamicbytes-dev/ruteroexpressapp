/// Punto de entrada público del paquete compartido de GAZ.
///
/// Las apps de pasajero (`gaz_passenger`) y conductor (`gaz_driver`) importan
/// `package:gaz_core/gaz_core.dart` y construyen su `main` con estas piezas.
library;

export 'core/api/api_client.dart';
export 'core/api/api_config.dart';
export 'core/api/api_exception.dart';
export 'core/app/gaz_app.dart';
export 'core/legal/terminos_version.dart';
export 'core/push/push_service.dart';
export 'core/realtime/evento_tiempo_real.dart';
export 'core/realtime/tiempo_real_service.dart';
export 'core/storage/token_storage.dart';
export 'core/theme/app_theme.dart';
export 'core/theme/gaz_colors.dart';
export 'core/theme/gaz_tokens.dart';
export 'core/theme/motoruta_colors.dart';
export 'core/theme/theme_controller.dart';
export 'data/billetera_repository.dart';
export 'data/cuenta_repository.dart';
export 'data/dispositivos_repository.dart';
export 'data/models/aviso.dart';
export 'data/models/banner_promocional.dart';
export 'data/models/billetera.dart';
export 'data/models/ciudad.dart';
export 'data/models/favorito.dart';
export 'data/models/pagina_viajes.dart';
export 'data/models/periodo_historial.dart';
export 'data/models/registro_datos.dart';
export 'data/models/soporte.dart';
export 'data/models/usuario.dart';
export 'data/models/viaje.dart';
export 'data/notificaciones_repository.dart';
export 'data/publicidad_repository.dart';
export 'data/soporte_repository.dart';
export 'data/viajes_repository.dart';
export 'core/ui/barra_navegacion_flotante.dart';
export 'core/ui/boton_tema.dart';
export 'features/sesion/sesion_controller.dart';
export 'features/viajes/screens/viajes_agendados_screen.dart';

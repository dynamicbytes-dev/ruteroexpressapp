import '../../../core/legal/terminos_version.dart';

/// Una sección del documento legal.
class SeccionLegal {
  const SeccionLegal(this.titulo, this.parrafos);

  final String titulo;
  final List<String> parrafos;
}

/// Borrador de los términos y condiciones.
///
/// Está redactado a partir de las reglas de negocio actuales y debe revisarse
/// con un profesional del derecho antes de publicar la app en las tiendas.
const List<SeccionLegal> terminosLegales = <SeccionLegal>[
  SeccionLegal('1. Qué es GAZ', <String>[
    'GAZ es una plataforma tecnológica que conecta a personas que necesitan un viaje con conductores que ofrecen el servicio en moto o carro. La plataforma facilita la solicitud, la asignación, el seguimiento y el pago, pero no presta directamente el servicio de transporte.',
    'El servicio puede no estar disponible en todas las zonas ni en todo momento. La app muestra las opciones disponibles antes de confirmar cada viaje.',
  ]),
  SeccionLegal('2. Tu cuenta', <String>[
    'Para registrarte debes ser mayor de edad y proporcionar información verdadera, completa y actualizada. Cada persona puede tener una sola cuenta y la cédula no puede repetirse.',
    'La cuenta queda en espera de verificación hasta que el equipo revise los documentos requeridos. Mientras esté pendiente, rechazada, suspendida o bloqueada, no podrás pedir viajes, activarte como conductor ni recargar la billetera.',
    'Eres responsable de mantener tu contraseña y tu código de verificación en un lugar seguro. No compartas tu cuenta ni permitsas que otra persona la use.',
  ]),
  SeccionLegal('3. Conductores y vehículos', <String>[
    'El conductor debe tener licencia vigente, los documentos del vehículo exigidos por la ley y un vehículo en condiciones adecuadas para prestar el servicio. La información del vehículo debe coincidir con la registrada en la cuenta.',
    'El conductor elige si trabaja con moto o carro. El tipo de vehículo se muestra al pasajero antes de confirmar y afecta la tarifa y el tiempo estimado del viaje.',
    'Los documentos vencidos o rechazados pueden devolver la cuenta a revisión, desconectar al conductor y suspender la recepción de viajes.',
  ]),
  SeccionLegal('4. Tarifas, pagos y comisión', <String>[
    'El precio se calcula en el servidor de GAZ antes de pedir el viaje. La app solo muestra el resultado; no puedes modificar la tarifa desde el teléfono.',
    'La tarifa puede incluir distancia, tiempo, tarifa mínima, recargo nocturno, tarifa dinámica y un recargo fijo de \$1.00 cuando el pasajero elige carro. El desglose se muestra antes de confirmar.',
    'El pago puede hacerse con saldo de la billetera o en efectivo, según las opciones disponibles. Las recargas se acreditan cuando el auditor las aprueba. La comisión de la plataforma es del 10% del total del viaje y se descuenta de la ganancia del conductor.',
    'Las tarifas se expresan en dólares estadounidenses. Cuando corresponda un pago en bolívares, se aplica la tasa de cambio vigente al momento de la operación.',
  ]),
  SeccionLegal('5. Cancelaciones, rechazos y bloqueos', <String>[
    'El pasajero puede cancelar antes de que el viaje comience. El conductor puede rechazar una solicitud sin aceptarla. Las condiciones y posibles consecuencias se muestran en la app.',
    'GAZ puede suspender o bloquear una cuenta por documentos vencidos, uso fraudulento, conducta insegura, suplantación de identidad, cobros indebidos o incumplimiento de estas condiciones. Cuando la ley lo permita, se informará el motivo.',
  ]),
  SeccionLegal('6. Uso correcto y seguridad', <String>[
    'No está permitido usar la plataforma para actividades ilegales, transportar personas o bienes prohibidos, acosar a otras personas, suplantar identidades, manipular pagos o intentar acceder a datos de otras cuentas.',
    'Durante el viaje debes seguir las normas de tránsito y las indicaciones de seguridad. En una emergencia, contacta primero a los servicios de emergencia de tu país.',
  ]),
  SeccionLegal('7. Ubicación y comunicaciones', <String>[
    'La app usa la ubicación para mostrar el punto de recogida, calcular la ruta y permitir el seguimiento del viaje. El conductor comparte su ubicación mientras está en línea y durante un viaje activo.',
    'Podemos enviarte notificaciones relacionadas con tu cuenta, tus viajes, tus pagos y la seguridad. También podemos escribirte al correo registrado para verificar la cuenta o atender una solicitud.',
  ]),
  SeccionLegal('8. Disponibilidad y responsabilidad', <String>[
    'La plataforma puede tener interrupciones, errores o demoras por mantenimiento, conectividad, mapas de terceros o causas fuera de nuestro control. Hacemos esfuerzos razonables para mantener el servicio disponible.',
    'En la medida en que la ley lo permita, GAZ no responde por daños indirectos derivados del uso de la plataforma. Nada en estas condiciones limita los derechos que la ley reconoce al consumidor.',
  ]),
  SeccionLegal('9. Cambios y contacto', <String>[
    'Podemos actualizar estos términos cuando cambien el servicio o la ley. La versión vigente se muestra en la app y, si el cambio es relevante, se pedirá una nueva aceptación.',
    'Para consultas sobre estas condiciones escribe a $correoTerminos.',
  ]),
];

/// Borrador de la política de privacidad.
const List<SeccionLegal> privacidadLegal = <SeccionLegal>[
  SeccionLegal('1. Responsable del tratamiento', <String>[
    'GAZ es responsable de los datos personales que trata para operar la plataforma. Puedes contactarnos en $correoTerminos.',
  ]),
  SeccionLegal('2. Datos que recogemos', <String>[
    'Recogemos los datos que proporcionas al registrarte: nombre, apellido, cédula, correo, teléfono, dirección y contraseña protegida. Si eres conductor, también los datos del vehículo.',
    'Para verificar la cuenta tratamos los documentos que subes, como cédula, selfie, licencia, carnet de circulación, RCV y foto del vehículo. También registramos viajes, tarifas, pagos, recargas, retiros y el historial de atención.',
    'La app puede recoger la ubicación del dispositivo para calcular rutas, asignar viajes y mostrar el seguimiento. Del dispositivo tratamos datos técnicos necesarios para las notificaciones y la seguridad de la cuenta.',
  ]),
  SeccionLegal('3. Para qué usamos los datos', <String>[
    'Usamos los datos para crear y verificar cuentas, conectar pasajeros con conductores, calcular tarifas, procesar pagos, prevenir fraude, atender solicitudes, enviar avisos y cumplir obligaciones legales.',
    'No vendemos datos personales. No usamos los documentos de identidad para fines distintos de la verificación y la seguridad de la plataforma.',
  ]),
  SeccionLegal('4. Con quién se comparten', <String>[
    'El pasajero y el conductor ven la información necesaria para realizar el viaje, como nombre, punto de encuentro, teléfono cuando el viaje ya está asignado y datos del vehículo.',
    'Podemos compartir datos con proveedores que prestan servicios técnicos, de mapas, comunicaciones o pagos, y con autoridades cuando exista una obligación legal. Estos proveedores deben tratar la información con medidas de seguridad adecuadas.',
  ]),
  SeccionLegal('5. Ubicación', <String>[
    'La ubicación se usa para calcular la ruta, encontrar conductores cercanos, mostrar el avance del viaje y atender incidentes de seguridad. El conductor comparte su ubicación mientras está en línea y durante un viaje activo.',
    'Puedes desactivar los permisos de ubicación en el teléfono, pero algunas funciones de la app dejarán de funcionar.',
  ]),
  SeccionLegal('6. Conservación', <String>[
    'Conservamos los datos mientras la cuenta esté activa y durante el tiempo necesario para cumplir obligaciones legales, resolver disputas y prevenir fraude. Los documentos y el historial de viajes pueden conservarse después de cerrar la cuenta cuando la ley lo exija.',
  ]),
  SeccionLegal('7. Seguridad', <String>[
    'Aplicamos medidas técnicas y organizativas razonables para proteger la información, como control de acceso, contraseñas protegidas y verificación en dos pasos opcional. Ningún sistema es completamente infalible; si detectas un acceso no autorizado, avísanos de inmediato.',
  ]),
  SeccionLegal('8. Tus derechos', <String>[
    'Puedes solicitar acceso, corrección o eliminación de tus datos, oponerte a determinados tratamientos y actualizar tu nombre, apellido, teléfono, dirección y correo desde la app. La cédula solo puede corregirla un auditor con un motivo y registro de auditoría.',
    'Para ejercer tus derechos escríbenos a $correoTerminos. Responderemos dentro de los plazos que exija la ley aplicable.',
  ]),
  SeccionLegal('9. Cambios en esta política', <String>[
    'Podemos actualizar esta política cuando cambie el tratamiento de datos o la ley. Publicaremos la versión vigente en la app e informaremos los cambios relevantes.',
  ]),
];

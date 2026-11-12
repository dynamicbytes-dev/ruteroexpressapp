/* =========================================================
   GAZ 2027 · Prototipo móvil
   Interacciones: navegación, tema, hojas, viaje, recarga.
   ========================================================= */

(() => {
  "use strict";

  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

  const root = document.documentElement;
  const THEME_KEY = "gaz2027-tema";

  /* -------------------------------------------------------
     Tema
     ------------------------------------------------------- */

  function leerTema() {
    try {
      return localStorage.getItem(THEME_KEY);
    } catch {
      return null;
    }
  }

  function guardarTema(tema) {
    try {
      localStorage.setItem(THEME_KEY, tema);
    } catch {
      /* Sin persistencia: el prototipo sigue funcionando. */
    }
  }

  function aplicarTema(tema) {
    const oscuro = tema === "dark";
    root.dataset.theme = tema;

    $$(".review-theme-text").forEach((el) => {
      el.textContent = oscuro ? "Cambiar a claro" : "Cambiar a oscuro";
    });

    $$("[data-tema] .icon use").forEach((use) => {
      use.setAttribute("href", oscuro ? "#i-sun" : "#i-moon");
    });

    $$("[data-tema-switch]").forEach((sw) => {
      sw.classList.toggle("on", oscuro);
      sw.setAttribute("aria-checked", String(oscuro));
    });
  }

  function alternarTema() {
    const siguiente = root.dataset.theme === "dark" ? "light" : "dark";
    aplicarTema(siguiente);
    guardarTema(siguiente);
  }

  const temaGuardado = leerTema();
  const prefiereOscuro = window.matchMedia("(prefers-color-scheme: dark)").matches;
  aplicarTema(temaGuardado || (prefiereOscuro ? "dark" : "light"));

  window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", (event) => {
    if (!leerTema()) {
      aplicarTema(event.matches ? "dark" : "light");
    }
  });

  $$("[data-tema], [data-tema-switch]").forEach((boton) => {
    boton.addEventListener("click", (event) => {
      event.preventDefault();
      alternarTema();
    });
  });

  /* -------------------------------------------------------
     Navegación entre pantallas
     ------------------------------------------------------- */

  const pantallas = $$(".screen");
  const navPasajero = $("#nav-pasajero");
  const navConductor = $("#nav-conductor");
  const barraEstado = $("#status-bar");
  const tituloEscenario = $("#stage-titulo");
  const leyendaTelefono = $("#phone-caption");

  let pantallaActual = "inicio";

  function irA(id) {
    cerrarHojas(false);
    const destino = pantallas.find((pantalla) => pantalla.dataset.screen === id);

    if (!destino) {
      mostrarToast("Esta vista llega en la siguiente iteración");
      return;
    }

    pantallas.forEach((pantalla) => {
      pantalla.classList.toggle("activo", pantalla === destino);
    });

    pantallaActual = id;

    const tipoNav = destino.dataset.nav || "none";
    if (navPasajero) navPasajero.hidden = tipoNav !== "pasajero";
    if (navConductor) navConductor.hidden = tipoNav !== "conductor";

    if (barraEstado) {
      barraEstado.classList.toggle("status-bar--light", destino.dataset.status === "light");
    }

    const titulo = destino.dataset.titulo || id;
    if (tituloEscenario) tituloEscenario.textContent = titulo;
    if (leyendaTelefono) leyendaTelefono.textContent = `${titulo} · 390 × 844`;

    try {
      if (window.location.hash !== `#${id}`) {
        window.history.replaceState(null, "", `#${id}`);
      }
    } catch {
      /* file:// puede bloquear replaceState en algunos navegadores. */
    }

    $$("[data-go]", navPasajero || document).forEach((el) => {
      el.classList.toggle("activo", el.dataset.go === id);
    });
    $$("[data-go]", navConductor || document).forEach((el) => {
      el.classList.toggle("activo", el.dataset.go === id);
    });
    $$(".screen-nav button").forEach((boton) => {
      boton.classList.toggle("activo", boton.dataset.go === id);
    });

    const scroll = $(".screen-scroll", destino);
    if (scroll) scroll.scrollTop = 0;

    if (id === "driver-inicio") renderizarConexion();
  }

  document.addEventListener("click", (event) => {
    const destino = event.target.closest("[data-go]");
    if (!destino) return;
    event.preventDefault();
    irA(destino.dataset.go);
  });

  /* -------------------------------------------------------
     Hojas inferiores
     ------------------------------------------------------- */

  const overlay = $("#overlay");
  let ultimaHoja = null;

  function abrirHoja(nombre) {
    const hoja = document.getElementById(`sheet-${nombre}`);
    if (!hoja) return;

    cerrarHojas(false);
    ultimaHoja = hoja;
    if (overlay) overlay.classList.add("abierto");
    hoja.classList.add("abierto");
  }

  function cerrarHojas(devolverFoco = true) {
    if (overlay) overlay.classList.remove("abierto");
    $$(".sheet").forEach((hoja) => hoja.classList.remove("abierto"));

    if (devolverFoco && ultimaHoja) {
      const enfocable = $("button, input, [tabindex]", ultimaHoja);
      if (enfocable) enfocable.blur();
    }
    ultimaHoja = null;
  }

  document.addEventListener("click", (event) => {
    const abridor = event.target.closest("[data-sheet]");
    if (abridor) {
      event.preventDefault();
      abrirHoja(abridor.dataset.sheet);
      return;
    }

    const cerrador = event.target.closest("[data-cerrar]");
    if (cerrador) {
      event.preventDefault();
      cerrarHojas();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") cerrarHojas();
  });

  /* -------------------------------------------------------
     Toast
     ------------------------------------------------------- */

  const toast = $("#toast");
  const toastTexto = $("#toast-texto");
  let temporizadorToast;

  function mostrarToast(mensaje) {
    if (!toast || !toastTexto) return;
    toastTexto.textContent = mensaje;
    toast.classList.add("visible");
    window.clearTimeout(temporizadorToast);
    temporizadorToast = window.setTimeout(() => {
      toast.classList.remove("visible");
    }, 2600);
  }

  /* -------------------------------------------------------
     Saldo y recarga
     ------------------------------------------------------- */

  let saldo = 24.5;

  function formatearMonto(valor) {
    return `$${valor.toFixed(2)}`;
  }

  function renderizarSaldo() {
    const texto = formatearMonto(saldo);
    ["saldo-inicio", "saldo-billetera", "disp-billetera"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.textContent = texto;
    });
  }

  $$(".amount-chip").forEach((chip) => {
    chip.addEventListener("click", () => {
      $$(".amount-chip").forEach((otro) => otro.classList.remove("activo"));
      chip.classList.add("activo");

      const monto = chip.dataset.monto;
      const input = $("#monto-recarga");
      if (!input) return;

      if (monto === "otro") {
        input.value = "";
        input.focus();
      } else {
        input.value = Number(monto).toFixed(2);
      }
    });
  });

  const botonRecargar = $("#btn-recargar");
  if (botonRecargar) {
    botonRecargar.addEventListener("click", () => {
      const input = $("#monto-recarga");
      const valor = Number.parseFloat((input?.value || "").replace(",", "."));

      if (!Number.isFinite(valor) || valor <= 0) {
        mostrarToast("Ingresa un monto válido");
        input?.focus();
        return;
      }

      saldo += valor;
      renderizarSaldo();
      agregarMovimientoRecarga(valor);
      cerrarHojas();
      mostrarToast(`Recarga de ${formatearMonto(valor)} en revisión`);
    });
  }

  function agregarMovimientoRecarga(valor) {
    const lista = $(".movement")?.parentElement;
    if (!lista) return;

    const fila = document.createElement("div");
    fila.className = "movement";
    fila.innerHTML = `
      <span class="movement-icon movement-icon--in">
        <svg class="icon icon-sm"><use href="#i-download"/></svg>
      </span>
      <div class="grow">
        <div class="movement-title">Recarga con Pago Móvil</div>
        <div class="movement-meta">Ahora · En revisión</div>
      </div>
      <div class="movement-amount movement-amount--in">+${formatearMonto(valor)}</div>
    `;
    lista.prepend(fila);
  }

  /* -------------------------------------------------------
     Pedir viaje
     ------------------------------------------------------- */

  $$(".vehicle-option").forEach((opcion) => {
    opcion.addEventListener("click", () => {
      $$(".vehicle-option").forEach((otra) => otra.classList.remove("activo"));
      opcion.classList.add("activo");

      const precio = opcion.dataset.precio || "$2.80";
      const precioPedir = $("#precio-pedir");
      if (precioPedir) precioPedir.textContent = precio;

      const precioConfirmar = $("#confirmar-precio");
      if (precioConfirmar) precioConfirmar.textContent = precio;

      const vehiculoConfirmar = $("#confirmar-vehiculo");
      if (vehiculoConfirmar) {
        vehiculoConfirmar.textContent =
          opcion.dataset.vehiculo === "carro" ? "Carro · 6,8 km · 16 min" : "Moto · 4,2 km · 12 min";
      }
    });
  });

  let viajeActivo = false;
  let faseViaje = 0;

  const estadosViaje = [
    "Conductor en camino",
    "El conductor llegó",
    "En viaje",
    "Viaje completado",
  ];

  const etasViaje = [
    'Llega en <strong>4 min</strong>',
    "El conductor te espera",
    'Llegas en <strong>8 min</strong>',
    "Viaje completado",
  ];

  function renderizarViaje() {
    const pasos = $$("#timeline-viaje .timeline-step");
    pasos.forEach((paso, indice) => {
      const hecho = indice < faseViaje || faseViaje === 3;
      paso.classList.toggle("hecho", hecho);
      paso.classList.toggle("actual", indice === faseViaje && faseViaje < 3);
    });

    const estado = $("#viaje-estado");
    if (estado) estado.textContent = estadosViaje[faseViaje];

    const eta = $("#viaje-eta");
    if (eta) {
      eta.innerHTML = `<svg class="icon icon-sm"><use href="#i-clock"/></svg> ${etasViaje[faseViaje]}`;
    }
  }

  function avanzarViaje() {
    if (!viajeActivo) {
      mostrarToast("Primero pide un viaje");
      irA("pedir");
      return;
    }

    if (faseViaje < 3) {
      faseViaje += 1;
      renderizarViaje();

      if (faseViaje === 3) {
        viajeActivo = false;
        const tarjeta = $("#viaje-activo-card");
        if (tarjeta) tarjeta.hidden = true;
        abrirHoja("calificar");
        mostrarToast("Viaje completado");
      }
    }
  }

  const botonConfirmar = $("#btn-confirmar-viaje");
  if (botonConfirmar) {
    botonConfirmar.addEventListener("click", () => {
      viajeActivo = true;
      faseViaje = 0;
      renderizarViaje();

      const tarjeta = $("#viaje-activo-card");
      if (tarjeta) tarjeta.hidden = false;

      cerrarHojas();
      irA("viaje");
      mostrarToast("Conductor asignado · Jesús va en camino");
    });
  }

  /* -------------------------------------------------------
     Calificación
     ------------------------------------------------------- */

  $$(".rating-star").forEach((estrella) => {
    estrella.addEventListener("click", () => {
      const valor = Number(estrella.dataset.estrella);
      $$(".rating-star").forEach((otra, indice) => {
        otra.classList.toggle("activo", indice < valor);
      });
    });
  });

  const botonCalificar = $("#btn-calificar");
  if (botonCalificar) {
    botonCalificar.addEventListener("click", () => {
      cerrarHojas();
      irA("inicio");
      mostrarToast("¡Gracias por calificar a Jesús!");
    });
  }

  /* -------------------------------------------------------
     Historial
     ------------------------------------------------------- */

  $$("#filtro-historial button").forEach((boton) => {
    boton.addEventListener("click", () => {
      $$("#filtro-historial button").forEach((otro) => otro.classList.remove("activo"));
      boton.classList.add("activo");

      const periodo = boton.dataset.periodo;
      $$('[data-screen="historial"] .history-card').forEach((tarjeta) => {
        const tipo = tarjeta.dataset.periodo;
        const visible =
          periodo === "todos" ||
          tipo === periodo ||
          (periodo === "mes" && tipo === "semana");
        tarjeta.hidden = !visible;
      });
    });
  });

  $$("#filtro-driver-viajes button").forEach((boton) => {
    boton.addEventListener("click", () => {
      $$("#filtro-driver-viajes button").forEach((otro) => otro.classList.remove("activo"));
      boton.classList.add("activo");

      const periodo = boton.dataset.driverPeriodo;
      $$('[data-screen="driver-viajes"] .history-card').forEach((tarjeta) => {
        const tipo = tarjeta.dataset.driverPeriodo;
        const visible =
          periodo === "hoy"
            ? tipo === "hoy"
            : periodo === "semana"
              ? tipo === "hoy" || tipo === "semana"
              : true;
        tarjeta.hidden = !visible;
      });

      $$('[data-screen="driver-viajes"] .month-label[data-driver-periodo]').forEach((label) => {
        const tipo = label.dataset.driverPeriodo;
        const visible =
          periodo === "mes" ? true : periodo === "semana" ? tipo !== "mes" : tipo === "hoy";
        label.hidden = !visible;
      });
    });
  });

  const filtroDriverInicial = $("#filtro-driver-viajes [data-driver-periodo='hoy']");
  if (filtroDriverInicial) filtroDriverInicial.click();

  /* -------------------------------------------------------
     Conductor
     ------------------------------------------------------- */

  let enLinea = true;
  let faseConductor = 0;

  const estadosConductor = [
    "Ir al punto de recogida",
    "Esperando al pasajero",
    "Viaje en curso",
    "Viaje completado",
  ];

  const etiquetasConductor = [
    "Llegué al origen",
    "Iniciar viaje",
    "Finalizar viaje",
    "Viaje completado",
  ];

  function renderizarConexion() {
    const toggle = $("#online-toggle");
    const texto = $("#online-texto");
    const etiquetaCta = $("#conectar-texto");

    if (toggle) toggle.classList.toggle("off", !enLinea);
    if (texto) texto.textContent = enLinea ? "En línea" : "Desconectado";
    if (etiquetaCta) etiquetaCta.textContent = enLinea ? "En línea" : "Conectar";

    const cta = $("#btn-conectar");
    if (cta) cta.setAttribute("aria-label", enLinea ? "Salir de línea" : "Conectar");
  }

  function renderizarConductor() {
    const pasos = $$("#timeline-conductor .timeline-step");
    pasos.forEach((paso, indice) => {
      const hecho = indice < faseConductor || faseConductor === 3;
      paso.classList.toggle("hecho", hecho);
      paso.classList.toggle("actual", indice === faseConductor && faseConductor < 3);
    });

    const estado = $("#conductor-estado");
    if (estado) estado.textContent = estadosConductor[faseConductor];

    const boton = $("#btn-avanzar-conductor");
    if (boton) {
      boton.innerHTML = `${etiquetasConductor[faseConductor]}
        <svg class="icon icon-sm"><use href="#i-arrow-right"/></svg>`;
    }
  }

  function avanzarConductor() {
    if (faseConductor >= 3) return;

    faseConductor += 1;
    renderizarConductor();

    if (faseConductor === 3) {
      window.setTimeout(() => {
        irA("driver-inicio");
        mostrarToast("Viaje completado · $3.40 acreditados");
        faseConductor = 0;
        renderizarConductor();
      }, 650);
    }
  }

  const botonConductor = $("#btn-avanzar-conductor");
  if (botonConductor) {
    botonConductor.addEventListener("click", avanzarConductor);
  }

  const botonConectar = $("#btn-conectar");
  if (botonConectar) {
    botonConectar.addEventListener("click", () => {
      enLinea = !enLinea;
      renderizarConexion();
      mostrarToast(enLinea ? "Estás en línea · recibiendo ofertas" : "Has salido de línea");
    });
  }

  /* -------------------------------------------------------
     Controles de demostración del escenario
     ------------------------------------------------------- */

  const botonDemo = $("#btn-demo-avanzar");
  if (botonDemo) {
    botonDemo.addEventListener("click", () => {
      if (pantallaActual === "viaje") {
        avanzarViaje();
        return;
      }

      if (pantallaActual === "viaje-conductor") {
        avanzarConductor();
        return;
      }

      if (pantallaActual === "oferta") {
        irA("viaje-conductor");
        mostrarToast("Oferta aceptada · viaje iniciado");
        return;
      }

      if (pantallaActual === "driver-inicio") {
        irA("oferta");
        mostrarToast("Oferta de demostración");
        return;
      }

      if (["driver-viajes", "driver-ganancias", "driver-perfil"].includes(pantallaActual)) {
        mostrarToast("Abre una oferta desde el inicio del conductor");
        return;
      }

      if (!viajeActivo) {
        viajeActivo = true;
        faseViaje = 0;
        renderizarViaje();
        const tarjeta = $("#viaje-activo-card");
        if (tarjeta) tarjeta.hidden = false;
      }

      irA("viaje");
      mostrarToast("Demostración: viaje activo");
    });
  }

  /* -------------------------------------------------------
     Subir documento
     ------------------------------------------------------- */

  const botonSubir = $("#btn-subir-doc");
  if (botonSubir) {
    botonSubir.addEventListener("click", () => {
      cerrarHojas();
      mostrarToast("Documento enviado · en revisión");
    });
  }

  /* -------------------------------------------------------
     Acciones rápidas de demostración
     ------------------------------------------------------- */

  $$("[data-rechazar-oferta]").forEach((boton) => {
    boton.addEventListener("click", (event) => {
      event.preventDefault();
      mostrarToast("Oferta rechazada");
      irA("driver-inicio");
    });
  });

  $$("[data-toast]").forEach((elemento) => {
    elemento.addEventListener("click", (event) => {
      event.preventDefault();
      mostrarToast(elemento.dataset.toast);
    });
  });

  /* -------------------------------------------------------
     Estado inicial
     ------------------------------------------------------- */

  renderizarSaldo();
  renderizarViaje();
  renderizarConductor();

  window.addEventListener("hashchange", () => {
    const id = window.location.hash.replace("#", "");
    if (id && id !== pantallaActual) irA(id);
  });

  const pantallaInicial = window.location.hash.replace("#", "") || "inicio";
  irA(pantallaInicial);
})();

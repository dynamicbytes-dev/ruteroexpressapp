/* =========================================================
   GAZ 2026 · Prototipo interactivo
   ========================================================= */

(() => {
  "use strict";

  const html = document.documentElement;
  const phoneScreen = document.getElementById("phoneScreen");
  const statusbar = document.getElementById("statusbar");
  const toast = document.getElementById("toast");
  const pantallas = [...document.querySelectorAll(".pantalla")];
  const panelNav = [...document.querySelectorAll(".screen-nav button")];
  const navItems = [...document.querySelectorAll(".nav-item")];
  const navCta = document.querySelector(".nav-cta");

  let toastTimer = null;
  let montoRecarga = 10;
  let estadoViaje = 0;
  let destinoActual = "Casa · Los Palos Grandes";
  let vehiculoActual = "moto";
  let precioActual = "3,35";
  let tiempoActual = "12 min";

  const pantallasOscuras = new Set(["bienvenida", "acceso"]);
  const pantallasSinNav = new Set(["pedir", "viaje", "bienvenida", "acceso", "documentos"]);
  const pantallasValidas = new Set(pantallas.map((p) => p.dataset.pantalla));

  /* ---------------------------------------------------------
     Tema
     --------------------------------------------------------- */

  function temaGuardado() {
    try {
      return localStorage.getItem("gaz-prototipo-tema");
    } catch (_) {
      return null;
    }
  }

  function guardarTema(tema) {
    try {
      localStorage.setItem("gaz-prototipo-tema", tema);
    } catch (_) {
      /* almacenamiento no disponible */
    }
  }

  function aplicarTema(tema) {
    const oscuro = tema === "dark";
    html.dataset.theme = oscuro ? "dark" : "light";

    const panelTexto = document.querySelector(".theme-switch-text");
    const panelIcono = document.querySelector(".theme-switch-icon use");
    const stageIcono = document.querySelector("#btnTemaStage use");
    const switchTema = document.getElementById("switchTema");

    if (panelTexto) panelTexto.textContent = oscuro ? "Cambiar a claro" : "Cambiar a oscuro";
    if (panelIcono) panelIcono.setAttribute("href", oscuro ? "#i-sun" : "#i-moon");
    if (stageIcono) stageIcono.setAttribute("href", oscuro ? "#i-sun" : "#i-moon");
    if (switchTema) switchTema.checked = oscuro;

    guardarTema(oscuro ? "dark" : "light");
  }

  function alternarTema() {
    aplicarTema(html.dataset.theme === "dark" ? "light" : "dark");
  }

  aplicarTema(temaGuardado() || "light");

  document.getElementById("btnTemaPanel")?.addEventListener("click", alternarTema);
  document.getElementById("btnTemaStage")?.addEventListener("click", alternarTema);
  document.getElementById("switchTema")?.addEventListener("change", (e) => {
    aplicarTema(e.target.checked ? "dark" : "light");
  });

  /* ---------------------------------------------------------
     Navegación
     --------------------------------------------------------- */

  function irA(nombre, opciones = {}) {
    if (!pantallasValidas.has(nombre)) return;

    if (nombre === "viaje") reiniciarViaje();

    if (toastTimer) {
      clearTimeout(toastTimer);
      toastTimer = null;
    }
    toast?.classList.remove("visible");

    pantallas.forEach((pantalla) => {
      const activa = pantalla.dataset.pantalla === nombre;
      pantalla.classList.toggle("activa", activa);
      pantalla.setAttribute("aria-hidden", activa ? "false" : "true");
    });

    panelNav.forEach((boton) => {
      boton.classList.toggle("activo", boton.dataset.go === nombre);
    });

    navItems.forEach((item) => {
      const activo = item.dataset.navItem === nombre;
      item.classList.toggle("activo", activo);
      if (activo) {
        item.setAttribute("aria-current", "page");
      } else {
        item.removeAttribute("aria-current");
      }
    });

    if (navCta) navCta.classList.toggle("activo", nombre === "pedir");

    statusbar.classList.toggle("statusbar--claro", pantallasOscuras.has(nombre));

    if (opciones.scroll !== false) {
      const scroll = document.querySelector(`.pantalla[data-pantalla="${nombre}"] .scroll`);
      if (scroll) scroll.scrollTop = 0;
    }

    cerrarHojas();
    sincronizarNav();

    if (opciones.hash !== false) {
      history.replaceState(null, "", `#${nombre}`);
    }
  }

  function sincronizarNav() {
    const pantalla = document.querySelector(".pantalla.activa")?.dataset.pantalla;
    const hojaAbierta = document.querySelector(".sheet.abierto");
    phoneScreen.classList.toggle(
      "nav-off",
      pantallasSinNav.has(pantalla) || Boolean(hojaAbierta)
    );
  }

  document.addEventListener("click", (evento) => {
    const objetivo = evento.target.closest("[data-go], [data-toast]");
    if (!objetivo) return;

    const ir = objetivo.closest("[data-go]");
    if (ir) {
      irA(ir.dataset.go);
    }

    const aviso = objetivo.closest("[data-toast]");
    if (aviso) {
      mostrarToast(aviso.dataset.toast);
    }
  });

  window.addEventListener("hashchange", () => {
    const nombre = location.hash.replace("#", "");
    if (pantallasValidas.has(nombre)) irA(nombre, { hash: false });
  });

  /* ---------------------------------------------------------
     Toast
     --------------------------------------------------------- */

  function mostrarToast(mensaje) {
    if (!toast || !mensaje) return;

    toast.textContent = mensaje;
    const hojaAbierta = document.querySelector(".sheet.abierto");
    const pantallaViaje = document.querySelector('.pantalla.activa[data-pantalla="viaje"]');
    toast.classList.toggle("toast--alto", Boolean(hojaAbierta) || Boolean(pantallaViaje));
    toast.classList.add("visible");

    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      toast.classList.remove("visible");
    }, 2600);
  }

  /* ---------------------------------------------------------
     Hojas inferiores
     --------------------------------------------------------- */

  const hojas = {
    cotizacion: "sheetCotizacion",
    calificar: "sheetCalificar",
    detalle: "sheetDetalle",
    recargar: "sheetRecargar",
  };

  function abrirHoja(nombre) {
    const id = hojas[nombre];
    if (!id) return;

    cerrarHojas();

    const hoja = document.getElementById(id);
    const fondo = document.querySelector(`.sheet-backdrop[data-cerrar-sheet="${nombre}"]`);
    if (!hoja) return;

    hoja.classList.add("abierto");
    hoja.setAttribute("aria-hidden", "false");
    fondo?.classList.add("abierto");
    sincronizarNav();
  }

  function cerrarHoja(nombre) {
    const id = hojas[nombre];
    if (!id) return;

    const hoja = document.getElementById(id);
    const fondo = document.querySelector(`.sheet-backdrop[data-cerrar-sheet="${nombre}"]`);

    hoja?.classList.remove("abierto");
    hoja?.setAttribute("aria-hidden", "true");
    fondo?.classList.remove("abierto");
    sincronizarNav();
  }

  function cerrarHojas() {
    Object.keys(hojas).forEach(cerrarHoja);
  }

  document.addEventListener("click", (evento) => {
    const abrir = evento.target.closest("[data-sheet]");
    if (abrir) {
      abrirHoja(abrir.dataset.sheet);
      return;
    }

    const cerrar = evento.target.closest("[data-cerrar-sheet]");
    if (cerrar) {
      cerrarHoja(cerrar.dataset.cerrarSheet);
    }
  });

  document.addEventListener("keydown", (evento) => {
    if (evento.key === "Escape") cerrarHojas();
  });

  /* ---------------------------------------------------------
     Pedir viaje
     --------------------------------------------------------- */

  const campoDestino = document.getElementById("valorDestino");
  const pinDestino = document.getElementById("pinDestino");
  const rutaPedir = document.getElementById("rutaPedir");
  const btnPedir = document.getElementById("btnPedir");
  const destinoCotizacion = document.getElementById("destinoCotizacion");
  const farePrice = document.getElementById("farePrice");
  const fareSub = document.getElementById("fareSub");
  const fareLabel = document.getElementById("fareLabel");
  const totalCotizacion = document.getElementById("totalCotizacion");
  const confirmarViaje = document.getElementById("confirmarViaje");

  function seleccionarDestino(direccion) {
    destinoActual = direccion;

    if (campoDestino) {
      campoDestino.textContent = direccion;
      campoDestino.classList.remove("muted");
    }

    if (pinDestino) pinDestino.style.display = "grid";
    if (rutaPedir) rutaPedir.classList.add("visible");
    if (btnPedir) btnPedir.disabled = false;
    if (destinoCotizacion) destinoCotizacion.textContent = direccion;
    if (fareLabel) fareLabel.textContent = "Tarifa estimada";

    document.querySelectorAll("[data-destino]").forEach((chip) => {
      chip.classList.toggle("activo", chip.dataset.destino === direccion);
    });
  }

  document.querySelectorAll("[data-destino]").forEach((chip) => {
    chip.addEventListener("click", () => {
      seleccionarDestino(chip.dataset.destino);
      mostrarToast(`Destino: ${chip.dataset.destino}`);
    });
  });

  document.querySelectorAll("[data-vehiculo]").forEach((opcion) => {
    opcion.addEventListener("click", () => {
      document.querySelectorAll("[data-vehiculo]").forEach((otra) => {
        otra.classList.toggle("activo", otra === opcion);
      });

      vehiculoActual = opcion.dataset.vehiculo;
      precioActual = opcion.dataset.precio;
      tiempoActual = opcion.dataset.tiempo;

      if (farePrice) farePrice.textContent = `$${precioActual}`;
      if (fareSub) fareSub.textContent = `${tiempoActual} · 4,2 km`;
      if (totalCotizacion) totalCotizacion.textContent = `$${precioActual}`;

      const servicio = document.querySelector(".desglose > div:nth-child(3) strong");
      if (servicio) servicio.textContent = vehiculoActual === "carro" ? "$1,50" : "$0,50";

      if (confirmarViaje) confirmarViaje.textContent = `Confirmar viaje · $${precioActual}`;
    });
  });

  btnPedir?.addEventListener("click", () => {
    if (!destinoActual) {
      mostrarToast("Elige un destino para continuar.");
      return;
    }

    if (destinoCotizacion) destinoCotizacion.textContent = destinoActual;
    if (totalCotizacion) totalCotizacion.textContent = `$${precioActual}`;
    if (confirmarViaje) confirmarViaje.textContent = `Confirmar viaje · $${precioActual}`;
    abrirHoja("cotizacion");
  });

  confirmarViaje?.addEventListener("click", () => {
    cerrarHoja("cotizacion");
    reiniciarViaje();
    irA("viaje");
  });

  // Estado inicial de demostración: destino ya elegido.
  seleccionarDestino(destinoActual);

  /* ---------------------------------------------------------
     Viaje activo
     --------------------------------------------------------- */

  const estadosViaje = [
    {
      etiqueta: "Tu conductor está en camino",
      titulo: "Llega en 3 min",
      pill: "En camino",
      icono: "#i-moto",
      boton: "Simular llegada",
      eta: "3 min",
      pin: { left: "44%", top: "32%" },
      pasos: ["done", "activo", "", ""],
    },
    {
      etiqueta: "Tu conductor llegó al punto",
      titulo: "Te está esperando",
      pill: "En el punto",
      icono: "#i-pin",
      boton: "Iniciar viaje",
      eta: "Ahora",
      pin: { left: "18%", top: "46%" },
      pasos: ["done", "done", "activo", ""],
    },
    {
      etiqueta: "Viaje en curso",
      titulo: "Llegas en 8 min",
      pill: "En viaje",
      icono: "#i-nav",
      boton: "Completar viaje",
      eta: "8 min",
      pin: { left: "66%", top: "24%" },
      pasos: ["done", "done", "done", ""],
    },
    {
      etiqueta: "Viaje completado",
      titulo: "Gracias por viajar",
      pill: "Completado",
      icono: "#i-check",
      boton: "Calificar viaje",
      eta: "Listo",
      pin: { left: "82%", top: "14%" },
      pasos: ["done", "done", "done", "done"],
    },
  ];

  const estadoViajePill = document.getElementById("estadoViaje");
  const viajeEtiqueta = document.getElementById("viajeEtiqueta");
  const viajeTitulo = document.getElementById("viajeTitulo");
  const btnEstadoViaje = document.getElementById("btnEstadoViaje");
  const pinMoto = document.getElementById("pinMoto");
  const etaViaje = document.getElementById("etaViaje");
  const pasosTimeline = [...document.querySelectorAll("#timeline .paso")];

  function actualizarViaje() {
    const estado = estadosViaje[estadoViaje];

    if (estadoViajePill) {
      estadoViajePill.innerHTML = `<svg class="icon"><use href="${estado.icono}"/></svg> ${estado.pill}`;
    }
    if (viajeEtiqueta) viajeEtiqueta.textContent = estado.etiqueta;
    if (viajeTitulo) viajeTitulo.textContent = estado.titulo;
    if (btnEstadoViaje) btnEstadoViaje.textContent = estado.boton;
    if (etaViaje) etaViaje.textContent = estado.eta;

    if (pinMoto) {
      pinMoto.style.left = estado.pin.left;
      pinMoto.style.top = estado.pin.top;
    }

    pasosTimeline.forEach((paso, indice) => {
      paso.classList.remove("done", "activo");
      if (estado.pasos[indice]) paso.classList.add(estado.pasos[indice]);
    });
  }

  function reiniciarViaje() {
    estadoViaje = 0;
    actualizarViaje();
  }

  btnEstadoViaje?.addEventListener("click", () => {
    if (estadoViaje >= 3) {
      abrirHoja("calificar");
      return;
    }

    estadoViaje += 1;
    actualizarViaje();

    if (estadoViaje === 3) {
      setTimeout(() => abrirHoja("calificar"), 500);
    }
  });

  actualizarViaje();

  /* ---------------------------------------------------------
     Calificación
     --------------------------------------------------------- */

  const estrellas = [...document.querySelectorAll("#estrellas button")];

  estrellas.forEach((estrella, indice) => {
    estrella.addEventListener("click", () => {
      estrellas.forEach((otra, i) => {
        otra.classList.toggle("activa", i <= indice);
      });
    });
  });

  document.getElementById("enviarCalificacion")?.addEventListener("click", () => {
    cerrarHoja("calificar");
    mostrarToast("Gracias por calificar tu viaje.");
    setTimeout(() => {
      reiniciarViaje();
      irA("inicio");
    }, 900);
  });

  /* ---------------------------------------------------------
     Historial
     --------------------------------------------------------- */

  const filtrosHistorial = [...document.querySelectorAll("#filtrosHistorial .chip")];
  const gruposHistorial = [...document.querySelectorAll('[data-pantalla="historial"] .grupo-fecha')];

  function aplicarFiltroHistorial(indice) {
    filtrosHistorial.forEach((chip, i) => chip.classList.toggle("activo", i === indice));

    gruposHistorial.forEach((grupo, i) => {
      const mostrar = indice === 0 || indice === i + 1;
      grupo.style.display = mostrar ? "" : "none";
      const lista = grupo.nextElementSibling;
      if (lista) lista.style.display = mostrar ? "" : "none";
    });
  }

  filtrosHistorial.forEach((chip, indice) => {
    chip.addEventListener("click", () => {
      aplicarFiltroHistorial(indice);
      mostrarToast(
        indice === 0
          ? "Mostrando todos tus viajes."
          : indice === 1
            ? "Mostrando los viajes de este mes."
            : "Mostrando los viajes del mes anterior."
      );
    });
  });

  /* ---------------------------------------------------------
     Recarga
     --------------------------------------------------------- */

  const montosRecarga = [...document.querySelectorAll("#montosRecarga .monto-chip")];
  const confirmarRecarga = document.getElementById("confirmarRecarga");

  function formatearMonto(valor) {
    return valor.toFixed(2).replace(".", ",");
  }

  montosRecarga.forEach((chip) => {
    chip.addEventListener("click", () => {
      montosRecarga.forEach((otro) => otro.classList.toggle("activo", otro === chip));
      montoRecarga = Number(chip.dataset.monto);
      if (confirmarRecarga) {
        confirmarRecarga.textContent = `Recargar $${formatearMonto(montoRecarga)}`;
      }
    });
  });

  confirmarRecarga?.addEventListener("click", () => {
    const actual = 24.8;
    const nuevo = actual + montoRecarga;
    document.querySelectorAll(".balance-amount").forEach((monto) => {
      monto.textContent = `$${formatearMonto(nuevo)}`;
    });

    cerrarHoja("recargar");
    mostrarToast(`Recarga de $${formatearMonto(montoRecarga)} aprobada.`);
  });

  /* ---------------------------------------------------------
     Atajos de teclado para revisión
     --------------------------------------------------------- */

  const ordenPantallas = [
    "inicio",
    "pedir",
    "viaje",
    "historial",
    "billetera",
    "perfil",
    "bienvenida",
    "acceso",
    "documentos",
  ];

  document.addEventListener("keydown", (evento) => {
    if (evento.target.matches("input, textarea")) return;

    if (evento.key === "ArrowRight" || evento.key === "ArrowLeft") {
      const actual = document.querySelector(".pantalla.activa")?.dataset.pantalla || "inicio";
      const indice = ordenPantallas.indexOf(actual);
      const siguiente = evento.key === "ArrowRight"
        ? (indice + 1) % ordenPantallas.length
        : (indice - 1 + ordenPantallas.length) % ordenPantallas.length;
      irA(ordenPantallas[siguiente]);
    }
  });

  /* ---------------------------------------------------------
     Arranque
     --------------------------------------------------------- */

  const inicial = location.hash.replace("#", "");
  irA(pantallasValidas.has(inicial) ? inicial : "inicio", { hash: false });
})();

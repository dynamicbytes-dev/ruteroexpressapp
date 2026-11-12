/* =========================================================
   GAZ 2027 · Prototipo del panel
   Navegación, command palette, drawer, chat y tarifas.
   ========================================================= */

(() => {
  "use strict";

  const $ = (selector, scope = document) => scope.querySelector(selector);
  const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

  const normalizar = (texto) =>
    texto
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();

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
      /* El prototipo funciona sin persistencia. */
    }
  }

  function aplicarTema(tema) {
    const oscuro = tema === "dark";
    root.dataset.theme = tema;

    $$("[data-tema] .icon use").forEach((use) => {
      use.setAttribute("href", oscuro ? "#i-sun" : "#i-moon");
    });

    const comandoTema = $('[data-comando="tema"] use');
    if (comandoTema) {
      comandoTema.setAttribute("href", oscuro ? "#i-sun" : "#i-moon");
    }
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

  $$("[data-tema]").forEach((boton) => {
    boton.addEventListener("click", (event) => {
      event.preventDefault();
      alternarTema();
    });
  });

  /* -------------------------------------------------------
     Toast
     ------------------------------------------------------- */

  const toastEl = $("#panel-toast");
  const toastTexto = $("#panel-toast-texto");
  let toastTimer;

  function toast(mensaje) {
    if (!toastEl || !toastTexto) return;
    toastTexto.textContent = mensaje;
    toastEl.classList.add("visible");
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
      toastEl.classList.remove("visible");
    }, 2600);
  }

  /* -------------------------------------------------------
     Navegación
     ------------------------------------------------------- */

  const VISTAS = {
    dashboard: { titulo: "Inicio", grupo: "Operación" },
    verificacion: { titulo: "Verificación", grupo: "Operación" },
    detalle: { titulo: "Detalle del conductor", grupo: "Operación" },
    recargas: { titulo: "Recargas", grupo: "Dinero" },
    soporte: { titulo: "Soporte", grupo: "Operación" },
    configuracion: { titulo: "Tarifas y comisiones", grupo: "Sistema" },
  };

  const shell = $("#panel-shell");
  const drawer = $("#drawer-verificacion");
  const drawerOverlay = $("#drawer-overlay");

  function irA(vista, opciones = {}) {
    const destino = $(`.vista[data-vista="${vista}"]`);

    if (!destino) {
      toast("Vista disponible próximamente");
      return;
    }

    cerrarDrawer();
    cerrarMenu();

    $$(".vista").forEach((seccion) => {
      seccion.classList.toggle("activa", seccion === destino);
    });

    $$(".nav-link").forEach((link) => {
      link.classList.toggle("activo", link.dataset.vista === vista);
    });

    const meta = VISTAS[vista] || { titulo: vista, grupo: "Operación" };
    const crumbGrupo = $("#crumb-grupo");
    const crumbActual = $("#crumb-actual");

    if (crumbGrupo) crumbGrupo.textContent = meta.grupo;
    if (crumbActual) crumbActual.textContent = meta.titulo;
    document.title = `GAZ 2027 · ${meta.titulo}`;

    try {
      if (window.location.hash !== `#${vista}`) {
        window.history.replaceState(null, "", `#${vista}`);
      }
    } catch {
      /* file:// puede bloquear replaceState en algunos navegadores. */
    }

    if (!opciones.silencioso) {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  }

  document.addEventListener("click", (event) => {
    const destino = event.target.closest("[data-vista]");
    if (!destino) return;
    event.preventDefault();
    irA(destino.dataset.vista);
  });

  document.addEventListener("click", (event) => {
    const boton = event.target.closest("[data-toast]");
    if (!boton) return;
    event.preventDefault();
    toast(boton.dataset.toast);
  });

  /* -------------------------------------------------------
     Sidebar: colapsar / menú móvil
     ------------------------------------------------------- */

  function cerrarMenu() {
    shell.classList.remove("menu-abierto");
  }

  const botonMenu = $("#btn-menu");
  if (botonMenu) {
    botonMenu.addEventListener("click", () => {
      if (window.matchMedia("(max-width: 1100px)").matches) {
        shell.classList.toggle("menu-abierto");
      } else {
        shell.classList.toggle("colapsado");
      }
    });
  }

  document.addEventListener("click", (event) => {
    if (!shell.classList.contains("menu-abierto")) return;
    if (event.target.closest(".sidebar") || event.target.closest("#btn-menu")) return;
    cerrarMenu();
  });

  /* -------------------------------------------------------
     Command palette
     ------------------------------------------------------- */

  const commandOverlay = $("#command-overlay");
  const commandInput = $("#command-input");
  const commandItems = $$(".command-item");
  const commandGroups = $$("[data-command-group]");
  const commandEmpty = $("#command-empty");

  let commandSeleccion = 0;

  function comandosVisibles() {
    return commandItems.filter((item) => !item.hidden);
  }

  function seleccionarComando(indice) {
    const visibles = comandosVisibles();
    commandItems.forEach((item) => item.classList.remove("seleccionado"));

    if (!visibles.length) {
      commandSeleccion = -1;
      return;
    }

    commandSeleccion = (indice + visibles.length) % visibles.length;
    visibles[commandSeleccion].classList.add("seleccionado");
    visibles[commandSeleccion].scrollIntoView({ block: "nearest" });
  }

  function filtrarComandos(texto) {
    const consulta = normalizar(texto.trim());

    commandItems.forEach((item) => {
      item.hidden = Boolean(consulta) && !normalizar(item.textContent).includes(consulta);
    });

    commandGroups.forEach((grupo) => {
      const items = $$(".command-item", grupo);
      grupo.hidden = items.every((item) => item.hidden);
    });

    if (commandEmpty) {
      commandEmpty.hidden = comandosVisibles().length > 0;
    }

    seleccionarComando(0);
  }

  function abrirCommand() {
    commandOverlay.classList.add("abierto");
    if (commandInput) {
      commandInput.value = "";
      filtrarComandos("");
      commandInput.focus();
    }
  }

  function cerrarCommand() {
    commandOverlay.classList.remove("abierto");
  }

  function ejecutarComando(comando) {
    if (VISTAS[comando]) {
      cerrarCommand();
      irA(comando);
      return;
    }

    switch (comando) {
      case "tema":
        cerrarCommand();
        alternarTema();
        break;
      case "exportar":
        cerrarCommand();
        toast("Reporte del día exportado");
        break;
      case "cupon":
        cerrarCommand();
        toast("Nuevo cupón creado");
        break;
      case "pedro":
        cerrarCommand();
        irA("soporte");
        seleccionarConversacion("pedro");
        break;
      case "carlos":
        cerrarCommand();
        irA("soporte");
        seleccionarConversacion("carlos");
        break;
      default:
        cerrarCommand();
    }
  }

  ["#btn-buscar", "#btn-buscar-side"].forEach((selector) => {
    const boton = $(selector);
    if (boton) boton.addEventListener("click", abrirCommand);
  });

  if (commandInput) {
    commandInput.addEventListener("input", () => {
      filtrarComandos(commandInput.value);
    });

    commandInput.addEventListener("keydown", (event) => {
      if (event.key === "ArrowDown") {
        event.preventDefault();
        seleccionarComando(commandSeleccion + 1);
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        seleccionarComando(commandSeleccion - 1);
      } else if (event.key === "Enter") {
        event.preventDefault();
        const visibles = comandosVisibles();
        if (visibles[commandSeleccion]) visibles[commandSeleccion].click();
      } else if (event.key === "Escape") {
        event.preventDefault();
        cerrarCommand();
      }
    });
  }

  document.addEventListener("click", (event) => {
    const item = event.target.closest(".command-item");
    if (!item) return;
    event.preventDefault();
    ejecutarComando(item.dataset.comando);
  });

  if (commandOverlay) {
    commandOverlay.addEventListener("click", (event) => {
      if (event.target === commandOverlay) cerrarCommand();
    });
  }

  /* -------------------------------------------------------
     Drawer de verificación
     ------------------------------------------------------- */

  let filaActual = null;

  function iniciales(nombre) {
    return nombre
      .split(" ")
      .filter(Boolean)
      .slice(0, 2)
      .map((parte) => parte[0])
      .join("")
      .toUpperCase();
  }

  function abrirDrawer(boton) {
    cerrarCommand();
    filaActual = boton.dataset.fila;
    const nombre = boton.dataset.nombre || "Usuario";
    const rol = boton.dataset.rol || "Cuenta";
    const docs = boton.dataset.docs || "0 de 5";

    const nombreEl = $("#drawer-nombre");
    const subEl = $("#drawer-sub");
    const avatarEl = $("#drawer-avatar");

    if (nombreEl) nombreEl.textContent = nombre;
    if (subEl) subEl.textContent = `${rol} · ${docs} documentos`;
    if (avatarEl) avatarEl.textContent = iniciales(nombre);

    drawer.classList.add("abierto");
    drawerOverlay.classList.add("abierto");
    drawer.setAttribute("aria-hidden", "false");
  }

  function cerrarDrawer() {
    if (!drawer) return;
    drawer.classList.remove("abierto");
    if (drawerOverlay) drawerOverlay.classList.remove("abierto");
    drawer.setAttribute("aria-hidden", "true");
  }

  document.addEventListener("click", (event) => {
    const boton = event.target.closest("[data-revisar]");
    if (!boton) return;
    event.preventDefault();
    abrirDrawer(boton);
  });

  const botonCerrarDrawer = $("#btn-cerrar-drawer");
  if (botonCerrarDrawer) botonCerrarDrawer.addEventListener("click", cerrarDrawer);
  if (drawerOverlay) drawerOverlay.addEventListener("click", cerrarDrawer);

  if (drawer) {
    drawer.addEventListener("click", (event) => {
      const boton = event.target.closest("[data-accion]");
      if (!boton || !filaActual) return;

      const accion = boton.dataset.accion;
      const fila = $(`#tabla-verificacion tr[data-fila="${filaActual}"]`);

      if (!fila) {
        cerrarDrawer();
        toast(accion === "aprobar" ? "Cuenta aprobada" : "Cuenta rechazada");
        return;
      }

      const eraPendiente = fila.dataset.estado === "pendiente" || fila.dataset.estado === "revision";
      const celda = fila.querySelector(".estado-celda");

      if (celda) {
        celda.innerHTML =
          accion === "aprobar"
            ? '<span class="badge badge-success">Aprobado</span>'
            : '<span class="badge badge-danger">Rechazado</span>';
      }

      fila.dataset.estado = accion === "aprobar" ? "aprobado" : "rechazado";

      if (eraPendiente) {
        ["#verif-pendientes", "#stat-pendientes", "#count-verificacion"].forEach((selector) => {
          const el = $(selector);
          if (el) {
            const actual = Number.parseInt(el.textContent, 10) || 0;
            el.textContent = String(Math.max(0, actual - 1));
          }
        });

        if (accion === "aprobar") {
          const aprobados = $("#verif-aprobados");
          if (aprobados) {
            aprobados.textContent = String((Number.parseInt(aprobados.textContent, 10) || 0) + 1);
          }
        } else {
          const rechazados = $("#verif-rechazados");
          if (rechazados) {
            rechazados.textContent = String((Number.parseInt(rechazados.textContent, 10) || 0) + 1);
          }
        }
      }

      cerrarDrawer();
      toast(accion === "aprobar" ? "Cuenta aprobada correctamente" : "Cuenta rechazada");
    });
  }

  /* -------------------------------------------------------
     Verificación: tabs y búsqueda
     ------------------------------------------------------- */

  let filtroVerificacion = "todos";

  function aplicarFiltroVerificacion() {
    const consulta = normalizar(($("#buscar-verificacion")?.value || "").trim());

    $$("#tabla-verificacion tbody tr").forEach((fila) => {
      const rolOk = filtroVerificacion === "todos" || fila.dataset.rol === filtroVerificacion;
      const textoOk = normalizar(fila.textContent).includes(consulta);
      fila.hidden = !(rolOk && textoOk);
    });
  }

  $$("#tabs-verificacion [data-filtro-verificacion]").forEach((boton) => {
    boton.addEventListener("click", () => {
      $$("#tabs-verificacion .tab").forEach((tab) => tab.classList.remove("activo"));
      boton.classList.add("activo");
      filtroVerificacion = boton.dataset.filtroVerificacion;
      aplicarFiltroVerificacion();
    });
  });

  const buscarVerificacion = $("#buscar-verificacion");
  if (buscarVerificacion) {
    buscarVerificacion.addEventListener("input", aplicarFiltroVerificacion);
  }

  /* -------------------------------------------------------
     Recargas
     ------------------------------------------------------- */

  let filtroRecarga = "pendiente";

  function aplicarFiltroRecargas() {
    const consulta = normalizar(($("#buscar-recargas")?.value || "").trim());

    $$("#tabla-recargas tbody tr").forEach((fila) => {
      const estadoOk = filtroRecarga === "todas" || fila.dataset.estado === filtroRecarga;
      const textoOk = normalizar(fila.textContent).includes(consulta);
      fila.hidden = !(estadoOk && textoOk);
    });
  }

  $$("#tabs-recargas [data-filtro-recarga]").forEach((boton) => {
    boton.addEventListener("click", () => {
      $$("#tabs-recargas .tab").forEach((tab) => tab.classList.remove("activo"));
      boton.classList.add("activo");
      filtroRecarga = boton.dataset.filtroRecarga;
      aplicarFiltroRecargas();
    });
  });

  const buscarRecargas = $("#buscar-recargas");
  if (buscarRecargas) {
    buscarRecargas.addEventListener("input", aplicarFiltroRecargas);
  }

  document.addEventListener("click", (event) => {
    const boton = event.target.closest("[data-recarga-accion]");
    if (!boton) return;
    event.preventDefault();

    const fila = $(`#tabla-recargas tr[data-recarga="${boton.dataset.fila}"]`);
    if (!fila) return;

    const accion = boton.dataset.recargaAccion;
    const eraPendiente = fila.dataset.estado === "pendiente";
    const celda = fila.querySelector(".estado-celda");

    if (celda) {
      celda.innerHTML =
        accion === "aprobar"
          ? '<span class="badge badge-success">Aprobada</span>'
          : '<span class="badge badge-danger">Rechazada</span>';
    }

    fila.dataset.estado = accion === "aprobar" ? "aprobada" : "rechazada";

    const acciones = fila.querySelector(".table-actions");
    if (acciones) {
      acciones.innerHTML =
        '<button class="btn btn-sm btn-ghost" type="button" data-toast="Comprobante descargado">Ver</button>';
    }

    if (eraPendiente) {
      ["#recargas-pendientes", "#stat-recargas", "#count-recargas", "#tab-recargas-pendientes"].forEach((selector) => {
        const el = $(selector);
        if (el) {
          const actual = Number.parseInt(el.textContent, 10) || 0;
          el.textContent = String(Math.max(0, actual - 1));
        }
      });

      if (accion === "rechazar") {
        const rechazadas = $("#recargas-rechazadas");
        if (rechazadas) {
          rechazadas.textContent = String((Number.parseInt(rechazadas.textContent, 10) || 0) + 1);
        }
      }
    }

    toast(accion === "aprobar" ? "Recarga aprobada" : "Recarga rechazada");
    aplicarFiltroRecargas();
  });

  /* -------------------------------------------------------
     Soporte: conversaciones
     ------------------------------------------------------- */

  const conversaciones = {
    pedro: {
      nombre: "Pedro Rojas",
      rol: "Pasajero · +58 412 555 0192",
      iniciales: "PR",
      mensajes: [
        { tipo: "in", texto: "Hola, buenas tardes. Cancelé un viaje ayer y el reembolso no aparece en mi billetera.", hora: "10:18" },
        { tipo: "out", texto: "Hola Pedro, gracias por escribir. Déjame revisar el movimiento.", hora: "10:20" },
        { tipo: "in", texto: "Claro, el viaje fue a las 6:15 p. m. desde Bellas Artes.", hora: "10:21" },
        { tipo: "out", texto: "Ya lo encontré. El reembolso se procesó y se verá reflejado en un máximo de 24 horas.", hora: "10:23" },
        { tipo: "in", texto: "Perfecto, gracias.", hora: "10:24" },
      ],
    },
    carlos: {
      nombre: "Carlos Pérez",
      rol: "Conductor · +58 414 555 0231",
      iniciales: "CP",
      mensajes: [
        { tipo: "in", texto: "Buenas, el sistema me pide actualizar el carnet de circulación pero ya lo subí.", hora: "9:52" },
        { tipo: "out", texto: "Hola Carlos, revisamos tu documento. La foto está un poco borrosa; ¿puedes subirla de nuevo?", hora: "9:54" },
        { tipo: "in", texto: "Listo, ya la subí.", hora: "9:57" },
        { tipo: "out", texto: "Perfecto, queda en revisión. Te avisamos hoy mismo.", hora: "9:58" },
      ],
    },
    ana: {
      nombre: "Ana Gómez",
      rol: "Conductora · +58 424 555 0177",
      iniciales: "AG",
      mensajes: [
        { tipo: "in", texto: "¿Cómo actualizo mi método de pago?", hora: "9:08" },
        { tipo: "out", texto: "Desde Perfil > Métodos de pago puedes agregar o cambiar tu método principal.", hora: "9:10" },
        { tipo: "in", texto: "¿Y si quiero pagar con saldo?", hora: "9:11" },
        { tipo: "out", texto: "Selecciona “Saldo de billetera” al confirmar el viaje.", hora: "9:12" },
      ],
    },
    luis: {
      nombre: "Luis Pérez",
      rol: "Pasajero · +58 416 555 0104",
      iniciales: "LP",
      mensajes: [
        { tipo: "in", texto: "Hola, tenía un cobro duplicado en mi último viaje.", hora: "Ayer 4:40 p. m." },
        { tipo: "out", texto: "Ya reversamos el cobro, Luis. Disculpa el inconveniente.", hora: "Ayer 4:42 p. m." },
        { tipo: "in", texto: "Gracias, ya se resolvió.", hora: "Ayer 4:45 p. m." },
      ],
    },
    maria: {
      nombre: "María López",
      rol: "Pasajera · +58 412 555 0184",
      iniciales: "ML",
      mensajes: [
        { tipo: "in", texto: "Quiero cambiar mi método de pago principal.", hora: "Ayer 2:10 p. m." },
        { tipo: "out", texto: "Claro, ¿prefieres Pago Móvil o Zelle?", hora: "Ayer 2:12 p. m." },
        { tipo: "in", texto: "Zelle, por favor.", hora: "Ayer 2:13 p. m." },
        { tipo: "out", texto: "Listo, actualizado. Puedes verificarlo en tu perfil.", hora: "Ayer 2:15 p. m." },
      ],
    },
  };

  let conversacionActual = "pedro";

  function escaparHtml(texto) {
    return texto.replace(/[&<>"']/g, (caracter) => {
      const mapa = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
      return mapa[caracter];
    });
  }

  function renderizarMensajes(mensajes) {
    const cuerpo = $("#chat-body");
    if (!cuerpo) return;

    cuerpo.innerHTML = mensajes
      .map(
        (mensaje) => `
          <div class="msg msg-${mensaje.tipo}">
            ${escaparHtml(mensaje.texto)}
            <div class="msg-time">${escaparHtml(mensaje.hora)}</div>
          </div>
        `
      )
      .join("");

    cuerpo.scrollTop = cuerpo.scrollHeight;
  }

  function seleccionarConversacion(id) {
    const conversacion = conversaciones[id];
    if (!conversacion) return;

    conversacionActual = id;

    $$(".conv").forEach((conv) => {
      const activa = conv.dataset.conv === id;
      conv.classList.toggle("activo", activa);
      if (activa) {
        const noLeido = conv.querySelector(".conv-unread");
        if (noLeido) noLeido.remove();
      }
    });

    const avatar = $("#chat-avatar");
    const nombre = $("#chat-nombre");
    const rol = $("#chat-rol");

    if (avatar) avatar.textContent = conversacion.iniciales;
    if (nombre) nombre.textContent = conversacion.nombre;
    if (rol) rol.textContent = conversacion.rol;

    renderizarMensajes(conversacion.mensajes);
  }

  document.addEventListener("click", (event) => {
    const conv = event.target.closest(".conv[data-conv]");
    if (!conv) return;
    seleccionarConversacion(conv.dataset.conv);
  });

  function horaActual() {
    return new Date().toLocaleTimeString("es-VE", {
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
    });
  }

  function enviarMensaje() {
    const input = $("#chat-input");
    if (!input) return;

    const texto = input.value.trim();
    if (!texto) return;

    const conversacion = conversaciones[conversacionActual];
    conversacion.mensajes.push({ tipo: "out", texto, hora: horaActual() });
    renderizarMensajes(conversacion.mensajes);
    input.value = "";
    input.focus();
  }

  const botonEnviar = $("#btn-enviar");
  if (botonEnviar) botonEnviar.addEventListener("click", enviarMensaje);

  const chatInput = $("#chat-input");
  if (chatInput) {
    chatInput.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        enviarMensaje();
      }
    });
  }

  $$(".quick-reply").forEach((boton) => {
    boton.addEventListener("click", () => {
      const input = $("#chat-input");
      if (!input) return;
      input.value = boton.dataset.respuesta || "";
      enviarMensaje();
    });
  });

  const buscarConv = $("#buscar-conv");
  if (buscarConv) {
    buscarConv.addEventListener("input", () => {
      const consulta = normalizar(buscarConv.value.trim());
      $$(".conv").forEach((conv) => {
        conv.hidden = !normalizar(conv.textContent).includes(consulta);
      });
    });
  }

  /* -------------------------------------------------------
     Configuración de tarifas
     ------------------------------------------------------- */

  const camposTarifa = $$("[data-tarifa]");
  const valoresIniciales = new Map(camposTarifa.map((campo) => [campo.id, campo.value]));
  const saveBar = $("#save-bar");

  function numero(id) {
    const campo = document.getElementById(id);
    if (!campo) return 0;
    const valor = Number.parseFloat(campo.value.replace(",", "."));
    return Number.isFinite(valor) ? valor : 0;
  }

  function dinero(valor) {
    return `$${valor.toFixed(2)}`;
  }

  function calcularTarifas() {
    const baseMoto = numero("moto-base");
    const kmMoto = numero("moto-km");
    const minMoto = numero("moto-min");
    const minimoMoto = numero("moto-minimo");

    const baseCarro = numero("carro-base");
    const kmCarro = numero("carro-km");
    const minCarro = numero("carro-min");
    const minimoCarro = numero("carro-minimo");

    const comision = numero("comision");

    const distancia = 4.2;
    const duracion = 12;

    const totalMoto = Math.max(minimoMoto, baseMoto + distancia * kmMoto + duracion * minMoto);
    const totalCarro = Math.max(minimoCarro, baseCarro + distancia * kmCarro + duracion * minCarro);

    const previewBaseMoto = $("#preview-base-moto");
    const previewKmMoto = $("#preview-km-moto");
    const previewMinMoto = $("#preview-min-moto");
    const previewMoto = $("#preview-moto");
    const previewCarro = $("#preview-carro");
    const previewComision = $("#preview-comision");

    if (previewBaseMoto) previewBaseMoto.textContent = dinero(baseMoto);
    if (previewKmMoto) previewKmMoto.textContent = dinero(distancia * kmMoto);
    if (previewMinMoto) previewMinMoto.textContent = dinero(duracion * minMoto);
    if (previewMoto) previewMoto.textContent = dinero(totalMoto);
    if (previewCarro) previewCarro.textContent = dinero(totalCarro);
    if (previewComision) previewComision.textContent = dinero((totalMoto * comision) / 100);
  }

  fieldsInit();

  function fieldsInit() {
    camposTarifa.forEach((campo) => {
      campo.addEventListener("input", () => {
        calcularTarifas();
        if (saveBar) saveBar.classList.remove("oculto");
      });
    });
  }

  const botonGuardar = $("#btn-guardar");
  if (botonGuardar) {
    botonGuardar.addEventListener("click", () => {
      camposTarifa.forEach((campo) => valoresIniciales.set(campo.id, campo.value));
      if (saveBar) saveBar.classList.add("oculto");
      toast("Tarifas actualizadas · aplican a los próximos viajes");
    });
  }

  const botonDescartar = $("#btn-descartar");
  if (botonDescartar) {
    botonDescartar.addEventListener("click", () => {
      camposTarifa.forEach((campo) => {
        const inicial = valoresIniciales.get(campo.id);
        if (inicial !== undefined) campo.value = inicial;
      });
      calcularTarifas();
      if (saveBar) saveBar.classList.add("oculto");
      toast("Cambios descartados");
    });
  }

  const botonRestaurar = $("#btn-restaurar");
  if (botonRestaurar) {
    botonRestaurar.addEventListener("click", () => {
      camposTarifa.forEach((campo) => {
        const inicial = valoresIniciales.get(campo.id);
        if (inicial !== undefined) campo.value = inicial;
      });
      calcularTarifas();
      if (saveBar) saveBar.classList.remove("oculto");
      toast("Valores restaurados · guarda para aplicar");
    });
  }

  /* -------------------------------------------------------
     Atajos de teclado
     ------------------------------------------------------- */

  const ordenVistas = ["dashboard", "verificacion", "detalle", "recargas", "soporte", "configuracion"];

  document.addEventListener("keydown", (event) => {
    const meta = event.metaKey || event.ctrlKey;
    const tecla = event.key.toLowerCase();

    if (meta && tecla === "k") {
      event.preventDefault();
      if (commandOverlay.classList.contains("abierto")) {
        cerrarCommand();
      } else {
        abrirCommand();
      }
      return;
    }

    if (meta && tecla === "d") {
      event.preventDefault();
      alternarTema();
      return;
    }

    if (meta && /^[1-6]$/.test(event.key)) {
      event.preventDefault();
      cerrarCommand();
      irA(ordenVistas[Number(event.key) - 1]);
      return;
    }

    if (event.key === "Escape") {
      cerrarCommand();
      cerrarDrawer();
      cerrarMenu();
    }
  });

  /* -------------------------------------------------------
     Estado inicial
     ------------------------------------------------------- */

  aplicarFiltroVerificacion();
  aplicarFiltroRecargas();
  seleccionarConversacion("pedro");
  calcularTarifas();

  window.addEventListener("hashchange", () => {
    const vista = window.location.hash.replace("#", "");
    if (VISTAS[vista]) irA(vista, { silencioso: true });
  });

  const vistaInicial = window.location.hash.replace("#", "");
  irA(VISTAS[vistaInicial] ? vistaInicial : "dashboard", { silencioso: true });
})();

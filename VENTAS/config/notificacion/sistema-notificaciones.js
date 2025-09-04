class SistemaNotificacionesUniversal {
  constructor(configuracion = {}) {
    this.config = {
      rutaPhp:
        configuracion.rutaPhp ||
        "/VENTAS/config/notificacion/check_nuevas_autorizaciones.php",
      rutaAutorizaciones:
        configuracion.rutaAutorizaciones ||
        "/VENTAS/secciones/contable/index.php",
      rutaIcono: configuracion.rutaIcono || "/VENTAS/utils/icono.ico",
      intervaloVerificacion: configuracion.intervaloVerificacion || 30000,
      debug:
        configuracion.debug ||
        window.location.hostname.includes("localhost") ||
        window.location.hostname.includes("test"),
      autoDetectar: configuracion.autoDetectar !== false,
    };

    this.ultimaVerificacion =
      localStorage.getItem("ultima_verificacion_auth") || "2020-01-01 00:00:00";
    this.ultimoIdVerificado =
      parseInt(localStorage.getItem("ultimo_id_auth")) || 0;
    this.intervalId = null;
    this.sonidoNotificacion = null;
    this.permisosNotificacion = false;
    this.sistemaActivo = false;

    this.ultimasNotificacionesIds = JSON.parse(
      localStorage.getItem("notificaciones_mostradas") || "[]"
    );
    this.tiempoLimiteRepeticion = 5 * 60 * 1000;

    this.init();
  }

  async init() {
    if (this.config.debug) {
      console.log("🚀 Iniciando Sistema de Notificaciones Universal v2.0");
      console.log("📁 Configuración:", this.config);
    }

    if (this.config.autoDetectar) {
      this.autoDetectarRutas();
    }

    const tienePermisos = await this.verificarPermisosUsuario();
    if (!tienePermisos) {
      if (this.config.debug) {
        console.log("❌ Usuario sin permisos para notificaciones");
      }
      return;
    }

    this.sistemaActivo = true;
    await this.solicitarPermisos();
    this.crearSonidoNotificacion();
    this.iniciarVerificacion();
    this.configurarEventos();

    if (this.config.debug) {
      console.log("✅ Sistema de notificaciones iniciado correctamente");
    }
  }

  autoDetectarRutas() {
    const rutaActual = window.location.pathname;
    const rutaBase = window.location.origin;

    if (this.config.debug) {
      console.log("🔍 Auto-detectando rutas desde:", rutaActual);
    }

    const proyectoBase = rutaActual.includes("/VENTAS/") ? "/VENTAS" : "";

    if (rutaActual === "/VENTAS/" || rutaActual.endsWith("/VENTAS/index.php")) {
      this.config.rutaPhp =
        "/VENTAS/config/notificacion/check_nuevas_autorizaciones.php";
      this.config.rutaAutorizaciones = "/VENTAS/secciones/contable/index.php";
      this.config.rutaIcono = "/VENTAS/utils/icono.ico";
    } else if (rutaActual.includes("/VENTAS/secciones/contable/")) {
      this.config.rutaPhp =
        "/VENTAS/config/notificacion/check_nuevas_autorizaciones.php";
      this.config.rutaAutorizaciones = "./index.php";
      this.config.rutaIcono = "/VENTAS/utils/icono.ico";
    } else if (rutaActual.includes("/VENTAS/secciones/")) {
      this.config.rutaPhp =
        "/VENTAS/config/notificacion/check_nuevas_autorizaciones.php";
      this.config.rutaAutorizaciones = "../contable/index.php";
      this.config.rutaIcono = "/VENTAS/utils/icono.ico";
    } else {
      this.config.rutaPhp =
        "/VENTAS/config/notificacion/check_nuevas_autorizaciones.php";
      this.config.rutaAutorizaciones = "/VENTAS/secciones/contable/index.php";
      this.config.rutaIcono = "/VENTAS/utils/icono.ico";
    }

    if (this.config.debug) {
      console.log("🎯 Rutas detectadas para proyecto VENTAS:", {
        php: this.config.rutaPhp,
        autorizaciones: this.config.rutaAutorizaciones,
        icono: this.config.rutaIcono,
        proyectoBase: proyectoBase,
        rutaActual: rutaActual,
      });
    }
  }

  async verificarPermisosUsuario() {
    try {
      const url = `${this.config.rutaPhp}?verificar_permisos=1`;
      const response = await fetch(url);

      if (response.status === 403) {
        return false;
      }

      if (response.ok) {
        const data = await response.json();
        if (this.config.debug && data.ruta_base_detectada) {
          console.log("🗂️ Ruta base del servidor:", data.ruta_base_detectada);
        }
        return data.success || false;
      }

      return false;
    } catch (error) {
      if (this.config.debug) {
        console.log("❌ Error verificando permisos:", error.message);
      }
      return false;
    }
  }

  async solicitarPermisos() {
    if (!("Notification" in window)) {
      console.warn("Este navegador no soporta notificaciones");
      this.mostrarMensajeEstado(
        "⚠️ Navegador no compatible con notificaciones",
        "warning"
      );
      return false;
    }

    try {
      const permission = await Notification.requestPermission();
      this.permisosNotificacion = permission === "granted";

      return this.permisosNotificacion;
    } catch (error) {
      console.error("Error al solicitar permisos:", error);
      this.mostrarMensajeEstado(
        "❌ Error al solicitar permisos de notificación",
        "error"
      );
      return false;
    }
  }

  mostrarNotificacionPrueba() {
    if (!this.permisosNotificacion) return;

    const notification = new Notification("🔔 Sistema de Ventas Universal", {
      body: "¡Sistema de notificaciones configurado correctamente!\nRecibirás alertas de nuevas autorizaciones.",
      icon: this.config.rutaIcono,
      tag: "test-notification-universal",
      requireInteraction: false,
      silent: false,
    });

    notification.onclick = () => {
      window.focus();
      notification.close();
    };

    setTimeout(() => {
      notification.close();
    }, 5000);
  }

  mostrarMensajeEstado(mensaje, tipo = "info") {
    const statusDiv = document.createElement("div");
    statusDiv.className = `status-message status-${tipo}`;
    statusDiv.textContent = mensaje;
    statusDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            max-width: 300px;
            font-size: 14px;
        `;

    const colores = {
      success: "rgba(40, 167, 69, 0.9)",
      warning: "rgba(255, 193, 7, 0.9)",
      error: "rgba(220, 53, 69, 0.9)",
      info: "rgba(23, 162, 184, 0.9)",
    };

    statusDiv.style.backgroundColor = colores[tipo] || colores.info;
    document.body.appendChild(statusDiv);

    setTimeout(() => {
      if (statusDiv.parentNode) {
        statusDiv.remove();
      }
    }, 4000);
  }

  crearSonidoNotificacion() {
    const audioContext = new (window.AudioContext ||
      window.webkitAudioContext)();

    this.reproducirSonido = () => {
      const oscillator = audioContext.createOscillator();
      const gainNode = audioContext.createGain();

      oscillator.connect(gainNode);
      gainNode.connect(audioContext.destination);

      oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
      oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.2);

      gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
      gainNode.gain.exponentialRampToValueAtTime(
        0.01,
        audioContext.currentTime + 0.5
      );

      oscillator.start(audioContext.currentTime);
      oscillator.stop(audioContext.currentTime + 0.5);
    };
  }

  crearPanelNotificaciones() {
    if (document.getElementById("panel-notificaciones-universal")) return;

    const panel = document.createElement("div");
    panel.id = "panel-notificaciones-universal";
    panel.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        `;
    document.body.appendChild(panel);
  }

  configurarEventos() {
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden) {
        setTimeout(() => {
          this.verificarNuevasAutorizaciones();
        }, 1000);

        this.limpiarNotificacionesAntiguas();
        this.ocultarBadge();

        if (this.config.debug) {
          console.log("👁️ Ventana visible - verificando estado...");
        }
      } else {
        if (this.config.debug) {
          console.log("👁️‍🗨️ Ventana oculta - notificaciones del sistema activas");
        }
      }
    });

    document.addEventListener("click", (e) => {
      if (e.target.id === "notification-badge-universal") {
        window.location.href = this.config.rutaAutorizaciones;
      }
    });

    this.limpiarNotificacionesAlCargar();
  }

  limpiarNotificacionesAlCargar() {
    setTimeout(() => {
      const badge = document.getElementById("notification-badge-universal");
      if (badge && badge.style.display === "none") {
        this.resetearNotificaciones();
        if (this.config.debug) {
          console.log("🧹 Historial de notificaciones limpiado");
        }
      }
    }, 3000);
  }

  resetearNotificaciones() {
    this.ultimasNotificacionesIds = [];
    localStorage.removeItem("notificaciones_mostradas");
    if (this.config.debug) {
      console.log("🔄 Sistema de notificaciones reseteado");
    }
  }

  probarNotificacion() {
    if (!this.sistemaActivo) {
      this.mostrarMensajeEstado(
        "❌ Sistema no disponible para tu rol",
        "error"
      );
      return;
    }

    if (!this.permisosNotificacion) {
      this.solicitarPermisos();
      return;
    }

    const datosPrueba = [
      {
        cliente: "Cliente de Prueba Universal",
        monto_total: "250000",
        moneda: "Guaraníes",
        vendedor: "Vendedor Test",
      },
    ];

    this.mostrarNotificacion(1, datosPrueba);
    this.reproducirSonido();
    this.mostrarNotificacionInterna(1, datosPrueba);

    this.mostrarMensajeEstado("🔔 Notificación de prueba enviada", "info");
  }

  iniciarVerificacion() {
    if (!this.sistemaActivo) return;

    this.verificarNuevasAutorizaciones();
    this.intervalId = setInterval(() => {
      this.verificarNuevasAutorizaciones();
    }, this.config.intervaloVerificacion);

    if (this.config.debug) {
      console.log(
        `⏰ Verificación iniciada cada ${
          this.config.intervaloVerificacion / 1000
        } segundos`
      );
    }
  }

  detenerVerificacion() {
    if (this.intervalId) {
      clearInterval(this.intervalId);
      this.intervalId = null;
      if (this.config.debug) {
        console.log("⏹️ Verificación detenida");
      }
    }
  }

  async verificarNuevasAutorizaciones() {
    if (!this.sistemaActivo) return;

    try {
      const url = `${
        this.config.rutaPhp
      }?ultima_verificacion=${encodeURIComponent(
        this.ultimaVerificacion
      )}&ultimo_id=${this.ultimoIdVerificado}`;

      if (this.config.debug) {
        console.log("🔍 Verificando:", {
          url: url,
          ultimaVerificacion: this.ultimaVerificacion,
          ultimoIdVerificado: this.ultimoIdVerificado,
        });
      }

      const response = await fetch(url);

      if (response.status === 403) {
        this.sistemaActivo = false;
        this.detenerVerificacion();
        if (this.config.debug) {
          console.log("🚫 Acceso denegado - desactivando sistema");
        }
        return;
      }

      const data = await response.json();

      if (this.config.debug && data.debug) {
        console.log("📊 Debug del servidor:", data.debug);
      }

      if (data.success) {
        this.procesarRespuesta(data);
      } else {
        console.error("❌ Error al verificar autorizaciones:", data.error);
      }
    } catch (error) {
      if (this.config.debug) {
        console.error("❌ Error en la verificación:", error);
      }
    }
  }

  procesarRespuesta(data) {
    const {
      total_pendientes,
      nuevas_autorizaciones,
      detalles_nuevas,
      timestamp,
      ultimo_id_actual,
    } = data;

    this.actualizarBadgeTotal(total_pendientes);

    if (nuevas_autorizaciones > 0) {
      if (this.config.debug) {
        console.log(
          `🔔 ${nuevas_autorizaciones} nuevas autorizaciones detectadas!`
        );
      }

      if (document.hidden) {
        this.mostrarNotificacion(nuevas_autorizaciones, detalles_nuevas);
        this.reproducirSonido();
      }

      this.mostrarNotificacionInterna(nuevas_autorizaciones, detalles_nuevas);
      this.actualizarTimestamps(timestamp, ultimo_id_actual);

      if (this.config.debug) {
        console.log("🔄 Timestamps actualizados para evitar repetición");
      }
    } else {
      this.actualizarTimestamps(timestamp, ultimo_id_actual);

      if (this.config.debug) {
        console.log("✅ No hay nuevas autorizaciones");
      }
    }
  }

  actualizarTimestamps(timestamp, ultimoId) {
    this.ultimaVerificacion = timestamp;
    this.ultimoIdVerificado = ultimoId || this.ultimoIdVerificado;

    localStorage.setItem("ultima_verificacion_auth", this.ultimaVerificacion);
    localStorage.setItem("ultimo_id_auth", this.ultimoIdVerificado);
  }

  actualizarBadgeTotal(total) {
    let badge = document.getElementById("notification-badge-universal");

    if (!badge) {
      badge = document.createElement("span");
      badge.id = "notification-badge-universal";
      badge.style.cssText = `
                position: fixed;
                top: 10px;
                right: 10px;
                background: #dc3545;
                color: white;
                border-radius: 50%;
                padding: 5px 8px;
                font-size: 12px;
                font-weight: bold;
                z-index: 10001;
                cursor: pointer;
                min-width: 20px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            `;
      document.body.appendChild(badge);
    }

    if (total > 0) {
      badge.textContent = total;
      badge.style.display = "inline-block";
      badge.title = `${total} autorización${total > 1 ? "es" : ""} pendiente${
        total > 1 ? "s" : ""
      }`;
    } else {
      badge.style.display = "none";
    }

    if (!window.location.pathname.includes("contable/index.php")) {
      if (total > 0) {
        document.title = `(${total}) ${document.title.replace(
          /^\(\d+\)\s*/,
          ""
        )}`;
      } else {
        document.title = document.title.replace(/^\(\d+\)\s*/, "");
      }
    }
  }

  mostrarNotificacion(cantidad, detalles) {
    if (!this.permisosNotificacion) return;

    if (detalles && detalles.length > 0) {
      const idsActuales = detalles.map((d) => d.id);
      const yaNotificadas = idsActuales.some((id) =>
        this.ultimasNotificacionesIds.includes(id)
      );

      if (yaNotificadas && this.config.debug) {
        console.log(
          "🚫 Notificación omitida - ya mostrada para IDs:",
          idsActuales
        );
        return;
      }

      this.ultimasNotificacionesIds = [
        ...this.ultimasNotificacionesIds,
        ...idsActuales,
      ];
      this.limpiarNotificacionesAntiguas();
      localStorage.setItem(
        "notificaciones_mostradas",
        JSON.stringify(this.ultimasNotificacionesIds)
      );
    }

    const titulo = `🔔 Sistema Universal - ${cantidad} Nueva${
      cantidad > 1 ? "s" : ""
    } Autorización${cantidad > 1 ? "es" : ""}`;
    let mensaje = "Requieren revisión contable urgente";

    if (detalles && detalles.length > 0) {
      const primer = detalles[0];
      const simbolo = primer.moneda === "Dólares" ? "U$D " : "₲ ";
      mensaje = `Cliente: ${primer.cliente}\nMonto: ${simbolo}${parseFloat(
        primer.monto_total
      ).toLocaleString()}`;
      if (cantidad > 1) {
        mensaje += `\n+ ${cantidad - 1} autorización${
          cantidad - 1 > 1 ? "es" : ""
        } más`;
      }
    }

    const notification = new Notification(titulo, {
      body: mensaje,
      icon: this.config.rutaIcono,
      badge: this.config.rutaIcono,
      tag: "auth-universal-" + Date.now(),
      requireInteraction: true,
      silent: false,
      renotify: true,
      timestamp: Date.now(),
    });

    notification.onclick = (event) => {
      event.preventDefault();
      window.focus();
      window.location.href = this.config.rutaAutorizaciones;
      notification.close();
    };

    setTimeout(() => {
      if (notification) {
        notification.close();
      }
    }, 30000);
  }

  limpiarNotificacionesAntiguas() {
    const limite = 10;
    if (this.ultimasNotificacionesIds.length > limite) {
      this.ultimasNotificacionesIds = this.ultimasNotificacionesIds.slice(
        -limite
      );
    }
  }

  mostrarNotificacionInterna(cantidad, detalles) {
    if (document.hidden) return;

    this.crearPanelNotificaciones();
    const panel = document.getElementById("panel-notificaciones-universal");
    if (!panel) return;

    const notificacion = document.createElement("div");
    notificacion.style.cssText = `
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            cursor: pointer;
            min-width: 300px;
            animation: slideIn 0.3s ease-out;
        `;

    notificacion.innerHTML = `
            <div style="display: flex; align-items: center;">
                <div style="color: #856404; margin-right: 10px; font-size: 18px;">🔔</div>
                <div style="flex-grow: 1;">
                    <strong style="color: #856404;">Mensaje: ${cantidad} Nueva${
      cantidad > 1 ? "s" : ""
    } Autorización${cantidad > 1 ? "es" : ""}</strong>
                    <br>
                    <small style="color: #856404;">Requieren revisión contable</small>
                    ${
                      detalles && detalles.length > 0
                        ? `
                        <div style="margin-top: 5px;">
                            <small style="color: #856404;">${detalles[0].cliente}</small>
                        </div>
                    `
                        : ""
                    }
                </div>
                <button style="background: none; border: none; color: #856404; font-size: 18px; cursor: pointer;" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

    notificacion.addEventListener("click", (e) => {
      if (e.target.tagName !== "BUTTON") {
        window.location.href = this.config.rutaAutorizaciones;
      }
    });

    panel.appendChild(notificacion);

    setTimeout(() => {
      if (notificacion.parentNode) {
        notificacion.remove();
      }
    }, 8000);

    if (this.config.debug) {
      console.log("📱 Notificación interna mostrada");
    }
  }

  ocultarBadge() {
    const badge = document.getElementById("notification-badge-universal");
    if (badge) {
      badge.style.display = "none";
    }
  }

  marcarComoInicializado() {
    this.yaInicializado = true;
    localStorage.setItem("sistema_notif_inicializado", "true");
  }

  marcarMensajesMostradosEnSesion() {
    this.mensajesMostradosEnSesion = true;
    sessionStorage.setItem("notif_mensajes_sesion", "true");
  }

  actualizarConfiguracion(nuevaConfig) {
    this.config = { ...this.config, ...nuevaConfig };
    if (this.config.debug) {
      console.log("⚙️ Configuración actualizada:", this.config);
    }
  }

  obtenerEstado() {
    return {
      activo: this.sistemaActivo,
      permisos: this.permisosNotificacion,
      configuracion: this.config,
      ultimaVerificacion: this.ultimaVerificacion,
      ultimoId: this.ultimoIdVerificado,
    };
  }
}

document.addEventListener("DOMContentLoaded", () => {
  window.sistemaNotificacionesUniversal = new SistemaNotificacionesUniversal();
});

window.addEventListener("beforeunload", () => {
  if (window.sistemaNotificacionesUniversal) {
    window.sistemaNotificacionesUniversal.detenerVerificacion();
  }
});

window.iniciarNotificaciones = function (configuracion = {}) {
  if (!window.sistemaNotificacionesUniversal) {
    window.sistemaNotificacionesUniversal = new SistemaNotificacionesUniversal(
      configuracion
    );
  } else {
    window.sistemaNotificacionesUniversal.actualizarConfiguracion(
      configuracion
    );
  }
  return window.sistemaNotificacionesUniversal;
};

window.probarNotificaciones = function () {
  if (window.sistemaNotificacionesUniversal) {
    window.sistemaNotificacionesUniversal.probarNotificacion();
  } else {
    console.error("Sistema de notificaciones no inicializado");
  }
};

window.estadoNotificaciones = function () {
  if (window.sistemaNotificacionesUniversal) {
    return window.sistemaNotificacionesUniversal.obtenerEstado();
  }
  return { error: "Sistema no inicializado" };
};

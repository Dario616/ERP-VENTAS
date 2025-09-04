class VerPcpManager {
  constructor() {
    // Variables globales para el manejo de paquetes
    this.productosConCantidad = new Set();
    this.validacionesCompletadas = new Map();

    this.init();
  }

  init() {
    console.log("=== INICIALIZANDO VER PCP MANAGER ===");

    // Inicializar contadores
    this.actualizarContadores();

    // Configurar event listeners DESPUÉS de que la página esté lista
    setTimeout(() => {
      this.configurarEventListeners();
      this.initImageGallery();
      this.initProductImages();
    }, 100);

    console.log("✅ VER PCP Manager inicializado correctamente");
  }

  /**
   * Inicializar galería de imágenes de autorización
   */
  initImageGallery() {
    const imagenesAutorizacion = window.imagenesAutorizacionData || [];
    let imagenActualIndex = 0;

    const modal = document.getElementById("modalImagenAutorizacion");
    if (!modal || imagenesAutorizacion.length === 0) return;

    const elements = {
      imgContainer: document.getElementById("imagen-autorizacion-container"),
      nombreModal: document.getElementById("nombre-imagen-modal"),
      descripcionModal: document.getElementById("descripcion-imagen-modal"),
      tituloModal: document.getElementById("titulo-imagen-modal"),
      counter: document.getElementById("imagen-counter"),
      actualSpan: document.getElementById("imagen-actual"),
      totalSpan: document.getElementById("total-imagenes"),
      btnAnterior: document.getElementById("btn-imagen-anterior"),
      btnSiguiente: document.getElementById("btn-imagen-siguiente"),
      btnDescargar: document.getElementById("btn-descargar-imagen"),
    };

    let modalBootstrap = null;

    const mostrarDocumento = (index) => {
      const documento = imagenesAutorizacion[index];
      imagenActualIndex = index;

      const esImagen = documento.tipo_archivo.startsWith("image/");
      const esPDF = documento.tipo_archivo === "application/pdf";

      if (esImagen) {
        elements.imgContainer.innerHTML = `
                    <img src="data:${documento.tipo_archivo};base64,${
          documento.base64_imagen
        }" 
                        class="img-fluid rounded shadow" 
                        style="max-height: 80vh; object-fit: contain;" 
                        alt="${
                          documento.nombre_archivo || "Imagen de autorización"
                        }">
                `;
        elements.tituloModal.textContent = "Imagen de Autorización";
      } else if (esPDF) {
        elements.imgContainer.innerHTML = `
                    <div style="width: 100%; height: 80vh; border: 2px solid #dee2e6; border-radius: 8px; overflow: hidden;">
                        <iframe 
                            src="data:${documento.tipo_archivo};base64,${
          documento.base64_imagen
        }#toolbar=1" 
                            style="width: 100%; height: 100%; border: none;"
                            title="${
                              documento.nombre_archivo || "PDF de autorización"
                            }">
                        </iframe>
                    </div>
                `;
        elements.tituloModal.textContent = "PDF de Autorización";
      }

      elements.nombreModal.textContent =
        documento.nombre_archivo || `Documento ${index + 1}`;
      elements.descripcionModal.innerHTML = documento.descripcion_imagen
        ? `<div class="alert alert-info"><strong>Descripción:</strong> ${documento.descripcion_imagen}</div>`
        : "";

      elements.actualSpan.textContent = index + 1;
      elements.totalSpan.textContent = imagenesAutorizacion.length;

      elements.btnDescargar.onclick = () => {
        const link = document.createElement("a");
        link.href = `data:${documento.tipo_archivo};base64,${documento.base64_imagen}`;
        link.download = documento.nombre_archivo || `documento_${index + 1}`;
        link.click();
      };

      // Mostrar/ocultar controles de navegación
      if (imagenesAutorizacion.length > 1) {
        elements.counter.style.display = "block";
        elements.btnAnterior.style.display = index > 0 ? "block" : "none";
        elements.btnSiguiente.style.display =
          index < imagenesAutorizacion.length - 1 ? "block" : "none";
      }
    };

    // Event listeners para thumbnails
    document.querySelectorAll(".imagen-autorizacion-thumb").forEach((thumb) => {
      thumb.addEventListener("click", function () {
        const index = parseInt(this.getAttribute("data-imagen-index"));
        imagenActualIndex = index;
        mostrarDocumento(index);

        if (!modalBootstrap) {
          modalBootstrap = new bootstrap.Modal(modal);
        }
        modalBootstrap.show();
      });
    });

    // Navegación
    elements.btnAnterior?.addEventListener("click", () => {
      if (imagenActualIndex > 0) {
        mostrarDocumento(imagenActualIndex - 1);
      }
    });

    elements.btnSiguiente?.addEventListener("click", () => {
      if (imagenActualIndex < imagenesAutorizacion.length - 1) {
        mostrarDocumento(imagenActualIndex + 1);
      }
    });

    // Navegación con teclado
    modal.addEventListener("keydown", (e) => {
      if (e.key === "ArrowLeft" && imagenActualIndex > 0) {
        mostrarDocumento(imagenActualIndex - 1);
      } else if (
        e.key === "ArrowRight" &&
        imagenActualIndex < imagenesAutorizacion.length - 1
      ) {
        mostrarDocumento(imagenActualIndex + 1);
      }
    });
  }

  /**
   * Inicializar imágenes de productos
   */
  initProductImages() {
    const imagenesProductos = window.imagenesProductosData || {};
    const nombresProductos = window.nombresProductosData || {};

    document.querySelectorAll(".ver-imagen-producto").forEach((btn) => {
      btn.addEventListener("click", function () {
        const idProducto = this.getAttribute("data-id-producto");
        const nombreProducto = nombresProductos[idProducto] || "Producto";
        const modal = new bootstrap.Modal(
          document.getElementById("modalImagenProducto")
        );

        document.getElementById("producto-nombre").textContent = nombreProducto;
        const imgContainer = document.getElementById(
          "producto-imagen-container"
        );

        if (imagenesProductos[idProducto]) {
          const imgData = imagenesProductos[idProducto];
          imgContainer.innerHTML = `
                        <img src="data:${imgData.tipo};base64,${imgData.imagen}" 
                            class="img-fluid rounded shadow" 
                            style="max-height: 70vh; object-fit: contain;" 
                            alt="${nombreProducto}">
                    `;
        } else {
          imgContainer.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No hay imagen disponible para este producto
                        </div>
                    `;
        }

        modal.show();
      });
    });
  }

  /**
   * FUNCIÓN PRINCIPAL: Actualizar cálculo de paquetes
   */
  actualizarCalculoPaquetes(productoIndex) {
    console.log(
      "=== INICIANDO actualizarCalculoPaquetes para índice:",
      productoIndex
    );

    const input = document.querySelector(
      `input[data-producto-index="${productoIndex}"]`
    );
    if (!input) {
      console.error("No se encontró input para índice:", productoIndex);
      return;
    }

    const bobinasSolicitadas = parseInt(input.value) || 0;
    const bobinasPorPaquete =
      parseInt(input.getAttribute("data-bobinas-por-paquete")) || 1;
    const nombreProducto = input.getAttribute("data-nombre-producto");
    const esUnidades = input.getAttribute("data-es-unidades") === "true";

    console.log("Datos del producto:", {
      nombreProducto,
      bobinasSolicitadas,
      bobinasPorPaquete,
      esUnidades,
      inputName: input.name,
      inputValue: input.value,
    });

    // Elementos de la interfaz
    const infoContainer = document.getElementById(
      `info-paquetes-${productoIndex}`
    );
    const paquetesSpan = document.getElementById(
      `paquetes-necesarios-${productoIndex}`
    );
    const bobinasTotalesSpan = document.getElementById(
      `bobinas-totales-${productoIndex}`
    );
    const excedenteSpan = document.getElementById(
      `excedente-bobinas-${productoIndex}`
    );
    const excedenteContainer = document.getElementById(
      `excedente-container-${productoIndex}`
    );
    const mensajeValidacion = document.getElementById(
      `mensaje-validacion-${productoIndex}`
    );

    if (bobinasSolicitadas > 0 && !esUnidades) {
      console.log("✅ Procesando producto con bobinas:", bobinasSolicitadas);

      // Cálculos
      const paquetesNecesarios = Math.ceil(
        bobinasSolicitadas / bobinasPorPaquete
      );
      const bobinasTotales = paquetesNecesarios * bobinasPorPaquete;
      const excedente = bobinasTotales - bobinasSolicitadas;

      console.log("Cálculos:", {
        paquetesNecesarios,
        bobinasTotales,
        excedente,
      });

      // Actualizar interfaz
      if (paquetesSpan) {
        paquetesSpan.textContent = paquetesNecesarios;
        console.log("✅ Actualizado paquetes necesarios:", paquetesNecesarios);
      }
      if (bobinasTotalesSpan) {
        bobinasTotalesSpan.textContent = bobinasTotales;
        console.log("✅ Actualizado bobinas totales:", bobinasTotales);
      }
      if (excedenteSpan) {
        excedenteSpan.textContent = excedente;
        console.log("✅ Actualizado excedente:", excedente);
      }
      if (excedenteContainer) {
        excedenteContainer.className = `fw-bold ${
          excedente > 0 ? "text-warning" : "text-success"
        }`;
      }

      if (infoContainer) {
        infoContainer.style.display = "block";
        console.log("✅ Mostrado contenedor de información");
      }

      // AGREGAR INMEDIATAMENTE A LA LISTA
      this.productosConCantidad.add(productoIndex);
      console.log(
        "✅ Producto agregado a la lista. Total productos:",
        this.productosConCantidad.size
      );

      // Validar disponibilidad con la API
      this.validarStockDisponibleAPI(
        productoIndex,
        nombreProducto,
        bobinasSolicitadas
      );
    } else if (bobinasSolicitadas > 0 && esUnidades) {
      console.log("✅ Procesando producto en unidades:", bobinasSolicitadas);

      this.productosConCantidad.add(productoIndex);
      input.classList.remove("is-invalid");
      input.classList.add("is-valid");
      this.validacionesCompletadas.set(productoIndex, true);

      if (mensajeValidacion) {
        mensajeValidacion.innerHTML = `
                    <div class="alert alert-success alert-sm">
                        <i class="fas fa-check-circle me-1"></i>
                        <strong>Disponible:</strong> ${bobinasSolicitadas} unidades
                    </div>
                `;
      }
      console.log(
        "✅ Producto en unidades agregado. Total productos:",
        this.productosConCantidad.size
      );
    } else {
      console.log("❌ Limpiando producto (cantidad = 0)");

      if (infoContainer) infoContainer.style.display = "none";
      this.productosConCantidad.delete(productoIndex);
      this.validacionesCompletadas.delete(productoIndex);
      input.classList.remove("is-valid", "is-invalid");
      if (mensajeValidacion) mensajeValidacion.innerHTML = "";

      console.log(
        "❌ Producto removido. Total productos:",
        this.productosConCantidad.size
      );
    }

    this.actualizarContadores();
    console.log(
      "=== FIN actualizarCalculoPaquetes. Productos con cantidad:",
      Array.from(this.productosConCantidad)
    );
  }

  /**
   * Validar stock disponible mediante API
   */
  validarStockDisponibleAPI(productoIndex, nombreProducto, bobinasSolicitadas) {
    const input = document.querySelector(
      `input[data-producto-index="${productoIndex}"]`
    );
    const mensajeValidacion = document.getElementById(
      `mensaje-validacion-${productoIndex}`
    );

    console.log(
      "=== VALIDANDO STOCK API para:",
      nombreProducto,
      "cantidad:",
      bobinasSolicitadas
    );

    if (mensajeValidacion) {
      mensajeValidacion.innerHTML = `
                <div class="alert alert-info alert-sm">
                    <i class="fas fa-spinner fa-spin me-1"></i>
                    <strong>Validando...</strong> Verificando disponibilidad
                </div>
            `;
    }

    const baseUrl = window.location.pathname;
    const url = `${baseUrl}?action=calcular_paquetes_necesarios&producto=${encodeURIComponent(
      nombreProducto
    )}&bobinas=${bobinasSolicitadas}`;

    fetch(url)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          this.mostrarResultadoValidacion(
            productoIndex,
            data,
            input,
            mensajeValidacion
          );
        } else {
          this.mostrarErrorValidacion(
            productoIndex,
            data.error,
            input,
            mensajeValidacion
          );
        }
      })
      .catch((error) => {
        console.warn("Error validando stock:", error);
        this.validacionesCompletadas.set(productoIndex, true);
        input.classList.remove("is-invalid");
        input.classList.add("is-valid");
        if (mensajeValidacion) {
          mensajeValidacion.innerHTML = `
                        <div class="alert alert-warning alert-sm">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Validación offline:</strong> Verificar manualmente
                        </div>
                    `;
        }
        this.actualizarContadores();
      });
  }

  mostrarResultadoValidacion(productoIndex, data, input, mensajeValidacion) {
    if (data.disponible) {
      this.validacionesCompletadas.set(productoIndex, true);
      input.classList.remove("is-invalid");
      input.classList.add("is-valid");

      if (mensajeValidacion) {
        let alertClass = "alert-success";
        let icon = "check-circle";
        let mensaje = data.mensaje;

        if (data.bobinas_excedente > 0) {
          alertClass = "alert-warning";
          icon = "exclamation-triangle";
          mensaje += `<br><small><strong>⚠️ Excedente:</strong> ${data.bobinas_excedente} bobinas</small>`;
        }

        mensajeValidacion.innerHTML = `
                    <div class="alert ${alertClass} alert-sm">
                        <i class="fas fa-${icon} me-1"></i>
                        <strong>Disponible:</strong> ${mensaje}
                    </div>
                `;
      }
      console.log("✅ Validación exitosa para producto índice:", productoIndex);
    } else {
      this.mostrarErrorValidacion(
        productoIndex,
        data.mensaje,
        input,
        mensajeValidacion
      );
    }
    this.actualizarContadores();
  }

  mostrarErrorValidacion(productoIndex, error, input, mensajeValidacion) {
    this.validacionesCompletadas.set(productoIndex, false);
    input.classList.remove("is-valid");
    input.classList.add("is-invalid");

    if (mensajeValidacion) {
      mensajeValidacion.innerHTML = `
                <div class="alert alert-danger alert-sm">
                    <i class="fas fa-times-circle me-1"></i>
                    <strong>Stock insuficiente:</strong> ${error}
                </div>
            `;
    }
    this.actualizarContadores();
  }

  actualizarContadores() {
    const contadorHeader = document.getElementById(
      "contador-productos-seleccionados"
    );
    const contadorBoton = document.getElementById("contador-boton");
    const btnCrear = document.getElementById("btnCrearReservas");

    const total = this.productosConCantidad.size;
    const validacionesPendientes = Array.from(this.productosConCantidad).some(
      (index) => {
        const tieneValidacion = this.validacionesCompletadas.has(index);
        const esValida = this.validacionesCompletadas.get(index);
        return !tieneValidacion || esValida === false;
      }
    );

    console.log("=== ACTUALIZANDO CONTADORES ===");
    console.log("Total productos:", total);
    console.log(
      "Productos con cantidad:",
      Array.from(this.productosConCantidad)
    );
    console.log(
      "Validaciones completadas:",
      Array.from(this.validacionesCompletadas.entries())
    );
    console.log("Validaciones pendientes:", validacionesPendientes);

    if (contadorHeader) {
      contadorHeader.textContent = `${total} productos`;
    }
    if (contadorBoton) {
      contadorBoton.textContent = `${total} productos`;
    }

    if (btnCrear) {
      const debeEstarDeshabilitado = total === 0 || validacionesPendientes;
      btnCrear.disabled = debeEstarDeshabilitado;

      if (total === 0) {
        btnCrear.innerHTML =
          '<i class="fas fa-shipping-fast me-2"></i>Crear Reservas de Stock <span class="badge bg-light text-dark ms-2">0 productos</span>';
        console.log("❌ Botón deshabilitado: sin productos");
      } else if (validacionesPendientes) {
        btnCrear.innerHTML =
          '<i class="fas fa-clock me-2"></i>Validando Stock... <span class="badge bg-warning text-dark ms-2">Espere</span>';
        console.log("⏳ Botón deshabilitado: validando");
      } else {
        btnCrear.innerHTML = `<i class="fas fa-shipping-fast me-2"></i>Crear Reservas de Stock <span class="badge bg-light text-dark ms-2">${total} productos</span>`;
        console.log("✅ Botón habilitado:", total, "productos");
      }
    }
  }

  configurarEventListeners() {
    console.log("=== CONFIGURANDO EVENT LISTENERS ===");

    // Buscar todos los inputs de bobinas
    const inputsBobinas = document.querySelectorAll(".bobinas-calculator");
    console.log(
      "Configurando event listeners para",
      inputsBobinas.length,
      "inputs"
    );

    inputsBobinas.forEach((input, index) => {
      const productoIndex = input.getAttribute("data-producto-index");
      const nombreProducto = input.getAttribute("data-nombre-producto");

      console.log(`Configurando input ${index}:`, {
        productoIndex,
        nombreProducto,
        name: input.name,
      });

      // Event listener para input (tiempo real)
      input.addEventListener("input", (e) => {
        console.log(
          "🔄 EVENT INPUT disparado para índice:",
          productoIndex,
          "valor:",
          e.target.value
        );
        this.actualizarCalculoPaquetes(parseInt(productoIndex));
      });

      // Event listener para change (cuando pierde foco)
      input.addEventListener("change", (e) => {
        console.log(
          "🔄 EVENT CHANGE disparado para índice:",
          productoIndex,
          "valor:",
          e.target.value
        );
        this.actualizarCalculoPaquetes(parseInt(productoIndex));
      });

      // Event listener para blur (cuando pierde foco)
      input.addEventListener("blur", (e) => {
        console.log(
          "🔄 EVENT BLUR disparado para índice:",
          productoIndex,
          "valor:",
          e.target.value
        );
        this.actualizarCalculoPaquetes(parseInt(productoIndex));
      });
    });

    console.log("✅ Event listeners configurados");

    // Event listener para debug del formulario
    this.configurarDebugFormulario();
  }

  configurarDebugFormulario() {
    const form = document.getElementById("formStockExpedicion");
    if (!form) return;

    form.addEventListener("submit", (e) => {
      console.log("=== ENVIANDO FORMULARIO ===");

      const formData = new FormData(form);
      console.log("📋 Datos del formulario:");

      // Mostrar todos los datos
      for (let [key, value] of formData.entries()) {
        console.log(`${key}: ${value}`);
      }

      // Verificar específicamente cantidad_bobinas
      const cantidadesBobinas = {};
      const nombresProductos = {};

      for (let [key, value] of formData.entries()) {
        if (key.startsWith("cantidad_bobinas[")) {
          const indice = key.match(/\[(\d+)\]/)?.[1];
          if (indice) {
            cantidadesBobinas[indice] = value;
          }
        }
        if (key.startsWith("nombre_producto[")) {
          const indice = key.match(/\[(\d+)\]/)?.[1];
          if (indice) {
            nombresProductos[indice] = value;
          }
        }
      }

      console.log("🔢 Cantidades por índice:", cantidadesBobinas);
      console.log("📝 Nombres por índice:", nombresProductos);

      // Verificar que hay datos válidos
      const tieneValores = Object.values(cantidadesBobinas).some(
        (val) => parseInt(val) > 0
      );
      console.log("✅ Tiene valores válidos:", tieneValores);

      if (!tieneValores) {
        e.preventDefault();
        alert("ERROR DEBUG: No se detectaron valores válidos en el formulario");
        return false;
      }
    });
  }

  // Función de test manual
  testProducto() {
    console.log("=== TEST MANUAL ===");
    const input = document.querySelector(".bobinas-calculator");
    if (input) {
      console.log("Input encontrado:", input.name);
      input.value = "6";
      const index = input.getAttribute("data-producto-index");
      console.log("Llamando actualizarCalculoPaquetes con índice:", index);
      this.actualizarCalculoPaquetes(parseInt(index));
    } else {
      console.error("No se encontró input de bobinas");
    }
  }
}

/**
 * Sistema de Confirmación para Acciones PCP
 */
class ConfirmationSystem {
  constructor() {
    this.modal = new bootstrap.Modal(
      document.getElementById("modalConfirmacion")
    );
    this.modalElement = document.getElementById("modalConfirmacion");
    this.pendingForm = null;
    this.pendingAction = null;

    this.initializeEventListeners();
  }

  initializeEventListeners() {
    // Interceptar envío de formulario de producción
    const formProduccion = document.getElementById("formProduccion");
    if (formProduccion) {
      formProduccion.addEventListener("submit", (e) => {
        e.preventDefault();
        this.showConfirmation("produccion", formProduccion);
      });
    }

    // Interceptar envío de formulario de stock/expedición
    const formStock = document.getElementById("formStockExpedicion");
    if (formStock) {
      formStock.addEventListener("submit", (e) => {
        e.preventDefault();
        this.showConfirmation("stock", formStock);
      });
    }

    // Interceptar envío de formulario de finalizar venta
    const formFinalizar = document.getElementById("formFinalizar");
    if (formFinalizar) {
      formFinalizar.addEventListener("submit", (e) => {
        e.preventDefault();
        this.showConfirmation("finalizar", formFinalizar);
      });
    }

    // ✅ NUEVO: Interceptar envío de formulario de devolución
    const formDevolucion = document.getElementById("formDevolucion");
    if (formDevolucion) {
      formDevolucion.addEventListener("submit", (e) => {
        e.preventDefault();
        this.showConfirmation("devolucion", formDevolucion);
      });
    }

    // Evento del botón confirmar
    document.getElementById("btnConfirmar").addEventListener("click", () => {
      this.executeAction();
    });
  }

  showConfirmation(type, form) {
    this.pendingForm = form;
    this.pendingAction = type;

    // Resetear estado
    this.resetModal();

    if (type === "produccion") {
      this.setupProduccionConfirmation(form);
    } else if (type === "stock") {
      this.setupStockConfirmation(form);
    } else if (type === "finalizar") {
      this.setupFinalizarConfirmation(form);
    } else if (type === "devolucion") {
      // ✅ NUEVO
      this.setupDevolucionConfirmation(form);
    }

    this.modal.show();
  }

  setupProduccionConfirmation(form) {
    const formData = new FormData(form);

    // Configurar elementos del modal
    document.getElementById("confirmationTitle").innerHTML =
      '<i class="fas fa-industry me-2"></i>Confirmar Envío a Producción';

    document.getElementById("confirmationIcon").innerHTML =
      '<i class="fas fa-industry"></i>';

    document.getElementById("confirmationIcon").className =
      "confirmation-icon text-primary";

    document.getElementById("confirmationMessage").textContent =
      "¿Confirma el envío de los siguientes productos a producción?";

    // Obtener productos seleccionados
    const productos = this.getProductosProduccion(formData);

    // Generar detalles
    let detallesHTML =
      '<h6 class="mb-3"><i class="fas fa-list me-2"></i>Productos a procesar:</h6>';

    productos.forEach((producto) => {
      detallesHTML += `
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-box me-1"></i>${producto.nombre}
                    </div>
                    <div class="detail-value text-primary">
                        ${producto.cantidad} ${producto.unidad}
                    </div>
                </div>
            `;
    });

    if (productos.length === 0) {
      detallesHTML =
        '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No se encontraron productos para enviar a producción.</div>';
    }

    document.getElementById("confirmationDetails").innerHTML = detallesHTML;

    // Configurar advertencia
    document.getElementById("warningText").textContent =
      "Los productos serán enviados inmediatamente al sector producción.";
    document.getElementById("confirmationWarning").style.display = "block";

    // Configurar botón
    const btnConfirmar = document.getElementById("btnConfirmar");
    btnConfirmar.className = "btn btn-primary btn-confirm";
    btnConfirmar.innerHTML =
      '<i class="fas fa-industry me-2"></i>Enviar a Producción';
    btnConfirmar.disabled = productos.length === 0;
  }

  setupStockConfirmation(form) {
    const formData = new FormData(form);

    // Configurar elementos del modal
    document.getElementById("confirmationTitle").innerHTML =
      '<i class="fas fa-shipping-fast me-2"></i>Confirmar Reservas de Stock';

    document.getElementById("confirmationIcon").innerHTML =
      '<i class="fas fa-shipping-fast"></i>';

    document.getElementById("confirmationIcon").className =
      "confirmation-icon text-info";

    document.getElementById("confirmationMessage").textContent =
      "¿Confirma la creación de las siguientes reservas de stock?";

    // Obtener productos seleccionados
    const productos = this.getProductosStock(formData);

    // Generar detalles
    let detallesHTML =
      '<h6 class="mb-3"><i class="fas fa-list me-2"></i>Productos a reservar:</h6>';

    productos.forEach((producto) => {
      detallesHTML += `
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-cube me-1"></i>${producto.nombre}
                    </div>
                    <div class="detail-value text-info">
                        ${producto.cantidad} ${
        producto.tipo === "unidades" ? "unidades" : "bobinas"
      }
                        ${
                          producto.paquetes
                            ? `<br><small>(${producto.paquetes} paquetes)</small>`
                            : ""
                        }
                    </div>
                </div>
            `;
    });

    if (productos.length === 0) {
      detallesHTML =
        '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No se encontraron productos para reservar.</div>';
    }

    document.getElementById("confirmationDetails").innerHTML = detallesHTML;

    // Configurar advertencia
    document.getElementById("warningText").textContent =
      "Las reservas afectarán inmediatamente la disponibilidad del stock general.";
    document.getElementById("confirmationWarning").style.display = "block";

    // Configurar botón
    const btnConfirmar = document.getElementById("btnConfirmar");
    btnConfirmar.className = "btn btn-info btn-confirm";
    btnConfirmar.innerHTML =
      '<i class="fas fa-shipping-fast me-2"></i>Crear Reservas';
    btnConfirmar.disabled = productos.length === 0;
  }

  setupFinalizarConfirmation(form) {
    const formData = new FormData(form);

    // Configurar elementos del modal
    document.getElementById("confirmationTitle").innerHTML =
      '<i class="fas fa-check-double me-2"></i>Confirmar Finalización de Venta';

    document.getElementById("confirmationIcon").innerHTML =
      '<i class="fas fa-check-double"></i>';

    document.getElementById("confirmationIcon").className =
      "confirmation-icon text-success";

    document.getElementById("confirmationMessage").textContent =
      "¿Está seguro que desea finalizar esta venta?";

    // Obtener observaciones
    const observaciones = formData.get("observaciones_finalizacion");

    // Generar detalles
    let detallesHTML =
      '<h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Detalles de la finalización:</h6>';

    detallesHTML += `
            <div class="detail-item">
                <div class="detail-label">
                    <i class="fas fa-flag-checkered me-1"></i>Estado final
                </div>
                <div class="detail-value text-success">
                    <strong>Finalizado Manualmente</strong>
                </div>
            </div>
        `;

    if (observaciones && observaciones.trim()) {
      detallesHTML += `
                <div class="detail-item">
                    <div class="detail-label">
                        <i class="fas fa-comment me-1"></i>Observaciones
                    </div>
                    <div class="detail-value">
                        ${observaciones.trim()}
                    </div>
                </div>
            `;
    }

    document.getElementById("confirmationDetails").innerHTML = detallesHTML;

    // Configurar advertencia
    document.getElementById("warningText").textContent =
      "Una vez finalizada, la venta no podrá ser modificada.";
    document.getElementById("confirmationWarning").style.display = "block";

    // Configurar botón
    const btnConfirmar = document.getElementById("btnConfirmar");
    btnConfirmar.className = "btn btn-success btn-confirm";
    btnConfirmar.innerHTML =
      '<i class="fas fa-check-double me-2"></i>Finalizar Venta';
    btnConfirmar.disabled = false;
  }

  // ✅ NUEVO: Configurar confirmación para devolución a contabilidad
  setupDevolucionConfirmation(form) {
    const formData = new FormData(form);
    const motivoDevolucion = formData.get("motivo_devolucion");

    // Validar que haya motivo
    if (!motivoDevolucion || motivoDevolucion.trim() === "") {
      alert("Por favor, ingrese un motivo para la devolución.");
      this.modal.hide();
      return;
    }

    // Configurar elementos del modal
    document.getElementById("confirmationTitle").innerHTML =
      '<i class="fas fa-undo me-2"></i>Confirmar Devolución a Contabilidad';

    document.getElementById("confirmationIcon").innerHTML =
      '<i class="fas fa-undo"></i>';

    document.getElementById("confirmationIcon").className =
      "confirmation-icon text-warning";

    document.getElementById("confirmationMessage").textContent =
      "¿Está seguro que desea devolver esta venta a contabilidad?";

    // Generar detalles
    let detallesHTML =
      '<h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Detalles de la devolución:</h6>';

    detallesHTML += `
      <div class="detail-item">
        <div class="detail-label">
          <i class="fas fa-arrow-left me-1"></i>Acción
        </div>
        <div class="detail-value text-warning">
          <strong>Devolver a Contabilidad</strong>
        </div>
      </div>
      <div class="detail-item">
        <div class="detail-label">
          <i class="fas fa-comment me-1"></i>Motivo
        </div>
        <div class="detail-value">
          ${motivoDevolucion.trim()}
        </div>
      </div>
    `;

    document.getElementById("confirmationDetails").innerHTML = detallesHTML;

    // Configurar advertencia
    document.getElementById("warningText").textContent =
      "La venta será devuelta a contabilidad y requerirá nueva aprobación.";
    document.getElementById("confirmationWarning").style.display = "block";

    // Configurar botón
    const btnConfirmar = document.getElementById("btnConfirmar");
    btnConfirmar.className = "btn btn-warning btn-confirm";
    btnConfirmar.innerHTML =
      '<i class="fas fa-undo me-2"></i>Devolver a Contabilidad';
    btnConfirmar.disabled = false;
  }

  getProductosProduccion(formData) {
    const productos = [];

    // Buscar en el DOM los productos que se van a enviar
    const productosCards = document.querySelectorAll(
      "#formProduccion .producto-card"
    );

    productosCards.forEach((card) => {
      const nombreElement = card.querySelector("h6");
      const cantidadElement = card.querySelector(".alert-success");

      if (nombreElement && cantidadElement) {
        const nombre = nombreElement.textContent.trim().split("\n")[0].trim();
        const cantidadText = cantidadElement.textContent.trim();

        // Extraer cantidad del texto
        const match = cantidadText.match(/Se enviará a producción:\s*([^(]+)/);
        if (match) {
          productos.push({
            nombre: nombre,
            cantidad: match[1].trim(),
            unidad: cantidadText.includes("bobina")
              ? "bobinas"
              : cantidadText.includes("caja")
              ? "cajas"
              : "unidades",
          });
        }
      }
    });

    return productos;
  }

  getProductosStock(formData) {
    const productos = [];

    // Obtener todas las cantidades ingresadas
    for (let [key, value] of formData.entries()) {
      if (key.startsWith("cantidad_bobinas[") && value && parseInt(value) > 0) {
        const indice = key.match(/\[(\d+)\]/)?.[1];

        if (indice) {
          // Buscar el nombre del producto
          const nombreKey = `nombre_producto[${indice}]`;
          const nombreProducto = formData.get(nombreKey);

          if (nombreProducto) {
            // Buscar información adicional en el DOM
            const input = document.querySelector(
              `input[data-producto-index="${indice}"]`
            );
            if (input) {
              const esUnidades =
                input.getAttribute("data-es-unidades") === "true";
              const bobinasPorPaquete =
                parseInt(input.getAttribute("data-bobinas-por-paquete")) || 1;

              let producto = {
                nombre: nombreProducto,
                cantidad: value,
                tipo: esUnidades ? "unidades" : "bobinas",
              };

              // Calcular paquetes si no es en unidades
              if (!esUnidades && bobinasPorPaquete > 1) {
                const paquetesNecesarios = Math.ceil(
                  parseInt(value) / bobinasPorPaquete
                );
                producto.paquetes = paquetesNecesarios;
              }

              productos.push(producto);
            }
          }
        }
      }
    }

    return productos;
  }

  executeAction() {
    if (!this.pendingForm) return;

    // Mostrar estado de procesamiento
    document.getElementById("confirmationBody").classList.add("processing");
    document.getElementById("btnConfirmar").disabled = true;

    // Simular delay para mejor UX (opcional)
    setTimeout(() => {
      // Ejecutar el envío real del formulario
      this.pendingForm.submit();
    }, 500);
  }

  resetModal() {
    document.getElementById("confirmationBody").classList.remove("processing");
    document.getElementById("btnConfirmar").disabled = false;
    document.getElementById("confirmationWarning").style.display = "none";
  }
}

// Inicialización global
document.addEventListener("DOMContentLoaded", function () {
  console.log("=== INICIALIZANDO PÁGINA DESDE ARCHIVO EXTERNO ===");

  // Inicializar VER PCP Manager
  window.verPcpManager = new VerPcpManager();

  // Inicializar sistema de confirmación
  window.confirmationSystem = new ConfirmationSystem();

  // Función de test manual global
  window.testProducto = function () {
    if (window.verPcpManager) {
      window.verPcpManager.testProducto();
    }
  };

  console.log("✅ Inicialización completada desde archivo externo");
});

// Función de debug para verificar stock
function debugStockGeneral() {
  if (typeof window.stockGeneralData !== "undefined") {
    console.log("=== DEBUG STOCK GENERAL COMPLETO ===");
    console.log("Stock general completo:", window.stockGeneralData);

    if (window.stockGeneralData && window.stockGeneralData.length > 0) {
      console.log("Primer producto:", window.stockGeneralData[0]);
      console.log("Keys disponibles:", Object.keys(window.stockGeneralData[0]));

      // Verificar campos específicos
      const primer = window.stockGeneralData[0];
      console.log(
        "total_paquetes_disponibles:",
        primer.total_paquetes_disponibles
      );
      console.log(
        "total_bobinas_disponibles:",
        primer.total_bobinas_disponibles
      );
      console.log("bobinas_pacote:", primer.bobinas_pacote);
    } else {
      console.log("❌ Array vacío o null");
    }
  } else {
    console.log("❌ window.stockGeneralData no está definido");
  }
}

// Exportar funciones útiles
window.debugStockGeneral = debugStockGeneral;

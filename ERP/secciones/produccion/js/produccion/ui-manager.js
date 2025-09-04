/**
 * ui-manager.js - Manejo de Interfaz de Usuario - CORREGIDO
 * Gestiona selección de registros, eventos UI y actualizaciones visuales
 */

/**
 * Actualizar interfaz según selección actual
 */
function actualizarInterfazSeleccion() {
  const btnReimprimir = document.getElementById("btnReimprimirUnificado");
  const btnAbrirPDF = document.getElementById("btnAbrirPDFUnificado");
  const btnEliminar = document.getElementById("btnEliminarUnificado");
  const btnDesmarcar = document.getElementById("btnDesmarcarUnificado");
  const helpText = document.getElementById("reprint-help-unificado");
  const seleccionInfo = document.getElementById("seleccion-info-unificado");

  if (!registroSeleccionado) {
    // Estado inicial: ningún registro seleccionado
    if (btnReimprimir) {
      btnReimprimir.disabled = true;
      btnReimprimir.innerHTML = '<i class="fas fa-print me-2"></i>Reimprimir';
    }
    if (btnAbrirPDF) {
      btnAbrirPDF.disabled = true;
      btnAbrirPDF.innerHTML =
        '<i class="fas fa-external-link-alt me-2"></i>Abrir PDF';
    }
    if (btnEliminar) {
      btnEliminar.disabled = true;
      btnEliminar.innerHTML = '<i class="fas fa-trash me-2"></i>Eliminar';
    }
    if (btnDesmarcar) {
      btnDesmarcar.disabled = true;
    }
    if (helpText) {
      helpText.style.display = "block";
    }
    if (seleccionInfo) {
      seleccionInfo.style.display = "none";
    }
    return;
  }

  // Estado: registro seleccionado
  if (helpText) {
    helpText.style.display = "none";
  }

  // Mostrar información de selección - TODOS los tipos tienen tara
  if (seleccionInfo) {
    document.getElementById(
      "info-registro-numero"
    ).textContent = `#${registroSeleccionado.numero}`;
    document.getElementById("info-registro-tipo").textContent =
      registroSeleccionado.tipo;

    // Todos los productos tienen tara (TOALLITAS, PAÑOS, TNT, SPUNLACE, LAMINADORA)
    // ✅ ACTUALIZADO: Mencionar que NO está vinculado a venta
    document.getElementById(
      "info-registro-medidas"
    ).textContent = `Bruto: ${registroSeleccionado.pesoBruto} | Tara: ${registroSeleccionado.tara} | Líquido: ${registroSeleccionado.pesoLiquido} |`;

    seleccionInfo.style.display = "block";
  }

  // Habilitar todos los botones
  if (btnDesmarcar) {
    btnDesmarcar.disabled = false;
  }

  if (btnReimprimir) {
    btnReimprimir.disabled = false;
    btnReimprimir.innerHTML = '<i class="fas fa-print me-2"></i>Reimprimir';
    btnReimprimir.classList.remove("btn-secondary");
    btnReimprimir.classList.add("btn-reprint");
  }

  if (btnAbrirPDF) {
    btnAbrirPDF.disabled = false;
    btnAbrirPDF.innerHTML =
      '<i class="fas fa-external-link-alt me-2"></i>Abrir PDF';
  }

  if (btnEliminar) {
    btnEliminar.disabled = false;
    btnEliminar.innerHTML = '<i class="fas fa-trash me-2"></i>Eliminar';
  }

  console.log(
    "✅ Botones habilitados para registro tipo:",
    registroSeleccionado.tipo,
    "- ID específico:",
    registroSeleccionado.id,
    ""
  );
}

/**
 * Seleccionar un registro - ESTRUCTURA CORREGIDA para todos los tipos
 * @param {HTMLElement} elemento - Elemento de la fila seleccionada
 */
function seleccionarRegistro(elemento) {
  try {
    console.log("🖱️ Seleccionando registro:", elemento.dataset);

    // Remover selección previa
    document.querySelectorAll(".registro-row").forEach((row) => {
      row.classList.remove("selected");
    });

    // Agregar selección a la fila actual
    elemento.classList.add("selected");

    const tipoProducto = elemento.dataset.tipo;

    registroSeleccionado = {
      id: parseInt(elemento.dataset.id),
      orden: parseInt(elemento.dataset.orden),
      tipo: tipoProducto,
      numero: parseInt(elemento.dataset.numero),
      // ✅ CORREGIDO: Usar índices correctos basados en la estructura real
      pesoBruto: elemento.querySelector("td:nth-child(3)").textContent.trim(), // Columna 3: PESO BRUTO
      pesoLiquido: elemento.querySelector("td:nth-child(4)").textContent.trim(), // Columna 4: PESO LÍQUIDO
      tara: elemento.querySelector("td:nth-child(7)").textContent.trim(), // Columna 7: TARA
      vinculado: false, // ✅ NUEVO: Siempre false porque no están vinculados a ventas
    };

    console.log("✅ Registro seleccionado (CORREGIDO):", registroSeleccionado);

    // Actualizar interfaz
    actualizarInterfazSeleccion();

    // Efecto visual
    elemento.scrollIntoView({
      behavior: "smooth",
      block: "nearest",
    });
  } catch (error) {
    console.error("💥 Error al seleccionar registro:", error);
    alert("Error al seleccionar el registro: " + error.message);
  }
}

/**
 * Desmarcar selección
 */
function desmarcarSeleccion() {
  console.log("🔄 Desmarcando selección...");

  // Remover selección visual
  document.querySelectorAll(".registro-row").forEach((row) => {
    row.classList.remove("selected");
    row.style.backgroundColor = "";
  });

  // Reset variable global
  registroSeleccionado = null;

  // Actualizar interfaz
  actualizarInterfazSeleccion();
}

/**
 * Configurar event listeners para las filas de registros
 */
function setupRegistroRowListeners() {
  const registrosRows = document.querySelectorAll(".registro-row");
  console.log(
    `🔍 Encontradas ${registrosRows.length} filas de registros (CORREGIDO)`
  );

  registrosRows.forEach((row, index) => {
    row.addEventListener("click", function () {
      console.log(
        `🖱️ Click en fila ${index + 1} - ID: ${
          this.dataset.id
        } - ÍNDICES CORREGIDOS`
      );
      seleccionarRegistro(this);
    });

    // Efecto hover
    row.addEventListener("mouseenter", function () {
      if (!this.classList.contains("selected")) {
        this.style.backgroundColor = "#f8f9fa";
      }
    });

    row.addEventListener("mouseleave", function () {
      if (!this.classList.contains("selected")) {
        this.style.backgroundColor = "";
      }
    });
  });
}

/**
 * Configurar listeners globales de teclado para atajos
 */
function setupKeyboardShortcuts() {
  document.addEventListener("keydown", function (event) {
    // Solo procesar si no estamos en un campo de entrada
    if (
      event.target.tagName === "INPUT" ||
      event.target.tagName === "TEXTAREA" ||
      event.target.tagName === "SELECT"
    ) {
      return;
    }

    // Prevenir acciones por defecto para nuestros atajos
    const shortcuts = ["F2", "F3", "Delete", "Escape"];
    if (shortcuts.includes(event.key)) {
      event.preventDefault();
    }

    switch (event.key) {
      case "F2": // Reimprimir seleccionada
        if (registroSeleccionado) {
          reimprimirSeleccionada();
        } else {
          alert("Seleccione un registro primero (click en una fila)");
        }
        break;

      case "F3": // Abrir PDF
        if (registroSeleccionado) {
          abrirPDFSeleccionada();
        } else {
          alert("Seleccione un registro primero (click en una fila)");
        }
        break;

      case "Delete": // Eliminar
        if (registroSeleccionado) {
          eliminarRegistroSeleccionado();
        } else {
          alert("Seleccione un registro primero (click en una fila)");
        }
        break;

      case "Escape": // Desmarcar
        if (registroSeleccionado) {
          desmarcarSeleccion();
        }
        break;
    }
  });
}

/**
 * Mostrar tooltips con información de atajos de teclado
 */
function setupTooltips() {
  // Configurar tooltips de Bootstrap si están disponibles
  if (typeof bootstrap !== "undefined" && bootstrap.Tooltip) {
    // Botones con atajos de teclado
    const tooltipElements = [
      {
        selector: "#btnReimprimirUnificado",
        title: "Reimprimir seleccionada (F2)",
      },
      {
        selector: "#btnAbrirPDFUnificado",
        title: "Abrir PDF seleccionada (F3)",
      },
      {
        selector: "#btnEliminarUnificado",
        title: "Eliminar seleccionada (Delete)",
      },
      {
        selector: "#btnDesmarcarUnificado",
        title: "Desmarcar selección (Escape)",
      },
    ];

    tooltipElements.forEach(({ selector, title }) => {
      const element = document.querySelector(selector);
      if (element) {
        element.setAttribute("title", title);
        element.setAttribute("data-bs-toggle", "tooltip");
        element.setAttribute("data-bs-placement", "top");
        new bootstrap.Tooltip(element);
      }
    });
  }
}

/**
 * Actualizar contadores y estadísticas en tiempo real
 */
function actualizarContadores() {
  const filas = document.querySelectorAll(".registro-row");
  const totalItems = filas.length;

  // Actualizar contador en la interfaz si existe
  const contadorElement = document.querySelector(".stat-number.text-success");
  if (contadorElement) {
    contadorElement.textContent = totalItems;
  }

  console.log(`📊 Contadores actualizados: ${totalItems} registros`);
}

/**
 * Mostrar/ocultar elementos según el estado de la aplicación
 */
function toggleElementsVisibility() {
  const tipoProducto = obtenerTipoProductoActual();

  // Mostrar/ocultar campos específicos según el tipo de producto
  const bobinasPacoteContainer = document.getElementById(
    "bobinas_pacote_container"
  );
  if (bobinasPacoteContainer) {
    if (tipoProducto === "TOALLITAS" || tipoProducto === "PAÑOS") {
      bobinasPacoteContainer.style.display = "none";
    } else {
      // Para TNT/SPUNLACE/LAMINADORA, mostrar según largura
      toggleBobinasPacoteField();
    }
  }
}

/**
 * Configurar animaciones y efectos visuales
 */
function setupVisualEffects() {
  // Efecto de fade para mensajes de éxito/error
  const alerts = document.querySelectorAll(".alert");
  alerts.forEach((alert) => {
    // Auto-hide para mensajes de éxito después de 5 segundos
    if (alert.classList.contains("alert-success")) {
      setTimeout(() => {
        alert.style.transition = "opacity 0.5s";
        alert.style.opacity = "0";
        setTimeout(() => {
          if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
          }
        }, 500);
      }, 5000);
    }
  });

  // Efecto de highlight para nuevos registros (si se agrega dinámicamente)
  const nuevosRegistros = document.querySelectorAll(
    ".registro-row[data-nuevo]"
  );
  nuevosRegistros.forEach((registro) => {
    registro.style.backgroundColor = "#d4edda";
    setTimeout(() => {
      registro.style.transition = "background-color 1s";
      registro.style.backgroundColor = "";
      registro.removeAttribute("data-nuevo");
    }, 2000);
  });
}

/**
 * Inicializar todos los componentes de la interfaz
 */
function initializeUI() {
  console.log("🎨 Inicializando componentes de interfaz de usuario...");

  setupKeyboardShortcuts();
  setupTooltips();
  actualizarContadores();
  toggleElementsVisibility();
  setupVisualEffects();

  console.log("✅ Interfaz de usuario inicializada - ÍNDICES CORREGIDOS");
}

// Ejecutar inicialización de UI después del DOM
document.addEventListener("DOMContentLoaded", function () {
  // Pequeño delay para asegurar que otros scripts se hayan cargado
  setTimeout(initializeUI, 100);
});

// Exponer funciones globalmente
window.actualizarInterfazSeleccion = actualizarInterfazSeleccion;
window.seleccionarRegistro = seleccionarRegistro;
window.desmarcarSeleccion = desmarcarSeleccion;
window.setupRegistroRowListeners = setupRegistroRowListeners;
window.actualizarContadores = actualizarContadores;
window.initializeUI = initializeUI;

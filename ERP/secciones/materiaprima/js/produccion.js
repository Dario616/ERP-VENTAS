/**
 * JavaScript para el sistema de producción de materiales
 * America TNT - Sistema de Gestión de Producción - Versión Unificada
 * Sin estilos especiales para tubos - mismo diseño para todos los materiales
 */

// Variables globales
let ordenesDisponibles = [];
let timeoutBusqueda = null;
let ordenActual = null;
let requiereCantidad = false;

/**
 * Función para formatear números eliminando decimales innecesarios
 */
function formatearNumero(numero, decimales = 3) {
  const num = parseFloat(numero);
  if (isNaN(num)) return "0";

  // Si es un número entero, no mostrar decimales
  if (num % 1 === 0) {
    return num.toString();
  }

  // Si tiene decimales, mostrar solo los necesarios
  const fixed = num.toFixed(decimales);
  return parseFloat(fixed).toString();
}

// Inicialización cuando se carga la página
document.addEventListener("DOMContentLoaded", function () {
  console.log("🚀 Sistema de Producción iniciado");

  // Auto-focus en el campo de búsqueda si no hay orden cargada
  const campoBusqueda = document.getElementById("id_orden_buscar");
  if (campoBusqueda) {
    campoBusqueda.focus();
  }

  // Auto-focus en peso bruto si hay orden cargada
  const campoPesoBruto = document.getElementById("peso_bruto");
  if (campoPesoBruto) {
    campoPesoBruto.focus();

    // Configurar eventos para validación en tiempo real
    campoPesoBruto.addEventListener("input", calcularPesoLiquido);
    campoPesoBruto.addEventListener("blur", validarFormularioCompleto);
  }

  // Configurar campo tara
  const campoTara = document.getElementById("tara");
  if (campoTara) {
    campoTara.addEventListener("input", calcularPesoLiquido);
    campoTara.addEventListener("blur", validarFormularioCompleto);
  }

  // Configurar campo cantidad si existe
  const campoCantidad = document.getElementById("cantidad");
  if (campoCantidad) {
    campoCantidad.addEventListener("input", validarFormularioCompleto);
    campoCantidad.addEventListener("blur", validarCamposCantidad);

    // Solo permitir números enteros
    campoCantidad.addEventListener("keypress", function (e) {
      if (
        !/[0-9]/.test(e.key) &&
        ![
          "Backspace",
          "Delete",
          "Tab",
          "Enter",
          "ArrowLeft",
          "ArrowRight",
        ].includes(e.key)
      ) {
        e.preventDefault();
      }
    });

    // Detectar si se requiere cantidad basado en el campo hidden
    const unidadMedida = document.querySelector('input[name="unidad_medida"]');
    if (unidadMedida && unidadMedida.value === "UN") {
      requiereCantidad = true;
      console.log("📦 Orden requiere cantidad de unidades");
    }
  }

  // Configurar formulario con validación mejorada
  const formProduccion = document.getElementById("formProduccion");
  if (formProduccion) {
    formProduccion.addEventListener("submit", function (e) {
      const pesoBruto =
        parseFloat(document.getElementById("peso_bruto").value) || 0;
      const tara = parseFloat(document.getElementById("tara").value) || 0;

      // Validar pesos
      if (pesoBruto <= tara) {
        e.preventDefault();
        alert("El peso bruto debe ser mayor que la tara");
        return false;
      }

      // Validación para unidades
      if (campoCantidad && requiereCantidad) {
        const cantidad = parseInt(campoCantidad.value) || 0;
        if (cantidad <= 0) {
          e.preventDefault();
          alert("Debe especificar una cantidad de unidades válida");
          campoCantidad.focus();
          return false;
        }
      }

      // Mostrar resumen
      if (!mostrarResumenProduccion()) {
        e.preventDefault();
        return false;
      }

      return true;
    });
  }

  // Configurar enter key para buscar órdenes
  const terminoBusqueda = document.getElementById("terminoBusqueda");
  if (terminoBusqueda) {
    terminoBusqueda.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        buscarOrdenesDisponibles();
      }
    });
  }

  // Cargar órdenes disponibles si el modal está abierto
  const modal = document.getElementById("modalBuscadorOrdenes");
  if (modal) {
    modal.addEventListener("shown.bs.modal", function () {
      document.getElementById("terminoBusqueda").focus();
      buscarOrdenesDisponibles();
    });
  }
});

/**
 * Calcular peso líquido automáticamente
 */
function calcularPesoLiquido() {
  const pesoBruto =
    parseFloat(document.getElementById("peso_bruto").value) || 0;
  const tara = parseFloat(document.getElementById("tara").value) || 0;
  const pesoLiquido = pesoBruto - tara;

  const displayElement = document.getElementById("peso_liquido_display");
  const btnRegistrar = document.getElementById("btnRegistrar");

  if (pesoLiquido > 0) {
    displayElement.value = formatearNumero(pesoLiquido);
    displayElement.className =
      "form-control form-control-custom form-control-lg text-center bg-success text-white";
    if (btnRegistrar) btnRegistrar.disabled = false;
  } else if (pesoLiquido === 0) {
    displayElement.value = "0";
    displayElement.className =
      "form-control form-control-custom form-control-lg text-center bg-warning";
    if (btnRegistrar) btnRegistrar.disabled = true;
  } else {
    displayElement.value = "ERROR";
    displayElement.className =
      "form-control form-control-custom form-control-lg text-center bg-danger text-white";
    if (btnRegistrar) btnRegistrar.disabled = true;
  }

  // Validación en tiempo real
  validarFormularioCompleto();
}

/**
 * Validar formulario de producción completo
 */
function validarFormularioCompleto() {
  const pesoBruto =
    parseFloat(document.getElementById("peso_bruto").value) || 0;
  const tara = parseFloat(document.getElementById("tara").value) || 0;
  const btnRegistrar = document.getElementById("btnRegistrar");
  const campoCantidad = document.getElementById("cantidad");

  if (!btnRegistrar) return;

  let errores = [];

  // Validaciones de peso
  if (pesoBruto <= 0) {
    errores.push("El peso bruto debe ser mayor a 0");
  }

  if (tara < 0) {
    errores.push("La tara no puede ser negativa");
  }

  if (pesoBruto > 0 && tara >= 0 && pesoBruto <= tara) {
    errores.push("El peso bruto debe ser mayor que la tara");
  }

  // Validación para cantidad de unidades
  if (campoCantidad && requiereCantidad) {
    const cantidad = parseInt(campoCantidad.value) || 0;
    if (cantidad <= 0) {
      errores.push("Debe especificar cantidad de unidades");
    }
  }

  // Actualizar estado del botón
  if (errores.length === 0 && pesoBruto > 0 && tara >= 0) {
    btnRegistrar.disabled = false;
    btnRegistrar.innerHTML =
      '<i class="fas fa-save me-2"></i>Registrar Producción';
    btnRegistrar.className = "btn btn-success btn-lg";
    btnRegistrar.title = "Registrar producción";
  } else {
    btnRegistrar.disabled = true;
    btnRegistrar.innerHTML =
      '<i class="fas fa-exclamation-triangle me-2"></i>Verificar datos';
    btnRegistrar.className = "btn btn-outline-danger btn-lg";
    btnRegistrar.title = errores.join(", ");
  }
}

/**
 * Limpiar formulario de producción
 */
function limpiarFormulario() {
  document.getElementById("peso_bruto").value = "";

  // Solo limpiar tara si no es readonly (no es tubo)
  const campoTara = document.getElementById("tara");
  if (campoTara && !campoTara.readOnly) {
    campoTara.value = "";
  }

  document.getElementById("peso_liquido_display").value = "";
  document.getElementById("peso_liquido_display").className =
    "form-control form-control-custom form-control-lg text-center bg-light";

  // Limpiar campo cantidad si existe
  const campoCantidad = document.getElementById("cantidad");
  if (campoCantidad) {
    campoCantidad.value = "";
    campoCantidad.classList.remove("is-invalid");

    const grupoFormulario = campoCantidad.closest(".form-group-custom");
    if (grupoFormulario) {
      grupoFormulario.classList.remove("has-error");
      const mensajeError = grupoFormulario.querySelector(".invalid-feedback");
      if (mensajeError) {
        mensajeError.remove();
      }
    }
  }

  const btnRegistrar = document.getElementById("btnRegistrar");
  if (btnRegistrar) {
    btnRegistrar.disabled = true;
    btnRegistrar.innerHTML =
      '<i class="fas fa-save me-2"></i>Registrar Producción';
    btnRegistrar.className = "btn btn-success btn-lg";
    btnRegistrar.title = "Registrar producción";
  }

  // Focus en peso bruto
  document.getElementById("peso_bruto").focus();

  console.log("🧹 Formulario limpiado");
}

/**
 * Configurar campos según tipo de orden
 */
function configurarCamposSegunOrden(orden) {
  ordenActual = orden;
  requiereCantidad = orden && orden.unidad_medida === "UN";

  const campoCantidad = document.getElementById("cantidad");
  const containerCantidad = campoCantidad
    ? campoCantidad.closest(".mb-4")
    : null;

  if (containerCantidad) {
    if (requiereCantidad) {
      containerCantidad.style.display = "block";
      campoCantidad.required = true;

      // Agregar eventos para validación
      campoCantidad.addEventListener("input", validarFormularioCompleto);
      campoCantidad.addEventListener("blur", validarCamposCantidad);
    } else {
      containerCantidad.style.display = "none";
      campoCantidad.required = false;
    }
  }

  console.log(
    `🔧 Configuración de campos - Requiere cantidad: ${requiereCantidad}`
  );
}

/**
 * Validar específicamente campos de cantidad
 */
function validarCamposCantidad() {
  const campoCantidad = document.getElementById("cantidad");
  if (!campoCantidad || !requiereCantidad) return;

  const cantidad = parseInt(campoCantidad.value) || 0;
  const grupoFormulario = campoCantidad.closest(".form-group-custom");

  if (cantidad <= 0) {
    grupoFormulario.classList.add("has-error");
    campoCantidad.classList.add("is-invalid");

    // Mostrar mensaje de error si no existe
    let mensajeError = grupoFormulario.querySelector(".invalid-feedback");
    if (!mensajeError) {
      mensajeError = document.createElement("div");
      mensajeError.className = "invalid-feedback";
      grupoFormulario.appendChild(mensajeError);
    }
    mensajeError.textContent = "La cantidad debe ser mayor a 0";
  } else {
    grupoFormulario.classList.remove("has-error");
    campoCantidad.classList.remove("is-invalid");

    const mensajeError = grupoFormulario.querySelector(".invalid-feedback");
    if (mensajeError) {
      mensajeError.remove();
    }
  }
}

/**
 * Mostrar resumen antes de enviar
 */
function mostrarResumenProduccion() {
  const pesoBruto =
    parseFloat(document.getElementById("peso_bruto").value) || 0;
  const tara = parseFloat(document.getElementById("tara").value) || 0;
  const pesoLiquido = pesoBruto - tara;
  const campoCantidad = document.getElementById("cantidad");
  const cantidad = campoCantidad ? parseInt(campoCantidad.value) || 0 : 0;

  let resumen = `Peso Bruto: ${formatearNumero(pesoBruto)} KG\n`;
  resumen += `Tara: ${formatearNumero(tara)} KG\n`;
  resumen += `Peso Líquido: ${formatearNumero(pesoLiquido)} KG`;

  if (requiereCantidad && cantidad > 0) {
    resumen += `\nCantidad: ${cantidad} unidades`;
  }

  return confirm(`¿Confirmar registro de producción?\n\n${resumen}`);
}

/**
 * Mostrar modal de búsqueda de órdenes
 */
function mostrarBuscadorOrdenes() {
  const modal = new bootstrap.Modal(
    document.getElementById("modalBuscadorOrdenes")
  );
  modal.show();
}

/**
 * Buscar órdenes disponibles con debounce
 */
function buscarOrdenesDisponibles() {
  // Limpiar timeout anterior
  if (timeoutBusqueda) {
    clearTimeout(timeoutBusqueda);
  }

  // Establecer nuevo timeout
  timeoutBusqueda = setTimeout(function () {
    ejecutarBusquedaOrdenes();
  }, 300); // 300ms de delay
}

/**
 * Ejecutar búsqueda de órdenes
 */
function ejecutarBusquedaOrdenes() {
  const termino = document.getElementById("terminoBusqueda").value.trim();
  const loadingElement = document.getElementById("loadingOrdenes");
  const resultadosElement = document.getElementById("resultadosOrdenes");

  // Mostrar loading
  loadingElement.style.display = "block";
  resultadosElement.innerHTML = "";

  // Crear petición AJAX
  const formData = new FormData();
  formData.append("ajax", "true");
  formData.append("accion", "buscar_ordenes_disponibles");
  formData.append("termino", termino);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      loadingElement.style.display = "none";

      if (data.success) {
        mostrarResultadosOrdenes(data.ordenes);
        ordenesDisponibles = data.ordenes;
        console.log(`📋 Se encontraron ${data.total} órdenes`);
      } else {
        mostrarError("Error al buscar órdenes: " + data.error);
      }
    })
    .catch((error) => {
      loadingElement.style.display = "none";
      console.error("❌ Error en búsqueda:", error);
      mostrarError("Error de conexión al buscar órdenes");
    });
}

/**
 * Mostrar resultados de búsqueda de órdenes
 */
function mostrarResultadosOrdenes(ordenes) {
  const resultadosElement = document.getElementById("resultadosOrdenes");

  if (ordenes.length === 0) {
    resultadosElement.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h6 class="text-muted">No se encontraron órdenes</h6>
                <p class="text-muted">Intente con otros criterios de búsqueda</p>
            </div>
        `;
    return;
  }

  let html = '<div class="table-responsive">';
  html += '<table class="table table-hover">';
  html += `
        <thead class="table-light">
            <tr>
                <th>Orden</th>
                <th>Material</th>
                <th>Cantidad</th>
                <th>Progreso</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
    `;

  ordenes.forEach((orden) => {
    const badgeColor =
      orden.estado === "PENDIENTE"
        ? "warning"
        : orden.estado === "EN_PROCESO"
        ? "info"
        : "success";

    // Mostrar progreso según tipo de unidad
    let textoProgreso = "";
    if (
      orden.unidad_medida === "UN" &&
      orden.total_unidades_producidas !== undefined
    ) {
      textoProgreso = `${formatearNumero(
        orden.total_unidades_producidas,
        0
      )} / ${formatearNumero(orden.cantidad_solicitada, 0)} ${
        orden.unidad_medida
      }`;
    } else {
      textoProgreso = `${formatearNumero(
        orden.total_producido
      )} / ${formatearNumero(orden.cantidad_solicitada)} ${
        orden.unidad_medida
      }`;
    }

    // Badge para identificar órdenes que requieren cantidad
    let badgeUnidades = "";
    if (orden.unidad_medida === "UN") {
      badgeUnidades = '<small class="badge bg-info ms-1">Unidades</small>';
    }

    // Badge para identificar tubos
    let badgeTubo = "";
    if (
      orden.materia_prima_desc &&
      orden.materia_prima_desc.toLowerCase().includes("tubo")
    ) {
      badgeTubo = '<small class="badge bg-secondary ms-1">TUBO</small>';
    }

    html += `
            <tr>
                <td><strong class="text-primary">#${orden.id}</strong></td>
                <td>
                    <div class="fw-bold">${escapeHtml(
                      orden.materia_prima_desc
                    )}</div>
                    <small class="text-muted">${
                      orden.total_producciones
                    } producciones${badgeUnidades}${badgeTubo}</small>
                </td>
                <td>
                    <span class="badge bg-secondary">
                        ${formatearNumero(orden.cantidad_solicitada)} ${
      orden.unidad_medida
    }
                    </span>
                </td>
                <td>
                    <div class="progress mb-1" style="height: 20px;">
                        <div class="progress-bar bg-${orden.color_progreso}" 
                             style="width: ${orden.porcentaje_completado}%"
                             aria-valuenow="${orden.porcentaje_completado}" 
                             aria-valuemin="0" aria-valuemax="100">
                            ${formatearNumero(orden.porcentaje_completado, 1)}%
                        </div>
                    </div>
                    <small class="text-muted">
                        ${textoProgreso}
                    </small>
                </td>
                <td>
                    <span class="badge bg-${badgeColor}">${orden.estado}</span>
                </td>
                <td>
                    <small class="text-muted">${
                      orden.fecha_orden_formateada
                    }</small>
                </td>
                <td>
                    <button type="button" class="btn btn-primary btn-sm" 
                            onclick="seleccionarOrden(${orden.id})"
                            title="Seleccionar orden">
                        <i class="fas fa-arrow-right me-1"></i>Seleccionar
                    </button>
                </td>
            </tr>
        `;
  });

  html += "</tbody></table></div>";
  resultadosElement.innerHTML = html;
}

/**
 * Seleccionar una orden desde el modal
 */
function seleccionarOrden(idOrden) {
  console.log(`📋 Seleccionando orden #${idOrden}`);

  // Cerrar modal
  const modal = bootstrap.Modal.getInstance(
    document.getElementById("modalBuscadorOrdenes")
  );
  modal.hide();

  // Redirigir con la orden seleccionada
  window.location.href = `?orden=${idOrden}`;
}

/**
 * Eliminar producción con confirmación
 */
function eliminarProduccion(id, descripcion) {
  if (
    confirm(
      `¿Está seguro que desea eliminar la producción #${id}?\n\nEsta acción no se puede deshacer y se revertirá el peso en el inventario.`
    )
  ) {
    console.log(`🗑️ Eliminando producción #${id}`);

    const formEliminar = document.getElementById("formEliminar");
    const idEliminar = document.getElementById("idEliminar");

    if (formEliminar && idEliminar) {
      idEliminar.value = id;
      formEliminar.submit();
    } else {
      console.error(
        "❌ No se encontraron los elementos del formulario de eliminación"
      );
      alert(
        "Error: No se puede eliminar la producción. Recargue la página e intente nuevamente."
      );
    }
  }
}

/**
 * Actualizar listado de producciones vía AJAX
 */
function actualizarProducciones() {
  // Obtener ID de orden actual de la URL
  const urlParams = new URLSearchParams(window.location.search);
  const idOrden = urlParams.get("orden");

  if (!idOrden) {
    console.log("⚠️ No hay orden seleccionada para actualizar");
    return;
  }

  console.log(`🔄 Actualizando producciones de orden #${idOrden}`);

  // Mostrar indicador de carga
  mostrarCargando(true);

  // Crear petición AJAX
  const formData = new FormData();
  formData.append("ajax", "true");
  formData.append("accion", "obtener_producciones");
  formData.append("id_orden", idOrden);

  fetch(window.location.href, {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      mostrarCargando(false);

      if (data.success) {
        // Recargar la página para mostrar datos actualizados
        window.location.reload();
      } else {
        mostrarError("Error al actualizar: " + data.error);
      }
    })
    .catch((error) => {
      mostrarCargando(false);
      console.error("❌ Error actualizando:", error);
      mostrarError("Error de conexión al actualizar");
    });
}

/**
 * Limpiar orden actual y volver al buscador
 */
function limpiarOrden() {
  if (confirm("¿Desea salir de la orden actual y volver al buscador?")) {
    window.location.href = window.location.pathname;
  }
}

/**
 * Mostrar indicador de carga
 */
function mostrarCargando(mostrar) {
  let indicador = document.getElementById("indicadorCarga");

  if (mostrar && !indicador) {
    // Crear indicador si no existe
    indicador = document.createElement("div");
    indicador.id = "indicadorCarga";
    indicador.className = "position-fixed top-50 start-50 translate-middle";
    indicador.style.zIndex = "9999";
    indicador.innerHTML = `
            <div class="bg-white p-4 rounded shadow text-center">
                <div class="spinner-border text-primary mb-2" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <div>Actualizando...</div>
            </div>
        `;
    document.body.appendChild(indicador);
  } else if (!mostrar && indicador) {
    indicador.remove();
  }
}

/**
 * Mostrar mensaje de error
 */
function mostrarError(mensaje) {
  console.error("❌", mensaje);

  // Crear toast de error
  const toastHtml = `
        <div class="toast align-items-center text-white bg-danger border-0 position-fixed top-0 end-0 m-3" 
             role="alert" aria-live="assertive" aria-atomic="true" style="z-index: 9999;">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${mensaje}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                        data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

  // Agregar al DOM
  const toastContainer = document.createElement("div");
  toastContainer.innerHTML = toastHtml;
  document.body.appendChild(toastContainer);

  // Mostrar toast
  const toastElement = toastContainer.querySelector(".toast");
  const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
  toast.show();

  // Limpiar después de que se oculte
  toastElement.addEventListener("hidden.bs.toast", function () {
    toastContainer.remove();
  });
}

/**
 * Mostrar mensaje de éxito
 */
function mostrarExito(mensaje) {
  console.log("✅", mensaje);

  // Crear toast de éxito
  const toastHtml = `
        <div class="toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3" 
             role="alert" aria-live="assertive" aria-atomic="true" style="z-index: 9999;">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i>
                    ${mensaje}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                        data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

  // Agregar al DOM
  const toastContainer = document.createElement("div");
  toastContainer.innerHTML = toastHtml;
  document.body.appendChild(toastContainer);

  // Mostrar toast
  const toastElement = toastContainer.querySelector(".toast");
  const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
  toast.show();

  // Limpiar después de que se oculte
  toastElement.addEventListener("hidden.bs.toast", function () {
    toastContainer.remove();
  });
}

/**
 * Escapar HTML para evitar XSS
 */
function escapeHtml(unsafe) {
  return unsafe
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

/**
 * Configurar atajos de teclado
 */
document.addEventListener("keydown", function (e) {
  // Ctrl + L: Limpiar formulario
  if (e.ctrlKey && e.key === "l") {
    e.preventDefault();
    limpiarFormulario();
  }

  // Ctrl + B: Abrir buscador de órdenes
  if (e.ctrlKey && e.key === "b") {
    e.preventDefault();
    mostrarBuscadorOrdenes();
  }

  // Esc: Cerrar modales
  if (e.key === "Escape") {
    const modales = document.querySelectorAll(".modal.show");
    modales.forEach((modal) => {
      const modalInstance = bootstrap.Modal.getInstance(modal);
      if (modalInstance) {
        modalInstance.hide();
      }
    });
  }
});

/**
 * Auto-guardar configuración en localStorage
 */
function guardarConfiguracion() {
  const config = {
    ultimaOrden: new URLSearchParams(window.location.search).get("orden"),
    timestamp: Date.now(),
  };

  try {
    localStorage.setItem("produccion_config", JSON.stringify(config));
  } catch (e) {
    console.warn("⚠️ No se pudo guardar configuración:", e);
  }
}

/**
 * Cargar configuración guardada
 */
function cargarConfiguracion() {
  try {
    const config = JSON.parse(
      localStorage.getItem("produccion_config") || "{}"
    );
    return config;
  } catch (e) {
    console.warn("⚠️ No se pudo cargar configuración:", e);
    return {};
  }
}

/**
 * Debugging: Verificar que las funciones estén disponibles
 */
function verificarFunciones() {
  const funciones = [
    "eliminarProduccion",
    "actualizarProducciones",
    "limpiarOrden",
    "mostrarCargando",
    "calcularPesoLiquido",
    "limpiarFormulario",
    "mostrarBuscadorOrdenes",
    "seleccionarOrden",
  ];

  console.log("🔍 Verificando funciones disponibles:");
  funciones.forEach((funcion) => {
    if (typeof window[funcion] === "function") {
      console.log(`✅ ${funcion} - OK`);
    } else {
      console.error(`❌ ${funcion} - NO DEFINIDA`);
    }
  });
}
/**
 * Configurar navegación con Enter como Tab en el formulario de producción
 */
function configurarNavegacionEnter() {
  // Definir el orden de campos para navegación
  const ordenCampos = [
    "cantidad", // Solo visible cuando unidad_medida = "UN"
    "peso_bruto",
    "tara",
    "btnRegistrar", // Botón final
  ];

  // Función para obtener el siguiente campo visible
  function obtenerSiguienteCampo(campoActual) {
    const indicActual = ordenCampos.indexOf(campoActual);

    // Buscar el siguiente campo que exista y sea visible
    for (let i = indicActual + 1; i < ordenCampos.length; i++) {
      const siguienteCampo = document.getElementById(ordenCampos[i]);
      if (siguienteCampo && siguienteCampo.offsetParent !== null) {
        return siguienteCampo;
      }
    }

    return null;
  }

  // Configurar eventos para cada campo
  ordenCampos.forEach((idCampo) => {
    const campo = document.getElementById(idCampo);
    if (campo) {
      campo.addEventListener("keydown", function (e) {
        // Solo procesar Enter
        if (e.key === "Enter") {
          e.preventDefault(); // Evitar que envíe el formulario

          const siguienteCampo = obtenerSiguienteCampo(idCampo);

          if (siguienteCampo) {
            if (siguienteCampo.id === "btnRegistrar") {
              // Si es el botón y está habilitado, hacer click
              if (!siguienteCampo.disabled) {
                siguienteCampo.click();
              }
            } else {
              // Enfocar el siguiente campo
              siguienteCampo.focus();
              siguienteCampo.select(); // Seleccionar el texto si es input
            }
          }
        }
      });
    }
  });

  console.log("✅ Navegación con Enter configurada");
}

/**
 * Mejorar el comportamiento del campo cantidad para unidades
 */
function configurarCampoCantidad() {
  const campoCantidad = document.getElementById("cantidad");
  if (!campoCantidad) return;

  // Mejorar validación en tiempo real
  campoCantidad.addEventListener("keypress", function (e) {
    // Permitir solo números, backspace, delete, tab, enter y flechas
    if (
      !/[0-9]/.test(e.key) &&
      ![
        "Backspace",
        "Delete",
        "Tab",
        "Enter",
        "ArrowLeft",
        "ArrowRight",
      ].includes(e.key)
    ) {
      e.preventDefault();
    }
  });

  // Validar cuando pierde el foco
  campoCantidad.addEventListener("blur", function () {
    const valor = parseInt(this.value) || 0;
    if (valor <= 0) {
      this.classList.add("is-invalid");
      mostrarErrorCampo(this, "La cantidad debe ser mayor a 0");
    } else {
      this.classList.remove("is-invalid");
      limpiarErrorCampo(this);
    }
  });

  // Auto-corregir valores inválidos
  campoCantidad.addEventListener("input", function () {
    let valor = this.value.replace(/[^0-9]/g, ""); // Solo números
    this.value = valor;

    // Validación en tiempo real
    if (valor && parseInt(valor) > 0) {
      this.classList.remove("is-invalid");
      limpiarErrorCampo(this);
    }
  });
}

/**
 * Mostrar error en un campo específico
 */
function mostrarErrorCampo(campo, mensaje) {
  const grupo = campo.closest(".form-group-custom");
  if (!grupo) return;

  grupo.classList.add("has-error");

  // Crear o actualizar mensaje de error
  let mensajeError = grupo.querySelector(".invalid-feedback");
  if (!mensajeError) {
    mensajeError = document.createElement("div");
    mensajeError.className = "invalid-feedback";
    grupo.appendChild(mensajeError);
  }
  mensajeError.textContent = mensaje;
}

/**
 * Limpiar error de un campo
 */
function limpiarErrorCampo(campo) {
  const grupo = campo.closest(".form-group-custom");
  if (!grupo) return;

  grupo.classList.remove("has-error");
  const mensajeError = grupo.querySelector(".invalid-feedback");
  if (mensajeError) {
    mensajeError.remove();
  }
}

/**
 * Configurar auto-focus inteligente
 */
function configurarAutoFocus() {
  // Determinar el primer campo que debería tener foco
  const campoCantidad = document.getElementById("cantidad");
  const campoPesoBruto = document.getElementById("peso_bruto");

  // Si requiere cantidad y el campo está visible, enfocar ahí primero
  if (
    campoCantidad &&
    campoCantidad.offsetParent !== null &&
    requiereCantidad
  ) {
    campoCantidad.focus();
    console.log("🎯 Auto-focus en campo cantidad");
  } else if (campoPesoBruto) {
    campoPesoBruto.focus();
    console.log("🎯 Auto-focus en peso bruto");
  }
}

/**
 * Mejorar el comportamiento del formulario completo
 */
function mejorarComportamientoFormulario() {
  const form = document.getElementById("formProduccion");
  if (!form) return;

  // Prevenir envío accidental con Enter
  form.addEventListener("keydown", function (e) {
    if (e.key === "Enter") {
      const target = e.target;

      // Solo permitir Enter en el botón de envío
      if (target.id !== "btnRegistrar") {
        e.preventDefault();
        return false;
      }
    }
  });

  // Mejorar validación del formulario
  form.addEventListener("submit", function (e) {
    let errores = [];

    // Validar cantidad si es requerida
    if (requiereCantidad) {
      const campoCantidad = document.getElementById("cantidad");
      const cantidad = parseInt(campoCantidad.value) || 0;
      if (cantidad <= 0) {
        errores.push("Debe especificar una cantidad válida de unidades");
        campoCantidad.focus();
      }
    }

    // Validar pesos
    const pesoBruto =
      parseFloat(document.getElementById("peso_bruto").value) || 0;
    const tara = parseFloat(document.getElementById("tara").value) || 0;

    if (pesoBruto <= 0) {
      errores.push("El peso bruto debe ser mayor a 0");
    }

    if (pesoBruto <= tara) {
      errores.push("El peso bruto debe ser mayor que la tara");
    }

    if (errores.length > 0) {
      e.preventDefault();
      alert("Errores encontrados:\n\n" + errores.join("\n"));
      return false;
    }
  });
}

// Integrar todas las mejoras cuando se carga el DOM
document.addEventListener("DOMContentLoaded", function () {
  // Esperar un poco para que otros scripts se inicialicen
  setTimeout(function () {
    configurarNavegacionEnter();
    configurarCampoCantidad();
    mejorarComportamientoFormulario();

    // Auto-focus después de configurar todo
    setTimeout(configurarAutoFocus, 100);
  }, 500);
});

// Reconfigurar cuando cambia el tipo de orden
function reconfigurarNavegacion() {
  configurarNavegacionEnter();
  configurarAutoFocus();
}
// Ejecutar al cargar la página
document.addEventListener("DOMContentLoaded", function () {
  // Esperar un poco para que todo se cargue
  setTimeout(verificarFunciones, 1000);
});

// Asegurar que las funciones estén en el scope global
window.eliminarProduccion = eliminarProduccion;
window.actualizarProducciones = actualizarProducciones;
window.limpiarOrden = limpiarOrden;
window.mostrarCargando = mostrarCargando;
window.mostrarBuscadorOrdenes = mostrarBuscadorOrdenes;
window.seleccionarOrden = seleccionarOrden;
window.limpiarFormulario = limpiarFormulario;

// Guardar configuración al cargar la página
window.addEventListener("load", guardarConfiguracion);

// Guardar configuración antes de salir
window.addEventListener("beforeunload", guardarConfiguracion);

console.log("📋 JavaScript de producción unificado cargado correctamente");
